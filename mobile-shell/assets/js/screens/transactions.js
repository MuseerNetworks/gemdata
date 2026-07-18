const ScreenTransactions = {
  container: null,

  mount(container) {
    this.container = container;
    container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Transaction History')}
        
        <main class="app-main">
          <div style="margin-bottom: 16px;">
            <p style="font-size: 0.88rem; color: var(--color-text-muted);">View details of your recent recharges and service requests.</p>
          </div>

          <div id="history-tx-container" class="tx-list">
            <div style="font-size: 0.88rem; color: var(--color-text-muted);">Fetching transactions...</div>
          </div>
        </main>

        ${App.renderNavigation('#/transactions')}
      </div>
    `;

    this.loadData();
  },

  loadData() {
    const cache = localStorage.getItem('gemdata_dashboard_cache');
    if (cache) {
      try {
        const data = JSON.parse(cache);
        const container = document.getElementById('history-tx-container');
        
        // Update balance indicator in header
        App.updateHeaderBalance(data.wallet.balance);

        if (container) {
          if (data.recent_transactions && data.recent_transactions.length > 0) {
            container.innerHTML = data.recent_transactions.map(tx => `
              <div class="tx-item">
                <div class="tx-left">
                  <span class="tx-title">${tx.service} - ${tx.recipient}</span>
                  <span class="tx-meta">${tx.created_at}</span>
                  <span class="tx-meta" style="font-family: monospace; font-size: 0.7rem; color: var(--color-primary);">${tx.reference}</span>
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
            container.innerHTML = `
              <div style="font-size: 0.85rem; color: var(--color-text-muted); background: var(--color-surface); border: 1px solid var(--color-border); padding: 16px; border-radius: var(--radius-md); text-align: center;">
                No transactions found.
              </div>
            `;
          }
        }
      } catch (err) {
        console.error('Failed to render transaction history:', err);
      }
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenTransactions = ScreenTransactions;
