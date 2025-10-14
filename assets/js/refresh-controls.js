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
    this.controlPanel = document.createElement("div");
    this.controlPanel.className =
      "fixed top-12 right-4 bg-white rounded-xl shadow-2xl p-5 z-40 border border-gray-200 max-w-xs";
    this.controlPanel.innerHTML = `
        <div class="space-y-5">
            
            <div class="flex items-center justify-between">
            
                <h3 class="text-lg font-bold text-gray-800">Auto Refresh</h3>
                <div class="w-3 h-3 rounded-full bg-gradient-to-r from-green-400 to-blue-500"></div>
            </div>

            <button id="closeRefreshControls" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
            <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-4 border border-green-100">
                <h4 class="font-medium text-gray-700 mb-2 flex items-center">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                    Status
                </h4>
                <div class="text-sm text-green-700 font-medium">Active</div>
            </div>
            <p class="text-sm text-gray-600">Page will refresh after 2 minutes of inactivity</p>

            <!-- Refresh Now Button -->
            <button class="w-full py-3 px-4 rounded-xl font-medium text-white bg-gradient-to-r from-green-500 to-blue-500 hover:from-green-600 hover:via-yellow-600 hover:to-blue-600 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                Refresh Now
            </button>
        </div>
    `;

    // Add global styles if they don't exist
    if (!document.getElementById("control-panel-styles")) {
      const style = document.createElement("style");
      style.id = "control-panel-styles";
      style.textContent = `
            .control-panel-gradient {
                background: linear-gradient(135deg, #10b981 0%, #fbbf24 50%, #3b82f6 100%);
            }
            .control-panel-gradient:hover {
                background: linear-gradient(135deg, #059669 0%, #f59e0b 50%, #2563eb 100%);
            }
        `;
      document.head.appendChild(style);
    }

    document.body.appendChild(this.controlPanel);
  }

  setupEventListeners() {
    // Toggle auto-refresh
    document.getElementById("refreshToggle").addEventListener("change", (e) => {
      this.toggleAutoRefresh(e.target.checked);
    });

    // // Change refresh interval
    // document
    //   .getElementById("refreshInterval")
    //   .addEventListener("change", (e) => {
    //     this.changeRefreshInterval(parseInt(e.target.value));
    //   });

    // Manual refresh
    document.getElementById("manualRefresh").addEventListener("click", () => {
      this.manualRefresh();
    });

    // Close panel
    document
      .getElementById("closeRefreshControls")
      .addEventListener("click", () => {
        this.hideControlPanel();
      });

    // Keyboard shortcut: Ctrl + R to show/hide controls
    document.addEventListener("keydown", (e) => {
      if (e.ctrlKey && e.key === "r") {
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
    this.controlPanel.classList.toggle("hidden");
  }

  hideControlPanel() {
    this.controlPanel.classList.add("hidden");
  }

  showControlPanel() {
    this.controlPanel.classList.remove("hidden");
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
  const toggleBtn = document.createElement("button");
  toggleBtn.className =
    "fixed top-16 right-4 bg-green-500 text-white p-3 rounded-full shadow-lg z-40 hover:bg-green-600 transition";
  toggleBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
  toggleBtn.title = "Auto Refresh Controls (Ctrl+R)";

  toggleBtn.addEventListener("click", () => {
    if (!refreshControls) {
      initializeRefreshControls();
    } else {
      refreshControls.toggleControlPanel();
    }
  });

  document.body.appendChild(toggleBtn);
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", addRefreshToggleButton);
} else {
  addRefreshToggleButton();
}
