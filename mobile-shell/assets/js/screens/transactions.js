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

    // Configure Pull-to-refresh gesture
    const scrollEl = this.container.querySelector('.app-main');
    App.enablePullToRefresh(scrollEl, async () => {
      await this.loadData();
    });
  },

  async loadData() {
    const container = document.getElementById('history-tx-container');
    if (!container) return;

    container.innerHTML = `
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <div class="shimmer-skeleton skeleton-list-item" style="height: 60px;"></div>
        <div class="shimmer-skeleton skeleton-list-item" style="height: 60px;"></div>
        <div class="shimmer-skeleton skeleton-list-item" style="height: 60px;"></div>
      </div>
    `;

    const response = await Api.get('/user/transactions.php');
    if (response.success && response.data) {
      const data = response.data;
      if (data.length > 0) {
        container.innerHTML = data.map(tx => `
          <div class="tx-item" style="cursor: pointer; transition: transform 0.2s;" onclick="Router.navigate('#/receipt?reference=${tx.reference}')">
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
      
      if (window.SyncEngine) {
        window.SyncEngine.refreshLocalTransactionLogs();
      }
    } else {
      container.innerHTML = `
        <div class="alert alert-danger" style="margin: 0; padding: 10px;">
          ${response.message || 'Unable to sync history.'}
        </div>
      `;
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenTransactions = ScreenTransactions;
