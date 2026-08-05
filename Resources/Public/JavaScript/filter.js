/**
 * Module: @nitsan/ns-license/filter
 * Filter and search functionality for catalog and services pages
 */

import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

const loadedDataCache = {
    'ai-universe': false,
    extensions: false,
    templates: false,
    services: false,
};

/**
 * @param {string} tabKey
 */
function loadCatalogData(tabKey) {
    if (loadedDataCache[tabKey]) {
        return;
    }

    const paneId = tabKey === 'extensions' ? 'extensions-catalog-pane' : `${tabKey}-pane`;
    const catalogPane = document.querySelector(`#${paneId}`);
    const loadingPlaceholder = catalogPane?.querySelector('.catalog-loading-placeholder');
    const contentContainer = catalogPane?.querySelector('.catalog-content');

    if (!catalogPane || !loadingPlaceholder || !contentContainer) {
        return;
    }

    const catalogTab = document.querySelector(`.t3js-catalog-tab[data-catalog-tab="${tabKey}"]`);
    const catalogUrl = catalogTab?.getAttribute('data-catalog-url') || catalogPane.getAttribute('data-catalog-url');
    const errorUrlNotFound = catalogPane.getAttribute('data-error-url-not-found') || 'Catalog URL not found.';
    const errorFailed = catalogPane.getAttribute('data-error-failed') || 'Failed to load catalog data. Please try again.';
    const errorLoading = catalogPane.getAttribute('data-error-loading') || 'Error loading catalog data. Please refresh the page.';

    if (!catalogUrl) {
        loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorUrlNotFound + '</p>';
        return;
    }

    loadingPlaceholder.style.display = 'block';
    contentContainer.style.display = 'none';

    new AjaxRequest(catalogUrl)
        .get()
        .then(async function (response) {
            const html = await response.resolve();

            if (html) {
                loadingPlaceholder.style.display = 'none';
                contentContainer.innerHTML = html;
                contentContainer.style.display = 'block';
                loadedDataCache[tabKey] = true;

                setTimeout(() => {
                    bindCatalogFilters(catalogPane);
                    filterCatalog(catalogPane);
                }, 100);
            } else {
                loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorFailed + '</p>';
                Notification.error('Error', errorFailed);
            }
        })
        .catch(function (error) {
            loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorLoading + '</p>';
            Notification.error('Error', errorLoading);
            console.error('Error loading catalog data:', error);
        });
}

/**
 * @param {HTMLElement} pane
 */
function bindCatalogFilters(pane) {
    const searchInput = pane.querySelector('.catalog-search');
    const typeFilter = pane.querySelector('.catalog-filter');
    const sortSelect = pane.querySelector('.catalog-sort');

    if (searchInput && !searchInput.dataset.bound) {
        searchInput.dataset.bound = '1';
        searchInput.addEventListener('input', () => filterCatalog(pane));
        searchInput.addEventListener('keyup', () => filterCatalog(pane));
    }

    if (typeFilter && !typeFilter.dataset.bound) {
        typeFilter.dataset.bound = '1';
        typeFilter.addEventListener('change', () => filterCatalog(pane));
    }

    if (sortSelect && !sortSelect.dataset.bound) {
        sortSelect.dataset.bound = '1';
        sortSelect.addEventListener('change', () => filterCatalog(pane));
    }

    bindCatalogViewToggle(pane);
    bindCatalogCardImageFallbacks(pane);
}

const CATALOG_VIEW_STORAGE_KEY = 'ns-license-catalog-view';

/**
 * @param {HTMLElement} pane
 */
function bindCatalogViewToggle(pane) {
    const tabContent = pane.querySelector('.catalog-tab-content');
    if (!tabContent) {
        return;
    }
    const saved = readStoredCatalogView();
    applyCatalogView(tabContent, saved);
}

/**
 * @returns {'card'|'list'}
 */
function readStoredCatalogView() {
    try {
        const saved = localStorage.getItem(CATALOG_VIEW_STORAGE_KEY) || 'card';
        return saved === 'list' ? 'list' : 'card';
    } catch (err) {
        return 'card';
    }
}

/**
 * @param {HTMLElement} tabContent
 * @param {'card'|'list'} view
 */
function applyCatalogView(tabContent, view) {
    if (!(tabContent instanceof HTMLElement)) {
        return;
    }
    const next = view === 'list' ? 'list' : 'card';
    tabContent.setAttribute('data-catalog-view', next);
    tabContent.classList.toggle('catalog-view--list', next === 'list');
    tabContent.classList.toggle('catalog-view--card', next === 'card');

    tabContent.querySelectorAll('.catalog-view-toggle__btn').forEach((btn) => {
        const active = btn.getAttribute('data-catalog-view') === next;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

/**
 * @param {EventTarget|null} target
 * @returns {Element|null}
 */
function asElement(target) {
    if (target instanceof Element) {
        return target;
    }
    if (target && typeof target === 'object' && 'parentElement' in target) {
        return /** @type {{ parentElement: Element|null }} */ (target).parentElement;
    }
    return null;
}

// Delegated handler so view toggle works after AJAX catalog inject / icon clicks.
document.addEventListener('click', (e) => {
    const el = asElement(e.target);
    const btn = el?.closest?.('.catalog-view-toggle__btn');
    if (!btn) {
        return;
    }
    const tabContent = btn.closest('.catalog-tab-content');
    if (!tabContent) {
        return;
    }
    e.preventDefault();
    const view = btn.getAttribute('data-catalog-view') === 'list' ? 'list' : 'card';
    applyCatalogView(tabContent, view);
    try {
        localStorage.setItem(CATALOG_VIEW_STORAGE_KEY, view);
    } catch (err) {
        // ignore quota / private mode
    }
});

/**
 * Ensure SVG data-URIs used as &lt;img src&gt; include xmlns (required by browsers).
 * API listImage SVGs sometimes omit it, which makes the image fail and fall back to placeholder.
 *
 * @param {string} src
 * @returns {string}
 */
function normalizeSvgDataUri(src) {
    const value = String(src || '').trim();
    if (!value.startsWith('data:image/svg+xml')) {
        return value;
    }

    const comma = value.indexOf(',');
    if (comma < 0) {
        return value;
    }

    const meta = value.slice(0, comma);
    const payload = value.slice(comma + 1);
    let svg = '';
    try {
        svg = meta.includes(';base64')
            ? atob(payload)
            : decodeURIComponent(payload);
    } catch (e) {
        return value;
    }

    if (!svg || /xmlns\s*=/.test(svg)) {
        return value;
    }

    const withNs = svg.replace(
        /<svg(\s|>)/i,
        '<svg xmlns="http://www.w3.org/2000/svg"$1'
    );
    if (withNs === svg) {
        return value;
    }

    return `data:image/svg+xml;utf8,${encodeURIComponent(withNs)}`;
}

/**
 * Swap broken catalog card images to the extension placeholder icon.
 * Also normalizes SVG data-URIs that are missing xmlns.
 *
 * @param {ParentNode|null} root
 */
function bindCatalogCardImageFallbacks(root) {
    if (!root) {
        return;
    }

    root.querySelectorAll('img.t3js-catalog-card-image').forEach((img) => {
        if (img.dataset.fallbackBound === '1') {
            return;
        }
        img.dataset.fallbackBound = '1';

        const original = img.getAttribute('src') || '';
        const normalized = normalizeSvgDataUri(original);
        const didNormalize = !!(normalized && normalized !== original);
        if (didNormalize) {
            img.setAttribute('src', normalized);
        }

        const applyFallback = () => {
            // One more normalize attempt (in case src was set after bind).
            const currentAttr = img.getAttribute('src') || '';
            const retry = normalizeSvgDataUri(currentAttr);
            if (retry && retry !== currentAttr && !img.dataset.svgNsRetried) {
                img.dataset.svgNsRetried = '1';
                img.src = retry;
                return;
            }

            const fallback = img.dataset.fallback || '';
            if (!fallback) {
                return;
            }
            const current = img.currentSrc || img.src || '';
            if (current === fallback || img.getAttribute('src') === fallback) {
                img.classList.add('is-placeholder');
                return;
            }
            img.classList.add('is-placeholder');
            img.src = fallback;
        };

        img.addEventListener('error', applyFallback);
        // Only force-fallback immediately when we did not just rewrite the src
        // (a rewrite starts a new load; wait for error/load instead).
        if (!didNormalize && img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
            applyFallback();
        }
    });
}

/**
 * @param {HTMLElement|null} pane
 */
function filterCatalog(pane) {
    const activePane = pane || document.querySelector('.tab-pane.active');
    if (!activePane || !activePane.id.includes('pane')) {
        return;
    }

    const searchInput = activePane.querySelector('.catalog-search');
    const typeFilter = activePane.querySelector('.catalog-filter');
    if (!searchInput || !typeFilter) {
        return;
    }

    const filterValue = typeFilter.value || 'all';
    const sortValue = activePane.querySelector('.catalog-sort')?.value || 'popular';
    const searchText = searchInput.value.toLowerCase().trim();
    const cards = activePane.querySelectorAll('.catalog-card');
    let visibleCount = 0;
    const sectionVisibility = {};

    cards.forEach((card) => {
        const cardName = (card.dataset.name || '').toLowerCase();
        const cardDescription = (card.dataset.description || '').toLowerCase();
        const extensionKey = (card.dataset.extensionKey || '').toLowerCase();
        const isFree = card.dataset.isFree === '1';

        let typeMatch = true;
        if (filterValue === 'premium') {
            typeMatch = !isFree;
        } else if (filterValue === 'free') {
            typeMatch = isFree;
        }

        let searchMatch = true;
        if (searchText) {
            searchMatch = cardName.includes(searchText)
                || cardDescription.includes(searchText)
                || extensionKey.includes(searchText);
        }

        if (typeMatch && searchMatch) {
            card.style.display = '';
            visibleCount++;
            const sectionElement = card.closest('.catalog-section');
            if (sectionElement) {
                const sectionId = sectionElement.getAttribute('data-catalog-section') || sectionElement.id || 'section';
                sectionVisibility[sectionId] = (sectionVisibility[sectionId] || 0) + 1;
            }
        } else {
            card.style.display = 'none';
        }
    });

    activePane.querySelectorAll('.catalog-section').forEach((section) => {
        const sectionId = section.getAttribute('data-catalog-section') || section.id || 'section';
        const count = sectionVisibility[sectionId] || 0;
        section.style.display = count > 0 ? '' : 'none';
        const countEl = section.querySelector('.catalog-section-count');
        if (countEl) {
            countEl.textContent = String(count);
        }
    });

    sortCatalogCards(activePane, sortValue);

    let noResultsMessage = activePane.querySelector('.no-catalog-results');
    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-catalog-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-variant">No products found matching your criteria.</p>';
            activePane.appendChild(noResultsMessage);
        }
    } else if (noResultsMessage) {
        noResultsMessage.remove();
    }
}

/**
 * @param {string|undefined|null} value
 * @returns {number}
 */
function parseCatalogDownloads(value) {
    const raw = String(value ?? '').trim().toLowerCase().replace(/,/g, '');
    if (raw === '') {
        return 0;
    }
    const match = raw.match(/^([\d.]+)\s*([kmb])?$/i);
    if (!match) {
        const digits = parseFloat(raw.replace(/[^\d.]/g, ''));
        return Number.isFinite(digits) ? digits : 0;
    }
    const num = parseFloat(match[1]);
    if (!Number.isFinite(num)) {
        return 0;
    }
    const unit = (match[2] || '').toLowerCase();
    if (unit === 'k') {
        return num * 1000;
    }
    if (unit === 'm') {
        return num * 1000000;
    }
    if (unit === 'b') {
        return num * 1000000000;
    }
    return num;
}

/**
 * @param {string|undefined|null} value
 * @returns {number}
 */
function parseCatalogRating(value) {
    const num = parseFloat(String(value ?? '').replace(',', '.').replace(/[^\d.]/g, ''));
    return Number.isFinite(num) ? num : 0;
}

/**
 * @param {string|undefined|null} value
 * @returns {number}
 */
function parseCatalogPrice(value) {
    const raw = String(value ?? '').trim();
    if (raw === '' || /^free$/i.test(raw)) {
        return 0;
    }
    // Strip currency letters/symbols (incl. euro) and spaces; keep digits and separators.
    let cleaned = raw.replace(/[^\d.,]/g, '');
    if (cleaned === '') {
        return 0;
    }
    if (cleaned.includes(',') && cleaned.includes('.')) {
        if (cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')) {
            cleaned = cleaned.replace(/\./g, '').replace(',', '.');
        } else {
            cleaned = cleaned.replace(/,/g, '');
        }
    } else if (cleaned.includes(',')) {
        cleaned = cleaned.replace(',', '.');
    }
    const num = parseFloat(cleaned);
    return Number.isFinite(num) ? num : 0;
}

/**
 * @param {HTMLElement} card
 * @returns {number}
 */
function getCardDownloads(card) {
    const fromValue = card.dataset.downloadsValue;
    if (fromValue !== undefined && fromValue !== '') {
        const num = parseFloat(fromValue);
        if (Number.isFinite(num)) {
            return num;
        }
    }
    return parseCatalogDownloads(card.dataset.downloads);
}

/**
 * @param {HTMLElement} card
 * @returns {number}
 */
function getCardRating(card) {
    const fromValue = card.dataset.ratingValue;
    if (fromValue !== undefined && fromValue !== '') {
        const num = parseFloat(fromValue);
        if (Number.isFinite(num)) {
            return num;
        }
    }
    return parseCatalogRating(card.dataset.rating);
}

/**
 * @param {HTMLElement} card
 * @returns {number}
 */
function getCardPrice(card) {
    const fromValue = card.dataset.priceValue;
    if (fromValue !== undefined && fromValue !== '') {
        const num = parseFloat(fromValue);
        if (Number.isFinite(num)) {
            return num;
        }
    }
    const fromAttr = parseCatalogPrice(card.dataset.price);
    if (fromAttr > 0) {
        return fromAttr;
    }
    const priceEl = card.querySelector('.catalog-card-price');
    return parseCatalogPrice(priceEl?.textContent || '');
}

/**
 * Reorder cards within each section container by the selected sort mode.
 *
 * @param {HTMLElement} pane
 * @param {string} sortValue
 */
function sortCatalogCards(pane, sortValue) {
    pane.querySelectorAll('.catalog-card-container').forEach((container) => {
        let cards = [];
        try {
            cards = Array.from(container.querySelectorAll(':scope > .catalog-card'));
        } catch (err) {
            cards = Array.from(container.querySelectorAll('.catalog-card'));
        }

        if (cards.length < 2) {
            return;
        }

        cards.sort((a, b) => {
            let cmp = 0;
            if (sortValue === 'rated') {
                cmp = getCardRating(b) - getCardRating(a);
            } else if (sortValue === 'price-asc') {
                cmp = getCardPrice(a) - getCardPrice(b);
            } else if (sortValue === 'price-desc') {
                cmp = getCardPrice(b) - getCardPrice(a);
            } else {
                // popular (default): downloads descending
                cmp = getCardDownloads(b) - getCardDownloads(a);
            }
            if (cmp !== 0) {
                return cmp;
            }
            const nameA = (a.dataset.name || '').toLowerCase();
            const nameB = (b.dataset.name || '').toLowerCase();
            return nameA.localeCompare(nameB);
        });

        const fragment = document.createDocumentFragment();
        cards.forEach((card) => fragment.appendChild(card));
        container.appendChild(fragment);
    });
}

function loadServicesData() {
    if (loadedDataCache.services) {
        return;
    }

    const servicesPane = document.querySelector('#services-pane');
    const loadingPlaceholder = servicesPane?.querySelector('.services-loading-placeholder');
    const contentContainer = servicesPane?.querySelector('.services-content');

    if (!servicesPane || !loadingPlaceholder || !contentContainer) {
        return;
    }

    const servicesTab = document.querySelector('#services-tab');
    const servicesUrl = servicesTab?.getAttribute('data-services-url') || servicesPane.getAttribute('data-services-url');
    const errorUrlNotFound = servicesPane.getAttribute('data-error-url-not-found') || 'Services URL not found.';
    const errorFailed = servicesPane.getAttribute('data-error-failed') || 'Failed to load services data. Please try again.';
    const errorLoading = servicesPane.getAttribute('data-error-loading') || 'Error loading services data. Please refresh the page.';

    if (!servicesUrl) {
        loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorUrlNotFound + '</p>';
        return;
    }

    loadingPlaceholder.style.display = 'block';
    contentContainer.style.display = 'none';

    new AjaxRequest(servicesUrl)
        .get()
        .then(async function (response) {
            const html = await response.resolve();

            if (html) {
                loadingPlaceholder.style.display = 'none';
                contentContainer.innerHTML = html;
                contentContainer.style.display = 'block';
                loadedDataCache.services = true;

                setTimeout(() => {
                    filterServices();
                    const servicesCategoryFilter = document.querySelector('#servicesCategoryFilter');
                    const servicesSearch = document.querySelector('#servicesSearch');

                    if (servicesCategoryFilter) {
                        servicesCategoryFilter.addEventListener('change', filterServices);
                    }

                    if (servicesSearch) {
                        servicesSearch.addEventListener('input', filterServices);
                        servicesSearch.addEventListener('keyup', filterServices);
                    }
                }, 100);
            } else {
                loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorFailed + '</p>';
                Notification.error('Error', errorFailed);
            }
        })
        .catch(function (error) {
            loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorLoading + '</p>';
            Notification.error('Error', errorLoading);
            console.error('Error loading services data:', error);
        });
}

function filterServices() {
    const servicesPane = document.querySelector('#services-pane');

    if (!servicesPane || !servicesPane.classList.contains('active')) {
        return;
    }

    const categoryFilter = document.querySelector('#servicesCategoryFilter');
    const searchInput = document.querySelector('#servicesSearch');

    if (!categoryFilter || !searchInput) {
        return;
    }

    const categoryFilterValue = categoryFilter.value || 'all';
    const searchText = searchInput.value.toLowerCase().trim();
    const serviceCards = document.querySelectorAll('.service-card-wrapper');
    let visibleCount = 0;
    const categoryVisibility = {};

    serviceCards.forEach((card) => {
        const cardCategory = card.dataset.category || '';
        const cardName = card.dataset.name || '';
        const cardDescription = card.dataset.description || '';
        const categoryMatch = (categoryFilterValue === 'all' || categoryFilterValue === cardCategory);

        let searchMatch = true;
        if (searchText) {
            searchMatch = cardName.toLowerCase().includes(searchText)
                || cardDescription.toLowerCase().includes(searchText);
        }

        if (categoryMatch && searchMatch) {
            card.style.display = '';
            const col = card.closest('[class*="col-"]');
            if (col) {
                col.style.display = '';
            }
            visibleCount++;
            if (!categoryVisibility[cardCategory]) {
                categoryVisibility[cardCategory] = 0;
            }
            categoryVisibility[cardCategory]++;
        } else {
            card.style.display = 'none';
            const col = card.closest('[class*="col-"]');
            if (col) {
                col.style.display = 'none';
            }
        }
    });

    const categorySections = document.querySelectorAll('.service-category-section');
    categorySections.forEach((section) => {
        const sectionTitleElement = section.querySelector('.extension-section__header-title, .extension-section-header .card-title, .card-header .card-title');
        if (sectionTitleElement) {
            const sectionTitle = sectionTitleElement.textContent.trim();
            const hasVisibleItems = categoryVisibility[sectionTitle] > 0;
            section.style.display = hasVisibleItems ? '' : 'none';
        }
    });

    const extensionWrapper = document.querySelector('.extension-section-wrapper');
    let noResultsMessage = document.querySelector('.no-services-results');

    if (visibleCount === 0) {
        if (!noResultsMessage && extensionWrapper) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-services-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-variant">No services found matching your criteria.</p>';
            extensionWrapper.appendChild(noResultsMessage);
        }
    } else if (noResultsMessage) {
        noResultsMessage.remove();
    }
}

function filterExtensions() {
    const extensionsPane = document.querySelector('#extensions-pane');

    if (!extensionsPane || !extensionsPane.classList.contains('active')) {
        return;
    }

    const statusFilter = document.querySelector('#extFilter');
    const searchInput = document.querySelector('#extSearch');

    if (!statusFilter || !searchInput) {
        return;
    }

    const statusFilterValue = statusFilter.value || 'all';
    const searchText = searchInput.value.toLowerCase().trim();
    const noResultsText = extensionsPane.getAttribute('data-no-results-message') || 'No extensions found matching your criteria.';
    const extensionCards = extensionsPane.querySelectorAll('.extension-card-wrapper');
    let visibleCount = 0;
    const sectionVisibility = {};

    extensionCards.forEach((card) => {
        const cardName = card.dataset.name || '';
        const cardKey = card.dataset.key || '';
        const descriptionElement = card.querySelector('.card-body .card-text, .card-body p');
        const cardDescription = descriptionElement ? descriptionElement.textContent.toLowerCase() : '';
        const keySpan = card.querySelector('.card-subtitle');
        const extensionKey = keySpan ? keySpan.textContent.toLowerCase() : '';
        const sectionElement = card.closest('#premium-section, #free-section');
        const sectionId = sectionElement ? sectionElement.getAttribute('id') : '';
        let statusMatch = statusFilterValue === 'all';
        if (statusFilterValue === 'premium') {
            statusMatch = sectionId === 'premium-section';
        } else if (statusFilterValue === 'free') {
            statusMatch = sectionId === 'free-section';
        }

        let searchMatch = true;
        if (searchText) {
            searchMatch = cardName.toLowerCase().includes(searchText)
                || cardDescription.includes(searchText)
                || extensionKey.includes(searchText)
                || cardKey.toLowerCase().includes(searchText);
        }

        if (statusMatch && searchMatch) {
            card.style.display = '';
            visibleCount++;
            if (sectionId) {
                if (!sectionVisibility[sectionId]) {
                    sectionVisibility[sectionId] = 0;
                }
                sectionVisibility[sectionId]++;
            }
        } else {
            card.style.display = 'none';
        }
    });

    const premiumSection = extensionsPane.querySelector('#premium-section');
    const freeSection = extensionsPane.querySelector('#free-section');

    if (premiumSection) {
        premiumSection.style.display = sectionVisibility['premium-section'] > 0 ? '' : 'none';
    }

    if (freeSection) {
        freeSection.style.display = sectionVisibility['free-section'] > 0 ? '' : 'none';
    }

    let noResultsMessage = extensionsPane.querySelector('.no-extensions-results');

    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-extensions-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-variant">' + noResultsText + '</p>';
            extensionsPane.appendChild(noResultsMessage);
        }
    } else if (noResultsMessage) {
        noResultsMessage.remove();
    }
}

function initializeFilters() {
    const moduleTabsContainer = document.querySelector('.ns-license-nav-tabs');
    const moduleTabContent = document.querySelector('#license-tab-content');

    const clearMainTabState = () => {
        moduleTabsContainer?.querySelectorAll('.nav-link').forEach((tab) => {
            tab.classList.remove('active', 'is-active');
            tab.setAttribute('aria-selected', 'false');
        });
        moduleTabContent?.querySelectorAll('.tab-pane').forEach((pane) => {
            pane.classList.remove('show', 'active');
        });
    };

    const activateMainTab = (tab, pane) => {
        if (!tab || !pane) {
            return;
        }
        clearMainTabState();
        tab.classList.add('active', 'is-active');
        tab.setAttribute('aria-selected', 'true');
        pane.classList.add('show', 'active');
        updateTabPageHeader(tab);
    };

    const updateTabPageHeader = (tab) => {
        if (!tab) {
            return;
        }
        const title = (tab.getAttribute('data-page-title') || tab.textContent || '').trim();
        const subtitle = (tab.getAttribute('data-page-subtitle') || '').trim();
        const sectionEl = document.querySelector('.ns-license-tab-page-header__section');
        const subtitleEl = document.querySelector('.ns-license-tab-page-header__subtitle');
        if (sectionEl && title) {
            sectionEl.textContent = title;
        }
        if (subtitleEl && subtitle) {
            subtitleEl.textContent = subtitle;
        }
    };

    // Keep header in sync for Bootstrap tab events and our custom clicks.
    moduleTabsContainer?.addEventListener('shown.bs.tab', (event) => {
        const tab = event.target?.closest?.('.nav-link');
        if (tab && tab.hasAttribute('data-page-title')) {
            updateTabPageHeader(tab);
        }
    });

    const servicesCategoryFilter = document.querySelector('#servicesCategoryFilter');
    const servicesSearch = document.querySelector('#servicesSearch');

    if (servicesCategoryFilter) {
        servicesCategoryFilter.addEventListener('change', filterServices);
    }

    if (servicesSearch) {
        servicesSearch.addEventListener('input', filterServices);
        servicesSearch.addEventListener('keyup', filterServices);
    }

    const extensionsStatusFilter = document.querySelector('#extFilter');
    const extensionsSearch = document.querySelector('#extSearch');

    if (extensionsStatusFilter) {
        extensionsStatusFilter.addEventListener('change', filterExtensions);
    }

    if (extensionsSearch) {
        extensionsSearch.addEventListener('input', filterExtensions);
        extensionsSearch.addEventListener('keyup', filterExtensions);
    }

    document.querySelectorAll('.t3js-catalog-tab').forEach((catalogTab) => {
        catalogTab.addEventListener('click', function (e) {
            e.preventDefault();
            const tabKey = catalogTab.getAttribute('data-catalog-tab') || 'ai-universe';
            const paneId = tabKey === 'extensions' ? 'extensions-catalog-pane' : `${tabKey}-pane`;
            loadCatalogData(tabKey);
            activateMainTab(catalogTab, document.querySelector(`#${paneId}`));
        });
    });

    const servicesTab = document.querySelector('.t3js-services-tab, #services-tab');
    const extensionsTab = document.querySelector('#my-extensions-tab');

    if (servicesTab) {
        servicesTab.addEventListener('click', function (e) {
            e.preventDefault();
            loadServicesData();
            activateMainTab(servicesTab, document.querySelector('#services-pane'));
        });
    }

    if (extensionsTab) {
        extensionsTab.addEventListener('click', function (e) {
            e.preventDefault();
            activateMainTab(extensionsTab, document.querySelector('#extensions-pane'));
            setTimeout(() => {
                filterExtensions();
            }, 100);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFilters);
} else {
    initializeFilters();
}
