<?php
declare( strict_types=1 );

$paths = [
	__DIR__ . '/../plan-your-day.php',
	__DIR__ . '/../uninstall.php',
];

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( __DIR__ . '/../src', FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file_info ) {
	if ( $file_info instanceof SplFileInfo && 'php' === $file_info->getExtension() ) {
		$paths[] = $file_info->getPathname();
	}
}

$test_iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( __DIR__ . '/../tests', FilesystemIterator::SKIP_DOTS )
);

foreach ( $test_iterator as $file_info ) {
	if ( $file_info instanceof SplFileInfo && 'php' === $file_info->getExtension() ) {
		$paths[] = $file_info->getPathname();
	}
}

$php_binary = PHP_BINARY;

foreach ( $paths as $path ) {
	$command = escapeshellarg( $php_binary ) . ' -l ' . escapeshellarg( $path );
	passthru( $command, $exit_code );

	if ( 0 !== $exit_code ) {
		exit( $exit_code );
	}
}
