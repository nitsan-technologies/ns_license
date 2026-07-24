/**
 * Module: @nitsan/ns-license/filter
 * Filter and search functionality for Shop and Services pages
 */

import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

// Cache for loaded data
const loadedDataCache = {
    shop: false,
    services: false
};

/**
 * Load shop data via AJAX
 */
function loadShopData() {
    // Check if already loaded
    if (loadedDataCache.shop) {
        return;
    }
    
    const shopPane = document.querySelector('#shop-pane');
    const loadingPlaceholder = shopPane?.querySelector('.shop-loading-placeholder');
    const contentContainer = shopPane?.querySelector('.shop-content');
    
    if (!shopPane || !loadingPlaceholder || !contentContainer) {
        return;
    }
    
    // Get URL and translations from data attributes (try button first, then pane)
    const shopTab = document.querySelector('#shop-tab');
    const shopUrl = shopTab?.getAttribute('data-shop-url') || shopPane.getAttribute('data-shop-url');
    const errorUrlNotFound = shopPane.getAttribute('data-error-url-not-found') || 'Shop URL not found.';
    const errorFailed = shopPane.getAttribute('data-error-failed') || 'Failed to load shop data. Please try again.';
    const errorLoading = shopPane.getAttribute('data-error-loading') || 'Error loading shop data. Please refresh the page.';
    
    if (!shopUrl) {
        loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorUrlNotFound + '</p>';
        return;
    }
    
    // Show loading state
    loadingPlaceholder.style.display = 'block';
    contentContainer.style.display = 'none';
    
    // Load data via AJAX
    new AjaxRequest(shopUrl)
        .get()
        .then(async function (response) {
            const html = await response.resolve();
            
            if (html) {
                // Hide loading, show content
                loadingPlaceholder.style.display = 'none';
                contentContainer.innerHTML = html;
                contentContainer.style.display = 'block';
                
                // Mark as loaded
                loadedDataCache.shop = true;
                
                // Re-initialize filters for the new content
                setTimeout(() => {
                    filterShop();
                    // Re-attach event listeners for the new content
                    const shopSectionFilter = document.querySelector('#shopSectionFilter');
                    const shopSearch = document.querySelector('#shopSearch');
                    
                    if (shopSectionFilter) {
                        shopSectionFilter.addEventListener('change', filterShop);
                    }
                    
                    if (shopSearch) {
                        shopSearch.addEventListener('input', filterShop);
                        shopSearch.addEventListener('keyup', filterShop);
                    }
                }, 100);
            } else {
                // Show error
                loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorFailed + '</p>';
                Notification.error('Error', errorFailed);
            }
        })
        .catch(function (error) {
            loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorLoading + '</p>';
            Notification.error('Error', errorLoading);
            console.error('Error loading shop data:', error);
        });
}

/**
 * Load services data via AJAX
 */
function loadServicesData() {
    // Check if already loaded
    if (loadedDataCache.services) {
        return;
    }
    
    const servicesPane = document.querySelector('#services-pane');
    const loadingPlaceholder = servicesPane?.querySelector('.services-loading-placeholder');
    const contentContainer = servicesPane?.querySelector('.services-content');
    
    if (!servicesPane || !loadingPlaceholder || !contentContainer) {
        return;
    }
    
    // Get URL and translations from data attributes (try button first, then pane)
    const servicesTab = document.querySelector('#services-tab');
    const servicesUrl = servicesTab?.getAttribute('data-services-url') || servicesPane.getAttribute('data-services-url');
    const errorUrlNotFound = servicesPane.getAttribute('data-error-url-not-found') || 'Services URL not found.';
    const errorFailed = servicesPane.getAttribute('data-error-failed') || 'Failed to load services data. Please try again.';
    const errorLoading = servicesPane.getAttribute('data-error-loading') || 'Error loading services data. Please refresh the page.';
    
    if (!servicesUrl) {
        loadingPlaceholder.innerHTML = '<p class="text-danger">' + errorUrlNotFound + '</p>';
        return;
    }
    
    // Show loading state
    loadingPlaceholder.style.display = 'block';
    contentContainer.style.display = 'none';
    
    // Load data via AJAX
    new AjaxRequest(servicesUrl)
        .get()
        .then(async function (response) {
            const html = await response.resolve();
            
            if (html) {
                // Hide loading, show content
                loadingPlaceholder.style.display = 'none';
                contentContainer.innerHTML = html;
                contentContainer.style.display = 'block';
                
                // Mark as loaded
                loadedDataCache.services = true;
                
                // Re-initialize filters for the new content
                setTimeout(() => {
                    filterServices();
                    // Re-attach event listeners for the new content
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
                // Show error
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
    
    // Only run if we're on the services tab/pane
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
    
    // Get all service cards
    const serviceCards = document.querySelectorAll('.service-card-wrapper');
    let visibleCount = 0;
    const categoryVisibility = {};
    
    // Filter each service card
    serviceCards.forEach((card) => {
        const cardCategory = card.dataset.category || '';
        const cardName = card.dataset.name || '';
        const cardDescription = card.dataset.description || '';
        
        // Check category filter
        const categoryMatch = (categoryFilterValue === 'all' || categoryFilterValue === cardCategory);
        
        // Check search filter
        let searchMatch = true;
        if (searchText) {
            searchMatch = cardName.toLowerCase().includes(searchText) || 
                         cardDescription.toLowerCase().includes(searchText);
        }
        
        // Show or hide card based on both filters
        if (categoryMatch && searchMatch) {
            card.style.display = '';
            const col = card.closest('[class*="col-"]');
            if (col) {
                col.style.display = '';
            }
            visibleCount++;
            
            // Track which categories have visible items
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
    
    // Show/hide category sections based on visible items
    const categorySections = document.querySelectorAll('.service-category-section');
    categorySections.forEach((section) => {
        const sectionTitleElement = section.querySelector('.extension-section__header-title, .extension-section-header .card-title, .card-header .card-title');
        if (sectionTitleElement) {
            const sectionTitle = sectionTitleElement.textContent.trim();
            const hasVisibleItems = categoryVisibility[sectionTitle] > 0;
            section.style.display = hasVisibleItems ? '' : 'none';
        }
    });
    
    // Show message if no results
    const extensionWrapper = document.querySelector('.extension-section-wrapper');
    let noResultsMessage = document.querySelector('.no-services-results');
    
    if (visibleCount === 0) {
        if (!noResultsMessage && extensionWrapper) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-services-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-variant">No services found matching your criteria.</p>';
            extensionWrapper.appendChild(noResultsMessage);
        }
    } else {
        if (noResultsMessage) {
            noResultsMessage.remove();
        }
    }
}

/**
 * Shop Filter and Search functionality
 * - "All Sections": preview up to 8 cards per section
 * - Specific section (dropdown or All button): show all cards in that section
 */
function filterShop() {
    const shopPane = document.querySelector('#shop-pane');

    // Only run if we're on the shop tab/pane
    if (!shopPane || !shopPane.classList.contains('active')) {
        return;
    }

    const sectionFilter = document.querySelector('#shopSectionFilter');
    const searchInput = document.querySelector('#shopSearch');

    if (!sectionFilter || !searchInput) {
        return;
    }

    const sectionFilterValue = sectionFilter.value || 'All Sections';
    const searchText = searchInput.value.toLowerCase().trim();
    const isAllSections = sectionFilterValue === 'All Sections';
    const isSearching = searchText !== '';
    const previewLimit = 8;

    let visibleCount = 0;
    const extensionSections = shopPane.querySelectorAll('.extension-section');

    extensionSections.forEach((section) => {
        const sectionTitleElement = section.querySelector('.extension-section__header-title, .extension-section-header .card-title, .card-header .card-title');
        const sectionTitle = sectionTitleElement ? sectionTitleElement.textContent.trim() : '';
        const cards = section.querySelectorAll('.extension-card-wrapper[data-section]');
        let sectionVisible = 0;

        cards.forEach((card) => {
            const cardSection = card.dataset.section || '';
            const cardName = card.dataset.name || '';
            const cardDescription = card.dataset.description || '';
            const extensionKeySpan = card.querySelector('.card-subtitle');
            const extensionKey = extensionKeySpan ? extensionKeySpan.textContent.toLowerCase() : '';

            const sectionMatch = (isAllSections || sectionFilterValue === cardSection);
            let searchMatch = true;
            if (isSearching) {
                searchMatch = cardName.toLowerCase().includes(searchText)
                    || cardDescription.toLowerCase().includes(searchText)
                    || extensionKey.includes(searchText);
            }

            let show = sectionMatch && searchMatch;
            // Default overview: only first N cards per section (search shows all matches).
            if (show && isAllSections && !isSearching && sectionVisible >= previewLimit) {
                show = false;
            }

            if (show) {
                card.style.display = '';
                const col = card.closest('[class*="col-"]');
                if (col) {
                    col.style.display = '';
                }
                sectionVisible++;
                visibleCount++;
            } else {
                card.style.display = 'none';
                const col = card.closest('[class*="col-"]');
                if (col) {
                    col.style.display = 'none';
                }
            }
        });

        // When filtering to one section, hide other section wrappers entirely.
        if (!isAllSections) {
            section.style.display = (sectionTitle === sectionFilterValue && sectionVisible > 0) ? '' : 'none';
        } else {
            section.style.display = sectionVisible > 0 ? '' : 'none';
        }
    });

    // Show message if no results
    let noResultsMessage = shopPane.querySelector('.no-shop-results');

    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-shop-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-variant">No products found matching your criteria.</p>';
            shopPane.appendChild(noResultsMessage);
        }
    } else if (noResultsMessage) {
        noResultsMessage.remove();
    }
}

/**
 * "All Extensions/Templates" → select that category and show every product in it.
 * @param {string} sectionTitle
 */
function showShopSection(sectionTitle) {
    const title = (sectionTitle || '').trim();
    if (!title) {
        return;
    }
    const sectionFilter = document.querySelector('#shopSectionFilter');
    if (!sectionFilter) {
        return;
    }
    const options = Array.from(sectionFilter.options || []);
    const match = options.find((opt) => opt.value === title || opt.textContent.trim() === title);
    if (match) {
        sectionFilter.value = match.value;
    } else {
        sectionFilter.value = title;
    }
    const searchInput = document.querySelector('#shopSearch');
    if (searchInput) {
        searchInput.value = '';
    }
    filterShop();
    const shopPane = document.querySelector('#shop-pane');
    if (shopPane) {
        shopPane.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Extensions Filter and Search functionality
 */
function filterExtensions() {
    const extensionsPane = document.querySelector('#extensions-pane');
    
    // Only run if we're on the extensions tab/pane
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
    
    // Get all extension cards
    const extensionCards = extensionsPane.querySelectorAll('.extension-card-wrapper');
    
    let visibleCount = 0;
    const sectionVisibility = {};
    
    // Filter each extension card
    extensionCards.forEach((card) => {
        const cardStatus = card.dataset.status || '';
        const cardName = card.dataset.name || '';
        const cardKey = card.dataset.key || '';
        
        // Get extension description for search
        const descriptionElement = card.querySelector('.card-body .card-text, .card-body p');
        const cardDescription = descriptionElement ? descriptionElement.textContent.toLowerCase() : '';
        
        // Get extension key and version from subtitle for search
        const keySpan = card.querySelector('.card-subtitle');
        const extensionKey = keySpan ? keySpan.textContent.toLowerCase() : '';
        
        // Check status filter
        const statusMatch = (statusFilterValue === 'all' || statusFilterValue === cardStatus);
        
        // Check search filter
        let searchMatch = true;
        if (searchText) {
            searchMatch = cardName.toLowerCase().includes(searchText) || 
                         cardDescription.includes(searchText) ||
                         extensionKey.includes(searchText) ||
                         cardKey.toLowerCase().includes(searchText);
        }
        
        // Show or hide card based on both filters
        if (statusMatch && searchMatch) {
            card.style.display = '';
            visibleCount++;
            
            // Track which sections have visible items
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
    
    // Show/hide extension sections based on visible items
    const premiumSection = extensionsPane.querySelector('#premium-section');
    const freeSection = extensionsPane.querySelector('#free-section');
    
    if (premiumSection) {
        const hasVisibleItems = sectionVisibility['premium-section'] > 0;
        premiumSection.style.display = hasVisibleItems ? '' : 'none';
    }
    
    if (freeSection) {
        const hasVisibleItems = sectionVisibility['free-section'] > 0;
        freeSection.style.display = hasVisibleItems ? '' : 'none';
    }
    
    // Show message if no results
    let noResultsMessage = extensionsPane.querySelector('.no-extensions-results');
    
    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-extensions-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-variant">' + noResultsText + '</p>';
            extensionsPane.appendChild(noResultsMessage);
        }
    } else {
        if (noResultsMessage) {
            noResultsMessage.remove();
        }
    }
}

/**
 * Initialize filter functionality when DOM is ready
 */
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
    // Services Filter and Search - Event handlers
    const servicesCategoryFilter = document.querySelector('#servicesCategoryFilter');
    const servicesSearch = document.querySelector('#servicesSearch');
    
    if (servicesCategoryFilter) {
        servicesCategoryFilter.addEventListener('change', filterServices);
    }
    
    if (servicesSearch) {
        servicesSearch.addEventListener('input', filterServices);
        servicesSearch.addEventListener('keyup', filterServices);
    }
    
    // Shop Filter and Search - Event handlers
    const shopSectionFilter = document.querySelector('#shopSectionFilter');
    const shopSearch = document.querySelector('#shopSearch');
    
    if (shopSectionFilter) {
        shopSectionFilter.addEventListener('change', filterShop);
    }
    
    if (shopSearch) {
        shopSearch.addEventListener('input', filterShop);
        shopSearch.addEventListener('keyup', filterShop);
    }

    // "All Extensions / All Templates" → filter to that section (works after AJAX inject)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.t3js-shop-show-section');
        if (!btn) {
            return;
        }
        e.preventDefault();
        showShopSection(btn.getAttribute('data-section') || '');
    });
    
    // Extensions Filter and Search - Event handlers
    const extensionsStatusFilter = document.querySelector('#extFilter');
    const extensionsSearch = document.querySelector('#extSearch');
    
    if (extensionsStatusFilter) {
        extensionsStatusFilter.addEventListener('change', filterExtensions);
    }
    
    if (extensionsSearch) {
        extensionsSearch.addEventListener('input', filterExtensions);
        extensionsSearch.addEventListener('keyup', filterExtensions);
    }
    
    // Handle tab switching and AJAX loading using direct click events
    const shopTab = document.querySelector('.t3js-shop-tab, #shop-tab');
    const servicesTab = document.querySelector('.t3js-services-tab, #services-tab');
    const extensionsTab = document.querySelector('#my-extensions-tab');
    
    // Shop tab click handler
    if (shopTab) {
        shopTab.addEventListener('click', function(e) {
            e.preventDefault();
            // Load shop data if not already loaded
            loadShopData();
            
            activateMainTab(shopTab, document.querySelector('#shop-pane'));
        });
    }
    
    // Services tab click handler
    if (servicesTab) {
        servicesTab.addEventListener('click', function(e) {
            e.preventDefault();
            // Load services data if not already loaded
            loadServicesData();
            activateMainTab(servicesTab, document.querySelector('#services-pane'));
        });
    }
    
    // Extensions tab click handler
    if (extensionsTab) {
        extensionsTab.addEventListener('click', function(e) {
            e.preventDefault();
            activateMainTab(extensionsTab, document.querySelector('#extensions-pane'));
            // Re-apply filters
            setTimeout(() => {
                filterExtensions();
            }, 100);
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFilters);
} else {
    initializeFilters();
}
