const App = {
  async init() {
    // 1. Initialize Capacitor Plugins if available
    this.initCapacitor();

    // 2. Setup Offline Detection Banner listener
    this.initOfflineMonitor();

    // 3. Register screens inside the Router
    Router.add('#/login', ScreenLogin);
    Router.add('#/dashboard', ScreenDashboard);
    Router.add('#/wallet', ScreenWallet);
    Router.add('#/buy', ScreenBuy);
    Router.add('#/profile', ScreenProfile);
    Router.add('#/transactions', ScreenTransactions);

    // 4. Initialize Router
    Router.init();
  },

  async initCapacitor() {
    if (window.Capacitor) {
      const { StatusBar, SplashScreen } = window.Capacitor.Plugins;
      
      try {
        // Set Status Bar aesthetics
        if (StatusBar) {
          await StatusBar.setBackgroundColor({ color: '#1B4DFF' });
          await StatusBar.setStyle({ style: 'DARK' });
        }
        
        // Terminate Splash Screen after loading
        if (SplashScreen) {
          await SplashScreen.hide();
        }
      } catch (err) {
        console.warn('Capacitor plugin startup warning:', err);
      }
    }
  },

  initOfflineMonitor() {
    const banner = document.getElementById('offline-banner');
    
    const updateStatus = () => {
      if (navigator.onLine) {
        banner?.classList.remove('active');
      } else {
        banner?.classList.add('active');
      }
    };

    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    updateStatus(); // Initial run
  },

  // Helper utility to render a consistent header layout
  renderHeader(title) {
    return `
      <header class="app-header">
        <div class="app-title">${title}</div>
        <div class="app-header-actions">
          <span class="user-badge" id="header-balance" style="font-size: 0.88rem; font-weight: 800; background: var(--color-primary-soft); color: var(--color-primary); padding: 4px 10px; border-radius: 8px;">NGN ...</span>
        </div>
      </header>
    `;
  },

  // Helper utility to render the main navigation menu bottom bar
  renderNavigation(activeRoute) {
    const items = [
      { route: '#/dashboard', label: 'Home', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
      { route: '#/wallet', label: 'Wallet', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
      { route: '#/buy', label: 'Buy', icon: 'M12 4v16m8-8H4' },
      { route: '#/transactions', label: 'History', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
      { route: '#/profile', label: 'Profile', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' }
    ];

    return `
      <nav class="app-nav">
        ${items.map(item => `
          <button class="nav-item ${activeRoute === item.route ? 'active' : ''}" onclick="Router.navigate('${item.route}')">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="${item.icon}"/>
            </svg>
            <span>${item.label}</span>
          </button>
        `).join('')}
      </nav>
    `;
  },

  // Auto updates wallet balances displayed across panels
  updateHeaderBalance(balance) {
    const el = document.getElementById('header-balance');
    if (el) {
      el.textContent = `NGN ${Number(balance).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    }
  }
};

window.App = App;
window.addEventListener('DOMContentLoaded', () => App.init());
