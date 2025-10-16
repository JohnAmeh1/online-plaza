// Martly PWA Manager - Enhanced Version
class MartlyPWA {
  constructor() {
    this.deferredPrompt = null;
    this.isInstalled = false;
    this.init();
  }

  init() {
    console.log('[PWA] Initializing Martly PWA Manager');
    this.checkInstallStatus();
    this.setupServiceWorker();
    this.setupInstallPrompt();
    this.setupUpdateHandler();
  }

  checkInstallStatus() {
    // Check if app is installed
    this.isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                       window.navigator.standalone === true ||
                       document.referrer.includes('android-app://');
    
    console.log('[PWA] Installation status:', this.isInstalled ? 'Installed' : 'Not installed');
    
    if (this.isInstalled) {
      this.hideInstallButton();
      console.log('[PWA] App is running in standalone mode');
    }
  }

  setupServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      console.warn('[PWA] Service Workers not supported');
      return;
    }

    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/online-plaza/sw.js', {
        scope: '/online-plaza/'
      })
      .then((registration) => {
        console.log('[PWA] Service Worker registered successfully:', registration.scope);
        
        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every minute

        // Handle updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          console.log('[PWA] New Service Worker found');
          
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              console.log('[PWA] New content available, please refresh');
              this.showUpdateNotification();
            }
          });
        });
      })
      .catch((error) => {
        console.error('[PWA] Service Worker registration failed:', error);
      });

    // Handle controller change
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      console.log('[PWA] Controller changed, reloading...');
      window.location.reload();
    });
    });
  }

  setupInstallPrompt() {
    // Listen for beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
      console.log('[PWA] Install prompt available');
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallButton();
    });

    // Listen for successful installation
    window.addEventListener('appinstalled', () => {
      console.log('[PWA] App installed successfully');
      this.isInstalled = true;
      this.deferredPrompt = null;
      this.hideInstallButton();
      this.showSuccessNotification('App installed successfully! 🎉');
    });
  }

  setupUpdateHandler() {
    // Listen for app updates
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.ready.then((registration) => {
        registration.addEventListener('updatefound', () => {
          console.log('[PWA] Update found');
        });
      });
    }
  }

  showInstallButton() {
    const installBtn = document.getElementById('install-martly-btn');
    const mobileBadge = document.getElementById('pwa-install-badge');
    
    if (installBtn) {
      installBtn.style.display = 'flex';
      installBtn.disabled = false;
      installBtn.onclick = () => this.promptInstall();
      console.log('[PWA] Install button shown');
    }
    
    if (mobileBadge) {
      mobileBadge.style.display = 'flex';
      mobileBadge.disabled = false;
      mobileBadge.onclick = () => this.promptInstall();
      console.log('[PWA] Mobile install badge shown');
    }
  }

  hideInstallButton() {
    const installBtn = document.getElementById('install-martly-btn');
    const mobileBadge = document.getElementById('pwa-install-badge');
    
    if (installBtn) {
      installBtn.style.display = 'none';
      console.log('[PWA] Install button hidden');
    }
    
    if (mobileBadge) {
      mobileBadge.style.display = 'none';
      console.log('[PWA] Mobile install badge hidden');
    }
  }

  async promptInstall() {
    console.log('[PWA] Install prompt triggered');
    
    if (!this.deferredPrompt) {
      console.log('[PWA] No deferred prompt available, showing instructions');
      this.showInstallInstructions();
      return;
    }

    try {
      // Show the install prompt
      this.deferredPrompt.prompt();
      
      // Wait for user response
      const { outcome } = await this.deferredPrompt.userChoice;
      console.log(`[PWA] User ${outcome} the install prompt`);
      
      if (outcome === 'accepted') {
        console.log('[PWA] User accepted installation');
        this.hideInstallButton();
      } else {
        console.log('[PWA] User dismissed installation');
      }
      
      // Clear the deferred prompt
      this.deferredPrompt = null;
    } catch (error) {
      console.error('[PWA] Error showing install prompt:', error);
      this.showInstallInstructions();
    }
  }

  showInstallInstructions() {
    const userAgent = navigator.userAgent.toLowerCase();
    let instructions = '';

    if (userAgent.includes('chrome') && !userAgent.includes('edg')) {
      instructions = 'Chrome: Click the install icon (⚡) in the address bar or menu → "Install Martly"';
    } else if (userAgent.includes('edg')) {
      instructions = 'Edge: Click the install icon (⚡) in the address bar or menu → "Install Martly"';
    } else if (userAgent.includes('safari')) {
      instructions = 'Safari: Tap the Share button (⬆️) → "Add to Home Screen"';
    } else if (userAgent.includes('firefox')) {
      instructions = 'Firefox: Menu (⋮) → "Install" or look for the install button in the address bar';
    } else {
      instructions = 'Look for an "Install" or "Add to Home Screen" option in your browser menu';
    }

    this.showModal('Install Martly', instructions);
  }

  showModal(title, message) {
    const modal = document.createElement('div');
    modal.className = 'pwa-modal';
    modal.innerHTML = `
      <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px; animation: fadeIn 0.3s;">
        <div style="background: white; border-radius: 20px; padding: 30px; max-width: 450px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s;">
          <div style="display: flex; align-items: center; margin-bottom: 20px;">
            <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #10b981, #3b82f6); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
              <i class="fas fa-download" style="color: white; font-size: 1.5rem;"></i>
            </div>
            <h3 style="margin: 0; color: #1f2937; font-size: 1.5rem; font-weight: 700;">${title}</h3>
          </div>
          <p style="color: #6b7280; line-height: 1.6; margin-bottom: 24px; font-size: 1rem;">${message}</p>
          <button onclick="this.closest('.pwa-modal').remove()" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #10b981, #3b82f6); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 1rem; transition: transform 0.2s;">
            Got It
          </button>
        </div>
      </div>
      <style>
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        @keyframes slideUp {
          from { transform: translateY(20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .pwa-modal button:hover {
          transform: translateY(-2px);
        }
      </style>
    `;
    document.body.appendChild(modal);
  }

  showSuccessNotification(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #10b981, #3b82f6);
      color: white;
      padding: 16px 24px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
      z-index: 10000;
      max-width: 400px;
      font-weight: 600;
      animation: slideIn 0.3s ease-out;
    `;
    
    toast.innerHTML = `
      <div style="display: flex; align-items: center;">
        <i class="fas fa-check-circle" style="margin-right: 12px; font-size: 1.25rem;"></i>
        <span>${message}</span>
      </div>
      <style>
        @keyframes slideIn {
          from {
            transform: translateX(400px);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
      </style>
    `;
    
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideIn 0.3s ease-out reverse';
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, 4000);
  }

  showUpdateNotification() {
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed;
      bottom: 80px;
      left: 50%;
      transform: translateX(-50%);
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      color: white;
      padding: 16px 24px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
      z-index: 10000;
      max-width: 400px;
      font-weight: 600;
      animation: slideUp 0.3s ease-out;
    `;
    
    toast.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
        <div style="display: flex; align-items: center;">
          <i class="fas fa-sync-alt" style="margin-right: 12px; font-size: 1.25rem;"></i>
          <span>New update available!</span>
        </div>
        <button onclick="window.location.reload()" style="background: white; color: #3b82f6; padding: 8px 16px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">
          Update Now
        </button>
      </div>
    `;
    
    document.body.appendChild(toast);
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.martlyPWA = new MartlyPWA();
  });
} else {
  window.martlyPWA = new MartlyPWA();
}

// Global install function
window.installMartly = () => {
  if (window.martlyPWA) {
    window.martlyPWA.promptInstall();
  } else {
    console.error('[PWA] PWA Manager not initialized');
  }
};