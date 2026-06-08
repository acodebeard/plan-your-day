(() => {
  const ROOT_SELECTOR = '[data-plan-root]';
  const ENHANCED_FLAG = 'planYourDayEnhanced';
  const START_PANEL_ANIMATION_MS = 480;
  const COLOR_MODE_STORAGE_KEY = 'planYourDayColorMode';
  const COLOR_MODE_LIGHT = 'light';
  const COLOR_MODE_DARK = 'dark';
  const COLOR_MODE_SYSTEM = 'system';
  const COLOR_MODE_VALUES = [COLOR_MODE_LIGHT, COLOR_MODE_DARK];
  const COLOR_MODE_DEFAULT_VALUES = [...COLOR_MODE_VALUES, COLOR_MODE_SYSTEM];
  const CUSTOM_START_STATUS_CHECKING = 'checking';
  const CUSTOM_START_STATUS_FOUND = 'found';
  const CUSTOM_START_STATUS_NOT_FOUND = 'not_found';
  const CUSTOM_START_STATUSES = [
    CUSTOM_START_STATUS_CHECKING,
    CUSTOM_START_STATUS_FOUND,
    CUSTOM_START_STATUS_NOT_FOUND,
  ];

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

  const formatTemplate = (template, replacements = {}) => {
    let output = String(template || '');

    Object.entries(replacements || {}).forEach(([key, value]) => {
      output = output.split(`{${key}}`).join(String(value ?? ''));
    });

    return output;
  };
  const redactDebugValue = (value, key = '') => {
    if (Array.isArray(value)) {
      return value.map((item) => redactDebugValue(item));
    }

    if (value && typeof value === 'object') {
      return Object.fromEntries(
        Object.entries(value).map(([entryKey, entryValue]) => {
          const lowerKey = String(entryKey).toLowerCase();
          const shouldRedact =
            lowerKey.includes('token') ||
            lowerKey.includes('api_key') ||
            lowerKey.includes('authorization') ||
            lowerKey.includes('cookie') ||
            lowerKey.includes('secret');

          return [entryKey, shouldRedact ? '[redacted]' : redactDebugValue(entryValue, entryKey)];
        })
      );
    }

    if (typeof value === 'string') {
      if (String(key).toLowerCase().includes('token')) {
        return '[redacted]';
      }

      return value.replace(/([?&](?:key|api_key|token)=)[^&]+/gi, '$1[redacted]');
    }

    return value;
  };
  const debugLog = (config, level, event, data = {}) => {
    if (!config?.debug || typeof console === 'undefined') {
      return;
    }

    const logger = typeof console[level] === 'function' ? console[level] : console.log;
    logger.call(console, `[plan-your-day] ${event}`, redactDebugValue(data));
  };

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

  const isColorMode = (value) => COLOR_MODE_VALUES.includes(String(value || ''));

  const normalizeColorModeDefault = (value) => {
    const colorModeDefault = String(value || '');

    return COLOR_MODE_DEFAULT_VALUES.includes(colorModeDefault) ? colorModeDefault : COLOR_MODE_LIGHT;
  };

  const getStoredColorMode = () => {
    try {
      const storedMode = window.localStorage?.getItem(COLOR_MODE_STORAGE_KEY);

      return isColorMode(storedMode) ? storedMode : '';
    } catch (error) {
      return '';
    }
  };

  const setStoredColorMode = (colorMode) => {
    if (!isColorMode(colorMode)) {
      return;
    }

    try {
      window.localStorage?.setItem(COLOR_MODE_STORAGE_KEY, colorMode);
    } catch (error) {
      // Local storage can be blocked; the in-page mode still applies.
    }
  };

  const getColorModeMediaQuery = () => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
  };

  const resolveColorMode = (colorModeDefault, mediaQuery = getColorModeMediaQuery()) => {
    const storedMode = getStoredColorMode();

    if (isColorMode(storedMode)) {
      return storedMode;
    }

    const defaultMode = normalizeColorModeDefault(colorModeDefault);

    if (defaultMode === COLOR_MODE_SYSTEM) {
      return mediaQuery?.matches ? COLOR_MODE_DARK : COLOR_MODE_LIGHT;
    }

    return defaultMode;
  };

  const syncColorModeToggle = (refs, colorMode, strings) => {
    if (!(refs.colorModeToggle instanceof HTMLButtonElement)) {
      return;
    }

    refs.colorModeToggle.hidden = false;
    refs.colorModeToggle.setAttribute('aria-pressed', String(colorMode === COLOR_MODE_DARK));
    refs.colorModeToggle.setAttribute('aria-label', String(strings.darkModeLabel || 'Dark mode'));

    if (refs.colorModeToggleLabel instanceof HTMLElement) {
      refs.colorModeToggleLabel.textContent = String(strings.darkModeLabel || 'Dark mode');
    }
  };

  const applyColorMode = (root, refs, colorMode, strings) => {
    const nextMode = isColorMode(colorMode) ? colorMode : COLOR_MODE_LIGHT;

    root.setAttribute('data-plan-color-mode', nextMode);
    syncColorModeToggle(refs, nextMode, strings);
  };

  const getCheckedStartMode = (startModeInputs) => {
    const checkedInput = startModeInputs.find((input) => input.checked);

    return checkedInput ? checkedInput.value : '';
  };

  const normalizeCustomStartStatus = (status) => {
    const normalizedStatus = String(status || '');

    return CUSTOM_START_STATUSES.includes(normalizedStatus) ? normalizedStatus : '';
  };

  const getCustomStartStatusMessage = (status, strings) => {
    if (status === CUSTOM_START_STATUS_CHECKING) {
      return String(strings.customStartChecking || 'Checking starting address.');
    }

    if (status === CUSTOM_START_STATUS_FOUND) {
      return String(strings.customStartFound || 'Starting address found. Results are ready.');
    }

    if (status === CUSTOM_START_STATUS_NOT_FOUND) {
      return String(strings.customStartNotFound || 'Starting address was not found.');
    }

    return '';
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
        const role = type === 'warning' ? 'alert' : '';

        return `<li class="plan-your-day__message plan-your-day__message--${escapeHtml(type)}"${
          role ? ` role="${escapeHtml(role)}"` : ''
        }>${escapeHtml(text)}</li>`;
      })
      .join('');
  };

  const renderResultItemMarkup = (result, selectedWaypointIds, strings) => {
    const placeId = String(result?.id || '');
    const label = String(result?.label || '');
    const address = String(result?.address || '');
    const distanceLabel = String(result?.distance_label || '');
    const mapsUri = String(result?.maps_uri || '');
    const isInTrip = selectedWaypointIds.includes(placeId);

    return `
      <li class="plan-your-day__result-item">
        <div class="plan-your-day__result-copy">
          <h4>${escapeHtml(label)}</h4>
          ${distanceLabel ? `<p class="plan-your-day__result-distance">${escapeHtml(distanceLabel)}</p>` : ''}
          <p class="plan-your-day__result-meta">${escapeHtml(address)}</p>
        </div>
        <div class="plan-your-day__result-tools">
          ${mapsUri
            ? `<a class="plan-your-day__result-link" href="${escapeHtml(mapsUri)}" target="_blank" rel="noopener noreferrer" aria-label="${escapeHtml(
                formatTemplate(strings.viewPlaceInGoogleMapsLabel || '', { place: label })
              )}">${escapeHtml(strings.viewInGoogleMaps || '')}</a>`
            : ''}
          ${isInTrip
            ? `<span class="plan-your-day__result-added" aria-label="${escapeHtml(
                formatTemplate(strings.alreadyInTripAria || '', { place: label })
              )}">${escapeHtml(strings.inTrip || '')}</span>`
            : `<button class="plan-your-day__result-add" type="submit" name="waypoints[]" value="${escapeHtml(
                placeId
              )}" data-plan-action="add-waypoint" data-plan-route-mutation data-place-id="${escapeHtml(placeId)}" aria-label="${escapeHtml(
                formatTemplate(strings.addWaypointLabel || '', { place: label })
              )}">${escapeHtml(strings.addToTrip || '')}</button>`}
        </div>
      </li>
    `;
  };

  const renderResultsContentMarkup = (browseData, selectedWaypointIds, strings) => {
    const searchResults = Array.isArray(browseData?.searchResults) ? browseData.searchResults : [];

    if (searchResults.length === 0) {
      const emptyState = browseData?.resultsEmptyState || {};

      return `
        <div class="plan-your-day__results-empty" data-plan-results-empty>
          <h4>${escapeHtml(emptyState.heading || '')}</h4>
          <p>${escapeHtml(emptyState.body || '')}</p>
        </div>
      `;
    }

    return `
      <ul class="plan-your-day__results-list" data-plan-results-list>
        ${searchResults.map((result) => renderResultItemMarkup(result, selectedWaypointIds, strings)).join('')}
      </ul>
    `;
  };

  const renderLoadMoreMarkup = (browseData, strings, isLoadingMore = false) => {
    if (!browseData?.hasMoreResults) {
      return '';
    }

    const buttonLabel = isLoadingMore
      ? String(strings.loadingMoreResults || strings.moreResultsButton || '')
      : String(strings.moreResultsButton || '');

    return `
      <div class="plan-your-day__load-more" data-plan-load-more-wrap>
        <button class="plan-your-day__load-more-button${isLoadingMore ? ' is-loading' : ''}" type="button" data-plan-load-more-button${
          isLoadingMore ? ' disabled aria-disabled="true"' : ''
        }>
          ${escapeHtml(buttonLabel)}
        </button>
      </div>
    `;
  };

  const renderResultsMarkup = (browseData, selectedWaypointIds, strings, options = {}) => `
    <div class="plan-your-day__results-body" data-plan-results-body>
      ${renderResultsContentMarkup(browseData, selectedWaypointIds, strings)}
    </div>
    ${renderLoadMoreMarkup(browseData, strings, Boolean(options.isLoadingMore))}
  `;

  const getLoadedResultIds = (browseData) => {
    const loadedResultIds = [];

    (Array.isArray(browseData?.searchResults) ? browseData.searchResults : []).forEach((result) => {
      const placeId = String(result?.id || '');

      if (placeId && !loadedResultIds.includes(placeId)) {
        loadedResultIds.push(placeId);
      }
    });

    return loadedResultIds;
  };

  const getAppendableSearchResults = (currentBrowseData, incomingBrowseData) => {
    const seenPlaceIds = new Set(getLoadedResultIds(currentBrowseData));

    return (Array.isArray(incomingBrowseData?.searchResults) ? incomingBrowseData.searchResults : []).filter((result) => {
      const placeId = String(result?.id || '');

      if (!placeId) {
        return true;
      }

      if (seenPlaceIds.has(placeId)) {
        return false;
      }

      seenPlaceIds.add(placeId);

      return true;
    });
  };

  const mergeBrowseResults = (currentBrowseData, incomingBrowseData) => ({
    ...(currentBrowseData || {}),
    ...(incomingBrowseData || {}),
    searchResults: [
      ...(Array.isArray(currentBrowseData?.searchResults) ? currentBrowseData.searchResults : []),
      ...getAppendableSearchResults(currentBrowseData, incomingBrowseData),
    ],
  });

  const getLoadMoreAnnouncement = (newResultsCount, browseData, strings) => {
    if (newResultsCount > 0) {
      return formatTemplate(strings.loadedMoreResults || '', { count: newResultsCount });
    }

    if (!browseData?.hasMoreResults) {
      return String(strings.noMoreResults || '');
    }

    return String(strings.resultsUpdated || '');
  };

  const renderTripHeaderMarkup = (routeData, strings) => {
    const selectedWaypointIds = toStringArray(routeData?.selectedWaypointIds);
    const tripCountLabel = String(routeData?.tripCountLabel || '');

    return `
      <span class="plan-your-day__count-pill" data-plan-trip-count>${escapeHtml(tripCountLabel)}</span>
      ${
        selectedWaypointIds.length > 0
          ? `<button class="plan-your-day__clear-link" type="submit" name="clear_trip" value="1" data-plan-clear-trip data-plan-action="clear-trip" data-plan-route-mutation>${escapeHtml(
              strings.clearTrip || ''
            )}</button>`
          : ''
      }
    `;
  };

  const getWaypointStatusLabel = (routeData, strings) => {
    const waypointCount = toStringArray(routeData?.selectedWaypointIds).length;

    if (waypointCount <= 0) {
      return String(strings.waypointStatusEmpty || 'Add some waypoints!');
    }

    return formatTemplate(
      waypointCount === 1
        ? String(strings.waypointStatusSingle || '{count} waypoint added')
        : String(strings.waypointStatusPlural || '{count} waypoints added'),
      { count: waypointCount }
    );
  };

  const renderTripMarkup = (routeData, strings, tripHelpId) => {
    const tripWaypoints = Array.isArray(routeData?.tripWaypoints) ? routeData.tripWaypoints : [];

    if (tripWaypoints.length === 0) {
      const tripEmptyState = routeData?.tripEmptyState || {};

      return `
        <div class="plan-your-day__trip-empty" data-plan-trip-empty>
          <h4>${escapeHtml(tripEmptyState.heading || strings.tripEmptyHeading || '')}</h4>
          <p>${escapeHtml(tripEmptyState.body || strings.tripEmptyBody || '')}</p>
        </div>
      `;
    }

    return `
      <ol class="plan-your-day__trip-list" data-plan-trip-list${tripHelpId ? ` aria-describedby="${escapeHtml(tripHelpId)}"` : ''}>
        ${tripWaypoints
          .map((waypoint, index) => {
            const placeId = String(waypoint?.id || '');
            const label = String(waypoint?.label || '');
            const address = String(waypoint?.address || '');
            const canMoveUp = index > 0;
            const canMoveDown = index < tripWaypoints.length - 1;

            return `
              <li class="plan-your-day__trip-item" data-waypoint-id="${escapeHtml(placeId)}">
                <div class="plan-your-day__trip-main">
                  <span class="plan-your-day__trip-number" aria-hidden="true">${escapeHtml(String(index + 1))}</span>
                  <div class="plan-your-day__trip-copy">
                    <h4>${escapeHtml(label)}</h4>
                    <p class="plan-your-day__trip-meta">${escapeHtml(address)}</p>
                  </div>
                </div>
                <div class="plan-your-day__trip-tools">
                  <button
                    class="plan-your-day__reorder-button plan-your-day__reorder-button--up"
                    type="${canMoveUp ? 'submit' : 'button'}"
                    name="move_waypoint"
                    value="${escapeHtml(`${placeId}:up`)}"
                    data-plan-route-mutation
                    aria-label="${escapeHtml(formatTemplate(strings.moveWaypointUpLabel || '', { place: label }))}"
                    ${canMoveUp ? '' : 'disabled'}>
                    ${escapeHtml(strings.moveUp || '')}
                  </button>
                  <button
                    class="plan-your-day__reorder-button plan-your-day__reorder-button--down"
                    type="${canMoveDown ? 'submit' : 'button'}"
                    name="move_waypoint"
                    value="${escapeHtml(`${placeId}:down`)}"
                    data-plan-route-mutation
                    aria-label="${escapeHtml(formatTemplate(strings.moveWaypointDownLabel || '', { place: label }))}"
                    ${canMoveDown ? '' : 'disabled'}>
                    ${escapeHtml(strings.moveDown || '')}
                  </button>
                  <button type="submit" name="remove_waypoint" value="${escapeHtml(
                    placeId
                  )}" data-plan-action="remove-waypoint" data-plan-route-mutation data-place-id="${escapeHtml(placeId)}">
                    ${escapeHtml(formatTemplate(strings.removeWaypointLabel || '', { place: label }))}
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

  const hasResultsPanelContent = (panel) =>
    panel instanceof HTMLElement && panel.querySelector('[data-plan-results-list], [data-plan-results-empty]') instanceof HTMLElement;

  const appendResultsToPanel = (panel, appendedResults, selectedWaypointIds, strings) => {
    if (!(panel instanceof HTMLElement) || !Array.isArray(appendedResults) || appendedResults.length === 0) {
      return false;
    }

    const resultsList = panel.querySelector('[data-plan-results-list]');

    if (!(resultsList instanceof HTMLElement)) {
      return false;
    }

    const emptyState = panel.querySelector('[data-plan-results-empty]');

    if (emptyState instanceof HTMLElement) {
      emptyState.remove();
    }

    resultsList.insertAdjacentHTML(
      'beforeend',
      appendedResults.map((result) => renderResultItemMarkup(result, selectedWaypointIds, strings)).join('')
    );

    return true;
  };

  const syncLoadMoreMarkup = (panel, browseData, strings, isLoadingMore) => {
    if (!(panel instanceof HTMLElement)) {
      return;
    }

    const existingControl = panel.querySelector('[data-plan-load-more-wrap]');
    const nextMarkup = renderLoadMoreMarkup(browseData, strings, isLoadingMore);

    if (!nextMarkup) {
      if (existingControl instanceof HTMLElement) {
        existingControl.remove();
      }

      return;
    }

    if (existingControl instanceof HTMLElement) {
      existingControl.outerHTML = nextMarkup;
      return;
    }

    panel.insertAdjacentHTML('beforeend', nextMarkup);
  };

  const renderResultsPanel = (panel, browseData, selectedWaypointIds, strings, options = {}) => {
    if (!(panel instanceof HTMLElement)) {
      return;
    }

    const appendResults = Boolean(options.appendResults);
    const appendedResults = Array.isArray(options.appendedResults) ? options.appendedResults : [];

    if (!appendResults) {
      panel.innerHTML = renderResultsMarkup(browseData, selectedWaypointIds, strings, options);
      return;
    }

    if (appendedResults.length > 0 && appendResultsToPanel(panel, appendedResults, selectedWaypointIds, strings)) {
      syncLoadMoreMarkup(panel, browseData, strings, Boolean(options.isLoadingMore));
      return;
    }

    if (appendedResults.length === 0 && hasResultsPanelContent(panel)) {
      syncLoadMoreMarkup(panel, browseData, strings, Boolean(options.isLoadingMore));
      return;
    }

    panel.innerHTML = renderResultsMarkup(browseData, selectedWaypointIds, strings, options);
  };

  const renderCategoryPanels = (refs, state, strings, options = {}) => {
    const activeCategory = state.category || '';
    const expandedCategory = state.expandedCategory || '';
    const selectedWaypointIds = toStringArray(state.route?.selectedWaypointIds);
    const browseData = state.browse || {};
    const hasCustomSearch = Boolean(browseData.hasSearch) && !activeCategory;
    const shouldShowCustomResults = hasCustomSearch || refs.categoryButtons.length === 0;
    const isCustomResultsExpanded =
      (hasCustomSearch && Boolean(state.customResultsExpanded)) || (!hasCustomSearch && refs.categoryButtons.length === 0);
    const panelRenderOptions = {
      appendResults: Boolean(options.appendResults),
      appendedResults: Array.isArray(options.appendedResults) ? options.appendedResults : [],
      isLoadingMore: Boolean(options.isLoadingMore),
    };

    refs.categoryButtons.forEach((button) => {
      const categoryKey = button.getAttribute('data-category-key') || '';
      const isActive = categoryKey === activeCategory && categoryKey === expandedCategory;
      const accordionItem = button.closest('.plan-your-day__category-accordion-item');

      button.setAttribute('aria-expanded', String(isActive));

      if (accordionItem instanceof HTMLElement) {
        accordionItem.classList.toggle('is-expanded', isActive);
      }
    });

    refs.categoryRegions.forEach((region) => {
      const categoryKey = region.getAttribute('data-category-key') || '';
      const isActive = categoryKey === activeCategory && categoryKey === expandedCategory;
      const panel = region.querySelector('[data-plan-category-results-panel]');

      region.hidden = !isActive;

      if (panel instanceof HTMLElement) {
        if (!isActive) {
          panel.innerHTML = '';
          return;
        }

        renderResultsPanel(panel, browseData, selectedWaypointIds, strings, panelRenderOptions);
      }
    });

    if (refs.customResults) {
      refs.customResults.hidden = !shouldShowCustomResults;
      refs.customResults.classList.toggle('is-expanded', isCustomResultsExpanded);
    }

    if (refs.customResultsButton) {
      refs.customResultsButton.setAttribute('aria-expanded', String(isCustomResultsExpanded));
    }

    if (refs.customResultsRegion) {
      refs.customResultsRegion.hidden = !isCustomResultsExpanded;
    }

    if (refs.customResultsHeading) {
      refs.customResultsHeading.textContent = hasCustomSearch
        ? formatTemplate(strings.searchResultsFor || '', { search: browseData.categoryLabel || '' })
        : String(browseData?.resultsEmptyState?.heading || '');
    }

    if (refs.customResultsDescription) {
      refs.customResultsDescription.textContent = hasCustomSearch
        ? String(strings.customSearchResultsDescription || '')
        : String(browseData?.resultsEmptyState?.body || '');
    }

    if (refs.customResultsPanel) {
      if (!isCustomResultsExpanded) {
        refs.customResultsPanel.innerHTML = '';
      } else if (hasCustomSearch) {
        renderResultsPanel(refs.customResultsPanel, browseData, selectedWaypointIds, strings, panelRenderOptions);
      } else {
        refs.customResultsPanel.innerHTML = renderResultsMarkup(browseData, selectedWaypointIds, strings, {
          isLoadingMore: false,
        });
      }
    }
  };

  const renderTrip = (refs, state, strings) => {
    if (refs.tripHeaderActions) {
      refs.tripHeaderActions.innerHTML = renderTripHeaderMarkup(state.route, strings);
    }

    if (refs.tripRegion) {
      refs.tripRegion.innerHTML = renderTripMarkup(
        state.route,
        strings,
        refs.tripRegion.getAttribute('data-plan-trip-help-id') || ''
      );
    }
  };

  const renderPreview = (refs, state, strings) => {
    const routeData = state.route || {};
    const iframeSrc = String(routeData.iframeSrc || '');
    const emptyPreviewState = routeData.emptyPreviewState || {};
    const mapsUrl = String(routeData.mapsUrl || '');
    const hasSelectedWaypoints = toStringArray(routeData.selectedWaypointIds).length > 0;

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
      refs.summaryCount.hidden = hasSelectedWaypoints;
    }

    if (refs.openLinkLabel) {
      refs.openLinkLabel.textContent = String(routeData.mapsLinkLabel || '');
    }

    if (refs.openLink) {
      refs.openLink.hidden = !hasSelectedWaypoints;
      refs.openLink.classList.toggle('is-disabled', mapsUrl === '');

      if (mapsUrl) {
        refs.openLink.href = mapsUrl;
        refs.openLink.removeAttribute('aria-disabled');
        refs.openLink.removeAttribute('tabindex');
        refs.openLink.removeAttribute('role');
      } else {
        refs.openLink.removeAttribute('href');
        refs.openLink.setAttribute('aria-disabled', 'true');
        refs.openLink.setAttribute('tabindex', '0');
        refs.openLink.setAttribute('role', 'button');
      }
    }
  };

  const syncWaypointStatus = (refs, state, strings) => {
    if (!(refs.waypointStatus instanceof HTMLButtonElement)) {
      return;
    }

    refs.waypointStatus.textContent = getWaypointStatusLabel(state.route, strings);
  };

  const focusElement = (element) => {
    if (!(element instanceof HTMLElement) || typeof element.focus !== 'function') {
      return false;
    }

    element.focus();

    return document.activeElement === element;
  };

  const buildRouteFocusRequest = (submitter) => {
    if (!(submitter instanceof HTMLButtonElement)) {
      return null;
    }

    if (submitter.matches('[data-plan-action="add-waypoint"]')) {
      return {
        action: 'add-waypoint',
        placeId: submitter.getAttribute('data-place-id') || submitter.value || '',
      };
    }

    if (submitter.matches('[data-plan-action="remove-waypoint"]')) {
      return {
        action: 'remove-waypoint',
        placeId: submitter.getAttribute('data-place-id') || submitter.value || '',
      };
    }

    if (submitter.matches('[data-plan-clear-trip]')) {
      return {
        action: 'clear-trip',
        placeId: '',
      };
    }

    if (submitter.name === 'move_waypoint' && submitter.value) {
      const [placeId, direction] = String(submitter.value).split(':', 2);

      return {
        action: 'move-waypoint',
        placeId: placeId || '',
        direction: direction || '',
      };
    }

    return null;
  };

  const parseWaypointMoveValue = (moveValue) => {
    const [placeId, direction] = String(moveValue || '').split(':', 2);

    if (!placeId || (direction !== 'up' && direction !== 'down')) {
      return null;
    }

    return {
      placeId,
      direction,
    };
  };

  const moveWaypointIds = (selectedWaypointIds, moveValue) => {
    const move = parseWaypointMoveValue(moveValue);
    const nextWaypointIds = toStringArray(selectedWaypointIds);

    if (!move) {
      return nextWaypointIds;
    }

    const currentIndex = nextWaypointIds.indexOf(move.placeId);
    const targetIndex = move.direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (
      currentIndex < 0 ||
      targetIndex < 0 ||
      targetIndex >= nextWaypointIds.length
    ) {
      return nextWaypointIds;
    }

    [nextWaypointIds[currentIndex], nextWaypointIds[targetIndex]] = [
      nextWaypointIds[targetIndex],
      nextWaypointIds[currentIndex],
    ];

    return nextWaypointIds;
  };

  const sortTripWaypointsByIds = (tripWaypoints, waypointIds) => {
    const waypointById = new Map();

    (Array.isArray(tripWaypoints) ? tripWaypoints : []).forEach((waypoint) => {
      const placeId = String(waypoint?.id || '');

      if (placeId) {
        waypointById.set(placeId, waypoint);
      }
    });

    return toStringArray(waypointIds)
      .map((placeId) => waypointById.get(placeId))
      .filter(Boolean);
  };

  const getValidLocalTripWaypoints = (tripWaypoints) =>
    (Array.isArray(tripWaypoints) ? tripWaypoints : [])
      .map((waypoint) => ({
        id: String(waypoint?.id || '').trim(),
        label: String(waypoint?.label || '').trim(),
        address: String(waypoint?.address || '').trim(),
      }))
      .filter((waypoint) => waypoint.id && (waypoint.label || waypoint.address));

  const getWaypointDirectionsLabel = (waypoint) => String(waypoint?.address || waypoint?.label || '').trim();

  const buildLocalEmbedDirectionsUrl = (currentIframeSrc, tripWaypoints) => {
    const waypoints = getValidLocalTripWaypoints(tripWaypoints);

    if (waypoints.length === 0) {
      return '';
    }

    const currentSrc = String(currentIframeSrc || '');

    if (!currentSrc) {
      return '';
    }

    try {
      const url = new URL(currentSrc);
      const apiKey = url.searchParams.get('key') || '';
      const origin = url.searchParams.get('origin') || '';

      if (!apiKey || !origin) {
        return currentSrc;
      }

      const destination = waypoints[waypoints.length - 1];
      const intermediates = waypoints.slice(0, -1);

      url.searchParams.set('key', apiKey);
      url.searchParams.set('origin', origin);
      url.searchParams.set('destination', `place_id:${destination.id}`);
      url.searchParams.set('mode', 'walking');

      if (intermediates.length > 0) {
        url.searchParams.set('waypoints', intermediates.map((waypoint) => `place_id:${waypoint.id}`).join('|'));
      } else {
        url.searchParams.delete('waypoints');
      }

      return url.toString();
    } catch (error) {
      return currentSrc;
    }
  };

  const buildLocalDirectionsHandoffUrl = (currentMapsUrl, tripWaypoints) => {
    const waypoints = getValidLocalTripWaypoints(tripWaypoints);

    if (waypoints.length === 0) {
      return '';
    }

    try {
      const url = new URL(String(currentMapsUrl || 'https://www.google.com/maps/dir/'));
      const origin = url.searchParams.get('origin') || '';
      const destination = waypoints[waypoints.length - 1];
      const intermediates = waypoints.slice(0, -1);

      url.searchParams.set('api', '1');
      url.searchParams.set('destination', getWaypointDirectionsLabel(destination));
      url.searchParams.set('destination_place_id', destination.id);
      url.searchParams.set('travelmode', 'walking');

      if (origin) {
        url.searchParams.set('origin', origin);
      } else {
        url.searchParams.delete('origin');
      }

      if (intermediates.length > 0) {
        url.searchParams.set('waypoints', intermediates.map(getWaypointDirectionsLabel).join('|'));
        url.searchParams.set('waypoint_place_ids', intermediates.map((waypoint) => waypoint.id).join('|'));
      } else {
        url.searchParams.delete('waypoints');
        url.searchParams.delete('waypoint_place_ids');
      }

      return url.toString();
    } catch (error) {
      return String(currentMapsUrl || '');
    }
  };

  const restoreRouteActionFocus = (refs, focusRequest) => {
    if (!focusRequest) {
      return;
    }

    if (focusRequest.action === 'add-waypoint') {
      return;
    }

    const placeId = String(focusRequest.placeId || '');
    const tripHeaderActionButton =
      refs.tripHeaderActions?.querySelector('button:not([disabled]):not([hidden])');
    let target = null;

    if (placeId && focusRequest.action === 'move-waypoint') {
      const direction = String(focusRequest.direction || '');

      target =
        refs.tripRegion?.querySelector(
          `[data-waypoint-id="${placeId}"] button[name="move_waypoint"][value="${placeId}:${direction}"]`
        ) ||
        refs.tripRegion?.querySelector(
          `[data-waypoint-id="${placeId}"] button[name="remove_waypoint"]`
        );
    } else if (focusRequest.action === 'remove-waypoint') {
      target =
        refs.tripRegion?.querySelector('[data-plan-trip-list] button[name="remove_waypoint"]') ||
        tripHeaderActionButton;
    } else if (focusRequest.action === 'clear-trip') {
      target = tripHeaderActionButton || refs.tripHeading;
    }

    if (focusElement(target)) {
      return;
    }

    focusElement(refs.tripHeading);
  };

  const shouldRefreshBrowseRoute = (routeData) => toStringArray(routeData?.selectedWaypointIds).length === 0;

  const syncCustomStartStatusUi = (refs, status, strings) => {
    const normalizedStatus = normalizeCustomStartStatus(status);

    if (refs.customStartWrap instanceof HTMLElement) {
      refs.customStartWrap.setAttribute('data-plan-custom-start-state', normalizedStatus);
    }

    if (refs.customStartStatus instanceof HTMLElement) {
      refs.customStartStatus.textContent = getCustomStartStatusMessage(normalizedStatus, strings);
    }
  };

  const syncStartUi = (refs, state, strings) => {
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

    syncCustomStartStatusUi(refs, isCustom ? state.customStartStatus : '', strings);
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

  const setManagedControlBusyState = (controls, isBusy, stateAttribute) => {
    controls.forEach((control) => {
      if (
        !(control instanceof HTMLButtonElement) &&
        !(control instanceof HTMLInputElement)
      ) {
        return;
      }

      if (isBusy) {
        if (!control.hasAttribute(stateAttribute)) {
          control.setAttribute(stateAttribute, control.disabled ? 'true' : 'false');
        }

        control.disabled = true;
        return;
      }

      const disabledBeforeRequest = control.getAttribute(stateAttribute);

      if (null === disabledBeforeRequest) {
        return;
      }

      control.disabled = disabledBeforeRequest === 'true';
      control.removeAttribute(stateAttribute);
    });
  };

  const setRouteMutationBusyState = (root, isBusy) => {
    setManagedControlBusyState(
      root.querySelectorAll('[data-plan-route-mutation]'),
      isBusy,
      'data-plan-disabled-before-request'
    );
  };

  const setBrowseControlsBusyState = (root, isBusy) => {
    setManagedControlBusyState(
      root.querySelectorAll(
        [
          '[data-plan-form] button[type="submit"]:not([data-plan-route-mutation])',
          '[data-plan-load-more-button]',
          '[data-plan-start-toggle]',
          '[data-plan-custom-results-button]',
          'input[name="start_mode"]',
          '[data-plan-custom-start]',
          '[data-plan-category-search]',
        ].join(',')
      ),
      isBusy,
      'data-plan-browse-disabled-before-request'
    );
  };

  const setRegionBusyState = (refs, state, isBusy) => {
    const activeCategory = state.category || '';

    refs.categoryPanels.forEach((panel) => {
      const categoryKey = panel.getAttribute('data-category-key') || '';
      const shouldMarkBusy =
        refs.categoryButtons.length === 0 || categoryKey === activeCategory || refs.customResults?.hidden === false;

      panel.setAttribute('aria-busy', String(isBusy && shouldMarkBusy));
    });

    if (refs.customResultsPanel instanceof HTMLElement) {
      const customResultsVisible = refs.customResults?.hidden === false;
      refs.customResultsPanel.setAttribute('aria-busy', String(isBusy && customResultsVisible));
    }

    if (refs.tripRegion instanceof HTMLElement) {
      refs.tripRegion.setAttribute('aria-busy', String(isBusy));
    }

    if (refs.previewCard instanceof HTMLElement) {
      refs.previewCard.setAttribute('aria-busy', String(isBusy));
    }
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
      categoryRegions: Array.from(root.querySelectorAll('[data-plan-category-region]')),
      categoryPanels: Array.from(root.querySelectorAll('[data-plan-category-results-panel]')),
      categorySearchInput: root.querySelector('[data-plan-category-search]'),
      customResults: root.querySelector('[data-plan-custom-results]'),
      customResultsButton: root.querySelector('[data-plan-custom-results-button]'),
      customResultsHeading: root.querySelector('[data-plan-custom-results-heading]'),
      customResultsDescription: root.querySelector('[data-plan-custom-results-description]'),
      customResultsRegion: root.querySelector('[data-plan-custom-results-region]'),
      customResultsPanel: root.querySelector('[data-plan-custom-results-panel]'),
      resultsHeading: root.querySelector('[data-plan-results-heading]'),
      startModeInputs: Array.from(root.querySelectorAll('input[name="start_mode"]')),
      customStartWrap: root.querySelector('[data-plan-custom-start-wrap]'),
      customStartInput: root.querySelector('[data-plan-custom-start]'),
      customStartStatus: root.querySelector('[data-plan-custom-start-status]'),
      startToggle: root.querySelector('[data-plan-start-toggle]'),
      startToggleLabel: root.querySelector('[data-plan-start-toggle-label]'),
      startPanel: root.querySelector('[data-plan-start-panel]'),
      colorModeToggle: root.querySelector('[data-plan-color-mode-toggle]'),
      colorModeToggleLabel: root.querySelector('[data-plan-color-mode-toggle-label]'),
      tripHeaderActions: root.querySelector('[data-plan-trip-header-actions]'),
      tripHeading: root.querySelector('[data-plan-trip-heading]'),
      tripRegion: root.querySelector('[data-plan-trip-region]'),
      messages: root.querySelector('[data-plan-messages]'),
      previewCard: root.querySelector('[data-plan-preview-card]'),
      mapWrap: root.querySelector('[data-plan-map-wrap]'),
      iframe: root.querySelector('[data-plan-iframe]'),
      previewEmpty: root.querySelector('[data-plan-preview-empty]'),
      previewEmptyHeading: root.querySelector('[data-plan-preview-empty-heading]'),
      previewEmptyBody: root.querySelector('[data-plan-preview-empty-body]'),
      summaryCount: root.querySelector('[data-plan-summary-count]'),
      openLink: root.querySelector('[data-plan-open-link]'),
      openLinkLabel: root.querySelector('[data-plan-open-link-label]'),
      waypointStatus: root.querySelector('[data-plan-waypoint-status]'),
      apiCallCounter: root.querySelector('[data-plan-api-call-counter]'),
      apiCallCount: root.querySelector('[data-plan-api-call-count]'),
      apiCallBreakdown: root.querySelector('[data-plan-api-call-breakdown]'),
    };

    const state = {
      category: String(config.initialState?.category || ''),
      categorySearch: String(config.initialState?.categorySearch || ''),
      startMode: String(config.initialState?.startMode || 'default'),
      customStart: String(config.initialState?.customStart || ''),
      customStartStatus: normalizeCustomStartStatus(config.initialData?.browse?.customStartStatus || ''),
      expandedCategory: String(config.initialState?.category || ''),
      customResultsExpanded: Boolean(config.initialData?.browse?.isCustomSearch),
      isLoadingMore: false,
      browse: config.initialData?.browse || {},
      route: config.initialData?.route || {},
    };
    const canBootstrapEndpointToken =
      typeof config.rest?.bootstrapUrl === 'string' &&
      config.rest.bootstrapUrl !== '';
    let endpointToken = typeof config.rest?.endpointToken === 'string' ? config.rest.endpointToken : '';
    let hasBootstrappedEndpointToken = false;
    let endpointTokenRequest = null;
    const hasRestConfig =
      refs.form instanceof HTMLFormElement &&
      typeof config.rest?.browseUrl === 'string' &&
      config.rest.browseUrl !== '' &&
      typeof config.rest?.routeUrl === 'string' &&
      config.rest.routeUrl !== '' &&
      (endpointToken !== '' || canBootstrapEndpointToken);
    const shouldHydrateOnLoad = Boolean(config.hydration?.shouldHydrateOnLoad);
    const colorModeDefault = normalizeColorModeDefault(
      config.colorModeDefault || root.getAttribute('data-plan-color-mode-default')
    );
    const colorModeMediaQuery = getColorModeMediaQuery();

    let isStartPanelOpen = true;
    let activeRequestController = null;
    let activeRequestEndpointKey = '';
    let activeRequestId = 0;
    let pendingRouteFocusRequest = null;
    const prefersReducedMotion =
      typeof window !== 'undefined' &&
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let startPanelAnimationFrame = 0;
    const apiCallCounts = {
      bootstrap: 0,
      browse: 0,
      route: 0,
    };

    const syncApiCallCounter = () => {
      if (!(refs.apiCallCounter instanceof HTMLElement)) {
        return;
      }

      const totalCalls = apiCallCounts.bootstrap + apiCallCounts.browse + apiCallCounts.route;
      const breakdown = `Bootstrap ${apiCallCounts.bootstrap}, Browse ${apiCallCounts.browse}, Route ${apiCallCounts.route}`;

      if (refs.apiCallCount instanceof HTMLElement) {
        refs.apiCallCount.textContent = String(totalCalls);
      }

      if (refs.apiCallBreakdown instanceof HTMLElement) {
        refs.apiCallBreakdown.textContent = breakdown;
      }

      refs.apiCallCounter.setAttribute('title', breakdown);
      refs.apiCallCounter.setAttribute('aria-label', `API calls: ${totalCalls}. ${breakdown}.`);
    };

    const recordApiCall = (apiCallType) => {
      if (!Object.prototype.hasOwnProperty.call(apiCallCounts, apiCallType)) {
        return;
      }

      apiCallCounts[apiCallType] += 1;
      syncApiCallCounter();
    };

    applyColorMode(root, refs, resolveColorMode(colorModeDefault, colorModeMediaQuery), strings);
    syncApiCallCounter();

    if (colorModeDefault === COLOR_MODE_SYSTEM && colorModeMediaQuery) {
      colorModeMediaQuery.addEventListener('change', () => {
        if (getStoredColorMode()) {
          return;
        }

        applyColorMode(root, refs, resolveColorMode(colorModeDefault, colorModeMediaQuery), strings);
      });
    }

    const renderAll = () => {
      renderCategoryPanels(refs, state, strings, {
        isLoadingMore: state.isLoadingMore,
      });
      renderTrip(refs, state, strings);
      renderPreview(refs, state, strings);
      syncWaypointStatus(refs, state, strings);
      syncHiddenInputs(refs, state);
      syncStartUi(refs, state, strings);
      syncCategorySearchUi(refs, state);
      setRouteMutationBusyState(root, false);
    };

    const applyLocalWaypointMove = (moveValue, focusRequest) => {
      const currentWaypointIds = toStringArray(state.route?.selectedWaypointIds);
      const nextWaypointIds = moveWaypointIds(currentWaypointIds, moveValue);
      const didMove =
        currentWaypointIds.length !== nextWaypointIds.length ||
        currentWaypointIds.some((waypointId, index) => waypointId !== nextWaypointIds[index]);

      if (!didMove) {
        return true;
      }

      const nextTripWaypoints = sortTripWaypointsByIds(state.route?.tripWaypoints, nextWaypointIds);

      if (nextTripWaypoints.length !== nextWaypointIds.length) {
        return false;
      }

      state.route = {
        ...(state.route || {}),
        selectedWaypointIds: nextWaypointIds,
        tripWaypoints: nextTripWaypoints,
        hasTrip: nextWaypointIds.length > 0,
        iframeSrc: buildLocalEmbedDirectionsUrl(state.route?.iframeSrc || '', nextTripWaypoints),
        mapsUrl: buildLocalDirectionsHandoffUrl(state.route?.mapsUrl || '', nextTripWaypoints),
      };

      renderTrip(refs, state, strings);
      renderPreview(refs, state, strings);
      syncWaypointStatus(refs, state, strings);
      syncHiddenInputs(refs, state);
      restoreRouteActionFocus(refs, focusRequest);
      announce(refs.liveRegion, strings.tripUpdated || '');
      debugLog(config, 'info', 'route:move-local', {
        moveWaypoint: moveValue,
        selectedWaypointIds: nextWaypointIds,
      });

      return true;
    };

    const showRequestError = (message, announcementMessage = '') => {
      renderMessages(refs, [
        {
          type: 'warning',
          text: message || strings.requestFailed || '',
        },
      ]);

      announce(refs.liveRegion, announcementMessage || message || strings.requestFailed || '');
    };

    const updateStartPanelState = (options = {}) => {
      if (!refs.startToggle || !refs.startPanel) {
        return;
      }

      const syncHidden = options.syncHidden !== false;

      refs.startToggle.hidden = false;
      refs.startToggle.setAttribute('aria-expanded', String(isStartPanelOpen));
      refs.startToggle.classList.toggle('is-collapsed', !isStartPanelOpen);

      if (syncHidden) {
        refs.startPanel.hidden = !isStartPanelOpen;
      }

      if (refs.startToggleLabel) {
        refs.startToggleLabel.textContent = isStartPanelOpen
          ? String(strings.hideStartOptions || 'Hide options')
          : String(strings.showStartOptions || 'Show options');
      }
    };

    const easeInOutCubic = (progress) =>
      progress < 0.5
        ? 4 * progress * progress * progress
        : 1 - Math.pow(-2 * progress + 2, 3) / 2;

    const animateStartPanel = (nextOpenState) => {
      if (!(refs.startPanel instanceof HTMLElement) || !refs.startToggle) {
        isStartPanelOpen = nextOpenState;
        updateStartPanelState();
        return;
      }

      const panel = refs.startPanel;
      const duration = prefersReducedMotion ? 0 : START_PANEL_ANIMATION_MS;

      if (startPanelAnimationFrame) {
        window.cancelAnimationFrame(startPanelAnimationFrame);
        startPanelAnimationFrame = 0;
      }

      const panelWasHidden = panel.hidden;
      const startHeight = panelWasHidden ? 0 : panel.getBoundingClientRect().height;

      panel.hidden = false;

      if (nextOpenState) {
        panel.style.height = '';
      }

      const endHeight = nextOpenState ? panel.scrollHeight : 0;
      const startOpacity = nextOpenState && endHeight > 0 ? Math.max(startHeight / endHeight, 0) : 1;
      const endOpacity = nextOpenState ? 1 : 0;

      isStartPanelOpen = nextOpenState;
      panel.style.overflow = 'hidden';
      panel.style.pointerEvents = 'none';
      panel.style.height = `${startHeight}px`;
      panel.style.opacity = String(startOpacity);
      updateStartPanelState({ syncHidden: false });

      if (duration <= 0 || startHeight === endHeight) {
        panel.style.height = '';
        panel.style.overflow = '';
        panel.style.pointerEvents = '';
        panel.style.opacity = '';
        panel.hidden = !nextOpenState;
        updateStartPanelState();

        return;
      }

      const startedAt = performance.now();

      const finishAnimation = () => {
        panel.style.height = '';
        panel.style.overflow = '';
        panel.style.pointerEvents = '';
        panel.style.opacity = '';
        panel.hidden = !nextOpenState;
        updateStartPanelState();

        startPanelAnimationFrame = 0;
      };

      const stepAnimation = (now) => {
        const rawProgress = Math.min((now - startedAt) / duration, 1);
        const easedProgress = easeInOutCubic(rawProgress);
        const currentHeight = startHeight + (endHeight - startHeight) * easedProgress;
        const currentOpacity = startOpacity + (endOpacity - startOpacity) * easedProgress;

        panel.style.height = `${Math.max(currentHeight, 0)}px`;
        panel.style.opacity = String(Math.max(Math.min(currentOpacity, 1), 0));

        if (rawProgress < 1) {
          startPanelAnimationFrame = window.requestAnimationFrame(stepAnimation);
          return;
        }

        finishAnimation();
      };

      startPanelAnimationFrame = window.requestAnimationFrame(stepAnimation);
    };

    const openStartPanel = () => {
      if (isStartPanelOpen) {
        return;
      }

      animateStartPanel(true);
    };

    const closeStartPanel = () => {
      if (!isStartPanelOpen) {
        return;
      }

      animateStartPanel(false);
    };

    const ensureEndpointToken = async () => {
      if (!canBootstrapEndpointToken) {
        return endpointToken;
      }

      if (hasBootstrappedEndpointToken && endpointToken !== '') {
        return endpointToken;
      }

      if (endpointTokenRequest) {
        return endpointTokenRequest;
      }

      recordApiCall('bootstrap');
      endpointTokenRequest = fetch(config.rest.bootstrapUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({}),
      })
        .then(async (response) => {
          const responseBody = await response.json().catch(() => ({}));
          debugLog(config, response.ok ? 'info' : 'warn', 'request:bootstrap', {
            status: response.status,
            ok: response.ok,
            body: responseBody,
          });

          if (!response.ok) {
            throw new Error(responseBody?.message || strings.requestFailed || '');
          }

          const freshToken = String(responseBody?.endpointToken || '');

          if (freshToken === '') {
            throw new Error(strings.requestFailed || '');
          }

          endpointToken = freshToken;
          hasBootstrappedEndpointToken = true;

          return endpointToken;
        })
        .finally(() => {
          endpointTokenRequest = null;
        });

      return endpointTokenRequest;
    };

    const sendRequest = async (endpointKey, payload, requestOptions = {}) => {
      if (!hasRestConfig) {
        return 'unsupported';
      }

      const appendBrowseResults = Boolean(requestOptions.appendBrowseResults);
      const announcementMessage = String(requestOptions.announcementMessage || '');
      const errorMessage = String(requestOptions.errorMessage || '');
      const searchContextKey = String(requestOptions.searchContextKey || '');
      const refreshRoute = endpointKey === 'browse' ? requestOptions.refreshRoute !== false : false;
      const routeFocusRequest = endpointKey === 'route' ? requestOptions.routeFocusRequest ?? null : null;
      const payloadStartMode = String(payload.start_mode || '');
      const payloadCustomStart = String(payload.custom_start || '');
      const shouldCheckCustomStart =
        endpointKey === 'browse' &&
        !appendBrowseResults &&
        payloadStartMode === 'custom' &&
        payloadCustomStart.trim() !== '';
      const shouldClearRouteCustomStartStatus =
        endpointKey === 'route' &&
        (state.customStartStatus === CUSTOM_START_STATUS_CHECKING ||
          payloadStartMode !== 'custom' ||
          payloadCustomStart.trim() === '' ||
          payloadCustomStart !== String(state.customStart || ''));

      if (activeRequestController instanceof AbortController) {
        if (activeRequestEndpointKey === 'route') {
          debugLog(config, 'info', 'request:blocked', {
            endpointKey,
            blockedBy: activeRequestEndpointKey,
          });

          return 'busy';
        }

        activeRequestController.abort();
      }

      activeRequestId += 1;
      const requestId = activeRequestId;

      if (endpointKey === 'route') {
        pendingRouteFocusRequest = routeFocusRequest
          ? {
              requestId,
              focusRequest: routeFocusRequest,
            }
          : null;
      }

      activeRequestController = new AbortController();
      activeRequestEndpointKey = endpointKey;
      setBusyState(root, true);
      setRegionBusyState(refs, state, true);
      setRouteMutationBusyState(root, endpointKey === 'route');
      setBrowseControlsBusyState(root, endpointKey === 'route');
      if (shouldCheckCustomStart) {
        state.customStartStatus = CUSTOM_START_STATUS_CHECKING;
        syncStartUi(refs, state, strings);
      } else if ((endpointKey === 'browse' && !appendBrowseResults) || shouldClearRouteCustomStartStatus) {
        state.customStartStatus = '';
        syncStartUi(refs, state, strings);
      }
      debugLog(config, 'info', 'request:start', {
        endpointKey,
        payload,
      });

      try {
        const requestEndpointToken = await ensureEndpointToken();

        if (requestEndpointToken === '') {
          throw new Error(strings.requestFailed || '');
        }

        const requestBody = {
          ...payload,
          endpoint_token: requestEndpointToken,
        };

        if (endpointKey === 'browse') {
          requestBody.refresh_route = refreshRoute;

          if (searchContextKey !== '') {
            requestBody.search_context_key = searchContextKey;
          }
        }

        recordApiCall(endpointKey);
        const response = await fetch(config.rest[endpointKey === 'browse' ? 'browseUrl' : 'routeUrl'], {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(requestBody),
          signal: activeRequestController.signal,
        });
        const responseBody = await response.json().catch(() => ({}));
        debugLog(config, response.ok ? 'info' : 'warn', 'request:response', {
          endpointKey,
          status: response.status,
          ok: response.ok,
          body: responseBody,
        });

        if (!response.ok) {
          throw new Error(responseBody?.message || strings.requestFailed || '');
        }

        if (requestId !== activeRequestId) {
          return true;
        }

        if (endpointKey === 'browse') {
          const previousCategory = state.category || '';
          const previousExpandedCategory = state.expandedCategory || '';
          const previousCategorySearch = state.categorySearch || '';
          const responseBrowse = responseBody?.browse || {};
          const loadMoreRequestFailed =
            appendBrowseResults && String(responseBrowse.searchResultsError || '') !== '';

          if (loadMoreRequestFailed) {
            state.isLoadingMore = false;
            renderCategoryPanels(refs, state, strings, {
              appendResults: true,
              appendedResults: [],
              isLoadingMore: false,
            });
            showRequestError(errorMessage || strings.requestFailed || '', errorMessage || strings.requestFailed || '');

            return 'failed';
          }

          const appendResponseResults =
            appendBrowseResults &&
            searchContextKey !== '' &&
            searchContextKey === String(state.browse?.searchContextKey || '') &&
            searchContextKey === String(responseBrowse.searchContextKey || '');
          const appendedResults = appendResponseResults
            ? getAppendableSearchResults(state.browse, responseBrowse)
            : [];

          state.browse = appendResponseResults ? mergeBrowseResults(state.browse, responseBrowse) : responseBrowse;
          state.route = responseBody?.route || state.route || {};
          state.category = String(state.browse.categoryKey || payload.category || '');
          state.categorySearch = String(state.browse.categorySearch || payload.category_search || '');
          state.customStartStatus = normalizeCustomStartStatus(state.browse.customStartStatus || '');
          state.isLoadingMore = false;

          if (state.category) {
            state.expandedCategory = state.category === previousCategory ? previousExpandedCategory : state.category;
            state.customResultsExpanded = false;
          } else if (!state.browse.hasSearch) {
            state.expandedCategory = '';
            state.customResultsExpanded = false;
          } else if (String(payload.category_search || '') !== previousCategorySearch) {
            state.expandedCategory = '';
            state.customResultsExpanded = true;
          }

          if (appendResponseResults) {
            renderCategoryPanels(refs, state, strings, {
              appendResults: true,
              appendedResults,
              isLoadingMore: false,
            });
            renderTrip(refs, state, strings);
            renderPreview(refs, state, strings);
            syncWaypointStatus(refs, state, strings);
            syncHiddenInputs(refs, state);
            syncStartUi(refs, state, strings);
            syncCategorySearchUi(refs, state);
            setRouteMutationBusyState(root, false);
            announce(
              refs.liveRegion,
              state.browse?.searchResultsError
                ? errorMessage || strings.requestFailed || ''
                : getLoadMoreAnnouncement(appendedResults.length, state.browse, strings)
            );

            return 'success';
          }
        } else {
          state.route = responseBody?.route || state.route || {};
          state.category = String(state.route.categoryKey || state.category || '');
          state.categorySearch = String(state.route.categorySearch || payload.category_search || '');
        }

        state.startMode = String(payload.start_mode || state.startMode || 'default');
        state.customStart = String(payload.custom_start || '');

        renderAll();
        if (
          endpointKey === 'route' &&
          pendingRouteFocusRequest &&
          pendingRouteFocusRequest.requestId === requestId
        ) {
          restoreRouteActionFocus(refs, pendingRouteFocusRequest.focusRequest);
          pendingRouteFocusRequest = null;
        }
        announce(refs.liveRegion, announcementMessage || '');

        return 'success';
      } catch (error) {
        if (error?.name === 'AbortError') {
          debugLog(config, 'info', 'request:aborted', {
            endpointKey,
          });
          if (requestId === activeRequestId && shouldCheckCustomStart) {
            state.customStartStatus = '';
            syncStartUi(refs, state, strings);
          }
          return 'aborted';
        }

        if (requestId !== activeRequestId) {
          return 'stale';
        }

        debugLog(config, 'error', 'request:failed', {
          endpointKey,
          error: error instanceof Error ? error.message : String(error || ''),
          payload,
        });
        if (appendBrowseResults) {
          state.isLoadingMore = false;
          renderCategoryPanels(refs, state, strings, {
            appendResults: true,
            appendedResults: [],
            isLoadingMore: false,
          });
        }

        showRequestError(
          appendBrowseResults ? errorMessage || strings.requestFailed || '' : error instanceof Error ? error.message : strings.requestFailed || '',
          appendBrowseResults ? errorMessage || strings.requestFailed || '' : ''
        );
        if (shouldCheckCustomStart) {
          state.customStartStatus = '';
          syncStartUi(refs, state, strings);
        }

        return 'failed';
      } finally {
        if (requestId === activeRequestId) {
          if (appendBrowseResults && state.isLoadingMore) {
            state.isLoadingMore = false;
          }

          setBusyState(root, false);
          setRegionBusyState(refs, state, false);
          setRouteMutationBusyState(root, false);
          if (
            endpointKey === 'route' &&
            pendingRouteFocusRequest &&
            pendingRouteFocusRequest.requestId === requestId
          ) {
            pendingRouteFocusRequest = null;
          }
          setBrowseControlsBusyState(root, false);
          activeRequestController = null;
          activeRequestEndpointKey = '';
        }
      }
    };

    refs.startModeInputs.forEach((input) => {
      input.addEventListener('change', () => {
        state.startMode = getCheckedStartMode(refs.startModeInputs) || state.startMode || 'default';
        syncStartUi(refs, state, strings);

        if (!hasRestConfig) {
          announce(refs.liveRegion, strings.startingPointUpdated || '');
          return;
        }

        void sendRequest('browse', buildPayload(refs, state), {
          announcementMessage: strings.startingPointUpdated || '',
          refreshRoute: true,
        });
      });
    });

    if (refs.customStartInput instanceof HTMLInputElement) {
      refs.customStartInput.addEventListener('change', () => {
        state.customStart = refs.customStartInput.value || '';
        syncStartUi(refs, state, strings);

        if (!hasRestConfig) {
          announce(refs.liveRegion, strings.startingPointUpdated || '');
          return;
        }

        void sendRequest('browse', buildPayload(refs, state), {
          announcementMessage: strings.startingPointUpdated || '',
          refreshRoute: true,
        });
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
        state.expandedCategory = '';
        state.customResultsExpanded = true;

        void sendRequest('browse', payload, {
          announcementMessage: strings.resultsUpdated || '',
          refreshRoute: shouldRefreshBrowseRoute(state.route),
        });
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
          const nextCategory = submitter.getAttribute('data-category-key') || '';

          if (nextCategory === state.category) {
            const categoryLabel =
              submitter.querySelector('.plan-your-day__category-title')?.textContent?.trim() || nextCategory;

            event.preventDefault();
            state.expandedCategory = state.expandedCategory === nextCategory ? '' : nextCategory;
            state.customResultsExpanded = false;
            renderCategoryPanels(refs, state, strings);
            announce(
              refs.liveRegion,
              state.expandedCategory === nextCategory
                ? formatTemplate(strings.categoryResultsExpanded || '', { category: categoryLabel })
                : formatTemplate(strings.categoryResultsCollapsed || '', { category: categoryLabel })
            );
            return;
          }

          payload.category = nextCategory;
          payload.category_search = '';
          state.expandedCategory = nextCategory;
          state.customResultsExpanded = false;
        } else if (submitter.matches('[data-plan-action="search-category-query"]')) {
          payload.category = '';
          payload.category_search = refs.categorySearchInput instanceof HTMLInputElement ? refs.categorySearchInput.value || '' : '';
          state.expandedCategory = '';
          state.customResultsExpanded = true;
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
          const routeFocusRequest = buildRouteFocusRequest(submitter);

          event.preventDefault();
          if (applyLocalWaypointMove(submitter.value, routeFocusRequest)) {
            return;
          }

          payload.move_waypoint = submitter.value;
          endpointKey = 'route';
          announcementMessage = strings.tripUpdated || '';
        }

        event.preventDefault();
        void sendRequest(endpointKey, payload, {
          announcementMessage,
          refreshRoute: endpointKey === 'browse' ? shouldRefreshBrowseRoute(state.route) : undefined,
          routeFocusRequest: endpointKey === 'route' ? buildRouteFocusRequest(submitter) : null,
        });
      });
    }

    root.addEventListener('click', (event) => {
      const target = event.target;

      if (!(target instanceof HTMLElement)) {
        return;
      }

      const disabledOpenLink = target.closest('[data-plan-open-link][aria-disabled="true"]');

      if (disabledOpenLink instanceof HTMLElement) {
        event.preventDefault();
        announce(refs.liveRegion, strings.openMapsDisabled || '');
        return;
      }

      const waypointStatus = target.closest('[data-plan-waypoint-status]');

      if (waypointStatus instanceof HTMLButtonElement) {
        const scrollTarget =
          refs.mapWrap instanceof HTMLElement && !refs.mapWrap.hidden
            ? refs.mapWrap
            : refs.previewCard;

        event.preventDefault();

        if (scrollTarget instanceof HTMLElement) {
          scrollTarget.scrollIntoView({
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
            block: 'start',
          });
        }

        return;
      }

      const startToggle = target.closest('[data-plan-start-toggle]');

      if (startToggle instanceof HTMLButtonElement) {
        event.preventDefault();
        if (isStartPanelOpen) {
          closeStartPanel();
        } else {
          openStartPanel();
        }
        announce(
          refs.liveRegion,
          isStartPanelOpen ? strings.startOptionsExpanded || '' : strings.startOptionsCollapsed || ''
        );
        return;
      }

      const colorModeToggle = target.closest('[data-plan-color-mode-toggle]');

      if (colorModeToggle instanceof HTMLButtonElement) {
        event.preventDefault();

        const currentMode = root.getAttribute('data-plan-color-mode') === COLOR_MODE_DARK
          ? COLOR_MODE_DARK
          : COLOR_MODE_LIGHT;
        const nextMode = currentMode === COLOR_MODE_DARK ? COLOR_MODE_LIGHT : COLOR_MODE_DARK;

        setStoredColorMode(nextMode);
        applyColorMode(root, refs, nextMode, strings);
        return;
      }

      const customResultsButton = target.closest('[data-plan-custom-results-button]');

      if (customResultsButton instanceof HTMLButtonElement) {
        event.preventDefault();

        if (!state.browse?.hasSearch || state.category) {
          return;
        }

        state.expandedCategory = '';
        state.customResultsExpanded = !state.customResultsExpanded;
        renderCategoryPanels(refs, state, strings);
        announce(
          refs.liveRegion,
          state.customResultsExpanded
            ? String(strings.customResultsExpanded || '')
            : String(strings.customResultsCollapsed || '')
        );
        return;
      }

      const loadMoreButton = target.closest('[data-plan-load-more-button]');

      if (loadMoreButton instanceof HTMLButtonElement) {
        event.preventDefault();

        if (!hasRestConfig || state.isLoadingMore || loadMoreButton.disabled) {
          return;
        }

        const nextPageToken = String(state.browse?.nextPageToken || '');

        if (!nextPageToken) {
          announce(refs.liveRegion, strings.noMoreResults || '');
          return;
        }

        state.isLoadingMore = true;
        renderCategoryPanels(refs, state, strings, {
          appendResults: true,
          appendedResults: [],
          isLoadingMore: true,
        });
        announce(refs.liveRegion, strings.loadingMoreResults || '');

        void sendRequest(
          'browse',
          {
            ...buildPayload(refs, state),
            page_token: nextPageToken,
            append_results: true,
          },
          {
            appendBrowseResults: true,
            errorMessage: strings.loadMoreError || '',
            refreshRoute: false,
            searchContextKey: String(state.browse?.searchContextKey || ''),
          }
        );
      }
    });

    root.addEventListener('keydown', (event) => {
      const target = event.target;

      if (!(target instanceof HTMLElement)) {
        return;
      }

      const disabledOpenLink = target.closest('[data-plan-open-link][aria-disabled="true"]');

      if (!(disabledOpenLink instanceof HTMLElement)) {
        return;
      }

      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      event.preventDefault();
      announce(refs.liveRegion, strings.openMapsDisabled || '');
    });

    renderAll();
    updateStartPanelState();
    root.classList.add('is-enhanced');

    if (shouldHydrateOnLoad) {
      if (!hasRestConfig) {
        showRequestError(strings.requestFailed || '');
        return;
      }

      void sendRequest('browse', buildPayload(refs, state), {
        refreshRoute: true,
      });
    }
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
