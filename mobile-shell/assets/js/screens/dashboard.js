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

    // 3. Trigger API call to fetch fresh data
    await this.fetchFreshData();
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
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Quick Services</h3>
            <div class="grid-cols-2">
              <button class="menu-card" onclick="Router.navigate('#/buy?service=airtime')">
                <div class="icon-box green">
                  <svg style="width: 22px; height: 22px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M3 10h18M3 15h12M3 20h7"/>
                  </svg>
                </div>
                <div style="font-size: 0.88rem; font-weight: 800;">Buy Airtime</div>
              </button>

              <button class="menu-card" onclick="Router.navigate('#/buy?service=data')">
                <div class="icon-box blue">
                  <svg style="width: 22px; height: 22px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0"/>
                  </svg>
                </div>
                <div style="font-size: 0.88rem; font-weight: 800;">Buy Data</div>
              </button>
            </div>
          </div>

          <!-- Funding Accounts -->
          <div id="funding-section" style="margin-bottom: 24px;">
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Bank Funding Accounts</h3>
            <div id="funding-accounts-container" style="display: flex; flex-direction: column; gap: 8px;">
              <div style="font-size: 0.88rem; color: var(--color-text-muted);">Fetching accounts...</div>
            </div>
          </div>

          <!-- Recent Transactions -->
          <div>
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Recent Activity</h3>
            <div id="recent-transactions-container" class="tx-list">
              <div style="font-size: 0.88rem; color: var(--color-text-muted);">Fetching transactions...</div>
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
