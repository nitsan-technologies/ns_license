import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
import DeferredAction from '@typo3/backend/action-button/deferred-action.js';

// Confirmation modalbox `Version Update` button
document.addEventListener('click', (e) => {
  const link = e.target.closest('.license-activation-latest');
  if (!link) return;
  e.preventDefault();

  const targetUrl = link.getAttribute('href');
  if (!targetUrl) return;
  const title = link.dataset.title || 'Attention Please!';
  const content = link.dataset.content || 'Are you sure that you want to overwrite the existing TYPO3 extension?';
  const confirmText = link.dataset.confirmText || 'Update Now';

  Modal.confirm(title, content, Severity.warning, [
    {
      text: TYPO3.lang?.cancel || 'Cancel',
      trigger() {
        Modal.dismiss();
      },
    },
    {
      text: confirmText,
      btnClass: 'btn-warning',
      active: true,
      action: new DeferredAction(() => {
        const loader = document.getElementById('nsLicenseLoader');
        if (loader) loader.style.display = '';
        window.location.href = targetUrl;
        return Promise.resolve();
      }),
    },
  ]);
});

// Confirmation modalbox `License DeActivation` button
document.addEventListener('click', (e) => {
  const link = e.target.closest('.license-deactivation-latest');
  if (!link) return;
  e.preventDefault();

  const targetUrl = link.getAttribute('href');
  if (!targetUrl) return;
  const title = link.dataset.title || 'Caution!';
  const content = link.dataset.content || 'Do you want to deactivate the license key from this domain? The TYPO3 extension will not more work on this domain.';
  const confirmText = link.dataset.confirmText || 'Deactivate Now!';

  Modal.confirm(title, content, Severity.error, [
    {
      text: TYPO3.lang?.cancel || 'Cancel',
      trigger() {
        Modal.dismiss();
      },
    },
    {
      text: confirmText,
      btnClass: 'btn-danger',
      active: true,
      action: new DeferredAction(() => {
        const loader = document.getElementById('nsLicenseLoader');
        if (loader) loader.style.display = '';
        window.location.href = targetUrl;
        return Promise.resolve();
      }),
    },
  ]);
});

// If Cancel button from Modalbox
document.querySelectorAll('.modal .cancel-button, .modal .t3js-modal-close').forEach((el) => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.license-activation a.active').forEach((a) => a.classList.remove('active'));
  });
});

// Submit to register license key
document.querySelector('.ns-license-form')?.addEventListener('submit', () => {
  const loader = document.getElementById('nsLicenseLoader');
  if (loader) loader.style.display = '';
});

// Reactivation link: show loader on click (before navigation)
document.addEventListener('click', (e) => {
  const link = e.target.closest('a.license-reactivation-latest');
  if (!link || !link.getAttribute('href')) return;
  const loader = document.getElementById('nsLicenseLoader');
  if (loader) loader.style.display = '';
});

// Help widget dropdown (custom, no Bootstrap dropdown behavior)
(() => {
  const widget = document.querySelector('.ns-license-help-widget');
  const trigger = widget?.querySelector('[data-help-widget-trigger]');
  const popover = widget?.querySelector('[data-help-widget-popover]');
  if (!widget || !trigger || !popover) return;

  const openWidget = () => {
    widget.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');
  };

  const closeWidget = () => {
    widget.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  };

  const toggleWidget = () => {
    if (widget.classList.contains('is-open')) {
      closeWidget();
    } else {
      openWidget();
    }
  };

  trigger.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    toggleWidget();
  });

  document.addEventListener('click', (event) => {
    if (!widget.contains(event.target)) {
      closeWidget();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeWidget();
    }
  });
})();


/**
 * Copy text to clipboard (fallback for older browsers).
 * @param {string} text
 * @returns {Promise<void>}
 */
function copyToClipboard(text) {
  if (!text) return Promise.reject(new Error('No text'));
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }
  return new Promise((resolve, reject) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      resolve();
    } catch (err) {
      reject(err);
    }
    document.body.removeChild(ta);
  });
}

// Copy license key (delegated)
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('.t3js-copy-license-key');
  if (!trigger) return;
  e.preventDefault();
  const text = trigger.dataset.licenseKey || trigger.getAttribute('data-license-key');
  if (!text) return;
  copyToClipboard(text)
    .then(() => Notification.success('Copied', 'License key copied to clipboard'))
    .catch(() => Notification.error('Copy failed', 'Could not copy to clipboard'));
});

// Catalog / sync refresh button — delegated click
document.addEventListener('click', (e) => {
  const button = e.target.closest('.refresh-data-button[data-type]');
  if (!button) return;
  e.preventDefault();

  const type = button.dataset.type || button.getAttribute('data-type');
  const buttons = document.querySelectorAll('.refresh-data-button[data-type]');
  const originalHtml = button.innerHTML;

  buttons.forEach((b) => { b.disabled = true; });
  button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...';

  const restoreButtons = () => {
    buttons.forEach((b) => {
      b.disabled = false;
      b.innerHTML = originalHtml;
    });
  };

  new AjaxRequest(TYPO3.settings.ajaxUrls.fetch_data)
    .post({ type })
    .then(async (response) => {
      const responseData = await response.resolve();
      restoreButtons();

      if (responseData.success) {
        Notification.success('Success', responseData.message || 'Data updated successfully');
        setTimeout(() => window.location.reload(), 1000);
      } else {
        if (responseData.error_code === 'no_license_keys') {
          Notification.warning('', responseData.message || 'Failed to fetch data from API');
        } else {
          Notification.error('Error', responseData.message || 'Failed to fetch data from API');
        }
      }
    })
    .catch((error) => {
      restoreButtons();
      Notification.error('Error', 'An error occurred while fetching data');
      console.error('Error updating data:', error);
    });
});

// Trial extension: use TYPO3 modal API with DeferredAction to show spinner on OK.
document.addEventListener('click', (e) => {
  const button = e.target.closest('.js-trial-extend-trigger');
  if (!button) return;

  e.preventDefault();
  const title = button.dataset.title || 'Extend trial';
  const content = button.dataset.content || 'Do you want to extend this trial?';
  const targetUrl = button.dataset.href;
  if (!targetUrl) return;

  Modal.confirm(title, content, Severity.info, [
    {
      text: TYPO3.lang?.cancel || 'Cancel',
      trigger() {
        Modal.dismiss();
      },
    },
    {
      text: TYPO3.lang?.ok || 'OK',
      btnClass: 'btn-info',
      active: true,
      action: new DeferredAction(() => {
        const loader = document.getElementById('nsLicenseLoader');
        if (loader) loader.style.display = '';
        window.location.href = targetUrl;
        return Promise.resolve();
      }),
    },
  ]);
});

/**
 * Show an in-page Bootstrap modal (TYPO3 v14 does not bind data-bs-toggle).
 * @param {HTMLElement} modalElement
 * @param {HTMLElement|null} [relatedTarget]
 */
function showInlineBootstrapModal(modalElement, relatedTarget) {
  try {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalElement).show(relatedTarget);
      return;
    }
    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalElement).show(relatedTarget);
      return;
    }
  } catch (e) {
    // fall through
  }
  modalElement.classList.add('show');
  modalElement.style.display = 'block';
  modalElement.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';
  if (!document.querySelector('.modal-backdrop')) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(backdrop);
  }
}

/**
 * @param {HTMLElement} modal
 * @param {HTMLElement} button
 */
function fillRenewModal(modal, button) {
  let days = Number.parseInt(button.dataset.days ?? '', 10);
  const expirationTs = Number.parseInt(button.dataset.expirationDate ?? '', 10);
  const statusBadge = modal.querySelector('.js-renew-status-badge');
  const expiryEl = modal.querySelector('.js-renew-expiry-date');

  if (!Number.isFinite(days) && Number.isFinite(expirationTs) && expirationTs > 0) {
    days = Math.floor((expirationTs - Math.floor(Date.now() / 1000)) / 86400);
  }

  let statusKey = 'active';
  let badgeClass = 'badge rounded-pill badge-success js-renew-status-badge';
  if (!Number.isFinite(days) || days <= 0) {
    statusKey = 'expired';
    badgeClass = 'badge rounded-pill badge-danger js-renew-status-badge';
  } else if (days <= 30) {
    statusKey = 'expiring';
    badgeClass = 'badge rounded-pill badge-warning js-renew-status-badge';
  }

  const statusLabels = {
    active: modal.dataset.labelStatusActive || 'Active',
    expiring: modal.dataset.labelStatusExpiring || 'Expiring Soon',
    expired: modal.dataset.labelStatusExpired || 'Expired',
  };

  if (statusBadge) {
    statusBadge.className = badgeClass;
    statusBadge.textContent = statusLabels[statusKey] || statusLabels.active;
  }

  if (expiryEl) {
    if (Number.isFinite(expirationTs) && expirationTs > 0) {
      const date = new Date(expirationTs * 1000);
      const dd = String(date.getDate()).padStart(2, '0');
      const mm = String(date.getMonth() + 1).padStart(2, '0');
      const yyyy = date.getFullYear();
      expiryEl.textContent = `${dd}.${mm}.${yyyy}`;
    } else {
      expiryEl.textContent = '—';
    }
  }
}

// Renew / Cancellation: open in-page modals in JS (same as View domains).
document.addEventListener('click', (e) => {
  const renewBtn = e.target.closest('.js-license-renew-trigger');
  if (renewBtn) {
    e.preventDefault();
    const modal = document.getElementById('renew-license-modal');
    if (!(modal instanceof HTMLElement)) {
      return;
    }
    fillRenewModal(modal, renewBtn);
    showInlineBootstrapModal(modal, renewBtn);
    return;
  }

  const cancelBtn = e.target.closest('.js-license-cancellation-trigger');
  if (!cancelBtn) {
    return;
  }
  e.preventDefault();
  const modal = document.getElementById('cancellation-license-modal');
  if (modal instanceof HTMLElement) {
    showInlineBootstrapModal(modal, cancelBtn);
  }
});

// Keep filling Renew if anything else opens it via Bootstrap's show() + relatedTarget.
document.addEventListener('show.bs.modal', (e) => {
  const modal = e.target;
  if (!(modal instanceof HTMLElement) || modal.id !== 'renew-license-modal') {
    return;
  }

  const button = e.relatedTarget;
  if (!(button instanceof HTMLElement) || !button.classList.contains('js-license-renew-trigger')) {
    return;
  }

  fillRenewModal(modal, button);
});
