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
        
        // Find parent column element
        const parentColumn = card.closest('.col-md-6, .col-lg-4, .col-xl-3');
        
        // Show or hide card based on both filters
        if (categoryMatch && searchMatch) {
            if (parentColumn) {
                parentColumn.style.display = '';
            }
            card.style.display = '';
            visibleCount++;
            
            // Track which categories have visible items
            if (!categoryVisibility[cardCategory]) {
                categoryVisibility[cardCategory] = 0;
            }
            categoryVisibility[cardCategory]++;
        } else {
            if (parentColumn) {
                parentColumn.style.display = 'none';
            }
            card.style.display = 'none';
        }
    });
    
    // Show/hide category sections based on visible items
    const categorySections = document.querySelectorAll('.service-category-section');
    categorySections.forEach((section) => {
        const sectionTitleElement = section.querySelector('.extension-section__header-title');
        if (sectionTitleElement) {
            const sectionTitle = sectionTitleElement.textContent.trim();
            const hasVisibleItems = categoryVisibility[sectionTitle] > 0;
            
            if (hasVisibleItems) {
                section.style.display = '';
            } else {
                section.style.display = 'none';
            }
        }
    });
    
    // Show message if no results
    const extensionWrapper = document.querySelector('.extension-section-wrapper');
    let noResultsMessage = document.querySelector('.no-services-results');
    
    if (visibleCount === 0) {
        if (!noResultsMessage && extensionWrapper) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-services-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-muted">No services found matching your criteria.</p>';
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
    
    // Get all shop cards within shop-pane (cards with data-section attribute)
    const shopCards = shopPane.querySelectorAll('.extension-card-wrapper[data-section]');
    
    let visibleCount = 0;
    const sectionVisibility = {};
    
    // Filter each shop card
    shopCards.forEach((card) => {
        const cardSection = card.dataset.section || '';
        const cardName = card.dataset.name || '';
        const cardDescription = card.dataset.description || '';
        
        // Get extension key and version from span element for search
        const extensionKeySpan = card.querySelector('.card-header__title-content span');
        const extensionKey = extensionKeySpan ? extensionKeySpan.textContent.toLowerCase() : '';
        
        // Check section filter
        const sectionMatch = (sectionFilterValue === 'All Sections' || sectionFilterValue === cardSection);
        
        // Check search filter
        let searchMatch = true;
        if (searchText) {
            searchMatch = cardName.toLowerCase().includes(searchText) || 
                         cardDescription.toLowerCase().includes(searchText) ||
                         extensionKey.includes(searchText);
        }
        
        // Find parent column element
        const parentColumn = card.closest('.extension-section');
        // Show or hide card based on both filters
        if (sectionMatch && searchMatch) {
            if (parentColumn) {
                parentColumn.style.display = '';
            }
            card.style.display = '';
            visibleCount++;
            
            // Track which sections have visible items
            if (!sectionVisibility[cardSection]) {
                sectionVisibility[cardSection] = 0;
            }
            sectionVisibility[cardSection]++;
        } else {
            if (parentColumn) {
                parentColumn.style.display = 'none';
            }
            card.style.display = 'none';
        }
    });
    
    // Show/hide section wrappers based on visible items
    const extensionSections = shopPane.querySelectorAll('.extension-section');
    extensionSections.forEach((section) => {
        const sectionId = section.getAttribute('id');
        
        // Only hide if it's a shop section (has wrapper ID pattern)
        if (sectionId && sectionId.includes('-wrapper')) {
            const sectionTitleElement = section.querySelector('.extension-section__header-title');
            if (sectionTitleElement) {
                const sectionTitle = sectionTitleElement.textContent.trim();
                const hasVisibleItems = sectionVisibility[sectionTitle] > 0;
                if (hasVisibleItems) {
                    section.classList.remove('d-none');
                } else {
                    // section.classList.add('d-none');
                }
            }
        }
    });
    
    // Show message if no results
    let noResultsMessage = shopPane.querySelector('.no-shop-results');
    
    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-shop-results col-12 text-center py-5';
            noResultsMessage.innerHTML = '<p class="text-muted">No products found matching your criteria.</p>';
            shopPane.appendChild(noResultsMessage);
        }
    } else {
        if (noResultsMessage) {
            noResultsMessage.remove();
        }
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
        const descriptionElement = card.querySelector('.card-body p');
        const cardDescription = descriptionElement ? descriptionElement.textContent.toLowerCase() : '';
        
        // Get extension key and version from span for search
        const keySpan = card.querySelector('.extension-card-header__title-content span, .card-header__title-content span');
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
        
        // Find parent column element
        const parentColumn = card.closest('.col-md-6, .col-xl-4');
        
        // Show or hide card based on both filters
        if (statusMatch && searchMatch) {
            if (parentColumn) {
                parentColumn.style.display = '';
            }
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
            if (parentColumn) {
                parentColumn.style.display = 'none';
            }
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
            noResultsMessage.innerHTML = '<p class="text-muted">No extensions found matching your criteria.</p>';
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
            
            // Manually activate the tab (Bootstrap 5)
            const shopPane = document.querySelector('#shop-pane');
            if (shopPane) {
                // Remove active from all tabs and panes
                document.querySelectorAll('.nav-link').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Add active to shop tab and pane
                shopTab.classList.add('active');
                shopPane.classList.add('show', 'active');
            }
        });
    }
    
    // Services tab click handler
    if (servicesTab) {
        servicesTab.addEventListener('click', function(e) {
            e.preventDefault();
            // Load services data if not already loaded
            loadServicesData();
            
            // Manually activate the tab (Bootstrap 5)
            const servicesPane = document.querySelector('#services-pane');
            if (servicesPane) {
                // Remove active from all tabs and panes
                document.querySelectorAll('.nav-link').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Add active to services tab and pane
                servicesTab.classList.add('active');
                servicesPane.classList.add('show', 'active');
            }
        });
    }
    
    // Extensions tab click handler
    if (extensionsTab) {
        extensionsTab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Manually activate the tab (Bootstrap 5)
            const extensionsPane = document.querySelector('#extensions-pane');
            if (extensionsPane) {
                // Remove active from all tabs and panes
                document.querySelectorAll('.nav-link').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Add active to extensions tab and pane
                extensionsTab.classList.add('active');
                extensionsPane.classList.add('show', 'active');
                
                // Re-apply filters
                setTimeout(() => {
                    filterExtensions();
                }, 100);
            }
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFilters);
} else {
    initializeFilters();
}
