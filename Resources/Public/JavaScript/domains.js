import $ from 'jquery';
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import Modal from '@typo3/backend/modal.js';

/**
 * Build HTML for one domain row (shared by modal open and add-domain success).
 * @param {string} domain
 * @param {string} envType - 'production'|'staging'|'local'
 * @returns {string}
 */
function createDomainHtml(domain, envType) {
    let iconClass = 'domains-list--production-icon';
    let badgeClass = 'badge rounded-pill badge-success';
    if (envType === 'staging') {
        iconClass = 'domains-list--staging-icon';
        badgeClass = 'badge rounded-pill badge-warning';
    } else if (envType === 'local') {
        iconClass = 'domains-list--local-icon';
        badgeClass = 'badge rounded-pill badge-default';
    }
    return '<div class="col-md-6">' +
        '<div class="domains-list__item" data-domain="' + (domain.trim().replace(/"/g, '&quot;')) + '" data-environment="' + envType + '">' +
        '<div class="domains-list__item-content">' +
        '<div class="' + iconClass + '">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path></svg>' +
        '</div>' +
        '<div class="domains-list__item-content-text">' +
        '<p class="mb-0">' + domain.trim() + '</p>' +
        '<div class="d-flex align-items-center gap-2 mt-1">' +
        '<span class="' + badgeClass + ' text-uppercase" style="font-size: 0.6rem;">' + envType + '</span>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="domains-list__item-actions">' +
        '<button type="button" class="btn btn-sm domains-list__item-action-edit">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>' +
        '</button>' +
        '<button class="btn btn-sm domains-list__item-action-delete">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>' +
        '</button>' +
        '</div>' +
        '</div>' +
        '</div>';
}

/**
 * Refresh the domains list and badge in the Domains modal from stored all-domains data.
 * @param {string} modalTarget - e.g. '#domains-modal'
 */
function refreshDomainsList(modalTarget) {
    let allDomainsData = $(modalTarget).data('all-domains') || [];
    let searchText = $(modalTarget + ' #domains-search-input').val();
    searchText = (searchText && searchText.toLowerCase()) || '';
    let environmentFilter = $(modalTarget + ' #domains-environment-filter').val() || 'all';
    let filteredDomains = allDomainsData;
    if (environmentFilter !== 'all') {
        filteredDomains = filteredDomains.filter(function(item) {
            return item.envType === environmentFilter;
        });
    }
    if (searchText) {
        filteredDomains = filteredDomains.filter(function(item) {
            return item.domain.toLowerCase().includes(searchText);
        });
    }
    if (filteredDomains.length > 0) {
        let filteredHtml = '';
        filteredDomains.forEach(function(item) {
            filteredHtml += createDomainHtml(item.domain, item.envType);
        });
        $(modalTarget + ' .domains-list').html(filteredHtml);
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    } else {
        $(modalTarget + ' .domains-list').html('<div class="col-12"><p class="text-center text-gray-500">No domains found</p></div>');
    }
    let domainsTabBadge = $(modalTarget + ' #domains-tab .badge[data-badge]');
    if (domainsTabBadge.length) {
        domainsTabBadge.text(allDomainsData.length);
        domainsTabBadge.attr('data-badge', allDomainsData.length);
    }
}

// Domains Modal - Handle click event on button
$(document).on('click', '.t3js-domains-modal-trigger', function(e) {
    e.preventDefault();
    let button = $(this);
    let extName = button.data('ext-name');
    let extKey = button.data('ext-key');
    let domainsCount = button.data('domains-count');
    let domainsProduction = button.data('domains-production') || '';
    let domainsStaging = button.data('domains-staging') || '';
    let domainsLocal = button.data('domains-local') || '';
    let modalTarget = button.data('bs-target');
    
    // Update modal title
    $(modalTarget + ' .modal-ext-name').text(extName || 'Extension Name');
    
    // Update domains count badge in the domains-tab (line 34 of DomainsModal.html)
    let domainsTabBadge = $(modalTarget + ' #domains-tab .badge[data-badge]');
    if (domainsTabBadge.length) {
        domainsTabBadge.text(domainsCount || '0');
        domainsTabBadge.attr('data-badge', domainsCount || '0');
    }
    
    // Store extension key and license key for potential AJAX calls
    $(modalTarget).data('ext-key', extKey);
    $(modalTarget).data('license-key', button.data('license-key') || '');
    
    // Process and store all domains data
    var allDomainsData = [];
    function processDomains(domainsString, envType) {
        if (domainsString) {
            var domainArray = domainsString.split(' | ');
            domainArray.forEach(function(domain) {
                if (domain.trim()) {
                    allDomainsData.push({domain: domain.trim(), envType: envType});
                }
            });
        }
    }
    processDomains(domainsProduction, 'production');
    processDomains(domainsStaging, 'staging');
    processDomains(domainsLocal, 'local');
    $(modalTarget).data('all-domains', allDomainsData);
    
    // Initial display and wire search/filter to shared refresh
    refreshDomainsList(modalTarget);
    $(modalTarget + ' #domains-search-input').on('input', function() {
        refreshDomainsList(modalTarget);
    });
    $(modalTarget + ' #domains-environment-filter').on('change', function() {
        refreshDomainsList(modalTarget);
    });
    
    // Reset search and filter when modal is hidden
    $(modalTarget).on('hidden.bs.modal', function() {
        $(modalTarget + ' #domains-search-input').val('');
        $(modalTarget + ' #domains-environment-filter').val('all');
    });

    // Helper function to close modal
    function closeModal() {
        const modalElement = document.querySelector(modalTarget);
        if (modalElement) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            } else if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
                const modalInstance = window.bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            } else {
                // Fallback: manually hide modal
                $(modalElement).removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open').css('overflow', '');
                $('.modal-backdrop').remove();
            }
        }
    }
    
    // Handle close button clicks (header and footer)
    $(modalTarget + ' .t3js-modal-close, ' + modalTarget + ' [data-bs-dismiss="modal"]').on('click', function(e) {
        e.preventDefault();
        closeModal();
    });
    
    // Show the modal using Bootstrap 5 native API (TYPO3 standard)
    const modalElement = document.querySelector(modalTarget);
    if (modalElement) {
        // Use Bootstrap's native Modal API (available globally in TYPO3)
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
        } else if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
            // Try window.bootstrap as fallback
            const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
        } else {
            // Fallback: manually show modal using Bootstrap classes and proper structure
            $(modalElement).addClass('show').css({
                'display': 'block',
                'padding-right': '17px'
            });
            $('body').addClass('modal-open').css('overflow', 'hidden');
            
            // Create backdrop if it doesn't exist
            if ($('.modal-backdrop').length === 0) {
                $('body').append('<div class="modal-backdrop fade show"></div>');
            }
            
            // Handle close on backdrop click
            $(modalElement).on('click', function(e) {
                if ($(e.target).hasClass('modal') || $(e.target).hasClass('modal-dialog')) {
                    // Close modal
                    $(modalElement).removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open').css('overflow', '');
                    $('.modal-backdrop').remove();
                }
            });
        }
    }
    
    // Store logs URL and license key for this extension
    let logsUrl = button.data('href') || '';
    let licenseKey = '';
    if (logsUrl) {
        let urlParams = new URLSearchParams(logsUrl.split('?')[1] || '');
        licenseKey = urlParams.get('extension') || '';
        $(modalTarget).data('logs-url', logsUrl);
        $(modalTarget).data('license-key', licenseKey);
    }
    
    // Reset auth logs state when modal opens for a new extension
    let authLogsList = $(modalTarget + ' .auth-logs-list');
    let authTabBadge = $(modalTarget + ' #auth-tab .badge[data-badge]');
    
    // Clear previous logs and reset loaded flag
    authLogsList.html('');
    authLogsList.removeData('loaded');
    authLogsList.removeData('current-license-key');
    
    // Reset badge count
    if (authTabBadge.length) {
        authTabBadge.text('0');
        authTabBadge.attr('data-badge', '0');
    }
    
    // Check if logs are already loaded for this license key
    let currentLicenseKey = authLogsList.data('current-license-key');
    if (authLogsList.data('loaded') && currentLicenseKey === licenseKey) {
        return;
    }
    
    // Get logs URL and license key from modal data
    logsUrl = $(modalTarget).data('logs-url') || '';
    licenseKey = $(modalTarget).data('license-key') || '';
    
    if (!logsUrl || !licenseKey) {
        authLogsList.html('<div class="p-4 text-center text-gray-500">Unable to load logs. Missing URL or license key.</div>');
        return;
    }
    
    authLogsList.html('<div class="p-4 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading auth logs...</p></div>');
    
    new AjaxRequest(logsUrl)
        .post({
            license_key: licenseKey
        })
        .then(async function (response) {
            const html = await response.resolve();
            authLogsList.html(html);
            
            // Update badge count - count the log items
            let logCount = authLogsList.find('.auth-log-item').length;
            if (authTabBadge.length) {
                authTabBadge.text(logCount);
                authTabBadge.attr('data-badge', logCount);
            }
            
            // Mark as loaded and store current license key
            authLogsList.data('loaded', true);
            authLogsList.data('current-license-key', licenseKey);
        })
        .catch(function (error) {
            authLogsList.html('<div class="p-4 text-center text-danger">Error loading auth logs. Please try again.</div>');
            if (authTabBadge.length) {
                authTabBadge.text('0');
                authTabBadge.attr('data-badge', '0');
            }
            console.error('Error loading auth logs:', error);
            Notification.error('Error', 'An error occurred while loading auth logs');
        });
    
});

// Add Domain - Open via TYPO3 Modal.advanced so it appears on top of Domains modal without closing it
$(document).on('click', '.t3js-add-domain-modal-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    let triggerButton = $(this);
    let domainsModal = $('#domains-modal');
    let extKey = domainsModal.data('ext-key') || '';

    let templateEl = document.getElementById('add-domain-modal-template');
    if (!templateEl) return;
    let clone = templateEl.cloneNode(true);
    clone.removeAttribute('id');
    let extKeyInput = clone.querySelector('#add-domain-ext-key');
    if (extKeyInput) extKeyInput.value = extKey;
    // Pass jQuery of DOM nodes so TYPO3 modal renders HTML; string content is escaped and shown as text
    let content = $(clone).contents();

    let modalTitle = triggerButton.attr('data-add-domain-title') || 'New domain';
    let cancelText = triggerButton.attr('data-add-domain-cancel') || 'Cancel';
    let submitText = triggerButton.attr('data-add-domain-submit') || 'Add domain';

    let addDomainModalElement = null;
    let modalConfig = {
        title: modalTitle,
        content: content,
        staticBackdrop: true,
        severity: 0,
        buttons: [
            {
                text: cancelText,
                trigger: function() {
                    Modal.dismiss();
                }
            },
            {
                text: submitText,
                active: true,
                btnClass: 'btn-success',
                trigger: function() {
                    let modal = addDomainModalElement;
                    if (!modal) return;
                    let form = modal.querySelector('.js-add-domain-form');
                    if (!form) return;
                    let domainInput = form.querySelector('#add-domain-input');
                    let environmentSelect = form.querySelector('#add-domain-environment');
                    let domain = domainInput && domainInput.value.trim();
                    let environment = environmentSelect && environmentSelect.value;
                    if (!domain) {
                        Notification.error('Error', 'Please enter a domain name');
                        if (domainInput) domainInput.focus();
                        return;
                    }
                    if (!environment) {
                        Notification.error('Error', 'Please select an environment');
                        if (environmentSelect) environmentSelect.focus();
                        return;
                    }
                    domain = domain.replace(/^https?:\/\//, '').replace(/\/$/, '').trim();
                    let domainValidation = validateDomain(domain);
                    if (!domainValidation.valid) {
                        Notification.error('Invalid domain', domainValidation.message || 'Enter a valid domain (e.g. docs.t3planet.de, typo3-src-12.4.38.ddev.site).');
                        if (domainInput) domainInput.focus();
                        return;
                    }
                    let extKeyVal = form.querySelector('#add-domain-ext-key');
                    extKeyVal = extKeyVal ? extKeyVal.value : '';
                    if (!extKeyVal) {
                        Notification.error('Error', 'Extension key not found');
                        return;
                    }
                    let submitBtn = modal.querySelector('.btn-success');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span>Adding...</span>';
                    }
                    new AjaxRequest(TYPO3.settings.ajaxUrls.add_domain)
                        .post({
                            extension_key: extKeyVal,
                            domain: domain,
                            environment: environment
                        })
                        .then(async function(response) {
                            let responseData = await response.resolve();
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<span>' + submitText + '</span>';
                            }
                            if (responseData.success) {
                                Modal.dismiss();
                                let domainsModal = $('#domains-modal');
                                let allDomains = domainsModal.data('all-domains') || [];
                                allDomains.push({ domain: domain, envType: environment });
                                domainsModal.data('all-domains', allDomains);
                                refreshDomainsList('#domains-modal');
                                Notification.success('Success', responseData.message || 'Domain added successfully');
                            } else {
                                Notification.error('Error', responseData.message || 'Failed to add domain');
                            }
                        })
                        .catch(function(error) {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<span>' + submitText + '</span>';
                            }
                            Notification.error('Error', 'An error occurred while adding the domain');
                            console.error('Error adding domain:', error);
                        });
                }
            }
        ],
        callback: function(modal) {
            addDomainModalElement = modal;
        }
    };
    if (typeof Modal.sizes !== 'undefined' && Modal.sizes.small) {
        modalConfig.size = Modal.sizes.small;
    }
    Modal.advanced(modalConfig);
});

/**
 * Validate domain format. Valid: docs.t3planet.de, typo3-src-12.4.38.ddev.site, localhost.
 * Invalid: 123, 790d (no dot / not a hostname).
 * @param {string} value - Trimmed domain string (without protocol)
 * @returns {{ valid: boolean, message?: string }}
 */
function validateDomain(value) {
    if (!value || typeof value !== 'string') {
        return { valid: false, message: 'Please enter a domain name.' };
    }
    value = value.replace(/^https?:\/\//, '').replace(/\/$/, '').trim();
    if (!value) {
        return { valid: false, message: 'Please enter a domain name.' };
    }
    if (value.toLowerCase() === 'localhost') {
        return { valid: true };
    }
    // Must contain at least one dot (e.g. example.com, docs.t3planet.de)
    if (value.indexOf('.') === -1) {
        return { valid: false, message: 'Enter a valid domain' };
    }
    // Must contain at least one letter (reject e.g. 121.12, 123.456)
    if (!/[a-zA-Z]/.test(value)) {
        return { valid: false, message: 'Enter a valid domain ' };
    }
    // Only letters, digits, hyphens, dots
    if (!/^[a-zA-Z0-9.-]+$/.test(value)) {
        return { valid: false, message: 'Domain can only contain letters, numbers, hyphens and dots.' };
    }
    // No leading/trailing dot or hyphen
    if (/^[.-]|[.-]$/.test(value)) {
        return { valid: false, message: 'Domain cannot start or end with a dot or hyphen.' };
    }
    // Each label (between dots) must be non-empty and valid
    let labels = value.split('.');
    for (let i = 0; i < labels.length; i++) {
        let label = labels[i];
        if (!label.length) {
            return { valid: false, message: 'Invalid domain: empty part.' };
        }
        if (!/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/.test(label)) {
            return { valid: false, message: 'Invalid domain: each part must start and end with a letter or number.' };
        }
    }
    return { valid: true };
}

// Helper: revert a domain row from edit mode back to view mode (domain text + Edit button)
function revertDomainItemToViewMode(item) {
    let domain = item.attr('data-domain') || '';
    let contentText = item.find('.domains-list__item-content-text');
    let input = contentText.find('.domains-list__item-edit-input');
    let saveBtn = item.find('.domains-list__item-action-save');
    if (!input.length || !saveBtn.length) return;
    input.replaceWith('<p class="mb-0">' + (domain.replace(/</g, '&lt;').replace(/"/g, '&quot;')) + '</p>');
    saveBtn.replaceWith(
        '<button type="button" class="btn btn-sm domains-list__item-action-edit">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>' +
        '</button>'
    );
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Domains list - Edit: inline edit (replace domain text with input, replace Edit button with Save)
$(document).on('click', '.domains-list__item-action-edit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    let button = $(e.currentTarget);
    let item = button.closest('.domains-list__item');
    let contentText = item.find('.domains-list__item-content-text');
    let domain = item.attr('data-domain') || item.data('domain') || '';

    if (!domain) return;

    let $p = contentText.find('p.mb-0');
    if ($p.length && !contentText.find('.domains-list__item-edit-input').length) {
        $p.replaceWith('<input type="text" class="form-control form-control-sm domains-list__item-edit-input mb-0" value="' + domain.replace(/"/g, '&quot;') + '" placeholder="example.com">');
        button.replaceWith(
            '<button type="button" class="btn btn-sm btn-success domains-list__item-action-save" title="Save">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>' +
            '</button>'
        );
        item.find('.domains-list__item-edit-input').focus();
    }
});

// Domains list - Click outside: revert any row in edit mode back to view (Edit button)
$(document).on('click', function(e) {
    let clickedItem = $(e.target).closest('.domains-list__item');
    let isInsideEditingRow = clickedItem.length && clickedItem.find('.domains-list__item-edit-input').length;
    if (isInsideEditingRow) return;
    $('#domains-modal .domains-list__item').each(function() {
        let item = $(this);
        if (item.find('.domains-list__item-edit-input').length) {
            revertDomainItemToViewMode(item);
        }
    });
});

// Domains list - Save inline (submit domain change, then revert to view mode)
$(document).on('click', '.domains-list__item-action-save', function(e) {
    e.preventDefault();
    e.stopPropagation();
    let btn = $(e.currentTarget);
    let item = btn.closest('.domains-list__item');
    let input = item.find('.domains-list__item-edit-input');
    let oldDomain = item.attr('data-domain') || '';
    let newDomain = input.val().trim();
    let environment = item.attr('data-environment') || 'production';
    let domainsModal = $('#domains-modal');
    let licenseKey = domainsModal.data('license-key');

    if (!newDomain) {
        Notification.error('Error', 'Please enter a domain name');
        input.focus();
        return;
    }
    if (!licenseKey) {
        Notification.error('Error', 'License key not found');
        return;
    }

    newDomain = newDomain.replace(/^https?:\/\//, '').replace(/\/$/, '').trim();
    let domainValidation = validateDomain(newDomain);
    if (!domainValidation.valid) {
        Notification.error('Invalid domain', domainValidation.message || 'Enter a valid domain');
        input.focus();
        return;
    }

    btn.prop('disabled', true);
    let originalHtml = btn.html();
    btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

    new AjaxRequest(TYPO3.settings.ajaxUrls.update_domain)
        .post({
            license_key: licenseKey,
            old_domain: oldDomain,
            new_domain: newDomain,
            environment: environment
        })
        .then(async function(response) {
            let data = await response.resolve();
            btn.prop('disabled', false);
            btn.html(originalHtml);

            if (data.success) {
                Notification.success('Success', data.message || 'Domain updated.');

                let allDomains = domainsModal.data('all-domains') || [];
                let idx = allDomains.findIndex(function(d) { return d.domain === oldDomain && d.envType === environment; });
                if (idx !== -1) {
                    allDomains[idx] = { domain: newDomain, envType: environment };
                    domainsModal.data('all-domains', allDomains);
                }

                item.attr('data-domain', newDomain);
                input.replaceWith('<p class="mb-0">' + newDomain.replace(/</g, '&lt;') + '</p>');
                btn.replaceWith(
                    '<button type="button" class="btn btn-sm domains-list__item-action-edit">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>' +
                    '</button>'
                );
            } else {
                Notification.error('Error', data.message || 'Failed to update domain');
                revertDomainItemToViewMode(item);
            }
        })
        .catch(function(err) {
            btn.prop('disabled', false);
            btn.html(originalHtml);
            Notification.error('Error', 'An error occurred while updating the domain');
            console.error('Error updating domain:', err);
            revertDomainItemToViewMode(item);
        });
});

// Domains list - Delete domain
$(document).on('click', '.domains-list__item-action-delete', function(e) {
    e.preventDefault();
    let button = $(e.currentTarget);
    let item = button.closest('.domains-list__item');
    let domain = item.data('domain') || item.attr('data-domain') || '';
    let environment = item.data('environment') || item.attr('data-environment') || 'production';
    let modal = $('#domains-modal');
    let licenseKey = modal.data('license-key');

    if (!domain || !licenseKey) {
        Notification.error('Error', 'Missing domain or license key');
        return;
    }

    let deleteMessage = 'Are you sure you want to remove the domain "' + domain + '" from this license?';
    let modalConfig = {
        title: 'Delete domain',
        content: deleteMessage,
        severity: 2,
        staticBackdrop: true,
        buttons: [
            {
                text: 'Cancel',
                trigger: function() {
                    Modal.dismiss();
                }
            },
            {
                text: 'Yes',
                active: true,
                btnClass: 'btn-danger',
                trigger: function() {
                    new AjaxRequest(TYPO3.settings.ajaxUrls.delete_domain)
                        .post({
                            license_key: licenseKey,
                            domain: domain,
                            environment: environment
                        })
                        .then(async function(response) {
                            let responseData = await response.resolve();
                            Modal.dismiss();
                            if (responseData.success) {
                                Notification.success('Success', responseData.message || 'Domain removed.');
                                let allDomains = modal.data('all-domains') || [];
                                allDomains = allDomains.filter(function(d) {
                                    return d.domain !== domain || d.envType !== environment;
                                });
                                modal.data('all-domains', allDomains);
                                item.closest('.col-md-6').remove();
                                let badge = modal.find('#domains-tab .badge[data-badge]');
                                if (badge.length) {
                                    badge.text(allDomains.length);
                                    badge.attr('data-badge', allDomains.length);
                                }
                            } else {
                                Notification.error('Error', responseData.message || 'Failed to remove domain');
                            }
                        })
                        .catch(function(error) {
                            Modal.dismiss();
                            Notification.error('Error', 'An error occurred while removing the domain');
                            console.error('Error deleting domain:', error);
                        });
                }
            }
        ]
    };
    if (typeof Modal.sizes !== 'undefined' && Modal.sizes.small) {
        modalConfig.size = Modal.sizes.small;
    }
    Modal.advanced(modalConfig);
});

