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

  const containers = document.querySelectorAll('.js-grid');

  if (containers.length === 0) {
    return;
  }

  containers.forEach((container) => {
    const gridId = container.dataset.gridId || DEFAULT_GRID_ID;
    ensureGrid(gridId);
  });

  // render icon images in company grid
  setTimeout(() => {
    const companyGrid = document.querySelector('[data-grid-id="rj_multicarrier_company"]');
    if (companyGrid) {
      companyGrid.querySelectorAll('td[data-column-name="icon"]').forEach((cell) => {
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
      const searchForm = grid.querySelector('form.js-grid-search-form');
      if (searchForm) {
        const inputs = searchForm.querySelectorAll('input[name], textarea[name], select[name]');
        inputs.forEach((input) => {
          const name = input.getAttribute('name');
          if (!name || (name === '_token' && csrf)) {
            return;
          }

          const clone = document.createElement('input');
          clone.type = 'hidden';
          clone.name = name;
          clone.value = input.value || '';
          form.appendChild(clone);
        });
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
