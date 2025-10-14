class AutoRefreshManager {
    constructor() {
        // this.refreshInterval = 40000; // 30 seconds
        this.inactivityTimeout = 120000; // 2 minutes (fixed from incorrect comment)
        this.refreshTimer = null;
        this.inactivityTimer = null;
        this.lastActivity = Date.now();
        this.isEnabled = true;
        
        this.init();
    }

    init() {
        // Check if we should enable auto-refresh
        if (!this.shouldEnable()) {
            console.log('AutoRefreshManager disabled for this page');
            this.isEnabled = false;
            return;
        }

        this.setupRefreshTimer();
        this.setupInactivityListener();
        this.setupInactivityTimer();
        
        console.log('AutoRefreshManager initialized');
    }

    shouldEnable() {
        // Don't initialize on certain pages
        const excludedPages = [
            '/online-plaza/auth/',
            '/online-plaza/company/edit.php',
            '/online-plaza/profile/edit.php',
            '/online-plaza/posts/create.php',
            '/online-plaza/posts/index.php',
            '/online-plaza/posts/edit.php'
        ];
        
        const currentPath = window.location.pathname;
        const isExcluded = excludedPages.some(page => currentPath.includes(page));
        
        return !isExcluded;
    }

    setupRefreshTimer() {
        if (!this.isEnabled) return;

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
        if (!this.isEnabled) return;

        // Events that reset inactivity timer
        const activityEvents = [
            'mousemove', 'mousedown', 'keypress', 
            'scroll', 'touchstart', 'click',
            'submit', 'input'
        ];

        const resetActivity = () => {
            this.resetInactivityTimer();
        };

        activityEvents.forEach(event => {
            document.addEventListener(event, resetActivity, { passive: true });
        });

        // Also track visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.resetInactivityTimer();
            }
        });

        // Store references for cleanup
        this.activityHandler = resetActivity;
    }

    setupInactivityTimer() {
        if (!this.isEnabled) return;
        this.resetInactivityTimer();
    }

    resetInactivityTimer() {
        if (!this.isEnabled) return;

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
        // Only refresh if page is visible, user is active, and manager is enabled
        if (this.isEnabled && !document.hidden && this.isUserActive()) {
            console.log('Auto-refreshing page...');
            
            // Use location.reload for full refresh
            location.reload();
        }
    }

    handleInactivity() {
        if (!this.isEnabled) return;

        const timeSinceLastActivity = Date.now() - this.lastActivity;
        
        if (timeSinceLastActivity >= this.inactivityTimeout && !document.hidden) {
            console.log('User inactive, reloading page...');
            
            // Show a subtle notification (optional)
            this.showInactivityNotification();
            
            // Reload after a brief delay to show notification
            setTimeout(() => {
                if (this.isEnabled) {
                    location.reload();
                }
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
        if (!this.isEnabled) return;
        this.resetInactivityTimer();
        this.setupRefreshTimer();
    }

    // Public method to update intervals
    updateIntervals(refreshMs, inactivityMs) {
        this.refreshInterval = refreshMs;
        this.inactivityTimeout = inactivityMs;
        this.resetAllTimers();
    }

    // Public method to enable/disable the manager
    setEnabled(enabled) {
        this.isEnabled = enabled;
        if (enabled) {
            this.resetAllTimers();
        } else {
            this.destroyTimers();
        }
    }

    // Public method to destroy the manager
    destroy() {
        this.isEnabled = false;
        this.destroyTimers();
        
        // Remove event listeners
        if (this.activityHandler) {
            const activityEvents = [
                'mousemove', 'mousedown', 'keypress', 
                'scroll', 'touchstart', 'click',
                'submit', 'input'
            ];
            
            activityEvents.forEach(event => {
                document.removeEventListener(event, this.activityHandler);
            });
        }
        
        console.log('AutoRefreshManager destroyed');
    }

    destroyTimers() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
        if (this.inactivityTimer) {
            clearTimeout(this.inactivityTimer);
            this.inactivityTimer = null;
        }
    }
}

// Enhanced version with page-specific configurations
function initializeSmartAutoRefresh() {
    const currentPath = window.location.pathname;
    
    // Different configurations for different page types
    const pageConfigs = {
        // Dashboard pages - frequent updates
        'dashboard': { refresh: 30000, inactivity: 120000 },
        // Activity pages - frequent updates
        'activities': { refresh: 30000, inactivity: 120000 },
        // Post feeds - less frequent updates (fixed intervals)
        'posts/index': { refresh: 60000, inactivity: 120000 },
        // Product pages - moderate updates
        'products': { refresh: 45000, inactivity: 180000 },
        // View pages - moderate updates
        'view': { refresh: 40000, inactivity: 150000 },
        // Default configuration
        'default': { refresh: 40000, inactivity: 120000 }
    };

    // Determine which configuration to use
    let config = pageConfigs.default;
    
    for (const [pageType, pageConfig] of Object.entries(pageConfigs)) {
        if (pageType !== 'default' && currentPath.includes(pageType)) {
            config = pageConfig;
            break;
        }
    }

    // Initialize the manager
    const manager = new AutoRefreshManager();
    
    // Update with appropriate configuration
    manager.updateIntervals(config.refresh, config.inactivity);
    
    console.log(`AutoRefreshManager initialized for ${currentPath} - Refresh: ${config.refresh}ms, Inactivity: ${config.inactivity}ms`);
    // console.log(`AutoRefreshManager initialized for ${currentPath} - Inactivity: ${config.inactivity}ms`);
    
    return manager;
}

// Global instance management
let autoRefreshManager = null;

function initializeAutoRefresh() {
    if (autoRefreshManager) {
        autoRefreshManager.destroy();
    }
    
    autoRefreshManager = initializeSmartAutoRefresh();
}

function destroyAutoRefresh() {
    if (autoRefreshManager) {
        autoRefreshManager.destroy();
        autoRefreshManager = null;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        AutoRefreshManager, 
        initializeAutoRefresh, 
        initializeSmartAutoRefresh,
        destroyAutoRefresh 
    };
}

// Auto-initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAutoRefresh);
} else {
    initializeAutoRefresh();
}

// Also reinitialize when page becomes visible again
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !autoRefreshManager) {
        // Reinitialize if manager was destroyed but page is now visible
        initializeAutoRefresh();
    }
});