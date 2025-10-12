

class RefreshControls {
    constructor() {
        this.isEnabled = true;
        this.controlPanel = null;
        this.init();
    }

    init() {
        this.createControlPanel();
        this.setupEventListeners();
    }

    createControlPanel() {
        this.controlPanel = document.createElement('div');
        this.controlPanel.className = 'fixed top-12 right-4 bg-white rounded-lg shadow-lg p-4 z-40 border border-gray-200';
        this.controlPanel.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Auto Refresh</h3>
                <button id="closeRefreshControls" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Status</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="refreshToggle" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Refresh Every</span>
                    <select id="refreshInterval" class="text-sm border border-gray-300 rounded px-2 py-1">
                        <option value="5000">5 seconds</option>
                        <option value="10000">10 seconds</option>
                        <option value="15000">15 seconds</option>
                        <option value="30000">30 seconds</option>
                    </select>
                </div>
                <button id="manualRefresh" class="w-full bg-blue-500 text-white text-sm py-2 rounded hover:bg-blue-600 transition">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Now
                </button>
            </div>
        `;

        document.body.appendChild(this.controlPanel);
    }

    setupEventListeners() {
        // Toggle auto-refresh
        document.getElementById('refreshToggle').addEventListener('change', (e) => {
            this.toggleAutoRefresh(e.target.checked);
        });

        // Change refresh interval
        document.getElementById('refreshInterval').addEventListener('change', (e) => {
            this.changeRefreshInterval(parseInt(e.target.value));
        });

        // Manual refresh
        document.getElementById('manualRefresh').addEventListener('click', () => {
            this.manualRefresh();
        });

        // Close panel
        document.getElementById('closeRefreshControls').addEventListener('click', () => {
            this.hideControlPanel();
        });

        // Keyboard shortcut: Ctrl + R to show/hide controls
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                this.toggleControlPanel();
            }
        });
    }

    toggleAutoRefresh(enabled) {
        this.isEnabled = enabled;
        if (enabled) {
            autoRefreshManager.resetAllTimers();
        } else {
            autoRefreshManager.destroy();
        }
    }

    changeRefreshInterval(interval) {
        if (autoRefreshManager && this.isEnabled) {
            autoRefreshManager.updateIntervals(interval, 20000);
        }
    }

    manualRefresh() {
        location.reload();
    }

    toggleControlPanel() {
        this.controlPanel.classList.toggle('hidden');
    }

    hideControlPanel() {
        this.controlPanel.classList.add('hidden');
    }

    showControlPanel() {
        this.controlPanel.classList.remove('hidden');
    }
}

// Initialize controls if needed
let refreshControls;

function initializeRefreshControls() {
    // Only initialize on pages where auto-refresh is active
    if (autoRefreshManager) {
        refreshControls = new RefreshControls();
    }
}

// Add toggle button to page
function addRefreshToggleButton() {
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'fixed top-16 right-4 bg-blue-500 text-white p-3 rounded-full shadow-lg z-40 hover:bg-blue-600 transition';
    toggleBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
    toggleBtn.title = 'Auto Refresh Controls (Ctrl+R)';
    
    toggleBtn.addEventListener('click', () => {
        if (!refreshControls) {
            initializeRefreshControls();
        } else {
            refreshControls.toggleControlPanel();
        }
    });

    document.body.appendChild(toggleBtn);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addRefreshToggleButton);
} else {
    addRefreshToggleButton();
}