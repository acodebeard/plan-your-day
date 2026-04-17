#!/usr/bin/env node
import { mkdtemp, mkdir, cp, writeFile, rm, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const workDir = await mkdtemp(join(tmpdir(), 'dkc-plan-csp-'));
const wwwDir = join(workDir, 'www');
const phpLog = join(workDir, 'php-server.log');
const chromeLog = join(workDir, 'chrome.log');
const phpPort = Number(process.env.DKC_PLAN_TEST_PORT || 20000 + Math.floor(Math.random() * 20000));
const chromePort = Number(process.env.DKC_PLAN_CHROME_PORT || phpPort + 1);
const baseUrl = `http://127.0.0.1:${phpPort}/`;
let phpServer;
let chrome;
let cdp;

const sleep = (ms) => new Promise((resolveSleep) => setTimeout(resolveSleep, ms));

const waitForExit = (child) =>
	new Promise((resolveExit) => {
		if (!child || child.exitCode !== null) {
			resolveExit();
			return;
		}

		child.once('exit', resolveExit);
	});

const commandExists = async (command) => {
	const child = spawn('sh', ['-c', `command -v ${command}`], {
		stdio: 'ignore',
	});

	return new Promise((resolveExists) => {
		child.on('exit', (code) => resolveExists(code === 0));
	});
};

const findChrome = async () => {
	if (process.env.CHROME_BIN) {
		return process.env.CHROME_BIN;
	}

	for (const candidate of ['google-chrome', 'chromium', 'chromium-browser']) {
		if (await commandExists(candidate)) {
			return candidate;
		}
	}

	throw new Error('Chrome/Chromium not found. Set CHROME_BIN to run this smoke test.');
};

const cleanup = async () => {
	if (cdp) {
		cdp.close();
	}

	for (const child of [chrome, phpServer]) {
		if (child && !child.killed) {
			child.kill();
			await Promise.race([waitForExit(child), sleep(2000)]);
		}
	}

	await rm(workDir, { recursive: true, force: true, maxRetries: 5, retryDelay: 100 });
};

process.on('SIGINT', async () => {
	await cleanup();
	process.exit(130);
});

process.on('SIGTERM', async () => {
	await cleanup();
	process.exit(143);
});

const waitForHttp = async (url, label) => {
	let lastError;

	for (let attempt = 0; attempt < 100; attempt += 1) {
		try {
			const response = await fetch(url);
			if (response.ok) {
				return response;
			}
			lastError = new Error(`${label} returned ${response.status}`);
		} catch (error) {
			lastError = error;
		}

		await sleep(100);
	}

	throw lastError || new Error(`${label} did not start`);
};

const connectCdp = async (webSocketUrl) => {
	if (typeof WebSocket !== 'function') {
		throw new Error('This Node runtime does not provide a global WebSocket client.');
	}

	const socket = new WebSocket(webSocketUrl);
	const pending = new Map();
	const eventHandlers = new Set();
	let nextId = 1;

	await new Promise((resolveOpen, rejectOpen) => {
		socket.addEventListener('open', resolveOpen, { once: true });
		socket.addEventListener('error', rejectOpen, { once: true });
	});

	socket.addEventListener('message', (event) => {
		const message = JSON.parse(event.data);

		if (message.id && pending.has(message.id)) {
			const { resolveCommand, rejectCommand } = pending.get(message.id);
			pending.delete(message.id);

			if (message.error) {
				rejectCommand(new Error(message.error.message || 'Chrome DevTools command failed'));
			} else {
				resolveCommand(message.result || {});
			}

			return;
		}

		for (const handler of eventHandlers) {
			handler(message);
		}
	});

	return {
		close() {
			socket.close();
		},
		onEvent(handler) {
			eventHandlers.add(handler);
		},
		send(method, params = {}) {
			const id = nextId;
			nextId += 1;

			socket.send(JSON.stringify({ id, method, params }));

			return new Promise((resolveCommand, rejectCommand) => {
				pending.set(id, { resolveCommand, rejectCommand });
			});
		},
	};
};

const waitForLoad = () =>
	new Promise((resolveLoad) => {
		const handler = (message) => {
			if (message.method === 'Page.loadEventFired') {
				resolveLoad();
			}
		};
		cdp.onEvent(handler);
	});

try {
	await mkdir(wwwDir, { recursive: true });
	await cp(join(repoRoot, 'index.php'), join(wwwDir, 'index.php'), { recursive: false });
	await cp(join(repoRoot, 'plan.css'), join(wwwDir, 'plan.css'), { recursive: false });
	await cp(join(repoRoot, 'plan.js'), join(wwwDir, 'plan.js'), { recursive: false });
	await cp(join(repoRoot, 'icons'), join(wwwDir, 'icons'), { recursive: true });
	await writeFile(
		join(wwwDir, 'c88e3e98.php'),
		`<?php
if (!defined('DKC_PLAN_STANDALONE_BOOTSTRAP')) {
\thttp_response_code(403);
\texit;
}

if (!defined('DKC_PLAN_GOOGLE_API_KEY')) {
\tdefine('DKC_PLAN_GOOGLE_API_KEY', '');
}
`
	);

	phpServer = spawn('php', ['-S', `127.0.0.1:${phpPort}`, '-t', wwwDir], {
		stdio: ['ignore', await writeFile(phpLog, '').then(() => 'ignore'), 'pipe'],
	});

	phpServer.stderr.on('data', async (chunk) => {
		await writeFile(phpLog, chunk, { flag: 'a' });
	});

	const pageResponse = await waitForHttp(baseUrl, 'PHP server');
	const csp = pageResponse.headers.get('content-security-policy') || '';

	if (!csp.includes("style-src 'self'")) {
		throw new Error(`Expected style-src 'self' in CSP header, got: ${csp}`);
	}

	if (csp.includes("'unsafe-inline'")) {
		throw new Error(`CSP header still contains 'unsafe-inline': ${csp}`);
	}

	const chromeBin = await findChrome();

	chrome = spawn(
		chromeBin,
		[
			'--headless=new',
			'--disable-gpu',
			'--no-sandbox',
			`--user-data-dir=${join(workDir, 'chrome-profile')}`,
			`--remote-debugging-port=${chromePort}`,
			'about:blank',
		],
		{ stdio: ['ignore', 'ignore', 'pipe'] }
	);

	chrome.stderr.on('data', async (chunk) => {
		await writeFile(chromeLog, chunk, { flag: 'a' });
	});

	await waitForHttp(`http://127.0.0.1:${chromePort}/json/version`, 'Chrome DevTools');
	const targetsResponse = await waitForHttp(`http://127.0.0.1:${chromePort}/json`, 'Chrome target list');
	const targets = await targetsResponse.json();
	const pageTarget = targets.find((target) => target.type === 'page' && target.webSocketDebuggerUrl);

	if (!pageTarget) {
		throw new Error('Chrome page target not found.');
	}

	const cspViolations = [];
	const exceptions = [];

	cdp = await connectCdp(pageTarget.webSocketDebuggerUrl);
	cdp.onEvent((message) => {
		if (message.method === 'Log.entryAdded') {
			const text = message.params?.entry?.text || '';
			if (/Content Security Policy|violates the following Content Security Policy|Refused to/i.test(text)) {
				cspViolations.push(text);
			}
		}

		if (message.method === 'Runtime.exceptionThrown') {
			exceptions.push(message.params?.exceptionDetails?.text || 'Runtime exception');
		}
	});

	await cdp.send('Page.enable');
	await cdp.send('Runtime.enable');
	await cdp.send('Log.enable');

	const loadPromise = waitForLoad();
	await cdp.send('Page.navigate', { url: baseUrl });
	await loadPromise;

	const plannerPresent = await cdp.send('Runtime.evaluate', {
		expression: 'Boolean(document.querySelector("[data-plan-root]"))',
		returnByValue: true,
	});

	if (!plannerPresent.result?.value) {
		throw new Error('Planner root did not render.');
	}

	await cdp.send('Runtime.evaluate', {
		expression: `
			const toggle = document.querySelector('[data-plan-start-toggle]');
			if (toggle) {
				toggle.click();
				setTimeout(() => toggle.click(), 150);
			}
		`,
	});
	await sleep(700);

	if (exceptions.length > 0) {
		throw new Error(`Browser runtime exceptions:\n${exceptions.join('\n')}`);
	}

	if (cspViolations.length > 0) {
		throw new Error(`CSP violations:\n${cspViolations.join('\n')}`);
	}

	console.log('CSP browser smoke test passed.');
} catch (error) {
	const phpOutput = await readFile(phpLog, 'utf8').catch(() => '');
	const chromeOutput = await readFile(chromeLog, 'utf8').catch(() => '');

	if (phpOutput) {
		console.error('PHP server log:');
		console.error(phpOutput);
	}

	if (chromeOutput) {
		console.error('Chrome log:');
		console.error(chromeOutput);
	}

	console.error(error.message);
	process.exitCode = 1;
} finally {
	await cleanup();
}
