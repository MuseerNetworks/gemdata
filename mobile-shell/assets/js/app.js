const App = {
  splashHidden: false,

  async init() {
    this.hideSplashScreenAfterPaint();
    setTimeout(() => this.hideSplashScreen(), 2500);

    try {
      // 0. Initialize theme setting state
      this.Theme.init();

      // 1. Initialize Capacitor Plugins if available
      this.initCapacitor();

      // 2. Setup Offline Detection Banner listener
      this.initOfflineMonitor();

      // Initialize background offline transaction sync engine
      SyncEngine.init();

      // Push notifications require Firebase configuration; do not register at startup.

      // 3. Register screens inside the Router
      Router.add('#/welcome', ScreenWelcome);
      Router.add('#/login', ScreenLogin);
      Router.add('#/register', ScreenRegister);
      Router.add('#/dashboard', ScreenDashboard);
      Router.add('#/services', ScreenServices);
      Router.add('#/recharge', ScreenRecharge);
      Router.add('#/bulksms', ScreenBulkSMS);
      Router.add('#/withdrawals', ScreenWithdrawals);
      Router.add('#/referrals', ScreenReferrals);
      Router.add('#/wallet', ScreenWallet);
      Router.add('#/buy', ScreenBuy);
      Router.add('#/cable', ScreenCable);
      Router.add('#/electricity', ScreenElectricity);
      Router.add('#/exam', ScreenExam);
      Router.add('#/security', ScreenSecurity);
      Router.add('#/upgrade', ScreenUpgrade);
      Router.add('#/receipt', ScreenReceipt);
      Router.add('#/profile', ScreenProfile);
      Router.add('#/transactions', ScreenTransactions);

      // 4. Session validation and recovery on startup
      const token = localStorage.getItem('gemdata_refresh_token');
      const welcomeSeen = localStorage.getItem('gemdata_welcome_seen');
      const currentHash = window.location.hash;
      const isUnauthRoute = currentHash === '#/login' || currentHash === '#/register' || currentHash === '#/welcome';

      if (!welcomeSeen) {
        if (currentHash !== '#/welcome') {
          window.location.hash = '#/welcome';
        }
      } else if (!token) {
        if (!isUnauthRoute) {
          window.location.hash = '#/login';
        }
      } else {
        // Validate refresh token and restore user details
        if (isUnauthRoute || !currentHash) {
          window.location.hash = '#/dashboard';
        }

        // Perform a non-blocking check on session status
        Api.get('/auth/session.php').then(result => {
          if (result.success) {
            localStorage.setItem('gemdata_user_cache', JSON.stringify(result.data.user));
            this.updateHeaderBalance(result.data.user.balance);
          }
        }).catch(err => {
          console.warn('Silent startup session recovery failed:', err);
        });
      }

      // 5. Initialize Router
      Router.init();
    } catch (err) {
      console.error('GemData mobile startup failed:', err);
      this.renderStartupFallback(err);
    } finally {
      this.hideSplashScreenAfterPaint();
    }
  },

  async initCapacitor() {
    if (window.Capacitor) {
      const { StatusBar, App: NativeApp } = window.Capacitor.Plugins;
      
      try {
        // Set Status Bar aesthetics
        if (StatusBar) {
          await StatusBar.setBackgroundColor({ color: '#1B4DFF' });
          await StatusBar.setStyle({ style: 'DARK' });
        }

        // Configure Back Button native behavior
        if (NativeApp) {
          NativeApp.addListener('backButton', () => {
            const currentHash = window.location.hash || '#/dashboard';
            if (currentHash === '#/dashboard' || currentHash === '#/login' || currentHash === '#/welcome') {
              NativeApp.exitApp();
            } else {
              window.history.back();
            }
          });
        }
      } catch (err) {
        console.warn('Capacitor plugin startup warning:', err);
      }
    }
  },

  hideSplashScreen() {
    if (this.splashHidden) {
      return;
    }

    if (window.Capacitor) {
      const { SplashScreen } = window.Capacitor.Plugins;
      if (SplashScreen) {
        this.splashHidden = true;
        // Safe timeout delay of 300ms to guarantee DOM rendering settles first
        setTimeout(() => {
          SplashScreen.hide().catch(() => {});
        }, 300);
      }
    }
  },

  hideSplashScreenAfterPaint() {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => this.hideSplashScreen());
    });
  },

  renderStartupFallback(err) {
    const container = document.getElementById('app-container');
    if (!container) {
      return;
    }

    const welcomeSeen = localStorage.getItem('gemdata_welcome_seen');
    container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; justify-content: center; min-height: 100vh; padding: 24px; text-align: center;">
        <h1 style="font-size: 2rem; font-weight: 900; color: var(--color-primary);">GemData</h1>
        <p style="margin-top: 8px; color: var(--color-text-muted); line-height: 1.5;">Preparing your secure workspace...</p>
      </div>
    `;

    setTimeout(() => {
      window.location.hash = welcomeSeen ? '#/login' : '#/welcome';
    }, 800);

    if (window.Logger) {
      window.Logger.logError({
        message: err && err.message ? err.message : 'Mobile startup failed',
        stack: err && err.stack ? err.stack : ''
      }, 'MOBILE_STARTUP_EXCEPTION');
    }
  },

  initOfflineMonitor() {
    const banner = document.getElementById('offline-banner');
    
    const updateStatus = () => {
      if (navigator.onLine) {
        banner?.classList.remove('active');
        // Auto trigger queue synchronization when connection is recovered!
        if (window.SyncEngine && typeof window.SyncEngine.processQueue === 'function') {
          window.SyncEngine.processQueue();
        }
      } else {
        banner?.classList.add('active');
      }
    };

    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    updateStatus(); // Initial run
  },

  Theme: {
    init() {
      const theme = localStorage.getItem('gemdata_theme') || 'system';
      this.apply(theme);

      // Listen for system theme preference changes
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (localStorage.getItem('gemdata_theme') === 'system') {
          this.apply('system');
        }
      });
    },
    apply(theme) {
      localStorage.setItem('gemdata_theme', theme);
      if (theme === 'system') {
        const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
      } else {
        document.documentElement.setAttribute('data-theme', theme);
      }
    }
  },

  enablePullToRefresh(scrollableEl, refreshCallback) {
    if (!scrollableEl) return;
    
    let ptrEl = scrollableEl.querySelector('.ptr-loading');
    if (!ptrEl) {
      ptrEl = document.createElement('div');
      ptrEl.className = 'ptr-loading';
      ptrEl.innerHTML = '<div class="ptr-spinner"></div>';
      scrollableEl.insertBefore(ptrEl, scrollableEl.firstChild);
    }
    
    let startY = 0;
    let currentY = 0;
    let isPulling = false;
    const threshold = 70;
    
    scrollableEl.addEventListener('touchstart', (e) => {
      if (scrollableEl.scrollTop === 0) {
        startY = e.touches[0].pageY;
        isPulling = true;
        ptrEl.classList.add('pulling');
      }
    }, { passive: true });
    
    scrollableEl.addEventListener('touchmove', (e) => {
      if (!isPulling) return;
      currentY = e.touches[0].pageY;
      const diff = currentY - startY;
      
      if (diff > 0) {
        const pullDistance = Math.min(diff * 0.4, threshold + 20);
        ptrEl.style.transform = `translateY(${pullDistance}px)`;
        if (pullDistance >= threshold) {
          ptrEl.style.opacity = '1';
        }
      }
    }, { passive: true });
    
    scrollableEl.addEventListener('touchend', async () => {
      if (!isPulling) return;
      isPulling = false;
      ptrEl.classList.remove('pulling');
      
      const diff = currentY - startY;
      if (diff * 0.4 >= threshold) {
        ptrEl.style.transform = 'translateY(40px)';
        ptrEl.classList.add('loading');
        
        if (window.Logger) {
          window.Logger.triggerHaptic('medium');
        }
        
        try {
          await refreshCallback();
          if (window.Logger) {
            window.Logger.triggerHaptic('success');
          }
        } catch (err) {
          if (window.Logger) {
            window.Logger.triggerHaptic('error');
          }
        }
      }
      
      ptrEl.classList.remove('loading');
      ptrEl.style.transform = 'none';
      ptrEl.style.opacity = '0';
      startY = 0;
      currentY = 0;
    });
  },

  // Helper utility to render a consistent header layout
  renderHeader(title) {
    return `
      <header class="app-header">
        <div style="display: flex; align-items: center;">
          <button class="sidebar-trigger" onclick="App.toggleSidebar(true)">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <div class="app-title">${title}</div>
        </div>
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
      { route: '#/services', label: 'Services', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
      { route: '#/transactions', label: 'History', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
      { route: '#/profile', label: 'Profile', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' }
    ];

    return `
      <nav class="app-nav">
        ${items.map(item => {
          const isActive = activeRoute === item.route || 
            (item.route === '#/services' && (activeRoute.startsWith('#/buy') || activeRoute === '#/cable' || activeRoute === '#/electricity' || activeRoute === '#/exam' || activeRoute === '#/recharge' || activeRoute === '#/bulksms' || activeRoute === '#/withdrawals' || activeRoute === '#/referrals'));
          return `
            <button class="nav-item ${isActive ? 'active' : ''}" onclick="Router.navigate('${item.route}')">
              <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="${item.icon}"/>
              </svg>
              <span>${item.label}</span>
            </button>
          `;
        }).join('')}
      </nav>
    `;
  },

  // Auto updates wallet balances displayed across panels
  updateHeaderBalance(balance) {
    const el = document.getElementById('header-balance');
    if (el) {
      el.textContent = `NGN ${Number(balance).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    }
  },

  // Toggle sidebar overlay visibility and sync details
  toggleSidebar(show) {
    const sidebar = document.getElementById('app-sidebar');
    if (sidebar) {
      if (show) {
        sidebar.classList.add('active');
        this.updateSidebarUser();
      } else {
        sidebar.classList.remove('active');
      }
    }
  },

  // Sync sidebar user credentials with cache
  updateSidebarUser() {
    try {
      const userCache = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
      const name = userCache.full_name || 'Guest User';
      const tier = userCache.tier || 'Smart User';
      
      const initialEl = document.getElementById('sidebar-user-initial');
      const nameEl = document.getElementById('sidebar-user-name');
      const tierEl = document.getElementById('sidebar-user-tier');
      
      if (initialEl) initialEl.textContent = name.charAt(0).toUpperCase();
      if (nameEl) nameEl.textContent = name;
      if (tierEl) tierEl.textContent = tier.toUpperCase();
    } catch (e) {
      console.warn('Could not populate sidebar profile info:', e);
    }
  },

  openWhatsAppSupport() {
    if (window.Logger) {
      window.Logger.triggerHaptic('light');
    }
    const phone = '2348155568369';
    const appUrl = `whatsapp://send?phone=${phone}`;
    const webUrl = `https://wa.me/${phone}`;
    
    try {
      window.open(appUrl, '_system');
    } catch (e) {
      try {
        window.open(webUrl, '_system');
      } catch (err) {
        alert('Could not open WhatsApp. Please message us manually at +2348155568369.');
      }
    }
  },

  // Clean sign out and redirection
  async logout() {
    if (confirm('Are you sure you want to sign out?')) {
      const result = await Api.post('/auth/logout.php');
      localStorage.removeItem('gemdata_refresh_token');
      localStorage.removeItem('gemdata_user_cache');
      localStorage.removeItem('gemdata_dashboard_cache');
      window.location.hash = '#/login';
    }
  }
};

window.App = App;
if (document.readyState === 'loading') {
  window.addEventListener('DOMContentLoaded', () => App.init());
} else {
  App.init();
}
