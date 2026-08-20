/**
 * Module: @nitsan/ns-license/all-licenses
 * All Licenses tab — email OTP verification + portfolio table + read-only domains modal.
 */

import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';

const DEFAULT_TABLE_COLSPAN = 8;
/** Bump when table columns change (must match data-js-table-version on .ns-all-licenses). */
const ALL_LICENSES_JS_TABLE_VERSION = 7;

/**
 * @param {*} result
 * @returns {Promise<object>}
 */
async function resolveJson(result) {
  try {
    if (result && typeof result.resolve === 'function') {
      const data = await result.resolve();
      if (typeof data === 'object' && data !== null) {
        return data;
      }
      if (typeof data === 'string') {
        return JSON.parse(data);
      }
    }
  } catch (e) {
    // fall through
  }
  if (result && typeof result.json === 'function') {
    return result.json();
  }
  if (typeof result === 'object' && result !== null) {
    return result;
  }
  return {};
}

/**
 * @param {*} error
 * @returns {Promise<object>}
 */
async function resolveErrorJson(error) {
  try {
    if (error && typeof error.resolve === 'function') {
      return await error.resolve();
    }
    if (error && error.response && typeof error.response.json === 'function') {
      return await error.response.json();
    }
  } catch (e) {
    // ignore
  }
  return {
    success: false,
    message: error?.message || 'Request failed.',
  };
}

/**
 * @param {string} csv
 * @returns {string[]}
 */
function splitDomains(csv) {
  if (!csv || typeof csv !== 'string') {
    return [];
  }
  return csv
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean);
}

/**
 * @param {HTMLElement} root
 * @returns {object}
 */
function readLabels(root) {
  return {
    empty: root.getAttribute('data-labels-empty') || 'No licenses found for this email.',
    emptyFiltered: root.getAttribute('data-labels-empty-filtered') || 'No licenses match your filters.',
    domainsEmpty: root.getAttribute('data-labels-domains-empty') || 'No domains associated with this license.',
    lifetime: root.getAttribute('data-labels-lifetime') || 'Lifetime',
    statusActive: root.getAttribute('data-labels-status-active') || 'Active',
    statusExpired: root.getAttribute('data-labels-status-expired') || 'Expired',
    statusInactive: root.getAttribute('data-labels-status-inactive') || 'Inactive',
    statusComplete: root.getAttribute('data-labels-status-complete') || 'Complete',
    statusExisting: root.getAttribute('data-labels-status-existing') || 'Existing',
    statusPending: root.getAttribute('data-labels-status-pending') || 'Pending',
    statusOtpVerified: root.getAttribute('data-labels-status-otp-verified') || 'OTP verified',
    statusTrialStarted: root.getAttribute('data-labels-status-trial-started') || 'Trial started',
    envProduction: root.getAttribute('data-labels-env-production') || 'Production',
    envStaging: root.getAttribute('data-labels-env-staging') || 'Staging',
    envLocal: root.getAttribute('data-labels-env-local') || 'Local',
    otpSentTo: root.getAttribute('data-labels-otp-instructions')
      || root.getAttribute('data-labels-otp-sent-to')
      || 'Please enter the 6-digit verification code sent to %s. Check your inbox and spam folder. If you experience any issues, please contact T3Planet Support.',
    otpInvalid: root.getAttribute('data-labels-otp-invalid') || 'Please enter the 6-digit code.',
    viewDomains: root.getAttribute('data-labels-view-domains') || 'View domains',
    licenseKey: root.getAttribute('data-labels-license-key') || 'License key',
    copy: root.getAttribute('data-labels-copy') || 'Copy',
    copied: root.getAttribute('data-labels-copied') || 'Copied',
    renew: root.getAttribute('data-labels-renew') || 'Renew',
    cancellationButton: root.getAttribute('data-labels-cancellation-button') || 'Cancellation',
    summary: root.getAttribute('data-labels-summary') || '%1$s total · %2$s active',
    summaryExpiring: root.getAttribute('data-labels-summary-expiring') || ' · %1$s expiring soon',
    badgeTotal: root.getAttribute('data-labels-badge-total') || '%s total',
    badgeActive: root.getAttribute('data-labels-badge-active') || '%s active',
    badgeExpiring: root.getAttribute('data-labels-badge-expiring') || '%s expiring soon',
  };
}

/**
 * @param {string} status
 * @param {object} labels
 * @returns {{label: string, badge: string}}
 */
function statusMeta(status, labels) {
  const key = String(status || '').toLowerCase();
  if (key === 'complete') {
    return { label: labels.statusComplete, badge: 'success' };
  }
  if (key === 'existing') {
    return { label: labels.statusExisting, badge: 'default' };
  }
  if (key === 'pending') {
    return { label: labels.statusPending, badge: 'default' };
  }
  if (key === 'otp_verified') {
    return { label: labels.statusOtpVerified, badge: 'warning' };
  }
  if (key === 'trial_started') {
    return { label: labels.statusTrialStarted, badge: 'info' };
  }
  if (key === 'expired') {
    return { label: labels.statusExpired, badge: 'danger' };
  }
  if (key === 'inactive') {
    return { label: labels.statusInactive, badge: 'default' };
  }
  if (key === 'active') {
    return { label: labels.statusActive, badge: 'success' };
  }
  const raw = String(status || '').trim();
  return { label: raw || labels.statusInactive, badge: 'default' };
}

/**
 * @param {string} template
 * @param {...(string|number)} values
 */
function formatTemplate(template, ...values) {
  let out = template;
  values.forEach((value, index) => {
    out = out.replace('%' + (index + 1) + '$s', String(value));
    out = out.replace('%s', String(value));
  });
  return out;
}

/**
 * @param {HTMLElement} root
 * @param {boolean} show
 * @param {string} message
 */
function setError(root, show, message) {
  const el = root.querySelector('[data-all-licenses-error]');
  if (!el) {
    return;
  }
  if (show && message) {
    el.textContent = message;
    el.classList.remove('d-none');
  } else {
    el.textContent = '';
    el.classList.add('d-none');
  }
}

/**
 * @param {HTMLElement} button
 * @param {boolean} loading
 * @param {string} labelSelector
 * @param {string} loadingSelector
 */
function setButtonLoading(button, loading, labelSelector, loadingSelector) {
  if (!button) {
    return;
  }
  button.disabled = loading;
  const label = button.querySelector(labelSelector);
  const loadingLabel = button.querySelector(loadingSelector);
  if (label) {
    label.classList.toggle('d-none', loading);
  }
  if (loadingLabel) {
    loadingLabel.classList.toggle('d-none', !loading);
  }
}

/**
 * @param {boolean} show
 */
function setModuleLoader(show) {
  const loader = document.getElementById('nsLicenseLoader');
  if (!loader) {
    return;
  }
  loader.style.display = show ? 'block' : 'none';
  loader.style.zIndex = show ? '11000' : '';
  loader.setAttribute('aria-hidden', show ? 'false' : 'true');
}

/**
 * @param {HTMLElement|null} modal
 * @returns {HTMLInputElement[]}
 */
function getOtpDigits(modal) {
  if (!modal) {
    return [];
  }
  return Array.from(modal.querySelectorAll('[data-all-licenses-otp-digit]'));
}

/**
 * @param {HTMLElement|null} modal
 * @returns {string}
 */
function getOtpValue(modal) {
  return getOtpDigits(modal)
    .map((el) => String(el.value || '').replace(/\D/g, '').slice(-1))
    .join('');
}

/**
 * @param {HTMLElement|null} modal
 */
function clearOtpBoxes(modal) {
  getOtpDigits(modal).forEach((el) => {
    el.value = '';
  });
}

/**
 * @param {HTMLElement|null} modal
 * @param {boolean} disabled
 */
function setOtpBoxesDisabled(modal, disabled) {
  getOtpDigits(modal).forEach((el) => {
    el.disabled = disabled;
  });
}

/**
 * @param {HTMLElement|null} modal
 * @param {boolean} show
 * @param {string} message
 */
function setOtpModalError(modal, show, message) {
  const el = modal?.querySelector('[data-all-licenses-otp-error]');
  if (!el) {
    return;
  }
  if (show && message) {
    el.textContent = message;
    el.classList.remove('d-none');
  } else {
    el.textContent = '';
    el.classList.add('d-none');
  }
}

/**
 * @param {HTMLElement} modal
 * @param {string} template
 * @param {string} email
 */
function fillOtpHint(modal, template, email) {
  const hint = modal.querySelector('[data-all-licenses-otp-hint]');
  if (!hint) {
    return;
  }
  hint.innerHTML = formatTemplate(template, `<strong>${escapeHtml(email)}</strong>`);
}

/**
 * Auto-advance / paste only. Submit is Verify click or Enter.
 *
 * @param {HTMLElement} modal
 * @param {(code: string) => void} onSubmit
 */
function bindOtpBoxes(modal, onSubmit) {
  if (!modal || modal.dataset.otpBoxesBound === '1') {
    return;
  }
  modal.dataset.otpBoxesBound = '1';
  const digits = getOtpDigits(modal);

  digits.forEach((input, index) => {
    input.addEventListener('input', () => {
      const raw = String(input.value || '').replace(/\D/g, '');
      if (raw.length > 1) {
        const chars = raw.slice(0, digits.length - index).split('');
        chars.forEach((ch, offset) => {
          if (digits[index + offset]) {
            digits[index + offset].value = ch;
          }
        });
        digits[Math.min(index + chars.length, digits.length - 1)]?.focus();
      } else {
        input.value = raw.slice(-1);
        if (input.value && digits[index + 1]) {
          digits[index + 1].focus();
        }
      }
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        onSubmit(getOtpValue(modal));
        return;
      }
      if (event.key === 'Backspace' && !input.value && digits[index - 1]) {
        event.preventDefault();
        digits[index - 1].value = '';
        digits[index - 1].focus();
      }
      if (event.key === 'ArrowLeft' && digits[index - 1]) {
        event.preventDefault();
        digits[index - 1].focus();
      }
      if (event.key === 'ArrowRight' && digits[index + 1]) {
        event.preventDefault();
        digits[index + 1].focus();
      }
    });

    input.addEventListener('paste', (event) => {
      const text = String(event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!text) {
        return;
      }
      event.preventDefault();
      text.split('').forEach((ch, offset) => {
        if (digits[offset]) {
          digits[offset].value = ch;
        }
      });
      digits[Math.min(text.length, digits.length) - 1]?.focus();
    });
  });
}

/**
 * @param {string} value
 */
function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * @param {string} value
 */
function escapeAttr(value) {
  return escapeHtml(value);
}

/**
 * @param {string} text
 * @returns {Promise<boolean>}
 */
async function copyToClipboard(text) {
  try {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch (e) {
    // fall through
  }
  return false;
}

const COPY_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 2h5a1 1 0 0 1 1 1v1h1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h1V3a1 1 0 0 1 1-1m0 3H6v8h7V5h-1v1H6zm1-2v1h4V3z"/></svg>';

/**
 * Truncated value with a copy button.
 *
 * @param {string} value
 * @param {object} labels
 * @param {string} modifier
 * @returns {string}
 */
function copyableValue(value, labels, modifier) {
  const text = String(value || '').trim();
  if (!text) {
    return '<span class="text-variant">—</span>';
  }
  const extra = modifier ? ` ns-all-licenses-copycell--${modifier}` : '';
  return `<span class="ns-all-licenses-copycell${extra}">
    <code class="ns-all-licenses-clip" title="${escapeAttr(text)}">${escapeHtml(text)}</code>
    <button type="button" class="btn btn-default btn-sm ns-all-licenses-copy" data-all-licenses-copy="${escapeAttr(text)}" title="${escapeAttr(labels.copy)}" aria-label="${escapeAttr(labels.copy)}">${COPY_ICON_SVG}</button>
  </span>`;
}

/**
 * @param {object} labels
 * @param {MouseEvent} event
 * @returns {boolean}
 */
function handleCopyClick(labels, event) {
  const copyBtn = event.target.closest('[data-all-licenses-copy]');
  if (!copyBtn) {
    return false;
  }
  event.preventDefault();
  const text = copyBtn.getAttribute('data-all-licenses-copy') || '';
  if (!text) {
    return true;
  }
  copyToClipboard(text).then((ok) => {
    if (ok) {
      Notification.success(labels.copied, labels.copy);
    } else {
      Notification.error('Copy failed', 'Could not copy to clipboard');
    }
  });
  return true;
}

/**
 * @param {{label: string, domains: string[]}} section
 * @param {object} labels
 * @returns {string}
 */
function renderDomainSection(section, labels) {
  if (!section.domains.length) {
    return '';
  }
  const items = section.domains.map((domain) => `
    <div class="domains-list__item">
      <div class="domains-list__item-content">
        <div class="domains-list__item-content-text">
          <p class="mb-0 text-break ns-all-licenses-domains-domain">${escapeHtml(domain)}</p>
        </div>
      </div>
      <div class="domains-list__item-actions">
        <button type="button" class="btn btn-default btn-sm ns-all-licenses-copy" data-all-licenses-copy="${escapeAttr(domain)}" title="${escapeAttr(labels.copy)}" aria-label="${escapeAttr(labels.copy)}">${COPY_ICON_SVG}</button>
      </div>
    </div>`).join('');
  return `
    <div class="mb-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <h3 class="h6 mb-0 ns-all-licenses-domains-section__title">${escapeHtml(section.label)}</h3>
        <span class="badge badge-default">${section.domains.length}</span>
      </div>
      <div class="domains-list ns-all-licenses-domains-list">${items}</div>
    </div>`;
}

/**
 * @param {string} value
 */
function csvEscape(value) {
  const text = String(value ?? '');
  if (/[",\n\r]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }
  return text;
}

/**
 * First non-empty production domain from a CSV string.
 *
 * @param {string} csv
 * @returns {string}
 */
function firstProductionDomain(csv) {
  return splitDomains(csv)[0] || '';
}

/**
 * @param {string} licenseType
 * @returns {string}
 */
function formatLicenseTypeLabel(licenseType) {
  const value = String(licenseType || '').trim();
  if (!value) {
    return '—';
  }
  if (value.toUpperCase() === 'X') {
    return '∞';
  }
  return value;
}

/**
 * Fill missing fields from legacy API/cache payloads.
 *
 * @param {object} license
 * @returns {object}
 */
function normalizeLicense(license) {
  const row = { ...license };
  if (!String(row.composerUsername || '').trim()) {
    row.composerUsername = String(row.user_name || '').trim();
  }
  if (!String(row.primaryDomain || '').trim()) {
    row.primaryDomain = firstProductionDomain(row.domains || '');
  }
  const maxLabel = String(row.domainsMaxLabel || '').trim();
  if (!maxLabel || maxLabel === '—') {
    row.domainsMaxLabel = formatLicenseTypeLabel(row.license_type || '');
  }
  return row;
}

/**
 * @param {Array} licenses
 * @returns {Array}
 */
function normalizeLicenses(licenses) {
  if (!Array.isArray(licenses)) {
    return [];
  }
  return licenses.map((license) => normalizeLicense(license));
}

/**
 * @param {object} license
 * @returns {string}
 */
function licenseSearchBlob(license) {
  return [
    license.title,
    license.extensionKey,
    license.licenseKey,
    license.projectName,
    license.composerUsername,
    license.latestVersion,
    license.installedVersion,
    license.primaryDomain,
    license.domainsMaxLabel,
    license.domains,
    license.localDomains,
    license.stagingDomains,
    license.domainsSearch,
  ]
    .map((part) => String(part || '').toLowerCase())
    .join(' ');
}

/**
 * @param {Array} licenses
 * @param {string} query
 * @param {string} status
 * @returns {Array}
 */
function filterLicenses(licenses, query, status) {
  const q = String(query || '').trim().toLowerCase();
  const statusKey = String(status || '').trim().toLowerCase();
  const allowed = statusKey
    ? statusKey.split(',').map((part) => part.trim()).filter(Boolean)
    : [];
  return licenses.filter((license) => {
    if (allowed.length) {
      const licenseStatus = String(license.status || '').toLowerCase();
      if (!allowed.includes(licenseStatus)) {
        return false;
      }
    }
    if (!q) {
      return true;
    }
    return licenseSearchBlob(license).includes(q);
  });
}

/**
 * @param {object} license
 * @param {object} labels
 * @returns {string}
 */
function renderLicenseRow(license, labels) {
  const title = license.title || license.extensionKey || '';
  const extensionKey = license.extensionKey || '';
  const licenseKey = license.licenseKey || '';
  const composerUsername = license.composerUsername || '';
  const latestVersion = license.latestVersion || '—';
  const expiry = license.validUntilFormatted || (license.isLifeTime ? labels.lifetime : '—');
  const meta = statusMeta(license.status, labels);
  const domainsUsed = license.domainsUsed ?? 0;
  const primaryDomain = license.primaryDomain || '';

  const composerCell = copyableValue(composerUsername, labels, 'user');
  const domainCell = copyableValue(primaryDomain, labels, 'domain');
  const licenseKeyCell = copyableValue(licenseKey, labels, 'key');

  const viewDomainsBtn = `<button type="button"
                class="btn btn-default btn-sm t3js-all-licenses-domains"
                data-license-key="${escapeAttr(licenseKey)}"
                data-title="${escapeAttr(title)}"
                data-extension-key="${escapeAttr(extensionKey)}"
                data-domains="${escapeAttr(license.domains || '')}"
                data-local-domains="${escapeAttr(license.localDomains || '')}"
                data-staging-domains="${escapeAttr(license.stagingDomains || '')}"
                data-domains-used="${escapeAttr(String(domainsUsed))}">
          ${escapeHtml(labels.viewDomains)} (${domainsUsed})
        </button>`;

  const status = String(license.status || '');
  const renewBtn = (status === 'expired' || status === 'inactive')
    ? `<button type="button"
                class="btn btn-default btn-sm js-license-renew-trigger"
                data-days="${escapeAttr(String(license.expirationDays ?? ''))}"
                data-expiration-date="${escapeAttr(String(license.expirationDate ?? ''))}">
          ${escapeHtml(labels.renew)}
        </button>`
    : '';

  const cancelBtn = status === 'complete'
    ? `<button type="button"
                class="btn btn-default btn-sm js-license-cancellation-trigger">
          ${escapeHtml(labels.cancellationButton)}
        </button>`
    : '';

  return `
    <tr>
      <td>
        <div class="fw-semibold">${escapeHtml(title)}</div>
        <div class="small text-variant">${escapeHtml(extensionKey)}</div>
      </td>
      <td>${composerCell}</td>
      <td>${licenseKeyCell}</td>
      <td>${escapeHtml(latestVersion)}</td>
      <td>${escapeHtml(expiry)}</td>
      <td><span class="badge badge-${meta.badge}">${escapeHtml(meta.label)}</span></td>
      <td>${domainCell}</td>
      <td>
        <div class="d-flex flex-nowrap gap-1">
          ${renewBtn}
          ${cancelBtn}
          ${viewDomainsBtn}
        </div>
      </td>
    </tr>`;
}

/**
 * Bootstrap `.fade` + a results block that starts `display:none` can leave
 * the active pane at opacity 0. Keep panes on `.active` only (TYPO3 list style).
 *
 * @param {HTMLElement} root
 */
function ensureStatusPanesVisible(root) {
  const panes = root.querySelectorAll('[data-all-licenses-pane]');
  if (!panes.length) {
    return;
  }
  panes.forEach((pane) => {
    pane.classList.remove('fade', 'show');
  });
  let activePane = root.querySelector('[data-all-licenses-pane].active') || panes[0];
  panes.forEach((pane) => {
    pane.classList.toggle('active', pane === activePane);
  });
  const activeId = activePane.id;
  root.querySelectorAll('[data-all-licenses-status-tab]').forEach((tab) => {
    const target = (tab.getAttribute('data-bs-target') || '').replace(/^#/, '');
    const isActive = target === activeId;
    tab.classList.toggle('active', isActive);
    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });
}

/**
 * @param {HTMLElement} root
 * @param {object} labels
 * @param {Array} licenses
 * @param {object} summary
 * @param {string} email
 * @param {{query?: string, status?: string, tableColspan?: number}} options
 */
function renderResults(root, labels, licenses, summary, email, options = {}) {
  const results = root.querySelector('[data-all-licenses-results]');
  const otp = root.querySelector('[data-all-licenses-otp]');
  const tbody = root.querySelector('[data-all-licenses-tbody]');
  const emailEl = root.querySelector('[data-all-licenses-verified-email]');
  const summaryEl = root.querySelector('[data-all-licenses-summary]');
  const tableColspan = options.tableColspan ?? DEFAULT_TABLE_COLSPAN;
  const query = options.query ?? (root.querySelector('[data-all-licenses-search]')?.value || '');
  const panes = root.querySelectorAll('[data-all-licenses-pane]');
  const activeStatus = options.status ?? getActiveStatusFilter(root);

  if (otp) {
    otp.style.display = 'none';
  }
  if (results) {
    results.style.display = '';
  }
  ensureStatusPanesVisible(root);
  if (emailEl) {
    emailEl.textContent = email || '';
  }
  if (summaryEl) {
    const total = summary?.total ?? licenses.length;
    const active = summary?.active ?? 0;
    const expiringSoon = Number(summary?.expiringSoon ?? 0);
    const badges = [
      `<span class="badge badge-default">${escapeHtml(formatTemplate(labels.badgeTotal, total))}</span>`,
      `<span class="badge badge-success">${escapeHtml(formatTemplate(labels.badgeActive, active))}</span>`,
    ];
    if (expiringSoon > 0) {
      badges.push(`<span class="badge badge-warning">${escapeHtml(formatTemplate(labels.badgeExpiring, expiringSoon))}</span>`);
    }
    summaryEl.innerHTML = badges.join('');
  }

  if (panes.length) {
    panes.forEach((pane) => {
      const paneStatus = pane.getAttribute('data-all-licenses-status') || '';
      const paneTbody = pane.querySelector('[data-all-licenses-tbody]');
      fillLicenseTbody(paneTbody, licenses, query, paneStatus, labels, tableColspan);
    });
    return filterLicenses(licenses, query, activeStatus);
  }

  if (!tbody) {
    return filterLicenses(licenses, query, activeStatus);
  }

  fillLicenseTbody(tbody, licenses, query, activeStatus, labels, tableColspan);
  return filterLicenses(licenses, query, activeStatus);
}

/**
 * @param {HTMLElement} root
 * @returns {string}
 */
function getActiveStatusFilter(root) {
  const select = root.querySelector('[data-all-licenses-status]');
  if (select) {
    return String(select.value || '');
  }
  const activeTab = root.querySelector('[data-all-licenses-status-tab].active');
  return activeTab?.getAttribute('data-all-licenses-status-tab') || '';
}

/**
 * @param {HTMLElement|null} tbody
 * @param {Array} licenses
 * @param {string} query
 * @param {string} status
 * @param {object} labels
 * @param {number} tableColspan
 */
function fillLicenseTbody(tbody, licenses, query, status, labels, tableColspan) {
  if (!tbody) {
    return;
  }
  const filtered = filterLicenses(licenses, query, status);
  if (!licenses.length) {
    tbody.innerHTML = `<tr data-all-licenses-empty-row><td colspan="${tableColspan}" class="text-variant">${escapeHtml(labels.empty)}</td></tr>`;
    return;
  }
  if (!filtered.length) {
    tbody.innerHTML = `<tr data-all-licenses-empty-row><td colspan="${tableColspan}" class="text-variant">${escapeHtml(labels.emptyFiltered)}</td></tr>`;
    return;
  }
  tbody.innerHTML = filtered.map((license) => renderLicenseRow(license, labels)).join('');
}

/**
 * @param {Array} licenses
 * @param {object} labels
 */
function exportLicensesCsv(licenses, labels) {
  const headers = [
    'Product',
    'Extension key',
    'Composer username',
    'License key',
    'Latest',
    'Installed',
    'Expiry',
    'Status',
    'Production domain',
    'Domains used',
    'Domains',
    'Local domains',
    'Staging domains',
  ];
  const rows = licenses.map((license) => {
    const meta = statusMeta(license.status, labels);
    const expiry = license.validUntilFormatted || (license.isLifeTime ? labels.lifetime : '');
    return [
      license.title || '',
      license.extensionKey || '',
      license.composerUsername || '',
      license.licenseKey || '',
      license.latestVersion || '',
      license.installedVersion || '',
      expiry,
      meta.label,
      license.primaryDomain || '',
      license.domainsUsed ?? 0,
      license.domains || '',
      license.localDomains || '',
      license.stagingDomains || '',
    ].map(csvEscape).join(',');
  });
  const csv = `\uFEFF${[headers.join(','), ...rows].join('\n')}`;
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const stamp = new Date().toISOString().slice(0, 10);
  link.href = url;
  link.download = `all-licenses-${stamp}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

/**
 * @param {HTMLElement} button
 * @param {object} labels
 */
function openDomainsModal(button, labels) {
  const modal = document.getElementById('all-licenses-domains-modal');
  if (!modal) {
    return;
  }

  const title = button.getAttribute('data-title') || button.getAttribute('data-extension-key') || '';
  const licenseKey = button.getAttribute('data-license-key') || '';
  const production = splitDomains(button.getAttribute('data-domains') || '');
  const staging = splitDomains(button.getAttribute('data-staging-domains') || '');
  const local = splitDomains(button.getAttribute('data-local-domains') || '');

  const titleEl = modal.querySelector('[data-all-licenses-domains-title]');
  const keyEl = modal.querySelector('[data-all-licenses-domains-key]');
  const bodyEl = modal.querySelector('[data-all-licenses-domains-body]');

  if (titleEl) {
    titleEl.textContent = title;
  }
  if (keyEl) {
    keyEl.innerHTML = copyableValue(licenseKey, labels, 'key');
  }

  const sections = [
    { label: labels.envProduction, domains: production },
    { label: labels.envStaging, domains: staging },
    { label: labels.envLocal, domains: local },
  ];

  const hasAny = sections.some((section) => section.domains.length > 0);
  if (!hasAny) {
    bodyEl.innerHTML = `<p class="text-variant mb-0">${escapeHtml(labels.domainsEmpty)}</p>`;
  } else {
    bodyEl.innerHTML = sections.map((section) => renderDomainSection(section, labels)).join('');
  }

  showBootstrapModal(modal);
}

/**
 * @param {HTMLElement} modal
 */
function closeBootstrapModal(modal) {
  try {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const instance = bootstrap.Modal.getInstance(modal);
      if (instance) {
        instance.hide();
        return;
      }
    }
    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
      const instance = window.bootstrap.Modal.getInstance(modal);
      if (instance) {
        instance.hide();
        return;
      }
    }
  } catch (e) {
    // fall through
  }
  modal.classList.remove('show');
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
}

/**
 * @param {HTMLElement} modal
 */
function showBootstrapModal(modal) {
  // Wire dismiss controls once (same pattern as domains.js).
  if (!modal.dataset.allLicensesCloseBound) {
    modal.dataset.allLicensesCloseBound = '1';
    modal.querySelectorAll('.t3js-modal-close, [data-bs-dismiss="modal"]').forEach((btn) => {
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        closeBootstrapModal(modal);
      });
    });
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeBootstrapModal(modal);
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('show')) {
        closeBootstrapModal(modal);
      }
    });
  }

  try {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modal).show();
      return;
    }
    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
      return;
    }
  } catch (e) {
    // fall through
  }
  modal.classList.add('show');
  modal.style.display = 'block';
  modal.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';
  if (!document.querySelector('.modal-backdrop')) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(backdrop);
  }
}

/**
 * @param {HTMLElement} root
 */
function showOtpForm(root) {
  const results = root.querySelector('[data-all-licenses-results]');
  const otp = root.querySelector('[data-all-licenses-otp]');
  if (results) {
    results.style.display = 'none';
  }
  if (otp) {
    otp.style.display = '';
  }
  setError(root, false, '');
  const otpModal = document.getElementById('all-licenses-otp-modal');
  if (otpModal) {
    clearOtpBoxes(otpModal);
    setOtpModalError(otpModal, false, '');
    closeBootstrapModal(otpModal);
  }
}

/**
 * @param {HTMLElement} root
 * @returns {{licenses: Array, summary: object}}
 */
function readInitialPayload(root) {
  let licenses = [];
  let summary = { total: 0, active: 0, expiringSoon: 0 };
  const licensesEl = root.querySelector('[data-all-licenses-initial]');
  const summaryEl = root.querySelector('[data-all-licenses-initial-summary]');
  try {
    if (licensesEl?.textContent) {
      const parsed = JSON.parse(licensesEl.textContent);
      if (Array.isArray(parsed)) {
        licenses = parsed;
      }
    }
  } catch (e) {
    // ignore bad initial payload
  }
  try {
    if (summaryEl?.textContent) {
      const parsed = JSON.parse(summaryEl.textContent);
      if (parsed && typeof parsed === 'object') {
        summary = parsed;
      }
    }
  } catch (e) {
    // ignore
  }
  return { licenses, summary };
}

/**
 * @param {HTMLElement} root
 */
function initAllLicenses(root) {
  const labels = readLabels(root);
  const tableColspan = Number(root.getAttribute('data-table-colspan') || DEFAULT_TABLE_COLSPAN) || DEFAULT_TABLE_COLSPAN;
  let licensesCache = [];
  let summaryCache = { total: 0, active: 0, expiringSoon: 0 };
  let filteredCache = [];

  const emailInput = root.querySelector('#all-licenses-email');
  const sendBtn = root.querySelector('[data-all-licenses-send-otp]');
  const otpModal = document.getElementById('all-licenses-otp-modal');
  const domainsModal = document.getElementById('all-licenses-domains-modal');
  const resendBtn = otpModal?.querySelector('[data-all-licenses-resend-otp]');
  const verifyBtn = otpModal?.querySelector('[data-all-licenses-verify-otp]');
  const refreshBtn = root.querySelector('[data-all-licenses-refresh]');
  const switchBtn = root.querySelector('[data-all-licenses-switch-email]');
  const searchInput = root.querySelector('[data-all-licenses-search]');
  const statusSelect = root.querySelector('[data-all-licenses-status]');
  const statusTabs = root.querySelectorAll('[data-all-licenses-status-tab]');
  const exportBtn = root.querySelector('[data-all-licenses-export-csv]');
  let verifyingOtp = false;

  function applyFilters() {
    filteredCache = renderResults(
      root,
      labels,
      licensesCache,
      summaryCache,
      root.getAttribute('data-email') || '',
      {
        query: searchInput?.value || '',
        status: getActiveStatusFilter(root),
        tableColspan,
      }
    );
  }

  function activateStatusTab(status) {
    if (statusSelect) {
      statusSelect.value = status;
      return;
    }
    const target = Array.from(statusTabs).find((tab) => (tab.getAttribute('data-all-licenses-status-tab') || '') === status)
      || statusTabs[0];
    if (!target || target.classList.contains('active')) {
      return;
    }
    target.click();
  }

  function setPortfolio(licenses, summary, email) {
    licensesCache = normalizeLicenses(licenses);
    summaryCache = summary || { total: 0, active: 0, expiringSoon: 0 };
    if (email) {
      root.setAttribute('data-email', email);
    }
    applyFilters();
  }

  /**
   * @returns {Promise<object>}
   */
  async function sendOtp() {
    const email = (emailInput?.value || '').trim();
    setError(root, false, '');
    setOtpModalError(otpModal, false, '');
    if (!email) {
      setError(root, true, 'Please enter a valid email address.');
      return;
    }

    const ajaxUrl = TYPO3?.settings?.ajaxUrls?.send_license_email_otp;
    if (!ajaxUrl) {
      setError(root, true, 'AJAX route not found.');
      return;
    }

    setModuleLoader(true);
    setButtonLoading(sendBtn, true, '[data-all-licenses-send-label]', '[data-all-licenses-send-loading]');
    if (resendBtn) {
      resendBtn.disabled = true;
    }
    if (verifyBtn) {
      verifyBtn.disabled = true;
    }

    try {
      const response = await new AjaxRequest(ajaxUrl).post({
        email,
        language: document.documentElement.lang?.startsWith('de') ? 'de' : 'en',
      });
      const data = await resolveJson(response);
      if (!data.success) {
        setError(root, true, data.message || 'Failed to send verification code.');
        Notification.error('Error', data.message || 'Failed to send verification code.');
        return;
      }

      if (otpModal) {
        fillOtpHint(otpModal, labels.otpSentTo, email);
        clearOtpBoxes(otpModal);
        setOtpModalError(otpModal, false, '');
        showBootstrapModal(otpModal);
        window.setTimeout(() => {
          getOtpDigits(otpModal)[0]?.focus();
        }, 50);
      }
    } catch (error) {
      const data = await resolveErrorJson(error);
      setError(root, true, data.message || 'Failed to send verification code.');
      Notification.error('Error', data.message || 'Failed to send verification code.');
    } finally {
      setModuleLoader(false);
      setButtonLoading(sendBtn, false, '[data-all-licenses-send-label]', '[data-all-licenses-send-loading]');
      if (resendBtn) {
        resendBtn.disabled = false;
      }
      if (verifyBtn) {
        verifyBtn.disabled = false;
      }
    }
  }

  /**
   * @returns {Promise<object>}
   */
  async function verifyOtp() {
    if (verifyingOtp) {
      return;
    }
    const email = (emailInput?.value || '').trim();
    const otp = getOtpValue(otpModal);
    setError(root, false, '');
    setOtpModalError(otpModal, false, '');
    if (otp.length !== 6) {
      setOtpModalError(otpModal, true, labels.otpInvalid);
      return;
    }

    const ajaxUrl = TYPO3?.settings?.ajaxUrls?.verify_license_email_otp;
    if (!ajaxUrl) {
      setOtpModalError(otpModal, true, 'AJAX route not found.');
      return;
    }

    verifyingOtp = true;
    setModuleLoader(true);
    setOtpBoxesDisabled(otpModal, true);
    setButtonLoading(verifyBtn, true, '[data-all-licenses-verify-label]', '[data-all-licenses-verify-loading]');
    if (resendBtn) {
      resendBtn.disabled = true;
    }

    try {
      const response = await new AjaxRequest(ajaxUrl).post({ email, otp });
      const data = await resolveJson(response);
      if (!data.success) {
        const message = data.message || 'Verification failed.';
        setOtpModalError(otpModal, true, message);
        Notification.error('Error', message);
        clearOtpBoxes(otpModal);
        getOtpDigits(otpModal)[0]?.focus();
        return;
      }

      root.setAttribute('data-verified', '1');
      if (otpModal) {
        closeBootstrapModal(otpModal);
        clearOtpBoxes(otpModal);
      }
      setPortfolio(data.licenses || [], data.summary || {}, data.email || email);
      Notification.success('OK', data.message || 'Email verified successfully.');
    } catch (error) {
      const data = await resolveErrorJson(error);
      const message = data.message || 'Verification failed.';
      setOtpModalError(otpModal, true, message);
      Notification.error('Error', message);
      clearOtpBoxes(otpModal);
      getOtpDigits(otpModal)[0]?.focus();
    } finally {
      verifyingOtp = false;
      setModuleLoader(false);
      setOtpBoxesDisabled(otpModal, false);
      setButtonLoading(verifyBtn, false, '[data-all-licenses-verify-label]', '[data-all-licenses-verify-loading]');
      if (resendBtn) {
        resendBtn.disabled = false;
      }
    }
  }

  async function refreshLicenses() {
    const ajaxUrl = TYPO3?.settings?.ajaxUrls?.get_licenses_by_email;
    if (!ajaxUrl) {
      Notification.error('Error', 'AJAX route not found.');
      return;
    }

    try {
      const response = await new AjaxRequest(ajaxUrl).post({
        email: root.getAttribute('data-email') || '',
        refresh: 1,
      });
      const data = await resolveJson(response);
      if (!data.success) {
        if (data.error_code === 'email_not_verified') {
          root.setAttribute('data-verified', '0');
          showOtpForm(root);
        }
        Notification.error('Error', data.message || 'Could not refresh licenses.');
        return;
      }
      setPortfolio(data.licenses || [], data.summary || {}, data.email || '');
      const isGerman = document.documentElement.lang?.startsWith('de');
      Notification.success(
        isGerman ? 'Erfolg' : 'Success',
        isGerman ? 'Lizenzen erfolgreich aktualisiert.' : 'Licenses updated successfully.'
      );
    } catch (error) {
      const data = await resolveErrorJson(error);
      if (data.error_code === 'email_not_verified') {
        root.setAttribute('data-verified', '0');
        showOtpForm(root);
      }
      Notification.error('Error', data.message || 'Could not refresh licenses.');
    }
  }

  async function switchEmail() {
    const ajaxUrl = TYPO3?.settings?.ajaxUrls?.clear_all_licenses_session;
    if (ajaxUrl) {
      try {
        await new AjaxRequest(ajaxUrl).post({
          email: root.getAttribute('data-email') || '',
        });
      } catch (e) {
        // still reset UI
      }
    }
    licensesCache = [];
    summaryCache = { total: 0, active: 0, expiringSoon: 0 };
    filteredCache = [];
    if (searchInput) {
      searchInput.value = '';
    }
    activateStatusTab('');
    root.setAttribute('data-verified', '0');
    root.setAttribute('data-email', '');
    showOtpForm(root);
  }

  sendBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    sendOtp();
  });
  resendBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    sendOtp();
  });
  verifyBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    verifyOtp();
  });
  bindOtpBoxes(otpModal, () => {
    verifyOtp();
  });
  refreshBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    refreshLicenses();
  });
  switchBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    switchEmail();
  });
  searchInput?.addEventListener('input', () => {
    applyFilters();
  });
  statusSelect?.addEventListener('change', () => {
    applyFilters();
  });
  statusTabs.forEach((tab) => {
    tab.addEventListener('shown.bs.tab', () => {
      applyFilters();
    });
    tab.addEventListener('click', () => {
      window.setTimeout(applyFilters, 0);
    });
  });
  exportBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    exportLicensesCsv(filteredCache, labels);
  });

  emailInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      sendOtp();
    }
  });

  domainsModal?.addEventListener('click', (event) => {
    handleCopyClick(labels, event);
  });
  root.addEventListener('click', (event) => {
    if (handleCopyClick(labels, event)) {
      return;
    }
    const button = event.target.closest('.t3js-all-licenses-domains');
    if (!button || !root.contains(button)) {
      return;
    }
    event.preventDefault();
    openDomainsModal(button, labels);
  });

  if (root.getAttribute('data-verified') === '1') {
    const initial = readInitialPayload(root);
    licensesCache = normalizeLicenses(initial.licenses);
    summaryCache = initial.summary;
    applyFilters();
  }
}

function bootAllLicenses() {
  const root = document.querySelector('.ns-all-licenses');
  if (root) {
    initAllLicenses(root);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootAllLicenses);
} else {
  bootAllLicenses();
}
