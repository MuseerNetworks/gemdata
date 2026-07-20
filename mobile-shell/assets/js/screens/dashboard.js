const ScreenDashboard = {
  container: null,

  async mount(container) {
    this.container = container;
    
    // 1. Initial screen structure shell
    this.renderSkeleton();

    // 2. Fetch cached content from localStorage for instant load
    const cache = localStorage.getItem('gemdata_dashboard_cache');
    if (cache) {
      try {
        const cachedData = JSON.parse(cache);
        this.renderData(cachedData);
      } catch (err) {
        console.error('Failed to parse dashboard cache:', err);
      }
    }

    // 3. Trigger API call to fetch fresh data in background (non-blocking so splash screen hides immediately)
    this.fetchFreshData();

    // 4. Configure Pull-to-refresh gesture
    const scrollEl = this.container.querySelector('.app-main');
    App.enablePullToRefresh(scrollEl, async () => {
      await this.fetchFreshData();
    });
  },

  renderSkeleton() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Dashboard')}
        
        <main class="app-main" id="dashboard-content">
          <!-- Balance Card -->
          <div class="wallet-card">
            <div class="wallet-label">Main Wallet Balance</div>
            <div class="wallet-amount" id="dash-balance">NGN 0.00</div>
            <div style="font-size: 0.8rem; font-weight: 700; opacity: 0.9;" id="dash-role">Smart User</div>
          </div>

          <!-- Quick Services -->
          <div style="margin-bottom: 24px;">
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Utility Services</h3>
            <div class="grid-cols-2" style="grid-template-columns: repeat(3, 1fr); gap: 10px;">
              <button class="menu-card" onclick="Router.navigate('#/buy?service=airtime')" style="padding: 12px 8px; text-align: center; align-items: center;">
                <div class="icon-box green" style="width: 36px; height: 36px;">
                  <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; margin-top: 4px;">Airtime</div>
              </button>

              <button class="menu-card" onclick="Router.navigate('#/buy?service=data')" style="padding: 12px 8px; text-align: center; align-items: center;">
                <div class="icon-box blue" style="width: 36px; height: 36px;">
                  <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0"/>
                  </svg>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; margin-top: 4px;">Data</div>
              </button>

              <button class="menu-card" onclick="Router.navigate('#/cable')" style="padding: 12px 8px; text-align: center; align-items: center;">
                <div class="icon-box purple" style="width: 36px; height: 36px;">
                  <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                  </svg>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; margin-top: 4px;">Cable TV</div>
              </button>

              <button class="menu-card" onclick="Router.navigate('#/electricity')" style="padding: 12px 8px; text-align: center; align-items: center;">
                <div class="icon-box orange" style="width: 36px; height: 36px;">
                  <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                  </svg>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; margin-top: 4px;">Electricity</div>
              </button>

              <button class="menu-card" onclick="Router.navigate('#/exam')" style="padding: 12px 8px; text-align: center; align-items: center;">
                <div class="icon-box green" style="width: 36px; height: 36px; background: hsl(172, 70%, 40%);">
                  <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; margin-top: 4px;">Exam PINs</div>
              </button>

              <button class="menu-card" onclick="Router.navigate('#/upgrade')" style="padding: 12px 8px; text-align: center; align-items: center;">
                <div class="icon-box blue" style="width: 36px; height: 36px; background: hsl(342, 85%, 55%);">
                  <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                  </svg>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; margin-top: 4px;">Upgrade</div>
              </button>
            </div>
          </div>

          <!-- Quick Re-purchase Widget Staging Container -->
          <div id="dashboard-recent-purchases-container"></div>

          <!-- Funding Accounts -->
          <div id="funding-section" style="margin-bottom: 24px;">
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Bank Funding Accounts</h3>
            <div id="funding-accounts-container" style="display: flex; flex-direction: column; gap: 8px;">
              <div class="shimmer-skeleton skeleton-list-item" style="height: 52px;"></div>
            </div>
          </div>

          <!-- Recent Transactions -->
          <div>
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Recent Activity</h3>
            <div id="recent-transactions-container" class="tx-list">
              <div class="shimmer-skeleton skeleton-list-item" style="height: 60px; margin-bottom: 8px;"></div>
              <div class="shimmer-skeleton skeleton-list-item" style="height: 60px;"></div>
            </div>
          </div>
        </main>

        ${App.renderNavigation('#/dashboard')}
      </div>
    `;
  },

  async fetchFreshData() {
    const result = await Api.get('/user/dashboard.php');
    if (result.success) {
      // Save output to Cache
      localStorage.setItem('gemdata_dashboard_cache', JSON.stringify(result.data));
      this.renderData(result.data);
    }
  },

  renderData(data) {
    // 1. Update Header balance and Dashboard balance
    const formattedBalance = Number(data.wallet.balance).toLocaleString('en-US', { minimumFractionDigits: 2 });
    App.updateHeaderBalance(data.wallet.balance);
    
    const dashBalanceEl = document.getElementById('dash-balance');
    if (dashBalanceEl) {
      dashBalanceEl.textContent = `NGN ${formattedBalance}`;
    }

    const dashRoleEl = document.getElementById('dash-role');
    if (dashRoleEl) {
      dashRoleEl.textContent = data.user.role_label || 'Smart User';
    }

    // Render Recent purchases widget
    const recentsContainer = document.getElementById('dashboard-recent-purchases-container');
    if (recentsContainer) {
      recentsContainer.innerHTML = StorageHelper.renderRecentPurchasesWidget();
    }
    
    // 2. Render Funding Accounts list
    const fundingContainer = document.getElementById('funding-accounts-container');
    if (fundingContainer) {
      if (data.funding_accounts && data.funding_accounts.length > 0) {
        fundingContainer.innerHTML = data.funding_accounts.map(acc => `
          <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm);">
            <div>
              <div style="font-size: 0.88rem; font-weight: 800; color: var(--color-text);">${acc.bank_name}</div>
              <div style="font-size: 0.95rem; font-weight: 800; font-family: monospace; color: var(--color-primary); margin-top: 2px;">${acc.account_number}</div>
              <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px;">${acc.account_name}</div>
            </div>
            <button onclick="ScreenDashboard.copyAccountNumber('${acc.account_number}')" style="border: 0; background: var(--color-primary-soft); color: var(--color-primary); font-size: 0.75rem; font-weight: 800; padding: 6px 12px; border-radius: 8px; cursor: pointer;">
              Copy
            </button>
          </div>
        `).join('');
      } else {
        fundingContainer.innerHTML = `
          <div style="font-size: 0.85rem; color: var(--color-text-muted); background: var(--color-surface); border: 1px solid var(--color-border); padding: 12px; border-radius: var(--radius-md); text-align: center;">
            No dynamic funding account assigned yet. Contact admin.
          </div>
        `;
      }
    }

    // 3. Render Recent Transactions log list
    const txContainer = document.getElementById('recent-transactions-container');
    if (txContainer) {
      if (data.recent_transactions && data.recent_transactions.length > 0) {
        txContainer.innerHTML = data.recent_transactions.map(tx => `
          <div class="tx-item">
            <div class="tx-left">
              <span class="tx-title">${tx.service} - ${tx.recipient}</span>
              <span class="tx-meta">${tx.created_at}</span>
            </div>
            <div class="tx-right">
              <span class="tx-amount">NGN ${Number(tx.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
              <div>
                <span class="badge badge-${tx.status}">${tx.status}</span>
              </div>
            </div>
          </div>
        `).join('');
      } else {
        txContainer.innerHTML = `
          <div style="font-size: 0.85rem; color: var(--color-text-muted); background: var(--color-surface); border: 1px solid var(--color-border); padding: 12px; border-radius: var(--radius-md); text-align: center;">
            No transactions found yet.
          </div>
        `;
      }

      if (window.SyncEngine) {
        window.SyncEngine.refreshLocalTransactionLogs();
      }
    }
  },

  async copyAccountNumber(num) {
    try {
      await navigator.clipboard.writeText(num);
      alert('Account number copied to clipboard.');
    } catch (err) {
      console.warn('Clipboard write fallback failed:', err);
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenDashboard = ScreenDashboard;
