// Plain JavaScript grid bootstrapper (no module imports needed)

const DEFAULT_GRID_ID = 'rj_multicarrier_log';
const EXTENSION_NAMES = [
  'SortingExtension',
  'FiltersResetExtension',
  'ReloadListExtension',
  'ReloadListActionExtension',
  'ColumnTogglingExtension',
  'SubmitRowActionExtension',
  'SubmitGridActionExtension',
  'SubmitGridExtension',
  'SubmitBulkActionExtension',
  'SubmitBulkExtension',
  'BulkActionCheckboxExtension',
  'BulkOpenTabsExtension',
  'FiltersSubmitButtonEnablerExtension',
  'ModalFormSubmitExtension',
  'ChoiceExtension',
  'LinkRowActionExtension',
  'ExportToSqlManagerExtension',
];

function resolveExtensions(source) {
  if (!source) {
    return {};
  }

  return EXTENSION_NAMES.reduce((acc, name) => {
    const ctor = source[name];
    if (typeof ctor === 'function') {
      acc[name] = ctor;
    }

    return acc;
  }, {});
}

function instantiateGrid(gridId) {
  const prestashop = window.prestashop || {};
  let GridCtor = prestashop.component && prestashop.component.Grid;
  let extensions = prestashop.component && prestashop.component.GridExtensions;

  if (typeof GridCtor !== 'function') {
    GridCtor = window.Grid;
  }

  if (typeof GridCtor !== 'function') {
    return false;
  }

  const availableExtensions = extensions ? extensions : resolveExtensions(window);

  try {
    const grid = new GridCtor(gridId); // eslint-disable-line new-cap

    EXTENSION_NAMES.forEach((name) => {
      const ExtensionCtor = availableExtensions[name];
      if (typeof ExtensionCtor === 'function') {
        grid.addExtension(new ExtensionCtor()); // eslint-disable-line new-cap
      }
    });

    if (window.console && typeof window.console.debug === 'function') {
      window.console.debug('[rj_multicarrier] Grid ready for', gridId);
    }

    return true;
  } catch (error) {
    if (window.console && typeof window.console.error === 'function') {
      window.console.error('[rj_multicarrier] Grid bootstrap failed', error);
    }

    return false;
  }
}

function ensureGrid(gridId) {
  if (instantiateGrid(gridId)) {
    return;
  }

  const start = Date.now();
  const interval = window.setInterval(() => {
    if (instantiateGrid(gridId)) {
      window.clearInterval(interval);
      return;
    }

    if (Date.now() - start > 6000) {
      window.clearInterval(interval);
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('[rj_multicarrier] Grid initialisation timeout for', gridId);
      }
    }
  }, 200);
}

function ready(callback) {
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    window.setTimeout(callback, 0);
  } else {
    document.addEventListener('DOMContentLoaded', callback);
  }
}

ready(() => {
  initDetailModals();
  initPanelModals();

  const containers = document.querySelectorAll('.js-grid');

  if (containers.length === 0) {
    return;
  }

  containers.forEach((container) => {
    const gridId = container.dataset.gridId || DEFAULT_GRID_ID;
    ensureGrid(gridId);
  });

  // render icon images in carrier grid
  setTimeout(() => {
    const carrierGrid = document.querySelector('[data-grid-id="rj_multicarrier_carrier"]');
    if (carrierGrid) {
      carrierGrid.querySelectorAll('td[data-column-name="icon"]').forEach((cell) => {
        const text = (cell.textContent || '').trim();
        if (text && (text.startsWith('http') || text.startsWith('/'))) {
          const img = document.createElement('img');
          img.src = text;
          img.style.maxHeight = '36px';
          img.style.maxWidth = '100px';
          img.style.objectFit = 'contain';
          cell.textContent = '';
          cell.appendChild(img);
        }
      });
    }
  }, 400);

  // use capture phase to prevent legacy grid listeners from triggering a full navigation
  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const button = target.closest('.js-grid-action-submit-btn');
    if (!button) {
      return;
    }

    const url = button.getAttribute('data-url') || button.dataset.url;
    if (!url) {
      return;
    }

    event.preventDefault();

    const method = (button.getAttribute('data-method') || button.dataset.method || 'POST').toUpperCase();
    const csrf = button.getAttribute('data-csrf') || button.dataset.csrf;
    const form = document.createElement('form');

    form.style.display = 'none';
    form.method = method === 'GET' ? 'GET' : 'POST';
    form.action = url;

    if (csrf) {
      const tokenInput = document.createElement('input');
      tokenInput.type = 'hidden';
      tokenInput.name = '_token';
      tokenInput.value = csrf;
      form.appendChild(tokenInput);
    }

    const grid = button.closest('.js-grid');
    if (grid) {
      const gridIdAttr = grid.dataset.gridId || DEFAULT_GRID_ID;
      const searchForm = grid.querySelector('form.js-grid-search-form');
      if (searchForm) {
        const inputs = searchForm.querySelectorAll('input[name], textarea[name], select[name]');
        const debugThisGrid = gridIdAttr === 'rj_multicarrier_carrier' && window.console && typeof window.console.debug === 'function';
        if (debugThisGrid) {
          window.console.debug('[rj_multicarrier] Preparing to clone search form inputs for grid:', gridIdAttr, 'found inputs:', inputs.length);
        }

        inputs.forEach((input) => {
          const name = input.getAttribute('name');

          // skip if no name, disabled, or CSRF token handled separately
          if (!name || input.disabled || (name === '_token' && csrf)) {
            if (debugThisGrid) {
              window.console.debug('[rj_multicarrier] Skipping input', { name, disabled: input.disabled, csrfSkipped: name === '_token' && csrf });
            }
            return;
          }

          const tag = (input.tagName || '').toLowerCase();
          const type = (input.type || '').toLowerCase();

          if (debugThisGrid) {
            // log basic input info
            let val = input.value;
            if (tag === 'select' && input.multiple) {
              val = Array.from(input.selectedOptions).map((o) => o.value);
            }
            window.console.debug('[rj_multicarrier] Cloning input', { name, tag, type, value: val, checked: !!input.checked, multiple: !!input.multiple });
          }

          // handle select multiple: create one hidden field per selected option
          if (tag === 'select' && input.multiple) {
            Array.from(input.selectedOptions).forEach((opt) => {
              const clone = document.createElement('input');
              clone.type = 'hidden';
              clone.name = name;
              clone.value = opt.value || '';
              form.appendChild(clone);
            });
            return;
          }

          // handle checkboxes and radios: only include if checked
          if (type === 'checkbox' || type === 'radio') {
            if (!input.checked) {
              return;
            }

            const clone = document.createElement('input');
            clone.type = 'hidden';
            clone.name = name;
            // checkboxes may have explicit value, default to '1'
            clone.value = input.value !== undefined && input.value !== null && input.value !== '' ? input.value : '1';
            form.appendChild(clone);
            return;
          }

          // normal input / textarea / select(single)
          if (tag === 'textarea' || tag === 'select' || tag === 'input') {
            // skip file inputs
            if (type === 'file') {
              return;
            }

            const clone = document.createElement('input');
            clone.type = 'hidden';
            clone.name = name;
            clone.value = input.value || '';
            form.appendChild(clone);
          }
        });

        if (debugThisGrid) {
          // log what we appended to the temporary form
          const appended = Array.from(form.querySelectorAll('input[name]')).map((n) => ({ name: n.name, value: n.value }));
          window.console.debug('[rj_multicarrier] Appended hidden inputs for form submit:', appended);
        }
      }
    }

    document.body.appendChild(form);
    form.submit();

    window.setTimeout(() => {
      if (form.parentNode) {
        form.parentNode.removeChild(form);
      }
    }, 1500);
  }, true);

});

function initDetailModals() {
  const modals = document.querySelectorAll('.js-detail-modal');

  if (modals.length === 0) {
    return;
  }

  modals.forEach((modal) => {
    const triggerSelector = modal.dataset.trigger;
    if (!triggerSelector) {
      return;
    }

    setupDetailModal(modal, triggerSelector);
  });
}

function initPanelModals() {
  const modals = document.querySelectorAll('.js-panel-modal');

  if (modals.length === 0) {
    return;
  }

  modals.forEach((modal) => {
    const triggerSelector = modal.dataset.trigger;
    if (!triggerSelector) {
      return;
    }

    setupPanelModal(modal, triggerSelector);
  });
}

function executeInlineScripts(container) {
  if (!container) {
    return;
  }

  const scripts = container.querySelectorAll('script');

  scripts.forEach((script) => {
    const clone = document.createElement('script');

    Array.from(script.attributes).forEach((attr) => {
      clone.setAttribute(attr.name, attr.value);
    });

    if (script.parentNode) {
      script.parentNode.removeChild(script);
    }

    if (script.src) {
      clone.addEventListener('load', () => {
        clone.remove();
      });
      clone.addEventListener('error', () => {
        clone.remove();
      });
      clone.src = script.src;
      document.head.appendChild(clone);
    } else {
      clone.textContent = script.textContent;
      document.head.appendChild(clone);
      document.head.removeChild(clone);
    }
  });
}

function setupPanelModal(modal, triggerSelector) {
  const spinner = modal.querySelector('.js-panel-spinner');
  const contentBox = modal.querySelector('.js-panel-content');
  const errorBox = modal.querySelector('.js-panel-error');
  const titleNode = modal.querySelector('.js-panel-title');
  const defaultTitle = titleNode ? titleNode.textContent : (modal.dataset.titleDefault || '');
  const genericError = modal.dataset.errorGeneric || 'Error';
  const notFoundError = modal.dataset.errorNotFound || genericError;

  function setTitle(text) {
    if (!titleNode) {
      return;
    }
    titleNode.textContent = text || defaultTitle || '';
  }

  function openModal() {
    if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
      window.jQuery(modal).modal('show');
      return;
    }

    modal.classList.add('show');
    modal.style.display = 'block';
    modal.setAttribute('aria-modal', 'true');
    modal.removeAttribute('aria-hidden');
  }

  function resetModal() {
    if (errorBox) {
      errorBox.classList.add('d-none');
      errorBox.textContent = '';
    }

    if (contentBox) {
      contentBox.classList.add('d-none');
      contentBox.innerHTML = '';
    }

    if (spinner) {
      spinner.classList.remove('d-none');
    }

    setTitle(defaultTitle);
  }

  function showError(message) {
    if (spinner) {
      spinner.classList.add('d-none');
    }

    if (contentBox) {
      contentBox.classList.add('d-none');
    }

    if (errorBox) {
      errorBox.classList.remove('d-none');
      errorBox.textContent = message || genericError;
    }
  }

  function showContent(html) {
    if (spinner) {
      spinner.classList.add('d-none');
    }

    if (!contentBox) {
      return;
    }

    contentBox.innerHTML = html || '';
    executeInlineScripts(contentBox);

    if (window.rjMulticarrier && typeof window.rjMulticarrier.initOrderPanel === 'function') {
      window.rjMulticarrier.initOrderPanel(contentBox);
    }

    contentBox.classList.remove('d-none');
  }

  function fetchPanel(url, trigger) {
    openModal();
    resetModal();

    if (trigger) {
      const explicitTitle = trigger.getAttribute('data-modal-title') || trigger.dataset.modalTitle;
      if (explicitTitle) {
        setTitle(explicitTitle);
      }
    }

    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html',
      },
      credentials: 'same-origin',
    })
      .then((response) => {
        if (response.status === 404) {
          throw new Error(notFoundError);
        }

        if (!response.ok) {
          return response.text().then((text) => {
            const trimmed = (text || '').trim();
            throw new Error(trimmed || `${genericError} (Status: ${response.status})`);
          });
        }

        return response.text();
      })
      .then((html) => {
        showContent(html);
      })
      .catch((error) => {
        if (window.console && typeof window.console.error === 'function') {
          window.console.error('[rj_multicarrier] Panel fetch error:', error);
        }
        showError(error && error.message ? error.message : genericError);
      });
  }

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    let trigger = null;
    const selectorList = Array.isArray(triggerSelector)
      ? triggerSelector
      : String(triggerSelector)
        .split(',')
        .map((selector) => selector.trim())
        .filter((selector) => selector.length > 0);

    for (let index = 0; index < selectorList.length; index += 1) {
      const selector = selectorList[index];
      trigger = target.closest(selector);
      if (trigger) {
        break;
      }
    }

    if (!trigger) {
      return;
    }

    const url = trigger.getAttribute('href')
      || trigger.dataset.href
      || trigger.dataset.url;
    if (!url) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }
    fetchPanel(url, trigger);
  }, true);
}

function setupDetailModal(modal, triggerSelector) {
  const emptyLabel = modal.dataset.empty || '—';
  const genericError = modal.dataset.errorGeneric || 'Error';
  const notFoundError = modal.dataset.errorNotFound || genericError;

  const spinner = modal.querySelector('.js-detail-spinner');
  const errorBox = modal.querySelector('.js-detail-error');
  const contentBox = modal.querySelector('.js-detail-content');

  const fieldMap = {};
  modal.querySelectorAll('.js-detail-field').forEach((node) => {
    const field = node.dataset.detailField;
    if (field) {
      fieldMap[field] = node;
    }
  });

  function setText(node, value) {
    if (!node) {
      return;
    }

    const safeValue = value === undefined || value === null || value === '' ? emptyLabel : String(value);
    node.textContent = safeValue;
  }

  function resetModal() {
    if (errorBox) {
      errorBox.classList.add('d-none');
      errorBox.textContent = '';
    }

    if (contentBox) {
      contentBox.classList.add('d-none');
    }

    if (spinner) {
      spinner.classList.remove('d-none');
    }

    Object.keys(fieldMap).forEach((key) => {
      setText(fieldMap[key], emptyLabel);
    });
  }

  function showError(message) {
    if (spinner) {
      spinner.classList.add('d-none');
    }

    if (contentBox) {
      contentBox.classList.add('d-none');
    }

    if (errorBox) {
      errorBox.classList.remove('d-none');
      errorBox.textContent = message || genericError;
    }
  }

  function renderField(field, value) {
    const node = fieldMap[field];
    if (!node) {
      return;
    }

    const renderType = node.dataset.detailRender || 'text';

    if (renderType === 'boolean') {
      const trueLabel = node.dataset.detailTrue || 'Yes';
      const falseLabel = node.dataset.detailFalse || 'No';
      const normalized = value === true || value === '1' || value === 1 || value === 'true';
      setText(node, normalized ? trueLabel : falseLabel);
      return;
    }

    if (renderType === 'json') {
      if (value === null || value === undefined || value === '') {
        setText(node, emptyLabel);
        return;
      }

      try {
        node.textContent = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
      } catch (error) {
        setText(node, value);
      }

      return;
    }

    setText(node, value);
  }

  function showContent(data) {
    if (spinner) {
      spinner.classList.add('d-none');
    }

    if (errorBox) {
      errorBox.classList.add('d-none');
      errorBox.textContent = '';
    }

    Object.keys(fieldMap).forEach((field) => {
      const value = data && Object.prototype.hasOwnProperty.call(data, field) ? data[field] : null;
      renderField(field, value);
    });

    if (contentBox) {
      contentBox.classList.remove('d-none');
    }
  }

  function openModal() {
    if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
      window.jQuery(modal).modal('show');
      return;
    }

    modal.classList.add('show');
    modal.style.display = 'block';
    modal.setAttribute('aria-modal', 'true');
    modal.removeAttribute('aria-hidden');
  }

  function fetchDetail(url) {
    openModal();
    resetModal();

    if (window.console && typeof window.console.log === 'function') {
      window.console.log('[rj_multicarrier] Fetching detail from:', url);
    }

    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    })
      .then((response) => {
        if (window.console && typeof window.console.log === 'function') {
          window.console.log('[rj_multicarrier] Response status:', response.status);
        }

        if (response.status === 404) {
          throw new Error(notFoundError);
        }

        if (!response.ok) {
          throw new Error(genericError + ' (Status: ' + response.status + ')');
        }

        return response.json();
      })
      .then((data) => {
        if (window.console && typeof window.console.log === 'function') {
          window.console.log('[rj_multicarrier] Received data:', data);
        }
        showContent(data || {});
      })
      .catch((error) => {
        if (window.console && typeof window.console.error === 'function') {
          window.console.error('[rj_multicarrier] Fetch error:', error);
        }
        showError(error && error.message ? error.message : genericError);
      });
  }

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const trigger = target.closest(triggerSelector);
    if (!trigger) {
      return;
    }

    const url = trigger.getAttribute('href')
      || trigger.dataset.href
      || trigger.dataset.url;
    if (!url) {
      return;
    }

    const method = (trigger.getAttribute('data-method') || trigger.dataset.method || 'GET').toUpperCase();
    if (method !== 'GET') {
      return;
    }

    event.preventDefault();
    fetchDetail(url);
  });
}
