import Grid from '@components/grid/grid';
import SortingExtension from '@components/grid/extension/sorting-extension';
import FiltersResetExtension from '@components/grid/extension/filters-reset-extension';
import ReloadListExtension from '@components/grid/extension/reload-list-extension';
import ColumnTogglingExtension from '@components/grid/extension/column-toggling-extension';
import SubmitRowActionExtension from '@components/grid/extension/action/row/submit-row-action-extension';
import SubmitGridActionExtension from '@components/grid/extension/submit-grid-action-extension';
import SubmitBulkActionExtension from '@components/grid/extension/submit-bulk-action-extension';
import BulkActionCheckboxExtension from '@components/grid/extension/bulk-action-checkbox-extension';
import BulkOpenTabsExtension from '@components/grid/extension/bulk-open-tabs-extension';
import FiltersSubmitButtonEnablerExtension from '@components/grid/extension/filters-submit-button-enabler-extension';
import ModalFormSubmitExtension from '@components/grid/extension/modal-form-submit-extension';
import ChoiceExtension from '@components/grid/extension/choice-extension';
import LinkRowActionExtension from '@components/grid/extension/link-row-action-extension';
import ExportToSqlManagerExtension from '@components/grid/extension/export-to-sql-manager-extension';

const $ = window.$;

function tryInitWithImports(gridId) {
  try {
    if (!Grid || typeof Grid !== 'function') return false;
    // eslint-disable-next-line new-cap
    const grid = new Grid(gridId);

    if (typeof SortingExtension === 'function') grid.addExtension(new SortingExtension());
    if (typeof FiltersResetExtension === 'function') grid.addExtension(new FiltersResetExtension());
    if (typeof ReloadListExtension === 'function') grid.addExtension(new ReloadListExtension());
    if (typeof ColumnTogglingExtension === 'function') grid.addExtension(new ColumnTogglingExtension());
    if (typeof SubmitRowActionExtension === 'function') grid.addExtension(new SubmitRowActionExtension());
    if (typeof SubmitGridActionExtension === 'function') grid.addExtension(new SubmitGridActionExtension());
    if (typeof SubmitBulkActionExtension === 'function') grid.addExtension(new SubmitBulkActionExtension());
    if (typeof BulkActionCheckboxExtension === 'function') grid.addExtension(new BulkActionCheckboxExtension());
    if (typeof BulkOpenTabsExtension === 'function') grid.addExtension(new BulkOpenTabsExtension());
    if (typeof FiltersSubmitButtonEnablerExtension === 'function') grid.addExtension(new FiltersSubmitButtonEnablerExtension());
    if (typeof ModalFormSubmitExtension === 'function') grid.addExtension(new ModalFormSubmitExtension());
    if (typeof ChoiceExtension === 'function') grid.addExtension(new ChoiceExtension());
    if (typeof LinkRowActionExtension === 'function') grid.addExtension(new LinkRowActionExtension());
    if (typeof ExportToSqlManagerExtension === 'function') grid.addExtension(new ExportToSqlManagerExtension());

    if (window && window.console && window.console.debug) {
      console.debug('[rj_multicarrier] Grid initialized using imported components for', gridId);
    }

    return true;
  } catch (e) {
    if (window && window.console && window.console.error) console.error('[rj_multicarrier] tryInitWithImports failed', e);
    return false;
  }
}

function tryInitRuntime(gridId) {
  var prestashop = window.prestashop || {};
  var GridRuntime = prestashop.component && prestashop.component.Grid;
  var GridExtensions = prestashop.component && prestashop.component.GridExtensions;
  if (!GridRuntime) return false;
  try {
    // eslint-disable-next-line new-cap
    var grid = new GridRuntime(gridId);
    if (GridExtensions) {
      if (GridExtensions.SortingExtension) grid.addExtension(new GridExtensions.SortingExtension());
      if (GridExtensions.FiltersResetExtension) grid.addExtension(new GridExtensions.FiltersResetExtension());
      var ReloadCtor = GridExtensions.ReloadListExtension || GridExtensions.ReloadListActionExtension;
      if (ReloadCtor) grid.addExtension(new ReloadCtor());
      if (GridExtensions.ColumnTogglingExtension) grid.addExtension(new GridExtensions.ColumnTogglingExtension());
      if (GridExtensions.SubmitRowActionExtension) grid.addExtension(new GridExtensions.SubmitRowActionExtension());
      var SubmitGridCtor = GridExtensions.SubmitGridActionExtension || GridExtensions.SubmitGridExtension;
      if (SubmitGridCtor) grid.addExtension(new SubmitGridCtor());
      var SubmitBulkCtor = GridExtensions.SubmitBulkActionExtension || GridExtensions.SubmitBulkExtension;
      if (SubmitBulkCtor) grid.addExtension(new SubmitBulkCtor());
      if (GridExtensions.BulkActionCheckboxExtension) grid.addExtension(new GridExtensions.BulkActionCheckboxExtension());
      if (GridExtensions.BulkOpenTabsExtension) grid.addExtension(new GridExtensions.BulkOpenTabsExtension());
      if (GridExtensions.FiltersSubmitButtonEnablerExtension) grid.addExtension(new GridExtensions.FiltersSubmitButtonEnablerExtension());
      if (GridExtensions.ModalFormSubmitExtension) grid.addExtension(new GridExtensions.ModalFormSubmitExtension());
      if (GridExtensions.ChoiceExtension) grid.addExtension(new GridExtensions.ChoiceExtension());
      if (GridExtensions.LinkRowActionExtension) grid.addExtension(new GridExtensions.LinkRowActionExtension());
      if (GridExtensions.ExportToSqlManagerExtension) grid.addExtension(new GridExtensions.ExportToSqlManagerExtension());
    }

    if (window && window.console && window.console.debug) console.debug('[rj_multicarrier] Grid initialized using runtime components for', gridId);

    return true;
  } catch (e) {
    if (window && window.console && window.console.error) console.error('[rj_multicarrier] tryInitRuntime failed', e);
    return false;
  }
}

function domReady(cb) {
  if (typeof $ === 'function') return $(cb);
  if (document.readyState === 'complete' || document.readyState === 'interactive') return setTimeout(cb, 0);
  return document.addEventListener('DOMContentLoaded', cb);
}

domReady(() => {
  const gridDivs = document.querySelectorAll('.js-grid');
  let initialized = false;

  gridDivs.forEach((gridDiv) => {
    const gridId = gridDiv.dataset.gridId || 'rj_multicarrier_log';
    let ok = false;
    if (typeof Grid === 'function') {
      ok = tryInitWithImports(gridId);
    }
    if (!ok) {
      ok = tryInitRuntime(gridId);
    }
    if (ok) initialized = true;
  });

  if (!initialized) {
    const start = Date.now();
    const interval = setInterval(() => {
      let any = false;
      gridDivs.forEach((gridDiv) => {
        const gridId = gridDiv.dataset.gridId || 'rj_multicarrier_log';
        if (tryInitRuntime(gridId)) any = true;
      });
      if (any) { clearInterval(interval); return; }
      if ((Date.now() - start) > 6000) { clearInterval(interval); console.warn('[rj_carrier] runtime grid init timeout'); }
    }, 200);
  }

  // Fallback for submit actions (Export CSV) if Grid extensions didn't wire the button
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!(t instanceof Element)) return;
    var btn = t.closest('.js-grid-action-submit-btn');
    if (!btn) return;
    // Build and submit a form to trigger browser download
    ev.preventDefault();
    var url = btn.getAttribute('data-url') || btn.dataset.url;
    var method = (btn.getAttribute('data-method') || btn.dataset.method || 'POST').toUpperCase();
    var csrf = btn.getAttribute('data-csrf') || btn.dataset.csrf;

    if (!url) return;

    var form = document.createElement('form');
    form.style.display = 'none';
    form.method = method === 'GET' ? 'GET' : 'POST';
    form.action = url;
    if (csrf) {
      var token = document.createElement('input'); token.type = 'hidden'; token.name = '_token'; token.value = csrf; form.appendChild(token);
    }

    // Try to copy hidden inputs from grid search form if present
    var grid = btn.closest('.js-grid');
    if (grid) {
      var searchForm = grid.querySelector('form.js-grid-search-form');
      if (searchForm) {
        var inputs = searchForm.querySelectorAll('input[name], textarea[name], select[name]');
        inputs.forEach(function (inp) {
          var name = inp.getAttribute('name'); if (!name) return;
          if (name === '_token' && csrf) return;
          var i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = inp.value || '';
          form.appendChild(i);
        });
      }
    }

    document.body.appendChild(form);
    form.submit();
    setTimeout(function () { document.body.removeChild(form); }, 1500);
  }, true);
});
