/**
 * Catalog demo links (AI Universe + Extensions):
 * Supademo URLs open in modal; all other demo URLs open in a new tab.
 */
const MODAL_ID = 'demo-modal';
const TRIGGER_SELECTOR = '.t3js-demo-modal-trigger';

/**
 * @param {string} url
 * @returns {boolean}
 */
function isSupademoUrl(url) {
  const value = String(url || '').trim();
  if (!value) {
    return false;
  }
  try {
    const host = new URL(value, window.location.origin).hostname.toLowerCase();
    return host === 'supademo.com' || host.endsWith('.supademo.com');
  } catch (e) {
    return /supademo\.com/i.test(value);
  }
}

/**
 * @param {string} url
 */
function openDemoInNewTab(url) {
  const safeUrl = String(url || '').trim();
  if (!safeUrl) {
    return;
  }
  window.open(safeUrl, '_blank', 'noopener,noreferrer');
}

function ensureBackdrop() {
  if (document.querySelector('.modal-backdrop')) {
    return;
  }
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop fade show';
  const shell = document.querySelector('.ns-license-module-wrapper');
  if (shell?.parentNode) {
    shell.parentNode.insertBefore(backdrop, shell.nextSibling);
    return;
  }
  document.body.appendChild(backdrop);
}

/**
 * @param {HTMLElement} modalElement
 */
function showModal(modalElement) {
  modalElement.classList.add('show');
  modalElement.style.display = 'block';
  modalElement.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';

  const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
  if (scrollbarWidth > 0) {
    document.body.style.paddingRight = `${scrollbarWidth}px`;
  }

  ensureBackdrop();
}

/**
 * @param {HTMLElement} modalElement
 */
function hideModal(modalElement) {
  modalElement.classList.remove('show');
  modalElement.style.display = 'none';
  modalElement.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  document.body.style.paddingRight = '';
  document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
}

/**
 * @param {HTMLElement} modalElement
 */
function resetDemoModal(modalElement) {
  const iframe = modalElement.querySelector('[data-demo-modal-iframe]');
  if (iframe instanceof HTMLIFrameElement) {
    iframe.src = 'about:blank';
  }
  modalElement.dataset.demoUrl = '';
  const openTab = modalElement.querySelector('[data-demo-modal-open-tab]');
  if (openTab instanceof HTMLAnchorElement) {
    openTab.href = '#';
    openTab.setAttribute('aria-disabled', 'true');
    openTab.classList.add('disabled');
    openTab.tabIndex = -1;
  }
}

/**
 * @param {HTMLElement} modalElement
 */
function closeDemoModal(modalElement) {
  hideModal(modalElement);
  resetDemoModal(modalElement);
}

/**
 * @param {string} url
 * @param {string} title
 */
function openDemoModal(url, title) {
  const modal = document.getElementById(MODAL_ID);
  if (!(modal instanceof HTMLElement)) {
    return;
  }

  const iframe = modal.querySelector('[data-demo-modal-iframe]');
  const titleEl = modal.querySelector('[data-demo-modal-title]');
  const openTab = modal.querySelector('[data-demo-modal-open-tab]');
  const safeUrl = String(url || '').trim();
  if (!safeUrl) {
    return;
  }

  modal.dataset.demoUrl = safeUrl;

  if (titleEl) {
    titleEl.textContent = String(title || '').trim() || 'Demo';
  }
  if (iframe instanceof HTMLIFrameElement) {
    iframe.src = safeUrl;
  }
  if (openTab instanceof HTMLAnchorElement) {
    openTab.href = safeUrl;
    openTab.removeAttribute('aria-disabled');
    openTab.classList.remove('disabled');
    openTab.tabIndex = 0;
  }

  showModal(modal);
}

/**
 * @param {HTMLElement} modalElement
 */
function bindDemoModal(modalElement) {
  if (modalElement.dataset.demoModalBound === '1') {
    return;
  }
  modalElement.dataset.demoModalBound = '1';

  modalElement.querySelectorAll('.t3js-modal-close, [data-bs-dismiss="modal"], [data-demo-modal-cancel]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      closeDemoModal(modalElement);
    });
  });

  modalElement.addEventListener('click', (event) => {
    if (event.target === modalElement) {
      closeDemoModal(modalElement);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modalElement.classList.contains('show')) {
      closeDemoModal(modalElement);
    }
  });
}

document.addEventListener('click', (event) => {
  const trigger = event.target.closest(TRIGGER_SELECTOR);
  if (!(trigger instanceof HTMLElement)) {
    return;
  }

  event.preventDefault();

  const url = String(trigger.dataset.demoUrl || trigger.getAttribute('href') || '').trim();
  const title = String(trigger.dataset.demoTitle || trigger.textContent || '').trim();
  if (!url) {
    return;
  }

  if (isSupademoUrl(url)) {
    openDemoModal(url, title);
    return;
  }

  openDemoInNewTab(url);
});

const demoModal = document.getElementById(MODAL_ID);
if (demoModal instanceof HTMLElement) {
  bindDemoModal(demoModal);
  resetDemoModal(demoModal);
}
