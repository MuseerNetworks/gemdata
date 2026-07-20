const ScreenWallet = {
  container: null,

  mount(container) {
    this.container = container;
    container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Wallet Funding')}
        
        <main class="app-main">
          <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 20px; text-align: center;">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;">Funding Method</div>
            <h2 style="font-size: 1.4rem; font-weight: 800; margin-top: 4px; color: var(--color-primary);">Bank Transfer</h2>
            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-top: 6px;">Transfer funds to any of the bank accounts below to fund your wallet instantly.</p>
          </div>

          <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; color: var(--color-text);">Your Virtual Bank Accounts</h3>
          <div id="wallet-banks-container" style="display: flex; flex-direction: column; gap: 12px;">
            <div class="shimmer-skeleton skeleton-list-item" style="height: 120px;"></div>
          </div>
        </main>

        ${App.renderNavigation('#/wallet')}
      </div>
    `;

    this.loadData();

    // Configure Pull-to-refresh gesture
    const scrollEl = this.container.querySelector('.app-main');
    App.enablePullToRefresh(scrollEl, async () => {
      const result = await Api.get('/user/dashboard.php');
      if (result.success) {
        localStorage.setItem('gemdata_dashboard_cache', JSON.stringify(result.data));
        this.loadData();
      }
    });
  },

  loadData() {
    const cache = localStorage.getItem('gemdata_dashboard_cache');
    if (cache) {
      try {
        const data = JSON.parse(cache);
        const container = document.getElementById('wallet-banks-container');
        if (container) {
          if (data.funding_accounts && data.funding_accounts.length > 0) {
            container.innerHTML = data.funding_accounts.map(acc => `
              <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 18px; box-shadow: var(--shadow-sm);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                  <div>
                    <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--color-text-muted);">Bank Partner</span>
                    <h4 style="font-size: 1rem; font-weight: 800; color: var(--color-text); margin-top: 1px;">${acc.bank_name}</h4>
                  </div>
                  <button onclick="ScreenWallet.copy('${acc.account_number}')" style="border: 0; background: var(--color-primary-soft); color: var(--color-primary); font-size: 0.75rem; font-weight: 800; padding: 6px 12px; border-radius: 8px; cursor: pointer;">
                    Copy Number
                  </button>
                </div>
                
                <div style="margin-bottom: 10px;">
                  <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--color-text-muted);">Account Number</span>
                  <div style="font-size: 1.2rem; font-weight: 800; font-family: monospace; color: var(--color-primary); margin-top: 1px;">${acc.account_number}</div>
                </div>

                <div>
                  <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--color-text-muted);">Account Name</span>
                  <div style="font-size: 0.88rem; font-weight: 700; color: var(--color-text); margin-top: 1px;">${acc.account_name}</div>
                </div>
              </div>
            `).join('');
          } else {
            container.innerHTML = `
              <div style="font-size: 0.85rem; color: var(--color-text-muted); background: var(--color-surface); border: 1px solid var(--color-border); padding: 16px; border-radius: var(--radius-md); text-align: center;">
                No dynamic funding account assigned yet. Contact admin.
              </div>
            `;
          }
        }
      } catch (err) {
        console.error('Failed to render wallet accounts:', err);
      }
    }
  },

  async copy(num) {
    try {
      await navigator.clipboard.writeText(num);
      alert('Copied to clipboard.');
    } catch (err) {
      console.warn('Clipboard write fallback failed:', err);
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenWallet = ScreenWallet;
