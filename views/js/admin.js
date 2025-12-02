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
  initInlinePanelRows();
  initInlineEmployeeSelects();
  initInlineGridActions();
  initManifestForm();

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

function initInlineEmployeeSelects() {
  document.addEventListener('change', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const select = target.closest('.js-inline-employee-select');
    if (!select) {
      return;
    }

    const hiddenId = select.getAttribute('data-hidden-target');
    if (!hiddenId) {
      return;
    }

    const hidden = document.getElementById(hiddenId);
    if (!hidden) {
      return;
    }

    const option = select.options && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
    hidden.value = option ? (option.textContent || '').trim() : '';
  });
}

function initManifestForm() {
  const form = document.querySelector('.js-manifest-form');

  if (!form) {
    return;
  }

  const carrierSelect = form.querySelector('.js-manifest-carrier');
  const feedback = form.querySelector('.js-manifest-feedback');
  const dateFromInput = form.querySelector('input[name="date_from"]');
  const dateToInput = form.querySelector('input[name="date_to"]');
  const defaultFeedback = feedback ? feedback.textContent : '';
  const defaultOptionLabel = carrierSelect && carrierSelect.options.length > 0
    ? carrierSelect.options[0].textContent || 'Todos los transportistas'
    : 'Todos los transportistas';
  let abortController = null;

  const updateFeedback = (message, isError) => {
    if (!feedback) {
      return;
    }

    feedback.textContent = message || defaultFeedback;
    feedback.classList.toggle('text-danger', !!isError);
  };

  const buildDefaultOption = () => {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = defaultOptionLabel;

    return option;
  };

  const renderOptions = (list) => {
    if (!carrierSelect) {
      return;
    }

    const previousValue = carrierSelect.value;
    carrierSelect.innerHTML = '';
    carrierSelect.appendChild(buildDefaultOption());

    (list || []).forEach((item) => {
      if (!item || typeof item !== 'object') {
        return;
      }

      const option = document.createElement('option');
      option.value = String(item.id);
      const count = typeof item.shipments === 'number' ? item.shipments : null;
      option.textContent = count === null
        ? String(item.label)
        : `${item.label} (${count})`;
      carrierSelect.appendChild(option);
    });

    if (previousValue && carrierSelect.querySelector(`option[value="${previousValue}"]`)) {
      carrierSelect.value = previousValue;
    } else {
      carrierSelect.value = '';
    }

    const hasOptions = carrierSelect.options.length > 1;
    carrierSelect.disabled = false;

    if (!hasOptions) {
      updateFeedback(
        (carrierSelect && carrierSelect.dataset.emptyLabel) || 'Sin resultados para este rango',
        false
      );
    } else {
      updateFeedback(defaultFeedback, false);
    }
  };

  const sanitizeRange = () => {
    if (!dateFromInput || !dateToInput) {
      return;
    }

    if (dateFromInput.value && dateToInput.value && dateFromInput.value > dateToInput.value) {
      dateToInput.value = dateFromInput.value;
    }
  };

  const fetchCarriers = () => {
    if (!carrierSelect) {
      return;
    }

    const endpoint = form.getAttribute('data-endpoint') || '';
    const dateFrom = dateFromInput ? dateFromInput.value : '';
    const dateTo = dateToInput ? dateToInput.value : '';

    if (!endpoint || !dateFrom || !dateTo) {
      return;
    }

    if (abortController && typeof abortController.abort === 'function') {
      abortController.abort();
    }

    abortController = typeof AbortController === 'function' ? new AbortController() : null;

    const params = new URLSearchParams();
    params.append('date_from', dateFrom);
    params.append('date_to', dateTo);

    updateFeedback('Actualizando transportistas...', false);
    carrierSelect.disabled = true;

    const options = {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
    };

    if (abortController) {
      options.signal = abortController.signal;
    }

    fetch(`${endpoint}?${params.toString()}`, options)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
      })
      .then((payload) => {
        const list = payload && Array.isArray(payload.carriers) ? payload.carriers : [];
        renderOptions(list);
      })
      .catch((error) => {
        if (abortController && error && error.name === 'AbortError') {
          return;
        }

        renderOptions([]);
        updateFeedback('No se pudo actualizar la lista de transportistas.', true);

        if (window.console && typeof window.console.error === 'function') {
          window.console.error('[rj_multicarrier] Manifest carrier fetch failed', error);
        }
      })
      .finally(() => {
        abortController = null;
      });
  };

  const dateInputs = form.querySelectorAll('.js-manifest-date');
  dateInputs.forEach((input) => {
    input.addEventListener('change', () => {
      sanitizeRange();
      fetchCarriers();
    });
  });

  form.addEventListener('submit', (event) => {
    sanitizeRange();

    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
      event.preventDefault();
    }
  });
}

function initInlineGridActions() {
  const grid = document.querySelector('[data-grid-id="rj_multicarrier_info_shipment"]');

  if (!grid) {
    return;
  }

  grid.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const actionButton = target.closest('.js-inline-save, .js-inline-generate');
    if (!actionButton) {
      return;
    }

    const formAction = actionButton.getAttribute('data-form-action') || actionButton.dataset.formAction;
    if (!formAction) {
      return;
    }

    const row = actionButton.closest('tr');
    if (!row) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const extraFields = {};
    if (actionButton.classList.contains('js-inline-generate')) {
      extraFields.generate_shipment = '1';
    }

    submitInlineRowForm(row, formAction, extraFields, actionButton);
  });

  grid.addEventListener('change', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const typeSelect = target.closest('.js-inline-type');
    if (!typeSelect) {
      return;
    }

    const referenceInputId = typeSelect.getAttribute('data-reference-input');
    if (!referenceInputId) {
      return;
    }

    const referenceInput = document.getElementById(referenceInputId);
    if (!referenceInput) {
      return;
    }

    const selectedOption = typeSelect.options && typeSelect.selectedIndex >= 0
      ? typeSelect.options[typeSelect.selectedIndex]
      : null;

    if (selectedOption && selectedOption.dataset && selectedOption.dataset.referenceCarrier) {
      referenceInput.value = selectedOption.dataset.referenceCarrier;
    } else {
      referenceInput.value = '';
    }
  });
}

function submitInlineRowForm(row, actionUrl, extraFields, actionButton) {
  const supportsAjax = typeof window.fetch === 'function' && typeof window.FormData === 'function';

  if (!supportsAjax) {
    submitInlineRowFormLegacy(row, actionUrl, extraFields);
    return;
  }

  const formData = buildInlineRowFormData(row, extraFields);
  if (!formData) {
    return;
  }

  toggleInlineActionButtonState(actionButton, true);

  fetch(actionUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
    body: formData,
  })
    .then((response) => parseInlineResponse(response))
    .then((payload) => {
      if (!payload || payload.success !== true) {
        const message = getFirstInlineMessage(payload, ['error', 'warning', 'info']);
        throw new Error(message || getInlineGenericError());
      }

      if (payload.packageId) {
        updateInlineRowPackageId(row, payload.packageId);
      }

      if (extraFields && extraFields.generate_shipment && payload.labelsUrl) {
        openLabelsInNewTab(payload.labelsUrl);
      }

      if (extraFields && extraFields.generate_shipment) {
        reloadInlineGrid(row);
      }
    })
    .catch((error) => {
      const message = error && error.message ? error.message : getInlineGenericError();
      showInlineError(message);
    })
    .finally(() => {
      toggleInlineActionButtonState(actionButton, false);
    });
}

function parseInlineResponse(response) {
  return response.text().then((text) => {
    let payload = null;

    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (error) {
        if (window.console && typeof window.console.warn === 'function') {
          window.console.warn('[rj_multicarrier] Unable to parse inline response JSON', error);
        }
      }
    }

    if (payload && payload.messages) {
      dispatchInlineMessages(payload.messages);
    }

    if (!response.ok) {
      const message = getFirstInlineMessage(payload, ['error', 'warning', 'info']);
      const error = new Error(message || getInlineGenericError(response.status));
      error.payload = payload;
      throw error;
    }

    return payload;
  });
}

function dispatchInlineMessages(messageStack) {
  if (!messageStack || typeof messageStack !== 'object') {
    return;
  }

  const order = ['error', 'warning', 'info', 'success'];
  order.forEach((type) => {
    const messages = Array.isArray(messageStack[type]) ? messageStack[type] : [];
    messages.forEach((message) => {
      if (!message) {
        return;
      }

      showInlineMessage(type, message);
    });
  });
}

function showInlineMessage(type, message) {
  if (!message) {
    return;
  }

  const noop = () => {};
  const successFn = typeof window.showSuccessMessage === 'function' ? window.showSuccessMessage : noop;
  const errorFn = typeof window.showErrorMessage === 'function' ? window.showErrorMessage : noop;
  const noticeFn = typeof window.showNoticeMessage === 'function' ? window.showNoticeMessage : noop;

  switch (type) {
    case 'success':
      successFn(message);
      break;
    case 'error':
      errorFn(message);
      break;
    default:
      noticeFn(message);
      break;
  }
}

function showInlineError(message) {
  if (typeof window.showErrorMessage === 'function') {
    window.showErrorMessage(message);
    return;
  }

  window.alert(message);
}

function getFirstInlineMessage(payload, priorities) {
  if (!payload || !payload.messages) {
    return null;
  }

  const stack = payload.messages;
  const types = Array.isArray(priorities) && priorities.length > 0
    ? priorities
    : ['error', 'warning', 'info', 'success'];

  for (let i = 0; i < types.length; i += 1) {
    const type = types[i];
    const messages = Array.isArray(stack[type]) ? stack[type] : [];
    if (messages.length > 0 && messages[0]) {
      return messages[0];
    }
  }

  return null;
}

function getInlineGenericError(statusCode) {
  if (statusCode) {
    return `Unexpected error (${statusCode}).`;
  }

  return 'Unexpected error while saving the shipment information.';
}

function updateInlineRowPackageId(row, packageId) {
  if (!row || !packageId) {
    return;
  }

  const normalizedValue = String(packageId);
    row.querySelectorAll('.js-inline-input[data-field="id_info_shipment"]').forEach((input) => {
    input.value = normalizedValue;
  });

  row.querySelectorAll('.js-inline-save, .js-inline-generate').forEach((button) => {
    button.dataset.infoId = normalizedValue;
  });
}

function openLabelsInNewTab(labelsUrl) {
  if (!labelsUrl) {
    return;
  }

  const newTab = window.open(labelsUrl, '_blank');
  if (newTab) {
    newTab.opener = null;
  }
}

function reloadInlineGrid(row) {
  const grid = row ? row.closest('.js-grid') : document.querySelector('.js-grid');

  if (grid) {
    const searchForm = grid.querySelector('form.js-grid-search-form');
    if (searchForm) {
      if (typeof searchForm.requestSubmit === 'function') {
        searchForm.requestSubmit();
      } else {
        searchForm.submit();
      }
      return;
    }
  }

  window.location.reload();
}

function toggleInlineActionButtonState(button, isLoading) {
  if (!button) {
    return;
  }

  if (isLoading) {
    button.dataset.inlineWasDisabled = button.disabled ? '1' : '0';
    button.disabled = true;
    button.classList.add('is-loading');
  } else {
    const wasDisabled = button.dataset.inlineWasDisabled === '1';
    button.disabled = wasDisabled;
    button.classList.remove('is-loading');
    delete button.dataset.inlineWasDisabled;
  }
}

function buildInlineRowFormData(row, extraFields) {
  if (!row) {
    return null;
  }

  const formData = new FormData();
  const inputs = row.querySelectorAll('.js-inline-input');

  inputs.forEach((input) => {
    const fieldName = input.getAttribute('data-field');
    if (!fieldName) {
      return;
    }

    if (input instanceof HTMLInputElement && input.type === 'checkbox' && !input.checked) {
      return;
    }

    if (input instanceof HTMLInputElement && input.type === 'file') {
      return;
    }

    let value = '';
    if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement) {
      value = input.value || '';
    }

    formData.append(fieldName, value);
  });

  Object.keys(extraFields || {}).forEach((name) => {
    const value = extraFields[name];
    formData.append(name, value === undefined || value === null ? '' : value);
  });

  return formData;
}

function submitInlineRowFormLegacy(row, actionUrl, extraFields) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = actionUrl;
  form.style.display = 'none';

  const appendField = (name, value) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  };

  const inputs = row.querySelectorAll('.js-inline-input');
  inputs.forEach((input) => {
    const fieldName = input.getAttribute('data-field');
    if (!fieldName) {
      return;
    }

    if (input instanceof HTMLInputElement && input.type === 'checkbox' && !input.checked) {
      return;
    }

    let value = '';

    if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement) {
      value = input.value || '';
    }

    appendField(fieldName, value);
  });

  Object.keys(extraFields || {}).forEach((name) => {
    appendField(name, extraFields[name]);
  });

  document.body.appendChild(form);
  form.submit();

  window.setTimeout(() => {
    if (form.parentNode) {
      form.parentNode.removeChild(form);
    }
  }, 1500);
}

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

function initInlinePanelRows() {
  const grid = document.querySelector('[data-grid-id="rj_multicarrier_info_shipment"]');

  if (!grid) {
    return;
  }

  grid.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const trigger = target.closest('.js-info-shipment-inline-row-action');
    if (!trigger) {
      return;
    }

    const url = trigger.getAttribute('href') || trigger.dataset.href || trigger.dataset.url;
    if (!url) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }

    openInlinePanelRow(trigger, url);
  });
}

function openInlinePanelRow(trigger, url) {
  const row = trigger.closest('tr');

  if (!row || !row.parentNode) {
    window.location.href = url;
    return;
  }

  const existing = row.nextElementSibling;
  if (existing && existing.classList && existing.classList.contains('rj-inline-form-row') && existing.dataset.panelUrl === url) {
    existing.parentNode.removeChild(existing);
    row.classList.remove('rj-inline-form-target');
    return;
  }

  closeAllInlinePanelRows();

  const colCount = Math.max(row.children.length, row.querySelectorAll('td,th').length, 1);
  const inlineRow = document.createElement('tr');
  inlineRow.className = 'rj-inline-form-row';
  inlineRow.dataset.panelUrl = url;
  inlineRow.dataset.loading = '1';

  const cell = document.createElement('td');
  cell.colSpan = colCount;
  cell.className = 'p-0 align-top';
  const loader = buildInlinePanelLoaderElement();
  if (loader) {
    cell.appendChild(loader);
  }

  inlineRow.appendChild(cell);
  row.parentNode.insertBefore(inlineRow, row.nextSibling);
  row.classList.add('rj-inline-form-target');

  fetchInlinePanelContent(url, cell)
    .then(() => {
      inlineRow.dataset.loading = '0';
    })
    .catch(() => {
      inlineRow.dataset.loading = '0';
    });
}

function closeAllInlinePanelRows() {
  document.querySelectorAll('.rj-inline-form-row').forEach((inlineRow) => {
    if (inlineRow.parentNode) {
      inlineRow.parentNode.removeChild(inlineRow);
    }
  });

  document.querySelectorAll('.rj-inline-form-target').forEach((tr) => {
    tr.classList.remove('rj-inline-form-target');
  });
}

function buildInlinePanelLoaderElement() {
  const config = (window.rjMulticarrier && window.rjMulticarrier.inlinePanelConfig) || {};
  const loadingLabel = String(config.loadingLabel || 'Cargando…');

  const wrapper = document.createElement('div');
  wrapper.className = 'rj-inline-form-loader text-center py-4';

  const spinner = document.createElement('span');
  spinner.className = 'spinner-border';
  spinner.setAttribute('role', 'status');
  spinner.setAttribute('aria-hidden', 'true');

  const srOnly = document.createElement('span');
  srOnly.className = 'sr-only';
  srOnly.textContent = loadingLabel;

  wrapper.appendChild(spinner);
  wrapper.appendChild(srOnly);

  return wrapper;
}

function fetchInlinePanelContent(url, container) {
  const config = (window.rjMulticarrier && window.rjMulticarrier.inlinePanelConfig) || {};
  const genericError = config.genericError || 'Error';
  const notFoundError = config.notFoundError || genericError;

  return fetch(url, {
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
      const wrapper = document.createElement('div');
      wrapper.className = 'rj-inline-form-wrapper p-3';
      wrapper.innerHTML = html;
      container.innerHTML = '';
      container.appendChild(wrapper);
      executeInlineScripts(container);

      if (window.rjMulticarrier && typeof window.rjMulticarrier.initOrderPanel === 'function') {
        window.rjMulticarrier.initOrderPanel(container);
      }

      return html;
    })
    .catch((error) => {
      const alert = document.createElement('div');
      alert.className = 'alert alert-danger m-3';
      alert.textContent = error && error.message ? error.message : genericError;
      container.innerHTML = '';
      container.appendChild(alert);
      throw error;
    });
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
