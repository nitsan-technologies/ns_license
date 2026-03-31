import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
import DeferredAction from '@typo3/backend/action-button/deferred-action.js';

// Confirmation modalbox `Version Update` button
document.querySelectorAll('.license-activation .license-activation-latest').forEach((el) => {
  el.addEventListener('click', (e) => {
    e.preventDefault();
    e.currentTarget.classList.add('active');
  });
});

document.querySelector('#activation-modal .activation-modal-update')?.addEventListener('click', (e) => {
  const activeLink = document.querySelector('.license-activation .license-activation-latest.active');
  const url = activeLink?.getAttribute('href');
  if (activeLink) activeLink.classList.remove('active');
  const loader = document.getElementById('nsLicenseLoader');
  if (loader) loader.style.display = '';
  if (url) window.location = url;
});

// Confirmation modalbox `License DeActivation` button
document.querySelectorAll('.license-activation .license-deactivation-latest').forEach((el) => {
  el.addEventListener('click', (e) => {
    e.preventDefault();
    e.currentTarget.classList.add('active');
  });
});

document.querySelector('#deactivation-modal .deactivation-modal-update')?.addEventListener('click', (e) => {
  const activeLink = document.querySelector('.license-activation .license-deactivation-latest.active');
  const url = activeLink?.getAttribute('href');
  if (activeLink) activeLink.classList.remove('active');
  const loader = document.getElementById('nsLicenseLoader');
  if (loader) loader.style.display = '';
  if (url) window.location = url;
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

// Shop/Services Refresh Button - delegated click, single API call for shop and services data
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
