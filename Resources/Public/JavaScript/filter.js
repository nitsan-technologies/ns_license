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

    if (searchInput && !searchInput.dataset.bound) {
        searchInput.dataset.bound = '1';
        searchInput.addEventListener('input', () => filterCatalog(pane));
        searchInput.addEventListener('keyup', () => filterCatalog(pane));
    }

    if (typeFilter && !typeFilter.dataset.bound) {
        typeFilter.dataset.bound = '1';
        typeFilter.addEventListener('change', () => filterCatalog(pane));
    }

    bindCatalogCardImageFallbacks(pane);
}

/**
 * Swap broken catalog card images to the extension placeholder icon.
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

        const applyFallback = () => {
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
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
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
        const cardStatus = card.dataset.status || '';
        const cardName = card.dataset.name || '';
        const cardKey = card.dataset.key || '';
        const descriptionElement = card.querySelector('.card-body .card-text, .card-body p');
        const cardDescription = descriptionElement ? descriptionElement.textContent.toLowerCase() : '';
        const keySpan = card.querySelector('.card-subtitle');
        const extensionKey = keySpan ? keySpan.textContent.toLowerCase() : '';
        const statusMatch = (statusFilterValue === 'all' || statusFilterValue === cardStatus);

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
            const sectionElement = card.closest('#premium-section, #free-section');
            if (sectionElement) {
                const sectionId = sectionElement.getAttribute('id');
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
        const title = tab?.getAttribute('data-page-title');
        const subtitle = tab?.getAttribute('data-page-subtitle');
        const titleEl = document.querySelector('.ns-license-tab-page-header__title');
        const subtitleEl = document.querySelector('.ns-license-tab-page-header__subtitle');
        if (titleEl && title) {
            titleEl.textContent = title;
        }
        if (subtitleEl && subtitle) {
            subtitleEl.textContent = subtitle;
        }
    };

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
