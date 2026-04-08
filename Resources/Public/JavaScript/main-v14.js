import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
import DeferredAction from '@typo3/backend/action-button/deferred-action.js';

// TYPO3 v14-only deactivation confirmation modal
document.addEventListener('click', (e) => {
  const link = e.target.closest('.license-activation .license-deactivation-latest');
  if (!link) return;
  e.preventDefault();

  const targetUrl = link.getAttribute('href');
  if (!targetUrl) return;

  const sourceModal = document.querySelector('#deactivation-modal');
  const title = sourceModal?.querySelector('.t3js-modal-title')?.textContent?.trim() || 'Confirm deactivation';
  const content = sourceModal?.querySelector('.t3js-modal-body p')?.textContent?.trim()
    || 'Are you sure you want to deactivate this license?';
  const confirmText = sourceModal?.querySelector('.deactivation-modal-update span')?.textContent?.trim()
    || TYPO3.lang?.ok || 'OK';
  const cancelText = sourceModal?.querySelector('.cancel-button span')?.textContent?.trim()
    || TYPO3.lang?.cancel || 'Cancel';

  Modal.confirm(title, content, Severity.error, [
    {
      text: cancelText,
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
