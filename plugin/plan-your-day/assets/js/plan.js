(() => {
  const ROOT_SELECTOR = '[data-plan-root]';
  const ENHANCED_FLAG = 'planYourDayEnhanced';

  const parseConfig = (root) => {
    const configElement = root.querySelector('[data-plan-config]');

    if (!configElement) {
      return {};
    }

    try {
      return JSON.parse(configElement.textContent || '{}');
    } catch (error) {
      return {};
    }
  };

  const announce = (liveRegion, message) => {
    if (!liveRegion || !message) {
      return;
    }

    liveRegion.textContent = '';

    window.requestAnimationFrame(() => {
      liveRegion.textContent = message;
    });
  };

  const getCheckedStartMode = (startModeInputs) => {
    const checkedInput = startModeInputs.find((input) => input.checked);

    return checkedInput ? checkedInput.value : '';
  };

  const getStartDescription = (config, startMode) => {
    const routeNote = config.initialData?.route?.startNoteText || '';
    const configuredDescription = config.startPoints?.[startMode]?.description || '';

    return configuredDescription || routeNote;
  };

  const initRoot = (root) => {
    if (!(root instanceof HTMLElement) || root.dataset[ENHANCED_FLAG] === 'true') {
      return;
    }

    root.dataset[ENHANCED_FLAG] = 'true';

    const config = parseConfig(root);
    const refs = {
      form: root.querySelector('[data-plan-form]'),
      liveRegion: root.querySelector('[data-plan-live-region]'),
      categoryInput: root.querySelector('[data-plan-category-input]'),
      categoryButtons: Array.from(root.querySelectorAll('[data-plan-category-button]')),
      startModeInputs: Array.from(root.querySelectorAll('input[name="start_mode"]')),
      customStartWrap: root.querySelector('[data-plan-custom-start-wrap]'),
      customStartInput: root.querySelector('[data-plan-custom-start]'),
      startNote: root.querySelector('[data-plan-start-note]'),
      startToggle: root.querySelector('[data-plan-start-toggle]'),
      startToggleLabel: root.querySelector('[data-plan-start-toggle-label]'),
      startPanel: root.querySelector('[data-plan-start-panel]'),
    };

    let isStartPanelOpen = true;

    const updateCustomStartState = () => {
      const startMode = getCheckedStartMode(refs.startModeInputs) || config.initialState?.startMode || 'default';
      const isCustom = startMode === 'custom';

      if (refs.customStartWrap) {
        refs.customStartWrap.hidden = !isCustom;
      }

      if (refs.customStartInput) {
        refs.customStartInput.disabled = !isCustom;
      }

      if (refs.startNote) {
        refs.startNote.textContent = getStartDescription(config, startMode);
      }
    };

    const updateStartPanelState = () => {
      if (!refs.startToggle || !refs.startPanel) {
        return;
      }

      refs.startToggle.hidden = false;
      refs.startToggle.setAttribute('aria-expanded', String(isStartPanelOpen));
      refs.startPanel.hidden = !isStartPanelOpen;

      if (refs.startToggleLabel) {
        refs.startToggleLabel.textContent = isStartPanelOpen ? 'Hide' : 'Show';
      }
    };

    refs.startModeInputs.forEach((input) => {
      input.addEventListener('change', () => {
        updateCustomStartState();
        announce(refs.liveRegion, 'Starting point updated.');
      });
    });

    refs.categoryButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const categoryKey = button.getAttribute('data-category-key') || '';

        if (refs.categoryInput) {
          refs.categoryInput.value = categoryKey;
        }
      });
    });

    if (refs.form) {
      refs.form.addEventListener('submit', (event) => {
        const submitter = event.submitter;

        if (submitter instanceof HTMLButtonElement && submitter.matches('[data-plan-category-button]') && refs.categoryInput) {
          refs.categoryInput.value = submitter.getAttribute('data-category-key') || '';
        }

        root.classList.add('is-submitting');
      });
    }

    root.addEventListener('click', (event) => {
      const target = event.target;

      if (!(target instanceof HTMLElement)) {
        return;
      }

      const disabledOpenLink = target.closest('[data-plan-open-link][aria-disabled="true"]');

      if (disabledOpenLink instanceof HTMLAnchorElement) {
        event.preventDefault();
        announce(refs.liveRegion, 'Add at least one waypoint before opening the trip in Google Maps.');
        return;
      }

      const startToggle = target.closest('[data-plan-start-toggle]');

      if (startToggle instanceof HTMLButtonElement) {
        event.preventDefault();
        isStartPanelOpen = !isStartPanelOpen;
        updateStartPanelState();
        announce(
          refs.liveRegion,
          isStartPanelOpen ? 'Starting point options expanded.' : 'Starting point options collapsed.'
        );
      }
    });

    updateCustomStartState();
    updateStartPanelState();
    root.classList.add('is-enhanced');
  };

  const init = () => {
    document.querySelectorAll(ROOT_SELECTOR).forEach(initRoot);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
