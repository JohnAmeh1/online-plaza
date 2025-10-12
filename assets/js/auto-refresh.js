
class AutoRefreshManager {
    constructor() {
        this.refreshInterval = 30000; // 5 seconds
        this.inactivityTimeout = 60000; // 20 seconds
        this.refreshTimer = null;
        this.inactivityTimer = null;
        this.lastActivity = Date.now();
        
        this.init();
    }

    init() {
        this.setupRefreshTimer();
        this.setupInactivityListener();
        this.setupInactivityTimer();
        
        console.log('AutoRefreshManager initialized');
    }

    setupRefreshTimer() {
        // Clear existing timer
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }

        // Set up new refresh interval
        this.refreshTimer = setInterval(() => {
            this.refreshPage();
        }, this.refreshInterval);
    }

    setupInactivityListener() {
        // Events that reset inactivity timer
        const activityEvents = [
            'mousemove', 'mousedown', 'keypress', 
            'scroll', 'touchstart', 'click',
            'submit', 'input'
        ];

        activityEvents.forEach(event => {
            document.addEventListener(event, () => {
                this.resetInactivityTimer();
            }, { passive: true });
        });

        // Also track visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.resetInactivityTimer();
            }
        });
    }

    setupInactivityTimer() {
        this.resetInactivityTimer();
    }

    resetInactivityTimer() {
        this.lastActivity = Date.now();
        
        // Clear existing inactivity timer
        if (this.inactivityTimer) {
            clearTimeout(this.inactivityTimer);
        }

        // Set new inactivity timer
        this.inactivityTimer = setTimeout(() => {
            this.handleInactivity();
        }, this.inactivityTimeout);
    }

    refreshPage() {
        // Only refresh if page is visible and user is active
        if (!document.hidden && this.isUserActive()) {
            console.log('Auto-refreshing page...');
            
            // Use location.reload for full refresh
            location.reload();
        }
    }

    handleInactivity() {
        const timeSinceLastActivity = Date.now() - this.lastActivity;
        
        if (timeSinceLastActivity >= this.inactivityTimeout && !document.hidden) {
            console.log('User inactive for 20 seconds, reloading page...');
            
            // Show a subtle notification (optional)
            this.showInactivityNotification();
            
            // Reload after a brief delay to show notification
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    }

    isUserActive() {
        const timeSinceLastActivity = Date.now() - this.lastActivity;
        return timeSinceLastActivity < (this.inactivityTimeout - 1000);
    }

    showInactivityNotification() {
        // Create a subtle notification
        const notification = document.createElement('div');
        notification.innerHTML = `
            <div class="fixed bottom-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg z-50">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    <span>Refreshing due to inactivity...</span>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }

    // Public method to manually reset timers
    resetAllTimers() {
        this.resetInactivityTimer();
        this.setupRefreshTimer();
    }

    // Public method to update intervals
    updateIntervals(refreshMs, inactivityMs) {
        this.refreshInterval = refreshMs;
        this.inactivityTimeout = inactivityMs;
        this.resetAllTimers();
    }

    // Public method to destroy the manager
    destroy() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        if (this.inactivityTimer) {
            clearTimeout(this.inactivityTimer);
        }
        
        console.log('AutoRefreshManager destroyed');
    }
}

// Initialize auto-refresh manager
let autoRefreshManager;

function initializeAutoRefresh() {
    // Don't initialize on certain pages
    const excludedPages = [
        '/online-plaza/auth/',
        '/online-plaza/company/edit.php',
        '/online-plaza/posts/create.php',
        '/online-plaza/posts/edit.php'
    ];
    
    const currentPath = window.location.pathname;
    const isExcluded = excludedPages.some(page => currentPath.includes(page));
    
    if (!isExcluded) {
        autoRefreshManager = new AutoRefreshManager();
    }
}

// Enhanced version with page-specific configurations
function initializeSmartAutoRefresh() {
    const currentPath = window.location.pathname;
    
    // Different configurations for different page types
    const pageConfigs = {
        // Dashboard pages - frequent updates
        'dashboard': { refresh: 30000, inactivity: 60000 },
        // Activity pages - frequent updates
        'activities': { refresh: 30000, inactivity: 60000 },
        // Post feeds - moderate updates
        'posts/index': { refresh: 30000, inactivity: 60000 },
        // Product pages - less frequent
        'products': { refresh: 30000, inactivity: 60000 },
        // View pages - moderate updates
        'view': { refresh: 30000, inactivity: 60000 },
        // Default configuration
        'default': { refresh: 30000, inactivity: 60000 }
    };

    // Pages where auto-refresh should be disabled
    const disabledPages = [
        '/online-plaza/auth/',
        '/online-plaza/company/edit.php',
        '/online-plaza/posts/create.php',
        '/online-plaza/posts/edit.php',
        '/online-plaza/products/create.php',
        '/online-plaza/products/edit.php'
    ];

    // Check if current page is disabled
    const isDisabled = disabledPages.some(page => currentPath.includes(page));
    
    if (isDisabled) {
        console.log('Auto-refresh disabled for this page');
        return;
    }

    // Determine which configuration to use
    let config = pageConfigs.default;
    
    for (const [pageType, pageConfig] of Object.entries(pageConfigs)) {
        if (pageType !== 'default' && currentPath.includes(pageType)) {
            config = pageConfig;
            break;
        }
    }

    // Initialize with appropriate configuration
    autoRefreshManager = new AutoRefreshManager();
    autoRefreshManager.updateIntervals(config.refresh, config.inactivity);
    
    console.log(`AutoRefreshManager initialized for ${currentPath} - Refresh: ${config.refresh}ms, Inactivity: ${config.inactivity}ms`);
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AutoRefreshManager, initializeAutoRefresh, initializeSmartAutoRefresh };
}

// Auto-initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSmartAutoRefresh);
} else {
    initializeSmartAutoRefresh();
}