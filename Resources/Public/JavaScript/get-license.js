import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';

/**
 * "Get New License" modal.
 *
 * Buy / Purchase closes this dialog and opens Pabbly in a single TYPO3
 * Modal.iframe (same idea as ns_t3af credits — no nested modal).
 */

const MODAL_ID = 'get-license-modal';

let productsCache = null;
let productsLoaded = false;

// Context carried from the product step into the trial form / OTP / purchase steps.
let trialContext = null;
let purchaseContext = null;

/** @type {ReadonlySet<string>} */
const CHECKOUT_ALLOWED_HOSTS = new Set([
  'payments.pabbly.com',
  'pabbly.com',
  'pabbly.t3planet.de',
  't3planet.shop',
  'www.t3planet.shop',
]);

/** @type {readonly string[]} */
const CHECKOUT_ALLOWED_HOST_SUFFIXES = ['.t3planet.de', '.t3planet.shop', '.t3planet.com', '.pabbly.com'];

/**
 * Best-effort extraction of a JSON body from an AjaxRequest result or a rejection.
 * Handles TYPO3 AjaxResponse (.resolve()), a wrapped { response }, and a raw
 * fetch Response (.json()). Returns null when no JSON body is available.
 * @param {*} candidate
 * @returns {Promise<object|null>}
 */
async function extractJson(candidate) {
  const source = candidate?.response || candidate;
  if (!source) return null;
  try {
    if (typeof source.resolve === 'function') {
      return await source.resolve();
    }
    if (typeof source.json === 'function') {
      return await source.json();
    }
  } catch (e) {
    /* body was not JSON */
  }
  return null;
}

/**
 * Escape a string for safe insertion into HTML.
 * @param {string} value
 * @returns {string}
 */
function escapeHtml(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Show the modal using the Bootstrap 5 native API (TYPO3 standard),
 * with manual fallbacks (mirrors domains.js).
 * @param {HTMLElement} modalElement
 */
function showModal(modalElement) {
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
  } else if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
  } else {
    modalElement.classList.add('show');
    modalElement.style.display = 'block';
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);
    }
  }
}

/**
 * Hide the modal (native API with manual fallback).
 * @param {HTMLElement} modalElement
 */
function hideModal(modalElement) {
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
  } else if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
  } else {
    modalElement.classList.remove('show');
    modalElement.style.display = 'none';
    document.body.classList.remove('modal-open');
    document.querySelector('.modal-backdrop')?.remove();
  }
}

/**
 * Rank a product section for dropdown optgroup order.
 * AI Universe → TYPO3 Templates → Popular extensions.
 * @param {string} sectionId
 * @param {string} sectionTitle
 * @returns {number}
 */
function sectionRank(sectionId, sectionTitle) {
  const id = String(sectionId || '').trim().toLowerCase();
  const title = String(sectionTitle || '').trim().toLowerCase();

  if (id === 'ai-universe' || title.includes('ai universe')) {
    return 0;
  }
  if (
    id === 'popular-extensions'
    || id === 'popular'
    || title.includes('popular extension')
    || title === 'popular extensions'
  ) {
    return 1;
  }
  if (
    id === 'premium-templates'
    || title.includes('typo3 template')
    || title.includes('premium template')
    || (title.includes('template') && !title.includes('extension'))
  ) {
    return 2;
  }
  return 100;
}

/**
 * Filter products by name, extension key, or section.
 * @param {Array} products
 * @param {string} query
 * @returns {Array}
 */
function filterProducts(products, query) {
  const q = String(query || '').trim().toLowerCase();
  if (!q || !Array.isArray(products)) {
    return Array.isArray(products) ? products : [];
  }
  return products.filter((p) => {
    const name = String(p?.name || '').toLowerCase();
    const key = String(p?.extensionKey || '').toLowerCase();
    const section = String(p?.section || '').toLowerCase();
    return name.includes(q) || key.includes(q) || section.includes(q);
  });
}

/**
 * @param {HTMLElement} modal
 * @returns {HTMLInputElement|null}
 */
function getProductValueInput(modal) {
  return modal.querySelector('#gl-product-select');
}

/**
 * @param {HTMLElement} modal
 * @returns {string}
 */
function getSelectedExtensionKey(modal) {
  return String(getProductValueInput(modal)?.value || '').trim();
}

/**
 * Open/close the combobox menu.
 * @param {HTMLElement} modal
 * @param {boolean} open
 */
function setComboboxOpen(modal, open) {
  const menu = modal.querySelector('#gl-product-listbox');
  const input = modal.querySelector('#gl-product-combobox');
  if (!menu || !input) return;
  if (open) {
    menu.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  } else {
    menu.hidden = true;
    input.setAttribute('aria-expanded', 'false');
  }
}

/**
 * Build ordered section → items map for the combobox list.
 * @param {HTMLElement} modal
 * @param {Array} products
 * @returns {Array<[string, Array]>}
 */
function buildOrderedProductGroups(modal, products) {
  const groups = new Map();
  products.forEach((p) => {
    const section = (p.section || '').trim() || (modal.dataset.labelOtherProducts || 'Other Products');
    if (!groups.has(section)) groups.set(section, []);
    groups.get(section).push(p);
  });

  const orderedSections = Array.from(groups.keys()).sort((a, b) => {
    const sampleA = (groups.get(a) || [])[0] || {};
    const sampleB = (groups.get(b) || [])[0] || {};
    const rankA = sectionRank(sampleA.sectionId || '', a);
    const rankB = sectionRank(sampleB.sectionId || '', b);
    if (rankA !== rankB) {
      return rankA - rankB;
    }
    return String(a).localeCompare(String(b));
  });

  return orderedSections.map((section) => {
    const items = (groups.get(section) || []).slice().sort((a, b) =>
      String(a.name || a.extensionKey || '').localeCompare(String(b.name || b.extensionKey || '')),
    );
    return [section, items];
  });
}

/**
 * Render/filter the combobox menu from the product list.
 * @param {HTMLElement} modal
 * @param {Array} products
 * @param {{keepOpen?:boolean, queryOverride?:string|null}} [options]
 */
function renderProducts(modal, products, options = {}) {
  const loading = modal.querySelector('.get-license-products-loading');
  const empty = modal.querySelector('.get-license-products-empty');
  const combobox = modal.querySelector('.gl-combobox');
  const input = modal.querySelector('#gl-product-combobox');
  const toggle = modal.querySelector('.gl-combobox__toggle');
  const menu = modal.querySelector('#gl-product-listbox');
  const valueInput = getProductValueInput(modal);
  const modeActions = modal.querySelector('.gl-mode-actions');
  if (!combobox || !input || !menu || !valueInput) return;

  if (loading) loading.style.display = 'none';

  if (!Array.isArray(products) || products.length === 0) {
    combobox.style.display = 'none';
    input.disabled = true;
    if (toggle) toggle.disabled = true;
    valueInput.value = '';
    setComboboxOpen(modal, false);
    if (modeActions) modeActions.style.display = 'none';
    if (empty) {
      empty.textContent = modal.dataset.labelNoProducts || 'No products available.';
      empty.style.display = '';
    }
    onProductSelected(modal);
    return;
  }

  combobox.style.display = '';
  input.disabled = false;
  if (toggle) toggle.disabled = false;
  if (empty) empty.style.display = 'none';
  if (modeActions) modeActions.style.display = '';

  const selectedKey = valueInput.value;
  const selectedProduct = products.find((p) => p.extensionKey === selectedKey) || null;
  const query = options.queryOverride !== undefined && options.queryOverride !== null
    ? String(options.queryOverride)
    : String(input.value || '');

  // When a product is selected and the input still shows that label, list all products.
  const selectedLabel = selectedProduct
    ? `${selectedProduct.name || selectedProduct.extensionKey}${selectedProduct.extensionKey ? ` (${selectedProduct.extensionKey})` : ''}`
    : '';
  const effectiveQuery = selectedProduct && query.trim() === selectedLabel.trim() ? '' : query;
  const filtered = filterProducts(products, effectiveQuery);
  const groups = buildOrderedProductGroups(modal, filtered);

  if (filtered.length === 0) {
    menu.innerHTML = '<div class="gl-combobox__empty">'
      + escapeHtml(modal.dataset.labelSearchNoMatch || 'No products match your search.')
      + '</div>';
  } else {
    let html = '';
    groups.forEach(([section, items]) => {
      html += '<div class="gl-combobox__group">' + escapeHtml(section) + '</div>';
      items.forEach((p) => {
        const key = String(p.extensionKey || '');
        const name = String(p.name || key);
        const selectedClass = key && key === selectedKey ? ' is-selected' : '';
        html += '<button type="button" class="gl-combobox__option' + selectedClass + '"'
          + ' role="option"'
          + ' data-extension-key="' + escapeHtml(key) + '"'
          + ' aria-selected="' + (key === selectedKey ? 'true' : 'false') + '">'
          + '<span>' + escapeHtml(name) + '</span>'
          + (key ? '<span class="gl-combobox__option-key">' + escapeHtml(key) + '</span>' : '')
          + '</button>';
      });
    });
    menu.innerHTML = html;
  }

  if (options.keepOpen) {
    setComboboxOpen(modal, true);
  }
  onProductSelected(modal);
}

/**
 * Select a product in the combobox.
 * @param {HTMLElement} modal
 * @param {string} extensionKey
 */
function selectProduct(modal, extensionKey) {
  const valueInput = getProductValueInput(modal);
  const input = modal.querySelector('#gl-product-combobox');
  const product = (productsCache || []).find((p) => p.extensionKey === extensionKey) || null;
  if (!valueInput || !input) return;

  valueInput.value = product ? extensionKey : '';
  if (product) {
    const name = product.name || extensionKey;
    input.value = extensionKey ? `${name} (${extensionKey})` : name;
  } else {
    input.value = '';
  }
  setComboboxOpen(modal, false);
  onProductSelected(modal);
}

/**
 * Clear combobox selection/query state.
 * @param {HTMLElement} modal
 */
function resetProductCombobox(modal) {
  const valueInput = getProductValueInput(modal);
  const input = modal.querySelector('#gl-product-combobox');
  if (valueInput) valueInput.value = '';
  if (input) input.value = '';
  setComboboxOpen(modal, false);
}

/**
 * Show an error state in the products step.
 * @param {HTMLElement} modal
 * @param {string} message
 */
function showProductsError(modal, message) {
  const loading = modal.querySelector('.get-license-products-loading');
  const empty = modal.querySelector('.get-license-products-empty');
  const combobox = modal.querySelector('.gl-combobox');
  const input = modal.querySelector('#gl-product-combobox');
  const toggle = modal.querySelector('.gl-combobox__toggle');
  const valueInput = getProductValueInput(modal);
  const modeActions = modal.querySelector('.gl-mode-actions');
  if (loading) loading.style.display = 'none';
  if (combobox) combobox.style.display = 'none';
  if (modeActions) modeActions.style.display = 'none';
  if (input) {
    input.disabled = true;
    input.value = '';
  }
  if (toggle) toggle.disabled = true;
  if (valueInput) valueInput.value = '';
  setComboboxOpen(modal, false);
  if (empty) {
    empty.textContent = message || modal.dataset.labelLoadError || 'Failed to load products.';
    empty.style.display = '';
  }
  onProductSelected(modal);
}

/**
 * Load products from the server (once) and render them.
 * @param {HTMLElement} modal
 * @returns {Promise<void>}
 */
function loadProducts(modal) {
  if (productsLoaded && productsCache) {
    renderProducts(modal, productsCache);
    return Promise.resolve();
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.get_products;
  if (!ajaxUrl) {
    showProductsError(modal, modal.dataset.labelLoadError);
    return Promise.resolve();
  }

  return new AjaxRequest(ajaxUrl)
    .get()
    .then(async (response) => {
      const data = await response.resolve();
      if (data && data.success && Array.isArray(data.products)) {
        productsCache = data.products;
        productsLoaded = true;
        renderProducts(modal, data.products);
      } else {
        showProductsError(modal, data?.message);
      }
    })
    .catch(() => {
      showProductsError(modal, modal.dataset.labelLoadError);
    });
}

/**
 * Apply optional pre-select / mode from a Get New License trigger (e.g. Buy button).
 * Buy with a known extension key skips the product step and opens the purchase step.
 * @param {HTMLElement} modal
 * @param {string} extensionKey
 * @param {string} mode
 */
function applyTriggerProductSelection(modal, extensionKey, mode) {
  const key = (extensionKey || '').trim();
  if (!key) {
    return;
  }

  selectProduct(modal, key);

  const normalizedMode = (mode || '').trim().toLowerCase();
  if (normalizedMode === 'buy') {
    const product = (productsCache || []).find((p) => p.extensionKey === key) || null;
    const buyRadio = modal.querySelector('#gl-mode-buy');
    if (buyRadio && product && productCheckoutUrl(product)) {
      buyRadio.disabled = false;
      buyRadio.checked = true;
      onProductSelected(modal);
      const selection = getSelection(modal);
      if (selection) {
        openPurchaseStep(modal, selection);
      }
      return;
    }
    if (buyRadio && product) {
      onProductSelected(modal);
    }
    return;
  }

  if (normalizedMode === 'trial') {
    setLicenseMode(modal, 'trial');
    onProductSelected(modal);
  }
}

/**
 * Mirrors CheckoutUrlValidator for client-side checks before opening the iframe.
 * @param {string} url
 * @returns {boolean}
 */
function isAllowedCheckoutUrl(url) {
  try {
    const parsed = new URL((url || '').trim());
    if (parsed.protocol !== 'https:') {
      return false;
    }
    const host = parsed.hostname.toLowerCase();
    if (CHECKOUT_ALLOWED_HOSTS.has(host)) {
      return true;
    }
    return CHECKOUT_ALLOWED_HOST_SUFFIXES.some(
      (suffix) => host.endsWith(suffix) && host.length > suffix.length,
    );
  } catch {
    return false;
  }
}

/**
 * Backend host for same-origin detection (module may run in content iframe).
 * @returns {string}
 */
function backendHostname() {
  try {
    return (window.top || window).location.hostname;
  } catch {
    return window.location.hostname;
  }
}

/**
 * If checkout iframe returned to this TYPO3 host, promote URL to top and close modal.
 *
 * @param {HTMLIFrameElement} iframe
 * @returns {boolean}
 */
function tryPromoteCheckoutReturnToTop(iframe) {
  try {
    const win = iframe.contentWindow;
    if (!win) {
      return false;
    }
    const loc = win.location;
    if (loc.hostname !== backendHostname()) {
      return false;
    }
    const href = loc.href || '';
    const path = loc.pathname || '';
    const isLicenseReturn =
      href.includes('purchase_success') ||
      path.includes('NsLicense') ||
      path.includes('nslicense') ||
      path.includes('nitsan');
    if (!isLicenseReturn) {
      return false;
    }
    try {
      Modal.dismiss();
    } catch {
      /* ignore */
    }
    (window.top || window).location.assign(href);
    return true;
  } catch {
    return false;
  }
}

/**
 * @param {HTMLIFrameElement} iframe
 */
function bindCheckoutIframeReturnWatch(iframe) {
  if (!(iframe instanceof HTMLIFrameElement) || iframe.dataset.nsLicenseReturnWatch === '1') {
    return;
  }
  iframe.dataset.nsLicenseReturnWatch = '1';
  iframe.addEventListener('load', () => {
    tryPromoteCheckoutReturnToTop(iframe);
  });
}

/**
 * Close Get New License → open one BE checkout iframe modal (ns_t3af-style).
 *
 * @param {string} checkoutUrl
 * @param {HTMLElement} [sourceModal]
 */
function openCheckoutModal(checkoutUrl, sourceModal) {
  const normalized = (checkoutUrl || '').trim();
  if (normalized === '') {
    return;
  }
  if (!isAllowedCheckoutUrl(normalized)) {
    Notification.error(
      sourceModal?.dataset?.labelTitleError || 'Error',
      sourceModal?.dataset?.labelCheckoutInvalid || 'This checkout link is not allowed.',
    );
    return;
  }

  const title = sourceModal?.dataset?.labelCheckoutTitle || 'T3Planet Checkout';
  const openTabLabel = sourceModal?.dataset?.labelCheckoutOpenTab || 'Open in new tab';

  Modal.advanced({
    type: Modal.types.iframe,
    size: Modal.sizes.large,
    title,
    content: normalized,
    additionalCssClasses: ['ns-license-checkout-modal'],
    staticBackdrop: false,
    buttons: [
      {
        text: openTabLabel,
        btnClass: 'btn-link',
        trigger: () => {
          window.open(normalized, '_blank', 'noopener,noreferrer');
        },
      },
      {
        text: (typeof TYPO3 !== 'undefined' && TYPO3.lang?.['button.close']) || 'Close',
        active: true,
        btnClass: 'btn-default',
        trigger: () => {
          Modal.dismiss();
        },
      },
    ],
    callback: (currentModal) => {
      const body = currentModal?.querySelector?.('.t3js-modal-body');
      const iframe = body?.querySelector?.('iframe');
      if (body) {
        body.classList.add('ns-license-checkout-modal__body');
      }
      if (iframe instanceof HTMLIFrameElement) {
        iframe.classList.add('ns-license-checkout-modal__frame');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        bindCheckoutIframeReturnWatch(iframe);
      }
    },
  });
}

/**
 * Sync mode cards UI with hidden radios.
 * @param {HTMLElement} modal
 * @param {string} mode  'trial' | 'buy'
 */
function setLicenseMode(modal, mode) {
  const next = mode === 'buy' ? 'buy' : 'trial';
  const trialRadio = modal.querySelector('#gl-mode-trial');
  const buyRadio = modal.querySelector('#gl-mode-buy');
  if (trialRadio) trialRadio.checked = next === 'trial';
  if (buyRadio) buyRadio.checked = next === 'buy';

  modal.querySelectorAll('.gl-mode__card[data-gl-mode]').forEach((card) => {
    const active = card.getAttribute('data-gl-mode') === next;
    card.classList.toggle('is-active', active);
    card.setAttribute('aria-pressed', active ? 'true' : 'false');
  });

  const productsStep = modal.querySelector('.get-license-step[data-step="products"]');
  if (productsStep && !productsStep.hidden) {
    updateWizardChrome(modal, 'products');
  }
}

/**
 * Update mode cards and Continue button after a product change.
 * @param {HTMLElement} modal
 */
function onProductSelected(modal) {
  const extKey = getSelectedExtensionKey(modal);
  const hasSelection = !!extKey;
  const product = (productsCache || []).find((p) => p.extensionKey === extKey) || null;
  const canBuy = !!(product && productCheckoutUrl(product));

  const buyCard = modal.querySelector('.gl-mode__card--buy');
  if (buyCard) {
    buyCard.classList.remove('is-disabled');
    buyCard.setAttribute('aria-disabled', 'false');
  }

  const buyRadio = modal.querySelector('#gl-mode-buy');
  if (buyRadio) {
    buyRadio.disabled = false;
  }

  const hint = modal.querySelector('.get-license-buy-hint');
  if (hint) {
    if (hasSelection && !canBuy) {
      hint.textContent = modal.dataset.labelBuyUnavailable || 'Purchase is not available for this product yet.';
      hint.style.display = '';
    } else {
      hint.textContent = '';
      hint.style.display = 'none';
    }
  }

  const continueBtn = modal.querySelector('.t3js-get-license-continue');
  if (continueBtn) continueBtn.disabled = !hasSelection;
}

/**
 * Continue from products step with the selected mode.
 * @param {HTMLElement} modal
 */
function continueFromProducts(modal) {
  const selection = getSelection(modal);
  if (!selection) {
    Notification.warning(modal.dataset.labelTitleWarning || 'Warning', modal.dataset.labelSelectProduct || 'Please select a product first.');
    return;
  }

  if (selection.mode === 'buy' && !productCheckoutUrl(selection.product)) {
    Notification.warning(
      modal.dataset.labelTitleWarning || 'Warning',
      modal.dataset.labelBuyUnavailable || 'Purchase is not available for this product yet.',
    );
    return;
  }

  if (selection.mode === 'trial') {
    openTrialForm(modal, selection);
  } else {
    openPurchaseStep(modal, selection);
  }
}

/**
 * Return the current selection {extensionKey, mode, product}.
 * @param {HTMLElement} modal
 * @returns {{extensionKey:string, mode:string, product:(object|null)}|null}
 */
function getSelection(modal) {
  const extensionKey = getSelectedExtensionKey(modal);
  if (!extensionKey) return null;
  const modeEl = modal.querySelector('input[name="gl-mode"]:checked');
  const mode = modeEl ? modeEl.value : 'trial';
  const product = (productsCache || []).find((p) => p.extensionKey === extensionKey) || null;
  return { extensionKey, mode, product };
}

/**
 * Resolve wizard progress for chrome (meta + stepper).
 * Trial: welcome → products → form → otp → success (5)
 * Buy:   welcome → products → purchase → success (4; Verify pill hidden)
 * @param {HTMLElement} modal
 * @param {string} step
 * @returns {{index:number, total:number, hideOtp:boolean, activeOrder:string[]}}
 */
function getWizardChromeState(modal, step) {
  const onBuyPath = step === 'purchase'
    || (step === 'success' && !!purchaseContext && !trialContext)
    || (step === 'products' && modal.querySelector('input[name="gl-mode"]:checked')?.value === 'buy');

  const activeOrder = onBuyPath
    ? ['welcome', 'products', 'purchase', 'success']
    : ['welcome', 'products', 'form', 'otp', 'success'];

  const resolvedIndex = activeOrder.indexOf(step);
  const stepIndex = resolvedIndex >= 0 ? resolvedIndex + 1 : 1;

  return {
    index: stepIndex,
    total: activeOrder.length,
    hideOtp: onBuyPath,
    activeOrder,
  };
}

/**
 * Update wizard meta, title, and stepper for the current step.
 * @param {HTMLElement} modal
 * @param {string} step
 */
function updateWizardChrome(modal, step) {
  const chrome = getWizardChromeState(modal, step);

  const stepEl = modal.querySelector('.js-gl-wizard-step');
  const totalEl = modal.querySelector('.js-gl-wizard-total');
  if (stepEl) {
    stepEl.textContent = String(chrome.index);
  }
  if (totalEl) {
    totalEl.textContent = String(chrome.total);
  }

  // Fallback single-string meta if present
  const metaEl = modal.querySelector('.js-gl-wizard-meta');
  if (metaEl) {
    const tpl = modal.dataset.labelWizardMeta || 'Get New License · Step %d of %d';
    metaEl.textContent = tpl.replace('%d', String(chrome.index)).replace('%d', String(chrome.total));
  }

  const titleMap = {
    welcome: modal.dataset.labelWizardTitleWelcome,
    products: modal.dataset.labelWizardTitleProducts,
    form: modal.dataset.labelWizardTitleForm,
    purchase: modal.dataset.labelWizardTitlePurchase,
    otp: modal.dataset.labelWizardTitleOtp,
    success: modal.dataset.labelWizardTitleSuccess,
  };
  const titleEl = modal.querySelector('.js-get-license-title');
  if (titleEl && titleMap[step]) {
    titleEl.textContent = titleMap[step];
  }

  const order = chrome.activeOrder;
  const currentIdx = order.indexOf(step);

  modal.querySelectorAll('.gl-wizard-step-btn').forEach((item) => {
    const key = item.getAttribute('data-wizard-step');
    if (key === 'otp' && chrome.hideOtp) {
      item.hidden = true;
      item.classList.remove('is-active', 'active', 'is-complete', 'is-locked');
      return;
    }
    item.hidden = false;

    let orderIdx;
    if (key === 'details') {
      orderIdx = order.findIndex((s) => s === 'form' || s === 'purchase');
    } else {
      orderIdx = order.indexOf(key);
    }

    const isActive = orderIdx === currentIdx;
    const isComplete = orderIdx >= 0 && orderIdx < currentIdx;
    item.classList.toggle('is-active', isActive);
    item.classList.toggle('active', isActive);
    item.classList.toggle('is-complete', isComplete);
    item.classList.toggle('is-locked', !isActive && !isComplete);
    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  let num = 1;
  modal.querySelectorAll('.gl-wizard-step-btn:not([hidden])').forEach((item) => {
    const numEl = item.querySelector('.gl-wizard-step-num');
    if (numEl) {
      numEl.textContent = String(num++);
    }
  });
}

/**
 * Show a single step and toggle the matching footer controls.
 * @param {HTMLElement} modal
 * @param {string} step  'welcome' | 'products' | 'form' | 'otp' | 'purchase' | 'success'
 */
function showStep(modal, step) {
  modal.querySelectorAll('.get-license-step').forEach((el) => {
    const match = el.getAttribute('data-step') === step;
    el.style.display = match ? 'block' : 'none';
    el.hidden = !match;
  });
  modal.querySelectorAll('[data-step-footer]').forEach((el) => {
    const match = el.getAttribute('data-step-footer') === step;
    if (match) {
      el.style.removeProperty('display');
      el.hidden = false;
    } else {
      el.style.display = 'none';
      el.hidden = true;
    }
  });
  updateWizardChrome(modal, step);
}

/**
 * Resolve checkout URL from a product payload (camelCase or snake_case).
 * @param {object|null} product
 * @returns {string}
 */
function productCheckoutUrl(product) {
  if (!product || typeof product !== 'object') {
    return '';
  }
  return String(product.checkoutUrl || product.checkout_url || '').trim();
}

/**
 * Populate and open the purchase step for the current selection.
 * @param {HTMLElement} modal
 * @param {object} selection  from getSelection()
 */
function openPurchaseStep(modal, selection) {
  purchaseContext = selection;
  const product = selection.product || {};

  fillSelectedProductSummary(modal, product, selection.extensionKey, {
    name: '.js-gl-buy-name',
    key: '.js-gl-buy-key',
    desc: '.js-gl-buy-desc',
    descWrap: '.js-gl-buy-desc-wrap',
  });

  const priceEl = modal.querySelector('.js-gl-buy-price');
  const priceSuffixEl = modal.querySelector('.js-gl-buy-price-suffix');
  const price = (
    product.priceAnnual
    || product.price_annual
    || product.price
    || product.priceLifeTime
    || product.price_lifetime
    || ''
  ).toString().trim();
  if (priceEl) {
    priceEl.textContent = price || (modal.dataset.labelBuyPriceEmpty || 'See checkout');
  }
  if (priceSuffixEl) {
    const normalized = price.toLowerCase();
    const showAnnualSuffix = price !== ''
      && normalized !== 'free'
      && !normalized.includes('/year')
      && !normalized.includes('/ year')
      && !normalized.includes('lifetime');
    priceSuffixEl.style.display = showAnnualSuffix ? '' : 'none';
  }

  const terms = modal.querySelector('#gl-buy-terms');
  if (terms) terms.checked = false;

  const feedback = modal.querySelector('.js-gl-buy-feedback');
  const payBtn = modal.querySelector('.t3js-get-license-pay');
  const checkoutUrl = productCheckoutUrl(product);
  if (feedback) {
    if (!checkoutUrl) {
      feedback.textContent = modal.dataset.labelBuyUnavailable || 'Purchase is not available for this product yet.';
      feedback.style.display = '';
    } else {
      feedback.textContent = '';
      feedback.style.display = 'none';
    }
  }
  if (payBtn) {
    payBtn.disabled = !checkoutUrl;
  }

  showStep(modal, 'purchase');
}

/**
 * Show purchase success (with license key when returned from checkout redirect).
 * @param {HTMLElement} modal
 * @param {string} [licenseKey]
 */
function showPurchaseSuccess(modal, licenseKey) {
  // Mark buy path for wizard chrome (Verify pill hidden) after checkout return.
  purchaseContext = purchaseContext || { extensionKey: '', mode: 'buy', product: null };
  trialContext = null;

  const titleEl = modal.querySelector('.js-gl-success-title');
  const subtitleEl = modal.querySelector('.js-gl-success-subtitle');
  const keyWrap = modal.querySelector('.js-gl-success-key-wrap');
  const keyEl = modal.querySelector('.js-gl-license-key');
  const emailNote = modal.querySelector('.js-gl-success-email-note');
  const key = String(licenseKey || '').trim();

  if (titleEl) {
    titleEl.textContent = modal.dataset.labelBuySuccessTitle
      || 'Your license is ready!';
  }
  if (subtitleEl) {
    subtitleEl.textContent = modal.dataset.labelBuySuccessSubtitle
      || 'Your license has been created.';
  }
  const copyBtn = modal.querySelector('.js-gl-copy-license-key');
  const activateBtn = modal.querySelector('.js-gl-activate-license');
  if (key) {
    if (keyWrap) keyWrap.style.display = '';
    if (keyEl) keyEl.textContent = key;
    if (copyBtn) copyBtn.dataset.licenseKey = key;
    if (activateBtn) activateBtn.style.display = '';
    if (emailNote) {
      emailNote.textContent = modal.dataset.labelBuySuccessEmail
        || "We've also emailed the license details to you.";
    }
  } else {
    if (keyWrap) keyWrap.style.display = 'none';
    if (keyEl) keyEl.textContent = '';
    if (copyBtn) copyBtn.dataset.licenseKey = '';
    if (activateBtn) activateBtn.style.display = 'none';
    if (emailNote) {
      emailNote.textContent = modal.dataset.labelBuySuccessEmail
        || 'Check your email for the license key, then Activate it on this page.';
    }
  }
  showStep(modal, 'success');
}

/**
 * After Buy checkout redirect (?purchase_success=1[&purchase_token=…]), open success UI.
 * Resolves encrypted purchase_token via composer API (no plaintext key in the URL).
 */
function maybeShowPurchaseReturnSuccess() {
  const modal = document.getElementById(MODAL_ID);
  if (!modal) {
    return;
  }
  const success = modal.dataset.purchaseSuccess === '1';
  if (!success) {
    return;
  }
  const purchaseToken = String(modal.dataset.purchaseToken || '').trim();

  try {
    const url = new URL(window.location.href);
    if (
      url.searchParams.has('purchase_success')
      || url.searchParams.has('purchase_token')
      || url.searchParams.has('license_key')
    ) {
      url.searchParams.delete('purchase_success');
      url.searchParams.delete('purchase_token');
      url.searchParams.delete('license_key');
      const next = url.pathname + (url.searchParams.toString() ? `?${url.searchParams}` : '') + url.hash;
      window.history.replaceState({}, '', next);
    }
  } catch {
    /* ignore */
  }

  showModal(modal);

  if (!purchaseToken) {
    showPurchaseSuccess(modal, '');
    return;
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.resolve_purchase_token;
  if (!ajaxUrl) {
    showPurchaseSuccess(modal, '');
    return;
  }

  new AjaxRequest(ajaxUrl)
    .post({ purchase_token: purchaseToken })
    .then(async (response) => {
      const data = await response.resolve();
      const key = (data && data.success && data.license_key) ? String(data.license_key).trim() : '';
      showPurchaseSuccess(modal, key);
    })
    .catch(async () => {
      showPurchaseSuccess(modal, '');
    });
}

/**
 * Reset trial success copy when showing a trial key again.
 * @param {HTMLElement} modal
 * @param {string} licenseKey
 */
function showTrialSuccess(modal, licenseKey) {
  const titleEl = modal.querySelector('.js-gl-success-title');
  const subtitleEl = modal.querySelector('.js-gl-success-subtitle');
  const keyWrap = modal.querySelector('.js-gl-success-key-wrap');
  const keyEl = modal.querySelector('.js-gl-license-key');
  const copyBtn = modal.querySelector('.js-gl-copy-license-key');
  const activateBtn = modal.querySelector('.js-gl-activate-license');
  const emailNote = modal.querySelector('.js-gl-success-email-note');
  const key = String(licenseKey || '').trim();

  if (titleEl) titleEl.textContent = modal.dataset.labelTrialSuccessTitle || 'Your trial is ready!';
  if (subtitleEl) {
    subtitleEl.textContent = modal.dataset.labelTrialSuccessSubtitle
      || 'Your 30-day free trial license has been created.';
  }
  if (keyWrap) keyWrap.style.display = key ? '' : 'none';
  if (keyEl) keyEl.textContent = key;
  if (copyBtn) copyBtn.dataset.licenseKey = key;
  if (activateBtn) activateBtn.style.display = key ? '' : 'none';
  if (emailNote) {
    emailNote.textContent = modal.dataset.labelTrialSuccessEmail
      || "We've also emailed the license details to you.";
  }
  showStep(modal, 'success');
}

/**
 * Activate the license key shown on the success step, then reload the module.
 * @param {HTMLElement} modal
 * @param {HTMLElement} doneBtn
 */
function activateLicenseAndReload(modal, doneBtn) {
  const keyEl = modal.querySelector('.js-gl-license-key');
  const licenseKey = String(keyEl?.textContent || '').trim();

  if (!licenseKey) {
    Notification.warning(
      modal.dataset.labelTitleWarning || 'Warning',
      modal.dataset.labelActivateMissingKey
        || 'No license key to activate. Check your email, then Activate it on this page.',
    );
    hideModal(modal);
    window.location.reload();
    return;
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.activate_license;
  if (!ajaxUrl) {
    Notification.error(
      modal.dataset.labelTitleError || 'Error',
      modal.dataset.labelGenericError || 'Something went wrong. Please try again.',
    );
    return;
  }

  const label = doneBtn.querySelector('.label');
  const defaultLabel = doneBtn.dataset.labelDefault || 'Activate license';
  const activatingLabel = doneBtn.dataset.labelActivating
    || modal.dataset.labelActivating
    || 'Activating…';
  doneBtn.disabled = true;
  if (label) label.textContent = activatingLabel;

  new AjaxRequest(ajaxUrl)
    .post({ license: licenseKey })
    .then(async (response) => {
      const data = await response.resolve();
      if (data && data.success) {
        if (data.message) {
          Notification.success(modal.dataset.labelTitleSuccess || 'Success', data.message);
        }
        hideModal(modal);
        window.location.reload();
        return;
      }
      Notification.error(
        modal.dataset.labelTitleError || 'Error',
        (data && data.message)
          || modal.dataset.labelActivateFailed
          || 'Could not activate the license. You can Activate it manually on this page.',
      );
    })
    .catch(async (error) => {
      const data = await extractJson(error);
      Notification.error(
        modal.dataset.labelTitleError || 'Error',
        (data && data.message)
          || modal.dataset.labelActivateFailed
          || 'Could not activate the license. You can Activate it manually on this page.',
      );
    })
    .finally(() => {
      doneBtn.disabled = false;
      if (label) label.textContent = defaultLabel;
    });
}

/**
 * Prepare checkout via BE AJAX, close Get New License, open one BE checkout modal.
 * @param {HTMLElement} modal
 */
function startPurchaseCheckout(modal) {
  const terms = modal.querySelector('#gl-buy-terms');
  if (!terms?.checked) {
    Notification.warning(
      modal.dataset.labelTitleWarning || 'Warning',
      modal.dataset.labelTermsRequired || 'Please accept the Terms & Conditions and Privacy Policy.',
    );
    return;
  }

  const extensionKey = purchaseContext?.extensionKey || '';
  if (!extensionKey) {
    Notification.error(modal.dataset.labelTitleError || 'Error', modal.dataset.labelGenericError || 'Something went wrong.');
    return;
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.prepare_checkout;
  if (!ajaxUrl) {
    Notification.error(modal.dataset.labelTitleError || 'Error', modal.dataset.labelGenericError || 'Something went wrong.');
    return;
  }

  const payBtn = modal.querySelector('.t3js-get-license-pay');
  const labelEl = payBtn?.querySelector('.label');
  const defaultLabel = payBtn?.dataset?.labelDefault || 'Proceed to payment';
  const loadingLabel = payBtn?.dataset?.labelLoading || 'Preparing…';
  if (payBtn) payBtn.disabled = true;
  if (labelEl) labelEl.textContent = loadingLabel;

  new AjaxRequest(ajaxUrl)
    .post({ extension_key: extensionKey })
    .then(async (response) => {
      const data = await response.resolve();
      if (data && data.success && data.checkoutUrl) {
        hideModal(modal);
        openCheckoutModal(data.checkoutUrl, modal);
      } else {
        Notification.error(
          modal.dataset.labelTitleError || 'Error',
          data?.message || modal.dataset.labelBuyUnavailable || 'Purchase is not available for this product.',
        );
      }
    })
    .catch(async (error) => {
      const data = await extractJson(error);
      Notification.error(
        modal.dataset.labelTitleError || 'Error',
        data?.message || modal.dataset.labelGenericError || 'Something went wrong. Please try again.',
      );
    })
    .finally(() => {
      if (payBtn) payBtn.disabled = false;
      if (labelEl) labelEl.textContent = defaultLabel;
    });
}

/**
 * Fill selected-product summary (name, key, description) in a details card.
 * @param {HTMLElement} root  form or purchase step container (or modal)
 * @param {object|null} product
 * @param {string} extensionKey
 * @param {{name:string, key:string, desc:string, descWrap:string}} selectors
 */
function fillSelectedProductSummary(root, product, extensionKey, selectors) {
  const name = (product?.name || extensionKey || '').toString();
  const key = (product?.extensionKey || extensionKey || '').toString();
  const section = (product?.section || '').toString().trim();
  const description = (product?.description || product?.shortDescription || '').toString().trim();

  const nameEl = root.querySelector(selectors.name);
  if (nameEl) nameEl.textContent = name;

  const keyEl = root.querySelector(selectors.key);
  if (keyEl) {
    const parts = [];
    if (key) parts.push(key);
    if (section) parts.push(section);
    keyEl.textContent = parts.join(' · ');
    keyEl.style.display = parts.length ? '' : 'none';
  }

  const descEl = root.querySelector(selectors.desc);
  const descWrap = root.querySelector(selectors.descWrap);
  if (descEl && descWrap) {
    if (description) {
      descEl.textContent = description;
      descWrap.style.display = '';
    } else {
      descEl.textContent = '';
      descWrap.style.display = 'none';
    }
  }
}

/**
 * Populate and open the trial form for the current selection.
 * @param {HTMLElement} modal
 * @param {object} selection  from getSelection()
 */
function openTrialForm(modal, selection) {
  trialContext = selection;

  fillSelectedProductSummary(modal, selection.product, selection.extensionKey, {
    name: '.js-gl-selected-name',
    key: '.js-gl-selected-key',
    desc: '.js-gl-selected-desc',
    descWrap: '.js-gl-selected-desc-wrap',
  });

  // Prefill the production domain with the current backend host as a sensible default.
  const domainInput = modal.querySelector('#gl-domain');
  if (domainInput && !domainInput.value) {
    domainInput.value = window.location.hostname || '';
  }

  showStep(modal, 'form');
}

/**
 * Domains known to host disposable / temporary mailboxes.
 * Keep in sync with composer/API/Utils/DisposableEmailGuard.php.
 * @type {string[]}
 */
const DISPOSABLE_EMAIL_DOMAINS = [
  'yopmail.com',
  'yopmail.fr',
  'mailinator.com',
  'guerrillamail.com',
  'guerrillamailblock.com',
  'sharklasers.com',
  'grr.la',
  'tempmail.com',
  'temp-mail.org',
  'throwawaymail.com',
  '10minutemail.com',
  'trashmail.com',
  'fakeinbox.com',
  'maildrop.cc',
  'dispostable.com',
  'mailnesia.com',
  'getnada.com',
  'emailondeck.com',
  'moakt.com',
  'discard.email',
];

/**
 * @param {string} email
 * @returns {boolean}
 */
function isDisposableEmail(email) {
  const at = email.lastIndexOf('@');
  if (at < 0) {
    return false;
  }
  const domain = email.slice(at + 1).trim().toLowerCase().replace(/\.+$/, '');
  if (!domain) {
    return false;
  }
  return DISPOSABLE_EMAIL_DOMAINS.some(
    (blocked) => domain === blocked || domain.endsWith('.' + blocked),
  );
}

/**
 * Validate the trial form. Returns { valid, values } or shows a warning.
 * @param {HTMLElement} modal
 * @returns {{extension_key:string, email:string, name:string, domain:string}|null}
 */
function readTrialForm(modal) {
  const email = (modal.querySelector('#gl-email')?.value || '').trim();
  const name = (modal.querySelector('#gl-name')?.value || '').trim();
  const domain = (modal.querySelector('#gl-domain')?.value || '').trim();
  const terms = !!modal.querySelector('#gl-terms')?.checked;

  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  if (!emailOk) {
    Notification.warning(modal.dataset.labelTitleWarning || 'Warning', modal.dataset.labelInvalidEmail || 'Please enter a valid email address.');
    return null;
  }
  if (isDisposableEmail(email)) {
    Notification.warning(
      modal.dataset.labelTitleWarning || 'Warning',
      modal.dataset.labelDisposableEmail
        || 'Temporary or disposable email addresses are not allowed. Please use a permanent email.',
    );
    return null;
  }
  if (domain === '') {
    Notification.warning(modal.dataset.labelTitleWarning || 'Warning', modal.dataset.labelInvalidDomain || 'Please enter your domain.');
    return null;
  }
  // Free trial allows a single domain only (no comma-separated lists).
  if (domain.includes(',') || domain.includes(';')) {
    Notification.warning(
      modal.dataset.labelTitleWarning || 'Warning',
      modal.dataset.labelInvalidDomainMultiple || 'Please enter only one domain (comma-separated domains are not allowed).',
    );
    return null;
  }
  if (!terms) {
    Notification.warning(modal.dataset.labelTitleWarning || 'Warning', modal.dataset.labelTermsRequired || 'Please accept the Terms & Conditions and Privacy Policy.');
    return null;
  }

  return {
    extension_key: trialContext?.extensionKey || '',
    email,
    name,
    domain,
  };
}

/**
 * Toggle the "Send code" button between its default and sending states.
 * @param {HTMLElement} modal
 * @param {boolean} sending
 */
function setSendCodeBusy(modal, sending) {
  const btn = modal.querySelector('.t3js-get-license-send-code');
  if (!btn) return;
  const label = btn.querySelector('.label');
  btn.disabled = sending;
  if (label) {
    label.textContent = sending
      ? (btn.dataset.labelSending || 'Sending…')
      : (btn.dataset.labelDefault || 'Send verification code');
  }
}

/**
 * Submit the trial form to the start_trial route (sends the OTP email).
 * @param {HTMLElement} modal
 * @param {boolean} isResend
 */
function submitTrial(modal, isResend) {
  const values = isResend
    ? { extension_key: trialContext?.extensionKey || '', email: trialContext?.email || '', name: trialContext?.name || '', domain: trialContext?.domain || '' }
    : readTrialForm(modal);
  if (!values || !values.extension_key || !values.email) {
    return;
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.start_trial;
  if (!ajaxUrl) {
    Notification.error(modal.dataset.labelTitleError || 'Error', modal.dataset.labelGenericError || 'Something went wrong. Please try again.');
    return;
  }

  const product = trialContext?.product || {};
  const payload = {
    extension_key: values.extension_key,
    email: values.email,
    name: values.name,
    domain: values.domain,
    extension_name: product.name || values.extension_key,
    price_annual: product.priceAnnual || product.price || '',
    price_lifetime: product.priceLifeTime || product.priceLifetime || '',
    language: (document.documentElement.lang || 'en').slice(0, 2),
    terms_accepted: '1',
  };

  if (!isResend) setSendCodeBusy(modal, true);

  new AjaxRequest(ajaxUrl)
    .post(payload)
    .then(async (response) => {
      const data = await response.resolve();
      if (data && data.success) {
        // Remember what we need for the OTP verification step.
        trialContext = { ...(trialContext || {}), email: values.email, name: values.name, domain: values.domain, extensionKey: values.extension_key };
        const sentTpl = modal.dataset.labelOtpSent || 'Please enter the 6-digit code we just sent to %s';
        const sentEl = modal.querySelector('.js-gl-otp-sent');
        if (sentEl) {
          const emailHtml = '<strong class="gl-otp-email">' + escapeHtml(values.email) + '</strong>';
          sentEl.innerHTML = escapeHtml(sentTpl).replace('%s', emailHtml);
        }
        clearOtpInputs(modal);
        showStep(modal, 'otp');
        focusOtpDigit(modal, 0);
        if (isResend) {
          Notification.success(modal.dataset.labelTitleSuccess || 'Success', data.message || 'A new code has been sent.');
        }
      } else {
        handleTrialError(modal, data);
      }
    })
    .catch(async (error) => {
      // TYPO3 AjaxRequest may reject with a Response/AjaxResponse on non-2xx.
      const data = await extractJson(error);
      handleTrialError(modal, data);
    })
    .finally(() => {
      if (!isResend) setSendCodeBusy(modal, false);
    });
}

/**
 * Show a friendly error for a failed start-trial response.
 * @param {HTMLElement} modal
 * @param {object|null} data
 */
function handleTrialError(modal, data) {
  const message = data?.message || modal.dataset.labelGenericError || 'Could not start the trial. Please try again.';
  if (data?.error_code === 'trial_already_started' || data?.error_code === 'trial_domain_already_used') {
    Notification.info(modal.dataset.labelTitleInfo || 'Notice', message);
  } else {
    Notification.error(modal.dataset.labelTitleError || 'Error', message);
  }
}

/**
 * Toggle the "Verify" button between its default and verifying states.
 * @param {HTMLElement} modal
 * @param {boolean} busy
 */
function setVerifyBusy(modal, busy) {
  const btn = modal.querySelector('.t3js-get-license-verify');
  if (!btn) return;
  const label = btn.querySelector('.label');
  btn.disabled = busy;
  if (label) {
    label.textContent = busy
      ? (btn.dataset.labelVerifying || 'Verifying…')
      : (btn.dataset.labelDefault || 'Verify & start trial');
  }
}

/**
 * Show an inline error under the OTP input.
 * @param {HTMLElement} modal
 * @param {string} message
 */
function showOtpFeedback(modal, message) {
  const el = modal.querySelector('.gl-otp-feedback');
  if (!el) return;
  if (message) {
    el.textContent = message;
    el.style.display = '';
  } else {
    el.textContent = '';
    el.style.display = 'none';
  }
}

/**
 * OTP digit inputs in order.
 * @param {HTMLElement} modal
 * @returns {HTMLInputElement[]}
 */
function getOtpDigitInputs(modal) {
  return Array.from(modal.querySelectorAll('.gl-otp-digit'));
}

/**
 * Combined 6-digit OTP value.
 * @param {HTMLElement} modal
 * @returns {string}
 */
function getOtpCode(modal) {
  return getOtpDigitInputs(modal).map((el) => (el.value || '').replace(/\D/g, '')).join('').slice(0, 6);
}

/**
 * Sync hidden #gl-otp with digit boxes.
 * @param {HTMLElement} modal
 */
function syncHiddenOtp(modal) {
  const hidden = modal.querySelector('#gl-otp');
  if (hidden) hidden.value = getOtpCode(modal);
}

/**
 * Clear OTP digit boxes.
 * @param {HTMLElement} modal
 */
function clearOtpInputs(modal) {
  getOtpDigitInputs(modal).forEach((el) => {
    el.value = '';
  });
  syncHiddenOtp(modal);
  showOtpFeedback(modal, '');
}

/**
 * Focus an OTP digit by index.
 * @param {HTMLElement} modal
 * @param {number} index
 */
function focusOtpDigit(modal, index) {
  const digits = getOtpDigitInputs(modal);
  const el = digits[Math.max(0, Math.min(index, digits.length - 1))];
  if (el) {
    el.focus();
    el.select();
  }
}

/**
 * Fill digit boxes from a code string (typing or paste).
 * @param {HTMLElement} modal
 * @param {string} code
 * @param {number} [startIndex]
 */
function fillOtpDigits(modal, code, startIndex = 0) {
  const digits = getOtpDigitInputs(modal);
  const chars = String(code || '').replace(/\D/g, '').slice(0, digits.length - startIndex).split('');
  chars.forEach((ch, i) => {
    const el = digits[startIndex + i];
    if (el) el.value = ch;
  });
  syncHiddenOtp(modal);
  const nextIndex = Math.min(startIndex + chars.length, digits.length - 1);
  focusOtpDigit(modal, chars.length > 0 && startIndex + chars.length < digits.length
    ? startIndex + chars.length
    : nextIndex);
}

/**
 * Verify the entered OTP; on success show the success step.
 * @param {HTMLElement} modal
 */
function verifyOtp(modal) {
  syncHiddenOtp(modal);
  const otp = getOtpCode(modal);
  showOtpFeedback(modal, '');

  if (!/^\d{6}$/.test(otp)) {
    showOtpFeedback(modal, modal.dataset.labelOtpInvalid || 'Please enter the 6-digit code.');
    focusOtpDigit(modal, otp.length);
    return;
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.verify_trial_otp;
  if (!ajaxUrl) {
    Notification.error(modal.dataset.labelTitleError || 'Error', modal.dataset.labelGenericError || 'Something went wrong. Please try again.');
    return;
  }

  setVerifyBusy(modal, true);

  new AjaxRequest(ajaxUrl)
    .post({
      extension_key: trialContext?.extensionKey || '',
      email: trialContext?.email || '',
      otp,
    })
    .then(async (response) => {
      const data = await response.resolve();
      if (data && data.success) {
        showTrialSuccess(modal, data.license_key || '');
      } else {
        handleVerifyError(modal, data);
      }
    })
    .catch(async (error) => {
      const data = await extractJson(error);
      handleVerifyError(modal, data);
    })
    .finally(() => {
      setVerifyBusy(modal, false);
    });
}

/**
 * Show a friendly error for a failed verify response.
 * @param {HTMLElement} modal
 * @param {object|null} data
 */
function handleVerifyError(modal, data) {
  let message = data?.message || modal.dataset.labelGenericError || 'Verification failed. Please try again.';
  if (data && typeof data.remaining_attempts === 'number') {
    const tpl = modal.dataset.labelAttemptsLeft || '%d attempts left';
    message += ' ' + tpl.replace('%d', String(data.remaining_attempts));
  }
  showOtpFeedback(modal, message);
}

// --- Wiring -----------------------------------------------------------------

document.addEventListener('click', (e) => {
  const trigger = e.target.closest('.t3js-get-license-trigger');
  if (!trigger) return;
  e.preventDefault();

  const modal = document.getElementById(MODAL_ID);
  if (!modal) return;

  const extensionKey = trigger.dataset.extensionKey || '';
  const glMode = (trigger.dataset.glMode || '').trim().toLowerCase();

  trialContext = null;
  purchaseContext = null;
  resetProductCombobox(modal);
  setLicenseMode(modal, 'trial');

  // DocHeader (no key) → Welcome. Shop/deep-link with key → Mode step.
  // Buy + key may skip straight to purchase after products load.
  const skipToPurchase = glMode === 'buy' && !!extensionKey;
  if (!skipToPurchase) {
    showStep(modal, extensionKey ? 'products' : 'welcome');
  }
  showModal(modal);
  loadProducts(modal).then(() => {
    applyTriggerProductSelection(modal, extensionKey, glMode);
    // If Buy skip failed (no checkout URL / unknown product), fall back to product step.
    if (skipToPurchase) {
      const purchaseStep = modal.querySelector('.get-license-step[data-step="purchase"]');
      const onPurchase = purchaseStep && purchaseStep.style.display !== 'none' && !purchaseStep.hidden;
      if (!onPurchase) {
        showStep(modal, 'products');
      }
    }
  });
});

// Welcome: Skip / Get Started → product step
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-skip, .t3js-get-license-start');
  if (!btn) return;
  e.preventDefault();
  const modal = btn.closest('#' + MODAL_ID) || document.getElementById(MODAL_ID);
  if (!modal) return;
  showStep(modal, 'products');
});

// Combobox: type to filter, show menu.
document.addEventListener('input', (e) => {
  const input = e.target.closest('#gl-product-combobox');
  if (!input) return;
  const modal = input.closest('#' + MODAL_ID);
  if (!modal || !productsCache) return;

  // Typing means the user is searching again — clear prior selection until they pick.
  const valueInput = getProductValueInput(modal);
  if (valueInput) valueInput.value = '';
  renderProducts(modal, productsCache, { keepOpen: true, queryOverride: input.value });
  onProductSelected(modal);
});

document.addEventListener('focusin', (e) => {
  const input = e.target.closest('#gl-product-combobox');
  if (!input) return;
  const modal = input.closest('#' + MODAL_ID);
  if (modal && productsCache) {
    renderProducts(modal, productsCache, { keepOpen: true });
  }
});

document.addEventListener('click', (e) => {
  const modal = document.getElementById(MODAL_ID);
  if (!modal) return;

  const toggle = e.target.closest('.gl-combobox__toggle');
  if (toggle && modal.contains(toggle)) {
    e.preventDefault();
    if (!productsCache || toggle.disabled) return;
    const menu = modal.querySelector('#gl-product-listbox');
    const willOpen = !menu || menu.hidden;
    if (willOpen) {
      renderProducts(modal, productsCache, { keepOpen: true, queryOverride: '' });
      modal.querySelector('#gl-product-combobox')?.focus();
    } else {
      setComboboxOpen(modal, false);
    }
    return;
  }

  const option = e.target.closest('.gl-combobox__option');
  if (option && modal.contains(option)) {
    e.preventDefault();
    selectProduct(modal, option.getAttribute('data-extension-key') || '');
    return;
  }

  // Close menu when clicking outside the combobox.
  if (!e.target.closest('.gl-combobox')) {
    setComboboxOpen(modal, false);
  }
});

document.addEventListener('keydown', (e) => {
  const input = e.target.closest('#gl-product-combobox');
  if (!input) return;
  const modal = input.closest('#' + MODAL_ID);
  if (!modal) return;
  const menu = modal.querySelector('#gl-product-listbox');
  if (!menu || menu.hidden) {
    if (e.key === 'ArrowDown' && productsCache) {
      e.preventDefault();
      renderProducts(modal, productsCache, { keepOpen: true });
    }
    return;
  }

  const options = Array.from(menu.querySelectorAll('.gl-combobox__option'));
  if (!options.length) {
    if (e.key === 'Escape') {
      e.preventDefault();
      setComboboxOpen(modal, false);
    }
    return;
  }

  const active = menu.querySelector('.gl-combobox__option.is-active');
  let index = active ? options.indexOf(active) : -1;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    index = Math.min(options.length - 1, index + 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    index = Math.max(0, index <= 0 ? 0 : index - 1);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (active) {
      selectProduct(modal, active.getAttribute('data-extension-key') || '');
    }
    return;
  } else if (e.key === 'Escape') {
    e.preventDefault();
    setComboboxOpen(modal, false);
    return;
  } else {
    return;
  }

  options.forEach((opt) => opt.classList.remove('is-active'));
  const next = options[index];
  if (next) {
    next.classList.add('is-active');
    next.scrollIntoView({ block: 'nearest' });
  }
});

// Close via the header (X) or footer Cancel button (works without Bootstrap data-api).
document.addEventListener('click', (e) => {
  const closer = e.target.closest('#' + MODAL_ID + ' .t3js-modal-close, #' + MODAL_ID + ' [data-bs-dismiss="modal"]');
  if (!closer) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) {
    hideModal(modal);
  }
});

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-continue');
  if (!btn) return;
  e.preventDefault();

  const modal = document.getElementById(MODAL_ID);
  if (!modal || btn.disabled) return;

  continueFromProducts(modal);
});

// Mode cards: Free Trial / Buy Now — select mode only; Continue advances.
document.addEventListener('click', (e) => {
  const card = e.target.closest('.gl-mode__card[data-gl-mode]');
  if (!card) return;
  const modal = card.closest('#' + MODAL_ID);
  if (!modal || card.classList.contains('is-disabled')) return;
  e.preventDefault();
  setLicenseMode(modal, card.getAttribute('data-gl-mode') || 'trial');
  onProductSelected(modal);
});

// Purchase -> close Get New License, open single BE checkout modal.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-pay');
  if (!btn) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) startPurchaseCheckout(modal);
});

// Trial form -> send verification code.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-send-code');
  if (!btn) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) submitTrial(modal, false);
});

// Resend the OTP.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-resend');
  if (!btn) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) submitTrial(modal, true);
});

// Step back navigation.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-back');
  if (!btn) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) showStep(modal, btn.dataset.backTo || 'products');
});

// Verify the OTP.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-verify');
  if (!btn) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) verifyOtp(modal);
});

// Success -> activate the license key, then refresh so it appears in the list.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3js-get-license-done');
  if (!btn) return;
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) {
    activateLicenseAndReload(modal, btn);
  }
});

// OTP digit boxes: numeric only, auto-advance, paste, Enter to verify.
document.addEventListener('input', (e) => {
  const digit = e.target.closest('.gl-otp-digit');
  if (!digit) return;
  const modal = digit.closest('#' + MODAL_ID);
  if (!modal) return;

  const index = Number(digit.getAttribute('data-otp-index') || '0');
  const raw = String(digit.value || '').replace(/\D/g, '');
  showOtpFeedback(modal, '');

  if (raw.length > 1) {
    fillOtpDigits(modal, raw, index);
    return;
  }

  digit.value = raw.slice(0, 1);
  syncHiddenOtp(modal);
  if (raw && index < 5) {
    focusOtpDigit(modal, index + 1);
  }
});

document.addEventListener('keydown', (e) => {
  const digit = e.target.closest('.gl-otp-digit');
  if (!digit) return;
  const modal = digit.closest('#' + MODAL_ID);
  if (!modal) return;

  const index = Number(digit.getAttribute('data-otp-index') || '0');

  if (e.key === 'Enter') {
    e.preventDefault();
    verifyOtp(modal);
    return;
  }

  if (e.key === 'Backspace' && !digit.value && index > 0) {
    e.preventDefault();
    focusOtpDigit(modal, index - 1);
    const prev = getOtpDigitInputs(modal)[index - 1];
    if (prev) prev.value = '';
    syncHiddenOtp(modal);
    showOtpFeedback(modal, '');
  }
});

document.addEventListener('paste', (e) => {
  const digit = e.target.closest('.gl-otp-digit');
  if (!digit) return;
  const modal = digit.closest('#' + MODAL_ID);
  if (!modal) return;
  e.preventDefault();
  const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
  const index = Number(digit.getAttribute('data-otp-index') || '0');
  fillOtpDigits(modal, text, index);
  showOtpFeedback(modal, '');
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', maybeShowPurchaseReturnSuccess);
} else {
  maybeShowPurchaseReturnSuccess();
}
