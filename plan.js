(() => {
  const root = document.querySelector('[data-plan-root]');

  if (!root) {
    return;
  }

  const parseConfig = () => {
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

  const config = parseConfig();

  const refs = {
    form: root.querySelector('[data-plan-form]'),
    autoNote: root.querySelector('[data-plan-auto-note]'),
    liveRegion: root.querySelector('[data-plan-live-region]'),
    categoryInput: root.querySelector('[data-plan-category-input]'),
    categoryButtons: Array.from(root.querySelectorAll('[data-plan-category-button]')),
    resultsCount: root.querySelector('[data-plan-results-count]'),
    startModeInputs: Array.from(root.querySelectorAll('input[name="start_mode"]')),
    startToggle: root.querySelector('[data-plan-start-toggle]'),
    startToggleLabel: root.querySelector('[data-plan-start-toggle-label]'),
    startPanel: root.querySelector('[data-plan-start-panel]'),
    customStartWrap: root.querySelector('[data-plan-custom-start-wrap]'),
    customStartInput: root.querySelector('[data-plan-custom-start]'),
    startNote: root.querySelector('[data-plan-start-note]'),
    waypointInputsWrap: root.querySelector('[data-plan-waypoint-inputs]'),
    tripHeading: root.querySelector('[data-plan-trip-heading]'),
    tripHeaderActions: root.querySelector('[data-plan-trip-header-actions]'),
    tripRegion: root.querySelector('[data-plan-trip-region]'),
    previewCard: root.querySelector('[data-plan-preview-card]'),
    previewHeading: root.querySelector('[data-plan-preview-heading]'),
    openLink: root.querySelector('[data-plan-open-link]'),
    openLinkLabel: root.querySelector('[data-plan-open-link-label]'),
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
    summaryPreviewStart: root.querySelector('[data-plan-summary-preview-start]'),
    summaryMode: root.querySelector('[data-plan-summary-mode]'),
    summaryWaypoints: root.querySelector('[data-plan-summary-waypoints]'),
  };

  let customInputTimer = 0;
  let browseRequestController = null;
  let routeRequestController = null;
  let browseRequestToken = 0;
  let routeRequestToken = 0;
  let isStartPanelOpen = true;
  let startPanelAnimationCleanup = null;
  let waypointFeedbackTimer = 0;

  const waypointFeedback = {
    placeId: '',
    message: '',
  };

  const dragState = {
    waypointId: '',
    targetWaypointId: '',
    position: 'after',
  };

  const debugState = (window.__dkcPlanDebug = window.__dkcPlanDebug || {
    browseFetches: 0,
    routeFetches: 0,
    distanceRecalculations: 0,
    routeRecalculations: 0,
  });

  const state = {
    browse: {
      activeCategoryKey: config.initialState?.category || '',
      categoryLabel: config.initialData?.browse?.categoryLabel || 'Not selected',
      searchResults: Array.isArray(config.initialData?.browse?.searchResults)
        ? config.initialData.browse.searchResults
        : [],
      searchResultsLabel: config.initialData?.browse?.searchResultsLabel || 'No Google results loaded',
      resultsEmptyState: config.initialData?.browse?.resultsEmptyState || {
        heading: 'Pick a category to search Google',
        body: 'Choose coffee, food, beaches, shopping, history / culture, or another category to load real place results.',
      },
      messages: Array.isArray(config.initialData?.browse?.messages) ? config.initialData.browse.messages : [],
      cache: {},
      loading: false,
    },
    trip: {
      selectedWaypointIds: Array.isArray(config.initialState?.selectedWaypointIds)
        ? [...config.initialState.selectedWaypointIds]
        : [],
      waypoints: Array.isArray(config.initialData?.route?.tripWaypoints)
        ? [...config.initialData.route.tripWaypoints]
        : [],
    },
    route: {
      categoryKey: config.initialData?.route?.categoryKey || config.initialState?.category || '',
      categoryLabel: config.initialData?.route?.categoryLabel || 'Not selected',
      hasTrip: Boolean(config.initialData?.route?.hasTrip),
      iframeSrc: config.initialData?.route?.iframeSrc || '',
      mapsUrl: config.initialData?.route?.mapsUrl || '',
      mapsLinkLabel: config.initialData?.route?.mapsLinkLabel || 'Explore in Google Maps',
      previewModeLabel: config.initialData?.route?.previewModeLabel || 'Google place search',
      overview: config.initialData?.route?.overview || 'Choose a category to load Google results, then add exact places to your trip.',
      previewStartLabel: config.initialData?.route?.previewStartLabel || 'Kailua Pier',
      handoffStartLabel: config.initialData?.route?.handoffStartLabel || 'Kailua Pier',
      startNoteText:
        config.initialData?.route?.startNoteText ||
        'The on-page results and preview use Kailua Pier as the trip start. Google Maps will do the same.',
      emptyPreviewState: config.initialData?.route?.emptyPreviewState || {
        heading: 'Start with a category search',
        body: 'Choose a category to load Google results, then add the places you want to turn into trip waypoints.',
      },
      messages: Array.isArray(config.initialData?.route?.messages) ? config.initialData.route.messages : [],
      loading: false,
    },
    start: {
      mode: config.initialState?.startMode || 'pier',
      customStart: config.initialState?.customStart || '',
    },
  };

  const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  const logDebug = (type, detail = {}) => {
    if (type === 'browse-fetch') {
      debugState.browseFetches += 1;
    } else if (type === 'route-fetch') {
      debugState.routeFetches += 1;
    } else if (type === 'distance-refresh') {
      debugState.distanceRecalculations += 1;
    } else if (type === 'route-recalculation') {
      debugState.routeRecalculations += 1;
    }

    console.info('[DKC Plan]', type, detail);
  };

  const announce = (message) => {
    if (!refs.liveRegion || !message) {
      return;
    }

    refs.liveRegion.textContent = '';

    window.requestAnimationFrame(() => {
      if (refs.liveRegion) {
        refs.liveRegion.textContent = message;
      }
    });
  };

  const renderWaypointFeedbackMarkup = (placeId) => {
    if (!placeId || waypointFeedback.placeId !== placeId || !waypointFeedback.message) {
      return '';
    }

    return `<span class="dkc-plan__waypoint-feedback" aria-hidden="true">${escapeHtml(waypointFeedback.message)}</span>`;
  };

  const clearWaypointFeedback = () => {
    waypointFeedback.placeId = '';
    waypointFeedback.message = '';
    renderBrowseState();
    renderTripRegion();
  };

  const showWaypointFeedback = (placeId, message) => {
    if (!placeId || !message) {
      return;
    }

    window.clearTimeout(waypointFeedbackTimer);
    waypointFeedback.placeId = placeId;
    waypointFeedback.message = message;
    renderBrowseState();
    renderTripRegion();

    waypointFeedbackTimer = window.setTimeout(() => {
      clearWaypointFeedback();
    }, 1500);
  };

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const escapeAttr = (value) => escapeHtml(value);

  const safeHttpsUrl = (value) =>
    /^https:\/\/[^\s<>"']+$/i.test(String(value ?? '')) ? String(value) : '';

  const getCurrentPlannerState = () => ({
    category: state.browse.activeCategoryKey,
    selectedWaypointIds: [...state.trip.selectedWaypointIds],
    startMode: state.start.mode,
    customStart: state.start.customStart,
  });

  const appendStateToSearchParams = (searchParams, plannerState) => {
    if (plannerState.category) {
      searchParams.set('category', plannerState.category);
    }

    plannerState.selectedWaypointIds.forEach((waypointId) => {
      searchParams.append('waypoints[]', waypointId);
    });

    if (plannerState.startMode && plannerState.startMode !== 'pier') {
      searchParams.set('start_mode', plannerState.startMode);
    }

    if (plannerState.startMode === 'custom' && plannerState.customStart.trim() !== '') {
      searchParams.set('custom_start', plannerState.customStart.trim());
    }
  };

  const buildUrlFromState = (plannerState) => {
    const nextUrl = new URL(config.actionUrl || window.location.pathname, window.location.origin);
    appendStateToSearchParams(nextUrl.searchParams, plannerState);
    nextUrl.hash = config.sectionId || '';
    return nextUrl;
  };

  const buildAjaxUrl = (action) => {
    const nextUrl = new URL(config.ajaxUrl || window.location.pathname, window.location.origin);
    nextUrl.searchParams.set('action', action);
    if (config.ajaxNonce) {
      nextUrl.searchParams.set('nonce', config.ajaxNonce);
    }
    appendStateToSearchParams(nextUrl.searchParams, getCurrentPlannerState());
    return nextUrl;
  };

  const syncUrl = () => {
    const nextUrl = buildUrlFromState(getCurrentPlannerState());
    window.history.replaceState({}, '', `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`);
  };

  const formatWaypointCount = (count) => `${count} waypoint${count === 1 ? '' : 's'} selected`;

  const buildEmptyResultsState = (hasCategory) =>
    hasCategory
      ? {
          heading: 'No matching Google results',
          body: 'Try a different category or change the starting area to search a different part of Kona.',
        }
      : {
          heading: 'Pick a category to search Google',
          body: 'Choose coffee, food, beaches, shopping, history / culture, or another category to load real place results.',
        };

  const buildEmptyPreviewState = (hasCategory, hasTrip) => {
    if (!hasCategory && !hasTrip) {
      return {
        heading: 'Start with a category search',
        body: 'Choose a category to load Google results, then add the places you want to turn into trip waypoints.',
      };
    }

    if (hasCategory && !hasTrip) {
      return {
        heading: 'Search preview unavailable',
        body: 'The on-page map preview needs a valid Google Maps Embed API key. The Google Maps search link still works.',
      };
    }

    return {
      heading: 'Trip preview unavailable',
      body: 'The on-page trip preview needs a valid Google Maps Embed API key. The Google Maps handoff link still works.',
    };
  };

  const getCategoryLabel = (categoryKey) => {
    if (!categoryKey) {
      return 'Not selected';
    }

    return config.categoryCatalog?.[categoryKey]?.label || categoryKey;
  };

  const buildLocalSearchContext = () => {
    const customStart = state.start.customStart.trim();

    if (state.start.mode === 'current') {
      return {
        previewStartLabel: config.startPoints?.pier?.label || 'Kailua Pier',
        handoffStartLabel: 'Current location',
        handoffSummary: 'your current location',
        startNoteText:
          'The on-page results and preview use Kailua Pier so they work without geolocation. Google Maps will start from the current location.',
        messages: [
          {
            type: 'note',
            text: 'The on-page results and preview use Kailua Pier so they work without geolocation. Google Maps will start from the current location when you hand the trip off.',
          },
        ],
      };
    }

    if (state.start.mode === 'custom' && customStart) {
      return {
        previewStartLabel: customStart,
        handoffStartLabel: customStart,
        handoffSummary: customStart,
        startNoteText:
          'The on-page results, preview, and Google Maps handoff all use your custom starting point.',
        messages: [],
      };
    }

    if (state.start.mode === 'custom') {
      return {
        previewStartLabel: config.startPoints?.pier?.label || 'Kailua Pier',
        handoffStartLabel: 'Kailua Pier fallback',
        handoffSummary: 'Kailua Pier until you add a custom starting point',
        startNoteText:
          'Add a custom address to replace the Kailua Pier fallback for search results, the route preview, and the Google Maps handoff.',
        messages: [
          {
            type: 'warning',
            text: 'Add a hotel or address to replace the Kailua Pier fallback before finalizing the trip start.',
          },
        ],
      };
    }

    return {
      previewStartLabel: config.startPoints?.pier?.label || 'Kailua Pier',
      handoffStartLabel: config.startPoints?.pier?.label || 'Kailua Pier',
      handoffSummary: config.startPoints?.pier?.label || 'Kailua Pier',
      startNoteText:
        'The on-page results and preview use Kailua Pier as the trip start. Google Maps will do the same.',
      messages: [],
    };
  };

  const buildLocalIdleRouteState = () => {
    const searchContext = buildLocalSearchContext();
    const hasCategory = Boolean(state.browse.activeCategoryKey);
    const hasTrip = state.trip.selectedWaypointIds.length > 0;

    return {
      ...state.route,
      hasTrip,
      iframeSrc: '',
      mapsUrl: '',
      mapsLinkLabel: 'Explore in Google Maps',
      previewModeLabel: 'Google place search',
      overview: hasCategory
        ? `Browsing Google results for ${getCategoryLabel(
            state.browse.activeCategoryKey
          )} near ${searchContext.handoffSummary}. Add any result to start building a walking trip.`
        : 'Choose a category to load Google results, then add exact places to your trip.',
      previewStartLabel: searchContext.previewStartLabel,
      handoffStartLabel: searchContext.handoffStartLabel,
      startNoteText: searchContext.startNoteText,
      emptyPreviewState: buildEmptyPreviewState(hasCategory, false),
      messages: searchContext.messages,
    };
  };

  const getBrowseSignature = () => {
    const customStart = state.start.customStart.trim();

    if (state.start.mode === 'custom' && customStart) {
      return `custom:${customStart}`;
    }

    return 'pier-fallback';
  };

  const getRouteSignature = () => {
    const customStart = state.start.customStart.trim();

    if (state.start.mode === 'custom') {
      return `custom:${customStart || 'pier-fallback'}`;
    }

    return state.start.mode;
  };

  const getBrowseCacheKey = (categoryKey = state.browse.activeCategoryKey) =>
    `${categoryKey}::${getBrowseSignature()}`;

  const arraysEqual = (first, second) =>
    first.length === second.length && first.every((value, index) => value === second[index]);

  const cacheInitialBrowseState = () => {
    if (!state.browse.activeCategoryKey) {
      return;
    }

    state.browse.cache[getBrowseCacheKey()] = {
      browse: {
        categoryKey: state.browse.activeCategoryKey,
        categoryLabel: state.browse.categoryLabel,
        hasCategory: Boolean(state.browse.activeCategoryKey),
        searchResults: state.browse.searchResults,
        searchResultsLabel: state.browse.searchResultsLabel,
        resultsEmptyState: state.browse.resultsEmptyState,
        messages: state.browse.messages,
      },
    };
  };

  const clearStartPanelAnimation = () => {
    if (typeof startPanelAnimationCleanup === 'function') {
      startPanelAnimationCleanup();
      startPanelAnimationCleanup = null;
    }
  };

  const updateStartToggleUi = () => {
    if (!refs.startToggle) {
      return;
    }

    refs.startToggle.hidden = false;
    refs.startToggle.setAttribute('aria-expanded', String(isStartPanelOpen));
    refs.startToggle.classList.toggle('is-collapsed', !isStartPanelOpen);

    if (refs.startToggleLabel) {
      refs.startToggleLabel.textContent = isStartPanelOpen ? 'Hide' : 'Show';
    }
  };

  const applyStartPanelState = ({ animate = false } = {}) => {
    const panel = refs.startPanel;

    updateStartToggleUi();

    if (!panel) {
      return;
    }

    clearStartPanelAnimation();

    if (!animate || prefersReducedMotion()) {
      panel.hidden = !isStartPanelOpen;
      panel.style.height = '';
      panel.style.opacity = '';
      panel.style.overflow = '';
      panel.style.transition = '';
      return;
    }

    const duration = 220;

    if (isStartPanelOpen) {
      panel.hidden = false;
      panel.style.height = '0px';
      panel.style.opacity = '0';
      panel.style.overflow = 'hidden';
      panel.getBoundingClientRect();
      panel.style.transition = `height ${duration}ms ease, opacity ${duration}ms ease`;
      panel.style.height = `${panel.scrollHeight}px`;
      panel.style.opacity = '1';

      const handleTransitionEnd = (event) => {
        if (event.target !== panel || event.propertyName !== 'height') {
          return;
        }

        panel.removeEventListener('transitionend', handleTransitionEnd);
        panel.style.height = '';
        panel.style.opacity = '';
        panel.style.overflow = '';
        panel.style.transition = '';
        startPanelAnimationCleanup = null;
      };

      panel.addEventListener('transitionend', handleTransitionEnd);
      startPanelAnimationCleanup = () => {
        panel.removeEventListener('transitionend', handleTransitionEnd);
        panel.style.height = '';
        panel.style.opacity = '';
        panel.style.overflow = '';
        panel.style.transition = '';
      };

      return;
    }

    panel.hidden = false;
    panel.style.height = `${panel.scrollHeight}px`;
    panel.style.opacity = '1';
    panel.style.overflow = 'hidden';
    panel.getBoundingClientRect();
    panel.style.transition = `height ${duration}ms ease, opacity ${duration}ms ease`;
    panel.style.height = '0px';
    panel.style.opacity = '0';

    const handleTransitionEnd = (event) => {
      if (event.target !== panel || event.propertyName !== 'height') {
        return;
      }

      panel.removeEventListener('transitionend', handleTransitionEnd);
      panel.hidden = true;
      panel.style.height = '';
      panel.style.opacity = '';
      panel.style.overflow = '';
      panel.style.transition = '';
      startPanelAnimationCleanup = null;
    };

    panel.addEventListener('transitionend', handleTransitionEnd);
    startPanelAnimationCleanup = () => {
      panel.removeEventListener('transitionend', handleTransitionEnd);
      panel.hidden = !isStartPanelOpen;
      panel.style.height = '';
      panel.style.opacity = '';
      panel.style.overflow = '';
      panel.style.transition = '';
    };
  };

  const getCategoryPanel = (categoryKey) =>
    root.querySelector(`[data-plan-category-results-panel][data-category-key="${window.CSS?.escape ? window.CSS.escape(categoryKey) : categoryKey}"]`);

  const getCategoryRegion = (button) => {
    const panelId = button.getAttribute('aria-controls');
    return panelId ? root.querySelector(`#${window.CSS?.escape ? window.CSS.escape(panelId) : panelId}`) : null;
  };

  const getTripList = () => root.querySelector('[data-plan-trip-list]');

  const setBrowseBusy = (isBusy) => {
    state.browse.loading = isBusy;

    const activePanel = state.browse.activeCategoryKey ? getCategoryPanel(state.browse.activeCategoryKey) : null;

    if (activePanel) {
      activePanel.setAttribute('aria-busy', String(isBusy));
    }
  };

  const setRouteBusy = (isBusy) => {
    state.route.loading = isBusy;

    if (refs.tripRegion) {
      refs.tripRegion.setAttribute('aria-busy', String(isBusy));
    }

    if (refs.previewCard) {
      refs.previewCard.setAttribute('aria-busy', String(isBusy));
    }
  };

  const mergeMessages = () => {
    const dedupedMessages = [];
    const seen = new Set();

    [...state.route.messages, ...state.browse.messages].forEach((message) => {
      const key = `${message.type || 'note'}::${message.text || ''}`;

      if (!message?.text || seen.has(key)) {
        return;
      }

      seen.add(key);
      dedupedMessages.push(message);
    });

    return dedupedMessages;
  };

  const renderMessages = () => {
    if (!refs.messages) {
      return;
    }

    const messages = mergeMessages();

    refs.messages.hidden = messages.length === 0;
    refs.messages.innerHTML = messages
      .map(
        (message) =>
          `<li class="dkc-plan__message dkc-plan__message--${escapeAttr(message.type || 'note')}">${escapeHtml(
            message.text
          )}</li>`
      )
      .join('');
  };

  const renderHiddenInputs = () => {
    if (refs.categoryInput) {
      refs.categoryInput.value = state.browse.activeCategoryKey;
    }

    if (!refs.waypointInputsWrap) {
      return;
    }

    refs.waypointInputsWrap.innerHTML = state.trip.selectedWaypointIds
      .map(
        (waypointId) =>
          `<input type="hidden" name="waypoints[]" value="${escapeAttr(
            waypointId
          )}" data-plan-waypoint-input>`
      )
      .join('');
  };

  const renderStartState = () => {
    const searchContext = buildLocalSearchContext();

    refs.startModeInputs.forEach((input) => {
      input.checked = input.value === state.start.mode;
    });

    if (refs.customStartWrap) {
      refs.customStartWrap.hidden = state.start.mode !== 'custom';
    }

    if (refs.customStartInput) {
      refs.customStartInput.value = state.start.customStart;
    }

    if (refs.startNote) {
      refs.startNote.textContent = searchContext.startNoteText;
    }
  };

  const renderResultsMarkup = () => {
    if (state.browse.loading && state.browse.searchResults.length === 0) {
      return `
        <div class="dkc-plan__results-empty">
          <h4>Loading results</h4>
          <p>Google place results are updating for this category.</p>
        </div>
      `;
    }

    if (!state.browse.searchResults.length) {
      return `
        <div class="dkc-plan__results-empty">
          <h4>${escapeHtml(state.browse.resultsEmptyState.heading)}</h4>
          <p>${escapeHtml(state.browse.resultsEmptyState.body)}</p>
        </div>
      `;
    }

    return `
      <ul class="dkc-plan__results-list">
        ${state.browse.searchResults
          .map((result) => {
            const isInTrip = state.trip.selectedWaypointIds.includes(result.id);
            const addAnnouncement = `Added ${result.label} to the trip.`;

            return `
              <li class="dkc-plan__result-item">
                <div class="dkc-plan__result-copy">
                  <h4>${escapeHtml(result.label)}</h4>
                  ${
                    result.distance_label
                      ? `<p class="dkc-plan__result-distance">${escapeHtml(result.distance_label)}</p>`
                      : ''
                  }
                  <p class="dkc-plan__result-meta">${escapeHtml(result.address || '')}</p>
                </div>
                <div class="dkc-plan__result-tools">
                  ${
                    safeHttpsUrl(result.maps_uri)
                      ? `<a class="dkc-plan__result-link" href="${escapeAttr(
                          safeHttpsUrl(result.maps_uri)
                        )}" target="_blank" rel="noopener noreferrer">View in Google Maps</a>`
                      : ''
                  }
                  ${
                    isInTrip
                      ? `<span class="dkc-plan__result-added" aria-label="${escapeAttr(
                          `${result.label} is already in the trip`
                        )}">In trip</span>`
                      : `<button
                          class="dkc-plan__result-add"
                          type="submit"
                          name="waypoints[]"
                          value="${escapeAttr(result.id)}"
                          data-plan-action="add-waypoint"
                          data-place-id="${escapeAttr(result.id)}"
                          aria-label="${escapeAttr(`Add ${result.label} to trip`)}"
                          data-plan-announcement="${escapeAttr(addAnnouncement)}">Add to trip</button>`
                  }
                </div>
                ${renderWaypointFeedbackMarkup(result.id)}
              </li>
            `;
          })
          .join('')}
      </ul>
    `;
  };

  const renderBrowseState = () => {
    if (refs.resultsCount) {
      refs.resultsCount.textContent = state.browse.searchResultsLabel;
    }

    refs.categoryButtons.forEach((button) => {
      const categoryKey = button.getAttribute('data-category-key') || '';
      const isExpanded = state.browse.activeCategoryKey === categoryKey;
      const region = getCategoryRegion(button);
      const resultsPanel = getCategoryPanel(categoryKey);

      button.setAttribute('aria-expanded', String(isExpanded));
      button.closest('.dkc-plan__category-accordion-item')?.classList.toggle('is-expanded', isExpanded);

      if (region) {
        region.hidden = !isExpanded;
      }

      if (resultsPanel) {
        resultsPanel.hidden = !isExpanded;

        if (isExpanded) {
          resultsPanel.innerHTML = renderResultsMarkup();
        } else {
          resultsPanel.innerHTML = '';
        }
      }
    });
  };

  const renderTripHeaderActions = () => {
    if (!refs.tripHeaderActions) {
      return;
    }

    const tripCountLabel = state.trip.selectedWaypointIds.length
      ? formatWaypointCount(state.trip.selectedWaypointIds.length)
      : 'Trip not started';

    refs.tripHeaderActions.innerHTML = `
      <span class="dkc-plan__count-pill" data-plan-trip-count>${escapeHtml(tripCountLabel)}</span>
      ${
        state.trip.selectedWaypointIds.length
          ? `<button
              class="dkc-plan__clear-link"
              type="submit"
              name="clear_trip"
              value="1"
              data-plan-clear-trip
              data-plan-action="clear-trip"
              data-plan-announcement="Cleared the trip waypoints.">Clear trip</button>`
          : ''
      }
    `;
  };

  const renderTripRegion = () => {
    if (!refs.tripRegion) {
      return;
    }

    if (!state.trip.waypoints.length) {
      refs.tripRegion.innerHTML = `
        <div class="dkc-plan__trip-empty" data-plan-trip-empty>
          <h4>Start building the trip</h4>
          <p>Search Google by category, then add the exact places you want as walking-trip waypoints.</p>
        </div>
      `;
      return;
    }

    refs.tripRegion.innerHTML = `
      <ol class="dkc-plan__trip-list" aria-describedby="${escapeAttr(
        refs.tripHeading?.getAttribute('aria-describedby') || 'dkc-plan-your-day-trip-help'
      )}" data-plan-trip-list>
        ${state.trip.waypoints
          .map((waypoint, index) => {
            return `
              <li class="dkc-plan__trip-item" data-waypoint-id="${escapeAttr(waypoint.id)}" draggable="true">
                <div class="dkc-plan__trip-main">
                  <span class="dkc-plan__trip-number" aria-hidden="true">${escapeHtml(index + 1)}</span>
                  <div class="dkc-plan__trip-copy">
                    <h4>${escapeHtml(waypoint.label)}</h4>
                    <p class="dkc-plan__trip-meta">${escapeHtml(waypoint.address || '')}</p>
                  </div>
                </div>
                <div class="dkc-plan__trip-tools">
                  <span class="dkc-plan__drag-handle" aria-hidden="true">Drag</span>
                  <div class="dkc-plan__reorder-links">
                    ${
                      index > 0
                        ? `<button
                            class="dkc-plan__reorder-button dkc-plan__reorder-button--up"
                            type="submit"
                            name="move_waypoint"
                            value="${escapeAttr(`${waypoint.id}:up`)}"
                            data-plan-action="move-waypoint"
                            data-direction="up"
                            data-place-id="${escapeAttr(waypoint.id)}"
                            aria-label="${escapeAttr(`Move ${waypoint.label} up`)}"
                            data-plan-announcement="${escapeAttr(`Moved ${waypoint.label} up.`)}">Move up</button>`
                        : `<button
                            class="dkc-plan__reorder-disabled dkc-plan__reorder-button dkc-plan__reorder-button--up"
                            type="button"
                            disabled>Move up</button>`
                    }
                    ${
                      index < state.trip.waypoints.length - 1
                        ? `<button
                            class="dkc-plan__reorder-button dkc-plan__reorder-button--down"
                            type="submit"
                            name="move_waypoint"
                            value="${escapeAttr(`${waypoint.id}:down`)}"
                            data-plan-action="move-waypoint"
                            data-direction="down"
                            data-place-id="${escapeAttr(waypoint.id)}"
                            aria-label="${escapeAttr(`Move ${waypoint.label} down`)}"
                            data-plan-announcement="${escapeAttr(`Moved ${waypoint.label} down.`)}">Move down</button>`
                        : `<button
                            class="dkc-plan__reorder-disabled dkc-plan__reorder-button dkc-plan__reorder-button--down"
                            type="button"
                            disabled>Move down</button>`
                    }
                  </div>
                  <button
                    type="submit"
                    name="remove_waypoint"
                    value="${escapeAttr(waypoint.id)}"
                    data-plan-action="remove-waypoint"
                    data-place-id="${escapeAttr(waypoint.id)}"
                    aria-label="${escapeAttr(`Remove ${waypoint.label} from trip`)}"
                    data-plan-announcement="${escapeAttr(`Removed ${waypoint.label} from the trip.`)}">Remove</button>
                </div>
                ${renderWaypointFeedbackMarkup(waypoint.id)}
              </li>
            `;
          })
          .join('')}
      </ol>
    `;
  };

  const renderRouteState = () => {
    const tripCountLabel = state.trip.selectedWaypointIds.length
      ? formatWaypointCount(state.trip.selectedWaypointIds.length)
      : 'Trip not started';
    const canOpenTripInMaps = state.trip.selectedWaypointIds.length > 0 && Boolean(state.route.mapsUrl);

    if (refs.openLink) {
      refs.openLink.classList.toggle('is-disabled', !canOpenTripInMaps);

      if (canOpenTripInMaps) {
        refs.openLink.href = state.route.mapsUrl;
        refs.openLink.removeAttribute('aria-disabled');
      } else {
        refs.openLink.removeAttribute('href');
        refs.openLink.setAttribute('aria-disabled', 'true');
      }
    }

    if (refs.openLinkLabel) {
      refs.openLinkLabel.textContent = 'Go!';
    }

    if (refs.mapWrap) {
      refs.mapWrap.hidden = !state.route.iframeSrc;
    }

    if (refs.iframe) {
      const nextIframeSrc = state.route.iframeSrc || '';

      if (refs.iframe.getAttribute('src') !== nextIframeSrc) {
        if (nextIframeSrc) {
          refs.iframe.src = nextIframeSrc;
        } else {
          refs.iframe.removeAttribute('src');
        }
      }
    }

    if (refs.previewEmpty) {
      refs.previewEmpty.hidden = Boolean(state.route.iframeSrc);
    }

    if (refs.previewEmptyHeading) {
      refs.previewEmptyHeading.textContent = state.route.emptyPreviewState?.heading || '';
    }

    if (refs.previewEmptyBody) {
      refs.previewEmptyBody.textContent = state.route.emptyPreviewState?.body || '';
    }

    if (refs.summaryCount) {
      refs.summaryCount.textContent = tripCountLabel;
    }

    if (refs.summaryOverview) {
      refs.summaryOverview.textContent = state.route.overview;
    }

    if (refs.summaryCategory) {
      refs.summaryCategory.textContent = state.browse.categoryLabel;
    }

    if (refs.summaryResults) {
      refs.summaryResults.textContent = state.browse.searchResultsLabel;
    }

    if (refs.summaryHandoffStart) {
      refs.summaryHandoffStart.textContent = state.route.handoffStartLabel;
    }

    if (refs.summaryPreviewStart) {
      refs.summaryPreviewStart.textContent = state.route.previewStartLabel;
    }

    if (refs.summaryMode) {
      refs.summaryMode.textContent = state.route.previewModeLabel;
    }

    if (refs.summaryWaypoints) {
      refs.summaryWaypoints.textContent = tripCountLabel;
    }

    if (refs.startNote) {
      refs.startNote.textContent = state.route.startNoteText;
    }
  };

  const renderAll = () => {
    renderHiddenInputs();
    renderStartState();
    renderBrowseState();
    renderTripHeaderActions();
    renderTripRegion();
    renderRouteState();
    renderMessages();
    root.classList.add('is-enhanced');

    if (refs.autoNote) {
      refs.autoNote.hidden = true;
    }
  };

  const reorderWaypointIds = (waypointIds, draggedWaypointId, targetWaypointId, position) => {
    if (!draggedWaypointId || !targetWaypointId || draggedWaypointId === targetWaypointId) {
      return waypointIds;
    }

    const reorderedWaypointIds = waypointIds.filter((waypointId) => waypointId !== draggedWaypointId);
    const targetIndex = reorderedWaypointIds.indexOf(targetWaypointId);

    if (targetIndex < 0) {
      return waypointIds;
    }

    const insertIndex = position === 'before' ? targetIndex : targetIndex + 1;
    reorderedWaypointIds.splice(insertIndex, 0, draggedWaypointId);
    return reorderedWaypointIds;
  };

  const getTripItemFromTarget = (target) => {
    if (!(target instanceof HTMLElement)) {
      return null;
    }

    return target.closest('[data-waypoint-id]');
  };

  const clearDragStyles = () => {
    getTripList()
      ?.querySelectorAll('.is-dragging, .is-drop-before, .is-drop-after')
      .forEach((item) => {
        item.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after');
      });
  };

  const findCurrentResult = (placeId) => state.browse.searchResults.find((result) => result.id === placeId);

  const syncRouteState = (payload) => {
    state.trip.selectedWaypointIds = Array.isArray(payload.selectedWaypointIds)
      ? [...payload.selectedWaypointIds]
      : state.trip.selectedWaypointIds;
    state.trip.waypoints = Array.isArray(payload.tripWaypoints) ? [...payload.tripWaypoints] : state.trip.waypoints;
    state.route = {
      ...state.route,
      ...payload,
      messages: Array.isArray(payload.messages) ? payload.messages : state.route.messages,
      emptyPreviewState:
        payload.emptyPreviewState || buildEmptyPreviewState(Boolean(state.browse.activeCategoryKey), Boolean(payload.hasTrip)),
    };
    state.route.hasTrip = Boolean(payload.hasTrip);
    syncUrl();
    renderAll();
  };

  const syncBrowseState = (payload) => {
    state.browse.activeCategoryKey = payload.categoryKey || '';
    state.browse.categoryLabel = payload.categoryLabel || getCategoryLabel(payload.categoryKey || '');
    state.browse.searchResults = Array.isArray(payload.searchResults) ? [...payload.searchResults] : [];
    state.browse.searchResultsLabel = payload.searchResultsLabel || 'No Google results loaded';
    state.browse.resultsEmptyState = payload.resultsEmptyState || buildEmptyResultsState(Boolean(payload.categoryKey));
    state.browse.messages = Array.isArray(payload.messages) ? payload.messages : [];
    syncUrl();
    renderAll();
  };

  const fetchJson = async (scope, url, controller) => {
    const response = await window.fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
      signal: controller.signal,
      cache: 'no-store',
    });

    let payload = {};

    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok) {
      throw new Error(payload?.data?.message || `Bad ${scope} response`);
    }

    if (!payload?.success || !payload?.data) {
      throw new Error(payload?.data?.message || `Invalid ${scope} payload`);
    }

    return payload.data;
  };

  const showAjaxError = (scope, message = '') => {
    const fallbackMessage =
      scope === 'browse'
        ? 'Google place results could not be updated. Try again without leaving the page.'
        : 'The trip preview could not be updated. Try again without leaving the page.';
    const warning = {
      type: 'warning',
      text: message || fallbackMessage,
    };

    if (scope === 'browse') {
      state.browse.messages = [warning];
      state.browse.searchResults = [];
      state.browse.searchResultsLabel = 'Google results unavailable';
      state.browse.resultsEmptyState = {
        heading: 'Google results unavailable',
        body: warning.text,
      };
    } else {
      state.route.messages = [warning];
    }

    renderMessages();
    announce(warning.text);
  };

  const requestBrowseData = async (reason, { force = false, focus = '' } = {}) => {
    if (!state.browse.activeCategoryKey) {
      return;
    }

    const cacheKey = getBrowseCacheKey();
    const cachedResponse = state.browse.cache[cacheKey];

    if (!force && cachedResponse) {
      logDebug('browse-cache-hit', {
        reason,
        category: state.browse.activeCategoryKey,
        cacheKey,
      });
      syncBrowseState(cachedResponse.browse);

      if (focus === 'results') {
        refs.categoryButtons.find((button) => button.getAttribute('data-category-key') === state.browse.activeCategoryKey)?.focus();
      }

      return;
    }

    if (browseRequestController) {
      browseRequestController.abort();
    }

    browseRequestController = new window.AbortController();
    browseRequestToken += 1;
    const currentToken = browseRequestToken;

    setBrowseBusy(true);
    renderBrowseState();

    logDebug('browse-fetch', {
      reason,
      category: state.browse.activeCategoryKey,
      startMode: state.start.mode,
    });

    if (reason.includes('start')) {
      logDebug('distance-refresh', {
        reason,
        category: state.browse.activeCategoryKey,
      });
    }

    try {
      const responseData = await fetchJson(
        'browse',
        buildAjaxUrl(config.endpoints?.browseAction || 'dkc_plan_browse'),
        browseRequestController
      );

      if (currentToken !== browseRequestToken) {
        return;
      }

      state.browse.cache[cacheKey] = {
        browse: responseData.browse,
      };
      syncBrowseState(responseData.browse);

      browseRequestController = null;

      if (focus === 'results') {
        refs.categoryButtons.find((button) => button.getAttribute('data-category-key') === state.browse.activeCategoryKey)?.focus();
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      showAjaxError('browse', error.message);
    } finally {
      setBrowseBusy(false);
      renderBrowseState();
    }
  };

  const requestRouteData = async (reason, { focus = '' } = {}) => {
    if (routeRequestController) {
      routeRequestController.abort();
    }

    routeRequestController = new window.AbortController();
    routeRequestToken += 1;
    const currentToken = routeRequestToken;

    setRouteBusy(true);

    logDebug('route-fetch', {
      reason,
      selectedWaypointIds: [...state.trip.selectedWaypointIds],
      startMode: state.start.mode,
    });
    logDebug('route-recalculation', {
      reason,
      selectedWaypointIds: [...state.trip.selectedWaypointIds],
    });

    try {
      const responseData = await fetchJson(
        'route',
        buildAjaxUrl(config.endpoints?.routeAction || 'dkc_plan_route'),
        routeRequestController
      );

      if (currentToken !== routeRequestToken) {
        return;
      }

      syncRouteState(responseData.route);
      routeRequestController = null;

      if (focus === 'trip') {
        refs.tripHeading?.focus();
      } else if (focus === 'preview') {
        refs.previewHeading?.focus();
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      showAjaxError('route', error.message);
    } finally {
      setRouteBusy(false);
    }
  };

  const closeCategoryLocally = () => {
    state.browse.activeCategoryKey = '';
    state.browse.categoryLabel = 'Not selected';
    state.browse.searchResults = [];
    state.browse.searchResultsLabel = 'No Google results loaded';
    state.browse.resultsEmptyState = buildEmptyResultsState(false);
    state.browse.messages = [];

    if (!state.trip.selectedWaypointIds.length) {
      state.route = buildLocalIdleRouteState();
    }

    syncUrl();
    renderAll();
  };

  const clearTripLocally = () => {
    state.trip.selectedWaypointIds = [];
    state.trip.waypoints = [];
    state.route = buildLocalIdleRouteState();
    syncUrl();
    renderAll();
  };

  const addWaypointLocally = (placeId) => {
    if (!placeId || state.trip.selectedWaypointIds.includes(placeId)) {
      return false;
    }

    const result = findCurrentResult(placeId);

    state.trip.selectedWaypointIds = [...state.trip.selectedWaypointIds, placeId];

    if (result) {
      state.trip.waypoints = [
        ...state.trip.waypoints,
        {
          id: result.id,
          label: result.label,
          address: result.address || '',
          maps_uri: result.maps_uri || '',
        },
      ];
    }

    syncUrl();
    renderAll();
    return true;
  };

  const removeWaypointLocally = (placeId) => {
    if (!placeId) {
      return false;
    }

    state.trip.selectedWaypointIds = state.trip.selectedWaypointIds.filter((waypointId) => waypointId !== placeId);
    state.trip.waypoints = state.trip.waypoints.filter((waypoint) => waypoint.id !== placeId);

    if (!state.trip.selectedWaypointIds.length) {
      state.route = buildLocalIdleRouteState();
    }

    syncUrl();
    renderAll();
    return true;
  };

  const moveWaypointLocally = (placeId, direction) => {
    const currentIndex = state.trip.selectedWaypointIds.indexOf(placeId);

    if (currentIndex < 0) {
      return false;
    }

    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (targetIndex < 0 || targetIndex >= state.trip.selectedWaypointIds.length) {
      return false;
    }

    const targetWaypointId = state.trip.selectedWaypointIds[targetIndex];
    const position = direction === 'up' ? 'before' : 'after';

    state.trip.selectedWaypointIds = reorderWaypointIds(
      state.trip.selectedWaypointIds,
      placeId,
      targetWaypointId,
      position
    );
    state.trip.waypoints = reorderTripWaypoints(
      state.trip.waypoints,
      placeId,
      targetWaypointId,
      position
    );
    syncUrl();
    renderAll();
    return true;
  };

  const reorderTripWaypoints = (waypoints, draggedWaypointId, targetWaypointId, position) => {
    if (!draggedWaypointId || !targetWaypointId || draggedWaypointId === targetWaypointId) {
      return waypoints;
    }

    const reorderedWaypoints = waypoints.filter((waypoint) => waypoint.id !== draggedWaypointId);
    const targetIndex = reorderedWaypoints.findIndex((waypoint) => waypoint.id === targetWaypointId);

    if (targetIndex < 0) {
      return waypoints;
    }

    const draggedWaypoint = waypoints.find((waypoint) => waypoint.id === draggedWaypointId);

    if (!draggedWaypoint) {
      return waypoints;
    }

    const insertIndex = position === 'before' ? targetIndex : targetIndex + 1;
    reorderedWaypoints.splice(insertIndex, 0, draggedWaypoint);
    return reorderedWaypoints;
  };

  const syncLocalStartContext = () => {
    const searchContext = buildLocalSearchContext();

    state.route = {
      ...state.route,
      previewStartLabel: searchContext.previewStartLabel,
      handoffStartLabel: searchContext.handoffStartLabel,
      startNoteText: searchContext.startNoteText,
    };

    if (!state.trip.selectedWaypointIds.length && !state.browse.activeCategoryKey) {
      state.route = buildLocalIdleRouteState();
    }

    renderStartState();
    renderRouteState();
    renderMessages();
  };

  const refreshForStartChange = (
    reason,
    {
      force = false,
      announceMessage = '',
      previousBrowseSignature = getBrowseSignature(),
      previousRouteSignature = getRouteSignature(),
    } = {}
  ) => {
    const nextBrowseSignature = getBrowseSignature();
    const nextRouteSignature = getRouteSignature();
    const hasCategory = Boolean(state.browse.activeCategoryKey);
    const hasTrip = state.trip.selectedWaypointIds.length > 0;
    const shouldFetchBrowse = hasCategory && (force || previousBrowseSignature !== nextBrowseSignature);
    const shouldFetchRoute =
      (hasTrip && (force || previousRouteSignature !== nextRouteSignature)) ||
      (!hasTrip && hasCategory && !shouldFetchBrowse && (force || previousRouteSignature !== nextRouteSignature));

    syncUrl();
    syncLocalStartContext();

    if (shouldFetchBrowse) {
      requestBrowseData(reason, { force });
    } else if (!hasCategory) {
      state.browse.messages = [];
      state.browse.searchResults = [];
      state.browse.searchResultsLabel = 'No Google results loaded';
      state.browse.resultsEmptyState = buildEmptyResultsState(false);
      state.browse.categoryLabel = 'Not selected';
      renderBrowseState();
    }

    if (shouldFetchRoute) {
      requestRouteData(reason);
    } else if (!hasTrip && !hasCategory) {
      state.route = buildLocalIdleRouteState();
      renderRouteState();
      renderMessages();
    }

    if (announceMessage) {
      announce(announceMessage);
    }
  };

  cacheInitialBrowseState();
  applyStartPanelState();
  renderAll();

  root.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-plan-form]')) {
      return;
    }

    event.preventDefault();

    const hasCategory = Boolean(state.browse.activeCategoryKey);
    const hasTrip = state.trip.selectedWaypointIds.length > 0;

    syncUrl();

    if (hasCategory) {
      requestBrowseData('manual submit', { force: true, focus: 'results' });
    }

    if (hasTrip) {
      requestRouteData('manual submit', { focus: hasCategory ? '' : 'preview' });
    }

    if (!hasCategory && !hasTrip) {
      state.route = buildLocalIdleRouteState();
      renderRouteState();
      renderMessages();
    }

    announce('Search updated.');
  });

  root.addEventListener('change', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
      return;
    }

    if (target.matches('input[name="start_mode"]')) {
      const previousBrowseSignature = getBrowseSignature();
      const previousRouteSignature = getRouteSignature();
      state.start.mode = target.value;
      window.clearTimeout(customInputTimer);
      refreshForStartChange('start mode change', {
        announceMessage: 'Starting point updated.',
        previousBrowseSignature,
        previousRouteSignature,
      });
    }
  });

  root.addEventListener('input', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement) || !target.matches('[data-plan-custom-start]')) {
      return;
    }

    const previousBrowseSignature = getBrowseSignature();
    const previousRouteSignature = getRouteSignature();
    state.start.customStart = target.value;
    syncUrl();
    syncLocalStartContext();

    if (state.start.mode !== 'custom') {
      return;
    }

    window.clearTimeout(customInputTimer);
    customInputTimer = window.setTimeout(() => {
      refreshForStartChange('custom start typing', {
        announceMessage: 'Custom starting point updated.',
        previousBrowseSignature,
        previousRouteSignature,
      });
    }, 700);
  });

  root.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const disabledOpenLink = target.closest('[data-plan-open-link][aria-disabled="true"]');

    if (disabledOpenLink instanceof HTMLAnchorElement) {
      event.preventDefault();
      return;
    }

    const categoryButton = target.closest('[data-plan-category-button]');

    if (categoryButton instanceof HTMLButtonElement) {
      event.preventDefault();

      const categoryKey = categoryButton.getAttribute('data-category-key') || '';
      const isExpanded = categoryButton.getAttribute('aria-expanded') === 'true';
      const nextCategory = isExpanded ? '' : categoryKey;
      const announcement = nextCategory
        ? `Showing ${getCategoryLabel(categoryKey)} results.`
        : `Closed ${getCategoryLabel(categoryKey)} results.`;

      logDebug('accordion-toggle', {
        from: state.browse.activeCategoryKey,
        to: nextCategory,
      });

      if (!nextCategory) {
        closeCategoryLocally();
        announce(announcement);
        return;
      }

      const cachedResponse = state.browse.cache[`${nextCategory}::${getBrowseSignature()}`];

      state.browse.activeCategoryKey = nextCategory;
      state.browse.categoryLabel = getCategoryLabel(nextCategory);

      if (!cachedResponse) {
        state.browse.searchResults = [];
        state.browse.searchResultsLabel = 'Loading Google results';
        state.browse.resultsEmptyState = {
          heading: 'Loading results',
          body: 'Google place results are updating for this category.',
        };
        state.browse.messages = [];
      }

      syncUrl();
      renderBrowseState();

      requestBrowseData('category accordion open', {
        focus: 'results',
      });
      announce(announcement);
      return;
    }

    const startToggle = target.closest('[data-plan-start-toggle]');

    if (startToggle instanceof HTMLButtonElement) {
      event.preventDefault();
      isStartPanelOpen = !isStartPanelOpen;
      applyStartPanelState({ animate: true });
      announce(isStartPanelOpen ? 'Starting point options expanded.' : 'Starting point options collapsed.');
      return;
    }

    const actionControl = target.closest('[data-plan-action]');

    if (!(actionControl instanceof HTMLAnchorElement) && !(actionControl instanceof HTMLButtonElement)) {
      return;
    }

    if (actionControl instanceof HTMLAnchorElement && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) {
      return;
    }

    const action = actionControl.getAttribute('data-plan-action') || '';
    const placeId = actionControl.getAttribute('data-place-id') || '';
    const direction = actionControl.getAttribute('data-direction') || '';
    const announcement = actionControl.getAttribute('data-plan-announcement') || 'Trip updated.';

    if (action === 'add-waypoint') {
      event.preventDefault();

      if (addWaypointLocally(placeId)) {
        showWaypointFeedback(placeId, 'waypoint added');
        requestRouteData('add waypoint');
        announce(announcement);
      }

      return;
    }

    if (action === 'remove-waypoint') {
      event.preventDefault();

      if (!placeId || !state.trip.selectedWaypointIds.includes(placeId)) {
        return;
      }

      showWaypointFeedback(placeId, 'waypoint removed');
      announce(announcement);

      window.setTimeout(() => {
        if (!removeWaypointLocally(placeId)) {
          return;
        }

        if (state.trip.selectedWaypointIds.length || state.browse.activeCategoryKey) {
          requestRouteData('remove waypoint');
        }
      }, 800);

      return;
    }

    if (action === 'move-waypoint') {
      event.preventDefault();

      if (moveWaypointLocally(placeId, direction)) {
        requestRouteData('keyboard reorder', { focus: 'trip' });
        announce(announcement);
      }

      return;
    }

    if (action === 'clear-trip') {
      event.preventDefault();
      clearTripLocally();

      if (state.browse.activeCategoryKey) {
        requestRouteData('clear trip', { focus: 'preview' });
      }

      announce(announcement);
    }
  });

  root.addEventListener('dragstart', (event) => {
    const item = getTripItemFromTarget(event.target);

    if (!(item instanceof HTMLElement)) {
      return;
    }

    dragState.waypointId = item.getAttribute('data-waypoint-id') || '';
    dragState.targetWaypointId = '';
    dragState.position = 'after';

    clearDragStyles();
    item.classList.add('is-dragging');

    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', dragState.waypointId);
    }
  });

  root.addEventListener('dragover', (event) => {
    if (!dragState.waypointId) {
      return;
    }

    const tripList = getTripList();

    if (!tripList) {
      return;
    }

    const item = getTripItemFromTarget(event.target) || tripList.lastElementChild;

    if (!(item instanceof HTMLElement)) {
      return;
    }

    const targetWaypointId = item.getAttribute('data-waypoint-id') || '';

    if (!targetWaypointId || targetWaypointId === dragState.waypointId) {
      return;
    }

    event.preventDefault();

    const bounds = item.getBoundingClientRect();
    const position = event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';

    dragState.targetWaypointId = targetWaypointId;
    dragState.position = position;

    clearDragStyles();
    tripList
      .querySelector(
        `[data-waypoint-id="${window.CSS?.escape ? window.CSS.escape(dragState.waypointId) : dragState.waypointId}"]`
      )
      ?.classList.add('is-dragging');
    item.classList.add(position === 'before' ? 'is-drop-before' : 'is-drop-after');
  });

  root.addEventListener('drop', (event) => {
    if (!dragState.waypointId) {
      return;
    }

    event.preventDefault();

    const previousWaypointIds = [...state.trip.selectedWaypointIds];
    const nextWaypointIds = reorderWaypointIds(
      state.trip.selectedWaypointIds,
      dragState.waypointId,
      dragState.targetWaypointId,
      dragState.position
    );
    const nextWaypoints = reorderTripWaypoints(
      state.trip.waypoints,
      dragState.waypointId,
      dragState.targetWaypointId,
      dragState.position
    );

    clearDragStyles();
    dragState.waypointId = '';
    dragState.targetWaypointId = '';
    dragState.position = 'after';

    if (arraysEqual(previousWaypointIds, nextWaypointIds)) {
      return;
    }

    state.trip.selectedWaypointIds = nextWaypointIds;
    state.trip.waypoints = nextWaypoints;
    syncUrl();
    renderAll();
    requestRouteData('drag reorder', { focus: 'trip' });
    announce('Trip order updated.');
  });

  root.addEventListener('dragend', () => {
    dragState.waypointId = '';
    dragState.targetWaypointId = '';
    dragState.position = 'after';
    clearDragStyles();
  });
})();
