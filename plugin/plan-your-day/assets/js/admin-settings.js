(() => {
  const CATEGORY_ROW_SELECTOR = '[data-plan-category-row]';
  const CATEGORY_DRAG_HANDLE_SELECTOR = '[data-plan-category-drag-handle]';

  const getCategoryRows = (rows) => Array.from(rows.querySelectorAll(CATEGORY_ROW_SELECTOR));

  const syncCategorySortOrders = (rows) => {
    getCategoryRows(rows).forEach((row, rowIndex) => {
      const sortInput = row.querySelector('[data-plan-category-sort-order]');

      if (sortInput instanceof HTMLInputElement) {
        sortInput.value = String((rowIndex + 1) * 10);
      }
    });
  };

  const moveCategoryRow = (rows, row, direction) => {
    if (!(row instanceof HTMLTableRowElement)) {
      return false;
    }

    if ('up' === direction) {
      const previousRow = row.previousElementSibling;

      if (previousRow instanceof HTMLTableRowElement) {
        rows.insertBefore(row, previousRow);
        return true;
      }
    } else {
      const nextRow = row.nextElementSibling;

      if (nextRow instanceof HTMLTableRowElement) {
        rows.insertBefore(nextRow, row);
        return true;
      }
    }

    return false;
  };

  const getDeletedCategoryFocusTarget = (row, addButton) => {
    const focusRow =
      row.nextElementSibling instanceof HTMLTableRowElement
        ? row.nextElementSibling
        : row.previousElementSibling instanceof HTMLTableRowElement
          ? row.previousElementSibling
          : null;
    const dragHandle = focusRow?.querySelector(CATEGORY_DRAG_HANDLE_SELECTOR);

    return dragHandle instanceof HTMLButtonElement ? dragHandle : addButton;
  };

  const initializeJQueryCategorySorting = (rows, syncSortOrders) => {
    const jQuery = window.jQuery;

    if ('function' !== typeof jQuery || !jQuery.fn || 'function' !== typeof jQuery.fn.sortable) {
      return null;
    }

    const sortableRows = jQuery(rows);

    sortableRows.sortable({
      axis: 'y',
      cancel: 'input, textarea, select, option, [data-plan-delete-category]',
      handle: CATEGORY_DRAG_HANDLE_SELECTOR,
      items: CATEGORY_ROW_SELECTOR,
      tolerance: 'pointer',
      start: (event, ui) => {
        if (ui && ui.item) {
          ui.item.addClass('is-dragging');
        }
      },
      stop: (event, ui) => {
        if (ui && ui.item) {
          ui.item.removeClass('is-dragging');
        }

        syncSortOrders();
      },
    });

    return {
      configureRow: (row, dragHandle) => {
        if (dragHandle instanceof HTMLButtonElement) {
          dragHandle.draggable = false;
        }
      },
      refresh: () => {
        sortableRows.sortable('refresh');
      },
    };
  };

  const initializeVanillaCategorySorting = (rows, syncSortOrders) => {
    let draggedRow = null;

    const findRowAfterPointer = (clientY) => {
      return getCategoryRows(rows)
        .filter((row) => row !== draggedRow)
        .reduce(
          (closest, row) => {
            const box = row.getBoundingClientRect();
            const offset = clientY - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
              return {
                offset,
                row,
              };
            }

            return closest;
          },
          {
            offset: Number.NEGATIVE_INFINITY,
            row: null,
          }
        ).row;
    };

    rows.addEventListener('dragover', (event) => {
      if (null === draggedRow) {
        return;
      }

      event.preventDefault();

      const nextRow = findRowAfterPointer(event.clientY);

      if (nextRow instanceof HTMLTableRowElement) {
        rows.insertBefore(draggedRow, nextRow);
      } else {
        rows.appendChild(draggedRow);
      }
    });

    rows.addEventListener('drop', (event) => {
      if (null === draggedRow) {
        return;
      }

      event.preventDefault();
      syncSortOrders();
    });

    return {
      configureRow: (row, dragHandle) => {
        if (!(dragHandle instanceof HTMLButtonElement)) {
          return;
        }

        dragHandle.draggable = true;

        dragHandle.addEventListener('dragstart', (event) => {
          draggedRow = row;
          row.classList.add('is-dragging');

          if (null !== event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
          }
        });

        dragHandle.addEventListener('dragend', () => {
          row.classList.remove('is-dragging');
          draggedRow = null;
          syncSortOrders();
        });
      },
      refresh: () => {},
    };
  };

  const initializeCategorySorting = (rows, syncSortOrders) => {
    const jquerySorting = initializeJQueryCategorySorting(rows, syncSortOrders);

    if (null !== jquerySorting) {
      return jquerySorting;
    }

    return initializeVanillaCategorySorting(rows, syncSortOrders);
  };

  const initializeCategoryEditor = (editor) => {
    const rows = editor.querySelector('[data-plan-category-rows]');
    const template = editor.querySelector('[data-plan-category-row-template]');
    const addButton = editor.querySelector('[data-plan-add-category]');

    if (!(rows instanceof HTMLElement) || !(template instanceof HTMLTemplateElement) || !(addButton instanceof HTMLButtonElement)) {
      return;
    }

    let nextIndex = rows.querySelectorAll('tr').length;
    const syncSortOrders = () => {
      syncCategorySortOrders(rows);
    };
    const sorter = initializeCategorySorting(rows, syncSortOrders);
    const syncAndRefresh = () => {
      syncSortOrders();
      sorter.refresh();
    };

    const initializeRow = (row) => {
      if (!(row instanceof HTMLTableRowElement) || 'true' === row.dataset.planCategoryRowReady) {
        return;
      }

      const dragHandle = row.querySelector(CATEGORY_DRAG_HANDLE_SELECTOR);
      const deleteButton = row.querySelector('[data-plan-delete-category]');

      if (!(dragHandle instanceof HTMLButtonElement)) {
        return;
      }

      row.dataset.planCategoryRowReady = 'true';
      sorter.configureRow(row, dragHandle);

      if (deleteButton instanceof HTMLButtonElement) {
        deleteButton.addEventListener('click', () => {
          const message = editor.getAttribute('data-plan-delete-category-confirm') || '';

          if ('' !== message && !window.confirm(message)) {
            return;
          }

          const focusTarget = getDeletedCategoryFocusTarget(row, addButton);

          row.remove();
          syncAndRefresh();
          focusTarget.focus({ preventScroll: true });
        });
      }

      dragHandle.addEventListener('keydown', (event) => {
        if ('ArrowUp' === event.key) {
          event.preventDefault();

          if (moveCategoryRow(rows, row, 'up')) {
            syncAndRefresh();
          }

          dragHandle.focus();
        }

        if ('ArrowDown' === event.key) {
          event.preventDefault();

          if (moveCategoryRow(rows, row, 'down')) {
            syncAndRefresh();
          }

          dragHandle.focus();
        }
      });
    };

    addButton.addEventListener('click', () => {
      const markup = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
      rows.insertAdjacentHTML('beforeend', markup);
      initializeRow(rows.lastElementChild);
      nextIndex += 1;
      syncAndRefresh();
    });

    getCategoryRows(rows).forEach((row) => {
      initializeRow(row);
    });
    syncAndRefresh();
  };

  const initializeCategoryEditors = () => {
    document.querySelectorAll('[data-plan-category-editor]').forEach((editor) => {
      if (editor instanceof HTMLElement) {
        initializeCategoryEditor(editor);
      }
    });
  };

  const initializeBackToTop = () => {
    const topButton = document.querySelector('[data-plan-back-to-top]');
    const topTarget = document.getElementById('plan-your-day-settings-top');
    const topHeading = document.getElementById('plan-your-day-settings-title');

    if (!(topButton instanceof HTMLButtonElement) || !(topTarget instanceof HTMLElement)) {
      return;
    }

    const toggleVisibility = () => {
      const isVisible = window.scrollY > 280;

      topButton.hidden = !isVisible;
      topButton.classList.toggle('is-visible', isVisible);
    };

    topButton.addEventListener('click', () => {
      topTarget.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      });

      if (topHeading instanceof HTMLElement) {
        window.setTimeout(() => {
          topHeading.focus({
            preventScroll: true,
          });
        }, 180);
      }
    });

    window.addEventListener(
      'scroll',
      () => {
        toggleVisibility();
      },
      {
        passive: true,
      }
    );

    toggleVisibility();
  };

  const initialize = () => {
    initializeCategoryEditors();
    initializeBackToTop();
  };

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', initialize, {
      once: true,
    });
  } else {
    initialize();
  }
})();
