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

  const escapeHtml = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

  const formatString = (template, value) => String(template || '').replace('%s', String(value ?? ''));

  const toStringArray = (value) => {
    if (!Array.isArray(value)) {
      return [];
    }

    return value.map((item) => String(item ?? '')).filter(Boolean);
  };

  const normalizeFilterTerm = (value) =>
    String(value ?? '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();

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

  const getStartDescription = (config, routeData, startMode) => {
    const routeNote = routeData?.startNoteText || '';
    const configuredDescription = config.startPoints?.[startMode]?.description || '';

    return configuredDescription || routeNote;
  };

  const buildPayload = (refs, state) => {
    const formData = refs.form instanceof HTMLFormElement ? new FormData(refs.form) : new FormData();

    return {
      category: String(formData.get('category') || state.category || ''),
      category_search: String(formData.get('category_search') || state.categorySearch || ''),
      waypoints: formData.getAll('waypoints[]').map((waypoint) => String(waypoint || '')).filter(Boolean),
      start_mode: String(formData.get('start_mode') || state.startMode || 'default'),
      custom_start: String(formData.get('custom_start') || ''),
    };
  };

  const renderMessagesMarkup = (messages) => {
    const safeMessages = Array.isArray(messages) ? messages : [];

    return safeMessages
      .map((message) => {
        const type = String(message?.type || 'note');
        const text = String(message?.text || '');

        return `<li class="dkc-plan__message dkc-plan__message--${escapeHtml(type)}">${escapeHtml(text)}</li>`;
      })
      .join('');
  };

  const renderResultsMarkup = (browseData, selectedWaypointIds, strings) => {
    const searchResults = Array.isArray(browseData?.searchResults) ? browseData.searchResults : [];

    if (searchResults.length === 0) {
      const emptyState = browseData?.resultsEmptyState || {};

      return `
        <div class="dkc-plan__results-empty">
          <h4>${escapeHtml(emptyState.heading || '')}</h4>
          <p>${escapeHtml(emptyState.body || '')}</p>
        </div>
      `;
    }

    return `
      <ul class="dkc-plan__results-list">
        ${searchResults
          .map((result) => {
            const placeId = String(result?.id || '');
            const label = String(result?.label || '');
            const address = String(result?.address || '');
            const distanceLabel = String(result?.distance_label || '');
            const mapsUri = String(result?.maps_uri || '');
            const isInTrip = selectedWaypointIds.includes(placeId);

            return `
              <li class="dkc-plan__result-item">
                <div class="dkc-plan__result-copy">
                  <h4>${escapeHtml(label)}</h4>
                  ${distanceLabel ? `<p class="dkc-plan__result-distance">${escapeHtml(distanceLabel)}</p>` : ''}
                  <p class="dkc-plan__result-meta">${escapeHtml(address)}</p>
                </div>
                <div class="dkc-plan__result-tools">
                  ${mapsUri
                    ? `<a class="dkc-plan__result-link" href="${escapeHtml(mapsUri)}" target="_blank" rel="noopener noreferrer">${escapeHtml(strings.viewInGoogleMaps || '')}</a>`
                    : ''}
                  ${isInTrip
                    ? `<span class="dkc-plan__result-added" aria-label="${escapeHtml(formatString(strings.alreadyInTripAria, label))}">${escapeHtml(strings.inTrip || '')}</span>`
                    : `<button class="dkc-plan__result-add" type="submit" name="waypoints[]" value="${escapeHtml(
                        placeId
                      )}" data-plan-action="add-waypoint" data-place-id="${escapeHtml(placeId)}">${escapeHtml(
                        strings.addToTrip || ''
                      )}</button>`}
                </div>
              </li>
            `;
          })
          .join('')}
      </ul>
    `;
  };

  const renderTripHeaderMarkup = (routeData, strings) => {
    const selectedWaypointIds = toStringArray(routeData?.selectedWaypointIds);
    const tripCountLabel = String(routeData?.tripCountLabel || '');

    return `
      <span class="dkc-plan__count-pill" data-plan-trip-count>${escapeHtml(tripCountLabel)}</span>
      ${
        selectedWaypointIds.length > 0
          ? `<button class="dkc-plan__clear-link" type="submit" name="clear_trip" value="1" data-plan-clear-trip data-plan-action="clear-trip">${escapeHtml(
              strings.clearTrip || ''
            )}</button>`
          : ''
      }
    `;
  };

  const renderTripMarkup = (routeData, strings) => {
    const tripWaypoints = Array.isArray(routeData?.tripWaypoints) ? routeData.tripWaypoints : [];

    if (tripWaypoints.length === 0) {
      return `
        <div class="dkc-plan__trip-empty" data-plan-trip-empty>
          <h4>${escapeHtml(strings.tripEmptyHeading || '')}</h4>
          <p>${escapeHtml(strings.tripEmptyBody || '')}</p>
        </div>
      `;
    }

    return `
      <ol class="dkc-plan__trip-list" data-plan-trip-list>
        ${tripWaypoints
          .map((waypoint, index) => {
            const placeId = String(waypoint?.id || '');
            const label = String(waypoint?.label || '');
            const address = String(waypoint?.address || '');
            const canMoveUp = index > 0;
            const canMoveDown = index < tripWaypoints.length - 1;

            return `
              <li class="dkc-plan__trip-item" data-waypoint-id="${escapeHtml(placeId)}">
                <div class="dkc-plan__trip-main">
                  <span class="dkc-plan__trip-number" aria-hidden="true">${escapeHtml(String(index + 1))}</span>
                  <div class="dkc-plan__trip-copy">
                    <h4>${escapeHtml(label)}</h4>
                    <p class="dkc-plan__trip-meta">${escapeHtml(address)}</p>
                  </div>
                </div>
                <div class="dkc-plan__trip-tools">
                  <button
                    class="dkc-plan__reorder-button dkc-plan__reorder-button--up"
                    type="${canMoveUp ? 'submit' : 'button'}"
                    name="move_waypoint"
                    value="${escapeHtml(`${placeId}:up`)}"
                    ${canMoveUp ? '' : 'disabled'}>
                    ${escapeHtml(strings.moveUp || '')}
                  </button>
                  <button
                    class="dkc-plan__reorder-button dkc-plan__reorder-button--down"
                    type="${canMoveDown ? 'submit' : 'button'}"
                    name="move_waypoint"
                    value="${escapeHtml(`${placeId}:down`)}"
                    ${canMoveDown ? '' : 'disabled'}>
                    ${escapeHtml(strings.moveDown || '')}
                  </button>
                  <button type="submit" name="remove_waypoint" value="${escapeHtml(
                    placeId
                  )}" data-plan-action="remove-waypoint" data-place-id="${escapeHtml(placeId)}">
                    ${escapeHtml(formatString(strings.removeWaypointLabel, label))}
                  </button>
                </div>
              </li>
            `;
          })
          .join('')}
      </ol>
    `;
  };

  const syncHiddenInputs = (refs, state) => {
    if (refs.categoryInput) {
      refs.categoryInput.value = state.category || '';
    }

    if (!refs.waypointInputs) {
      return;
    }

    refs.waypointInputs.innerHTML = toStringArray(state.route?.selectedWaypointIds)
      .map(
        (waypointId) =>
          `<input type="hidden" name="waypoints[]" value="${escapeHtml(waypointId)}" data-plan-waypoint-input>`
      )
      .join('');
  };

  const renderMessages = (refs, messages) => {
    if (!refs.messages) {
      return;
    }

    const safeMessages = Array.isArray(messages) ? messages : [];
    refs.messages.hidden = safeMessages.length === 0;
    refs.messages.innerHTML = renderMessagesMarkup(safeMessages);
  };

  const renderCategoryPanels = (refs, state, strings) => {
    const activeCategory = state.category || '';
    const selectedWaypointIds = toStringArray(state.route?.selectedWaypointIds);
    const browseData = state.browse || {};
    const hasCustomSearch = Boolean(browseData.hasSearch) && !activeCategory;

    if (refs.resultsCount) {
      refs.resultsCount.textContent = String(browseData.searchResultsLabel || '');
    }

    refs.categoryButtons.forEach((button) => {
      const categoryKey = button.getAttribute('data-category-key') || '';
      const isActive = categoryKey === activeCategory;
      const accordionItem = button.closest('.dkc-plan__category-accordion-item');

      button.setAttribute('aria-expanded', String(isActive));

      if (accordionItem instanceof HTMLElement) {
        accordionItem.classList.toggle('is-expanded', isActive);
      }
    });

    refs.categoryPanels.forEach((panel) => {
      const categoryKey = panel.getAttribute('data-category-key') || '';
      const isActive = categoryKey === activeCategory;

      panel.hidden = !isActive;
      panel.innerHTML = isActive ? renderResultsMarkup(browseData, selectedWaypointIds, strings) : '';
    });

    if (refs.customResults) {
      refs.customResults.hidden = !hasCustomSearch && refs.categoryButtons.length > 0;
    }

    if (refs.customResultsHeading) {
      refs.customResultsHeading.textContent = hasCustomSearch
        ? formatString(strings.searchResultsFor || '', browseData.categoryLabel || '')
        : String(browseData?.resultsEmptyState?.heading || '');
    }

    if (refs.customResultsPanel) {
      refs.customResultsPanel.innerHTML = hasCustomSearch
        ? renderResultsMarkup(browseData, selectedWaypointIds, strings)
        : `
            <div class="dkc-plan__results-empty">
              <h4>${escapeHtml(browseData?.resultsEmptyState?.heading || '')}</h4>
              <p>${escapeHtml(browseData?.resultsEmptyState?.body || '')}</p>
            </div>
          `;
    }
  };

  const renderTrip = (refs, state, strings) => {
    if (refs.tripHeaderActions) {
      refs.tripHeaderActions.innerHTML = renderTripHeaderMarkup(state.route, strings);
    }

    if (refs.tripRegion) {
      refs.tripRegion.innerHTML = renderTripMarkup(state.route, strings);
    }
  };

  const renderPreview = (refs, state) => {
    const browseData = state.browse || {};
    const routeData = state.route || {};
    const iframeSrc = String(routeData.iframeSrc || '');
    const emptyPreviewState = routeData.emptyPreviewState || {};
    const categoryLabel = String(routeData.categoryLabel || browseData.categoryLabel || 'Not selected');
    const mapsUrl = String(routeData.mapsUrl || '');

    renderMessages(refs, routeData.messages);

    if (refs.mapWrap) {
      refs.mapWrap.hidden = iframeSrc === '';
    }

    if (refs.iframe) {
      refs.iframe.src = iframeSrc;
    }

    if (refs.previewEmpty) {
      refs.previewEmpty.hidden = iframeSrc !== '';
    }

    if (refs.previewEmptyHeading) {
      refs.previewEmptyHeading.textContent = String(emptyPreviewState.heading || '');
    }

    if (refs.previewEmptyBody) {
      refs.previewEmptyBody.textContent = String(emptyPreviewState.body || '');
    }

    if (refs.summaryCount) {
      refs.summaryCount.textContent = String(routeData.tripCountLabel || '');
    }

    if (refs.summaryOverview) {
      refs.summaryOverview.textContent = String(routeData.overview || '');
    }

    if (refs.summaryCategory) {
      refs.summaryCategory.textContent = categoryLabel;
    }

    if (refs.summaryResults) {
      refs.summaryResults.textContent = String(browseData.searchResultsLabel || '');
    }

    if (refs.summaryHandoffStart) {
      refs.summaryHandoffStart.textContent = String(routeData.handoffStartLabel || '');
    }

    if (refs.summaryMode) {
      refs.summaryMode.textContent = String(routeData.previewModeLabel || '');
    }

    if (refs.openLinkLabel) {
      refs.openLinkLabel.textContent = String(routeData.mapsLinkLabel || '');
    }

    if (refs.openLink) {
      refs.openLink.classList.toggle('is-disabled', mapsUrl === '');

      if (mapsUrl) {
        refs.openLink.href = mapsUrl;
        refs.openLink.removeAttribute('aria-disabled');
      } else {
        refs.openLink.removeAttribute('href');
        refs.openLink.setAttribute('aria-disabled', 'true');
      }
    }
  };

  const syncStartUi = (refs, config, state) => {
    refs.startModeInputs.forEach((input) => {
      input.checked = input.value === state.startMode;
    });

    if (refs.customStartInput) {
      refs.customStartInput.value = state.customStart || '';
    }

    const startMode = getCheckedStartMode(refs.startModeInputs) || state.startMode || 'default';
    const isCustom = startMode === 'custom';

    if (refs.customStartWrap) {
      refs.customStartWrap.hidden = !isCustom;
    }

    if (refs.customStartInput) {
      refs.customStartInput.disabled = !isCustom;
    }

    if (refs.startNote) {
      refs.startNote.textContent = getStartDescription(config, state.route, startMode);
    }
  };

  const syncCategorySearchUi = (refs, state) => {
    if (!(refs.categorySearchInput instanceof HTMLInputElement)) {
      return;
    }

    const nextValue = String(state.categorySearch || '');

    if (refs.categorySearchInput.value !== nextValue) {
      refs.categorySearchInput.value = nextValue;
    }
  };

  const setBusyState = (root, isBusy) => {
    root.classList.toggle('is-submitting', isBusy);
    root.setAttribute('aria-busy', String(isBusy));
  };

  const initRoot = (root) => {
    if (!(root instanceof HTMLElement) || root.dataset[ENHANCED_FLAG] === 'true') {
      return;
    }

    root.dataset[ENHANCED_FLAG] = 'true';

    const config = parseConfig(root);
    const strings = config.strings || {};
    const refs = {
      form: root.querySelector('[data-plan-form]'),
      liveRegion: root.querySelector('[data-plan-live-region]'),
      categoryInput: root.querySelector('[data-plan-category-input]'),
      waypointInputs: root.querySelector('[data-plan-waypoint-inputs]'),
      categoryButtons: Array.from(root.querySelectorAll('[data-plan-category-button]')),
      categoryItems: Array.from(root.querySelectorAll('[data-plan-category-item]')),
      categoryPanels: Array.from(root.querySelectorAll('[data-plan-category-results-panel]')),
      categorySearchInput: root.querySelector('[data-plan-category-search]'),
      customResults: root.querySelector('[data-plan-custom-results]'),
      customResultsHeading: root.querySelector('[data-plan-custom-results-heading]'),
      customResultsPanel: root.querySelector('[data-plan-custom-results-panel]'),
      startModeInputs: Array.from(root.querySelectorAll('input[name="start_mode"]')),
      customStartWrap: root.querySelector('[data-plan-custom-start-wrap]'),
      customStartInput: root.querySelector('[data-plan-custom-start]'),
      startNote: root.querySelector('[data-plan-start-note]'),
      startToggle: root.querySelector('[data-plan-start-toggle]'),
      startToggleLabel: root.querySelector('[data-plan-start-toggle-label]'),
      startPanel: root.querySelector('[data-plan-start-panel]'),
      resultsCount: root.querySelector('[data-plan-results-count]'),
      tripHeaderActions: root.querySelector('[data-plan-trip-header-actions]'),
      tripRegion: root.querySelector('[data-plan-trip-region]'),
      messages: root.querySelector('[data-plan-messages]'),
      mapWrap: root.querySelector('[data-plan-map-wrap]'),
      iframe: root.querySelector('[data-plan-iframe]'),
      previewEmpty: root.querySelector('[data-plan-preview-empty]'),
      previewEmptyHeading: root.querySelector('[data-plan-preview-empty-heading]'),
      previewEmptyBody: root.querySelector('[data-plan-preview-empty-body]'),
      summaryCount: root.querySelector('[data-plan-summary-count]'),
      summaryOverview: root.querySelector('[data-plan-summary-overview]'),
      summaryCategory: root.querySelector('[data-plan-summary-category]'),
      summaryResults: root.querySelector('[data-plan-summary-results]'),
      summaryHandoffStart: root.querySelector('[data-plan-summary-handoff-start]'),
      summaryMode: root.querySelector('[data-plan-summary-mode]'),
      openLink: root.querySelector('[data-plan-open-link]'),
      openLinkLabel: root.querySelector('[data-plan-open-link-label]'),
    };

    const state = {
      category: String(config.initialState?.category || ''),
      categorySearch: String(config.initialState?.categorySearch || ''),
      startMode: String(config.initialState?.startMode || 'default'),
      customStart: String(config.initialState?.customStart || ''),
      browse: config.initialData?.browse || {},
      route: config.initialData?.route || {},
    };
    const hasRestConfig =
      refs.form instanceof HTMLFormElement &&
      typeof config.rest?.browseUrl === 'string' &&
      config.rest.browseUrl !== '' &&
      typeof config.rest?.routeUrl === 'string' &&
      config.rest.routeUrl !== '' &&
      typeof config.rest?.endpointToken === 'string' &&
      config.rest.endpointToken !== '';

    let isStartPanelOpen = true;
    let activeRequestController = null;
    let activeRequestId = 0;

    const renderAll = () => {
      renderCategoryPanels(refs, state, strings);
      renderTrip(refs, state, strings);
      renderPreview(refs, state);
      syncHiddenInputs(refs, state);
      syncStartUi(refs, config, state);
      syncCategorySearchUi(refs, state);
    };

    const showRequestError = (message) => {
      renderMessages(refs, [
        {
          type: 'warning',
          text: message || strings.requestFailed || '',
        },
      ]);
      announce(refs.liveRegion, message || strings.requestFailed || '');
    };

    const updateStartPanelState = () => {
      if (!refs.startToggle || !refs.startPanel) {
        return;
      }

      refs.startToggle.hidden = false;
      refs.startToggle.setAttribute('aria-expanded', String(isStartPanelOpen));
      refs.startToggle.classList.toggle('is-collapsed', !isStartPanelOpen);
      refs.startPanel.hidden = !isStartPanelOpen;

      if (refs.startToggleLabel) {
        refs.startToggleLabel.textContent = isStartPanelOpen ? 'Hide' : 'Show';
      }
    };

    const sendRequest = async (endpointKey, payload, announcementMessage) => {
      if (!hasRestConfig) {
        return false;
      }

      activeRequestId += 1;
      const requestId = activeRequestId;

      if (activeRequestController instanceof AbortController) {
        activeRequestController.abort();
      }

      activeRequestController = new AbortController();
      setBusyState(root, true);

      try {
        const response = await fetch(config.rest[endpointKey === 'browse' ? 'browseUrl' : 'routeUrl'], {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            ...payload,
            endpoint_token: config.rest.endpointToken,
          }),
          signal: activeRequestController.signal,
        });
        const responseBody = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(responseBody?.message || strings.requestFailed || '');
        }

        if (requestId !== activeRequestId) {
          return true;
        }

        if (endpointKey === 'browse') {
          state.browse = responseBody?.browse || {};
          state.route = responseBody?.route || {};
          state.category = String(state.browse.categoryKey || payload.category || '');
          state.categorySearch = String(state.browse.categorySearch || payload.category_search || '');
        } else {
          state.route = responseBody?.route || {};
          state.category = String(state.route.categoryKey || state.category || '');
          state.categorySearch = String(state.route.categorySearch || payload.category_search || '');
        }

        state.startMode = String(payload.start_mode || state.startMode || 'default');
        state.customStart = String(payload.custom_start || '');

        renderAll();
        announce(refs.liveRegion, announcementMessage || '');

        return true;
      } catch (error) {
        if (error?.name === 'AbortError') {
          return false;
        }

        showRequestError(error instanceof Error ? error.message : strings.requestFailed || '');

        return false;
      } finally {
        if (requestId === activeRequestId) {
          setBusyState(root, false);
        }
      }
    };

    refs.startModeInputs.forEach((input) => {
      input.addEventListener('change', () => {
        state.startMode = getCheckedStartMode(refs.startModeInputs) || state.startMode || 'default';
        syncStartUi(refs, config, state);

        if (!hasRestConfig) {
          announce(refs.liveRegion, strings.startingPointUpdated || '');
          return;
        }

        void sendRequest('browse', buildPayload(refs, state), strings.startingPointUpdated || '');
      });
    });

    if (refs.customStartInput instanceof HTMLInputElement) {
      refs.customStartInput.addEventListener('change', () => {
        state.customStart = refs.customStartInput.value || '';
        syncStartUi(refs, config, state);

        if (!hasRestConfig) {
          announce(refs.liveRegion, strings.startingPointUpdated || '');
          return;
        }

        void sendRequest('browse', buildPayload(refs, state), strings.startingPointUpdated || '');
      });
    }

    if (refs.categorySearchInput instanceof HTMLInputElement) {
      refs.categorySearchInput.addEventListener('input', () => {
        state.categorySearch = refs.categorySearchInput.value || '';
      });

      refs.categorySearchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !hasRestConfig) {
          return;
        }

        event.preventDefault();

        const payload = buildPayload(refs, state);
        payload.category = '';
        payload.category_search = refs.categorySearchInput.value || '';

        void sendRequest('browse', payload, strings.resultsUpdated || '');
      });
    }

    if (refs.form instanceof HTMLFormElement) {
      refs.form.addEventListener('submit', (event) => {
        const submitter = event.submitter;

        if (!(submitter instanceof HTMLButtonElement) || !hasRestConfig) {
          return;
        }

        let endpointKey = 'browse';
        let announcementMessage = strings.resultsUpdated || '';
        const payload = buildPayload(refs, state);

        if (submitter.matches('[data-plan-category-button]')) {
          payload.category = submitter.getAttribute('data-category-key') || '';
          payload.category_search = '';
        } else if (submitter.matches('[data-plan-action="search-category-query"]')) {
          payload.category = '';
          payload.category_search = refs.categorySearchInput instanceof HTMLInputElement ? refs.categorySearchInput.value || '' : '';
        } else if (submitter.matches('[data-plan-action="add-waypoint"]')) {
          payload.waypoints = [...payload.waypoints, submitter.getAttribute('data-place-id') || submitter.value || ''];
          endpointKey = 'route';
          announcementMessage = strings.tripUpdated || '';
        } else if (submitter.matches('[data-plan-action="remove-waypoint"]')) {
          payload.remove_waypoint = submitter.getAttribute('data-place-id') || submitter.value || '';
          endpointKey = 'route';
          announcementMessage = strings.tripUpdated || '';
        } else if (submitter.matches('[data-plan-clear-trip]')) {
          payload.clear_trip = true;
          endpointKey = 'route';
          announcementMessage = strings.tripUpdated || '';
        } else if (submitter.name === 'move_waypoint' && submitter.value) {
          payload.move_waypoint = submitter.value;
          endpointKey = 'route';
          announcementMessage = strings.tripUpdated || '';
        }

        event.preventDefault();
        void sendRequest(endpointKey, payload, announcementMessage);
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
        announce(refs.liveRegion, strings.openMapsDisabled || '');
        return;
      }

      const startToggle = target.closest('[data-plan-start-toggle]');

      if (startToggle instanceof HTMLButtonElement) {
        event.preventDefault();
        isStartPanelOpen = !isStartPanelOpen;
        updateStartPanelState();
        announce(
          refs.liveRegion,
          isStartPanelOpen ? strings.startOptionsExpanded || '' : strings.startOptionsCollapsed || ''
        );
      }
    });

    renderAll();
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
