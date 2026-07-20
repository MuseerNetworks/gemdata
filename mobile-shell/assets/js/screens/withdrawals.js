const ScreenWithdrawals = {
  container: null,
  balance: 0,
  minimum: 0,
  history: [],

  async mount(container) {
    this.container = container;
    this.renderLoading();
    this.loadData();
  },

  renderLoading() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Withdraw Commissions')}
        
        <main class="app-main">
          <!-- Loading Skeletons -->
          <div class="shimmer-skeleton skeleton-card" style="margin-bottom: 20px;"></div>
          <div class="shimmer-skeleton skeleton-text" style="width: 40%; margin-bottom: 12px;"></div>
          <div class="shimmer-skeleton skeleton-list-item" style="margin-bottom: 10px;"></div>
          <div class="shimmer-skeleton skeleton-list-item" style="margin-bottom: 10px;"></div>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  async loadData() {
    const response = await Api.get('/user/withdrawals.php');
    if (response.success && response.data) {
      this.balance = response.data.balance;
      this.minimum = response.data.minimum_amount;
      this.history = response.data.history || [];
      this.render();
      
      const scrollEl = this.container.querySelector('.app-main');
      App.enablePullToRefresh(scrollEl, async () => {
        await this.loadData();
      });
    } else {
      this.renderError(response.message);
    }
  },

  renderError(msg = 'Failed to load details.') {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Withdraw Commissions')}
        <main class="app-main" style="text-align: center; padding-top: 40px;">
          <div style="font-size: 3rem; margin-bottom: 16px;">⚠️</div>
          <h4 style="font-weight: 700; color: var(--color-text);">Error Loading Wallet</h4>
          <p style="color: var(--color-text-muted); font-size: 0.82rem; margin-top: 4px; padding: 0 20px;">${msg}</p>
          <button class="btn-primary" onclick="ScreenWithdrawals.loadData()" style="margin-top: 20px; width: auto; padding: 10px 24px;">Retry Loading</button>
        </main>
        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  render() {
    const minVal = this.minimum;
    const canWithdraw = this.balance >= minVal;

    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Withdraw Commissions')}
        
        <main class="app-main ptr-container">
          <!-- Balance Summary Banner -->
          <div style="background: var(--color-primary); color: #fff; border-radius: var(--radius-md); padding: 20px; margin-bottom: 20px; box-shadow: var(--shadow-md);">
            <span style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; opacity: 0.85;">Withdrawable Commission Balance</span>
            <h1 style="font-size: 1.8rem; font-weight: 900; margin-top: 4px; font-family: monospace;">NGN ${this.balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}</h1>
            <p style="font-size: 0.75rem; margin-top: 8px; opacity: 0.9;">Minimum payout threshold: <strong>NGN ${minVal.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></p>
          </div>

          <div id="withdrawal-feedback"></div>

          <!-- Withdrawal Request form -->
          <form id="withdrawal-form" style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 30px;">
            <div class="form-group">
              <label class="form-label" for="wd-amount">Amount to Withdraw</label>
              <input class="form-control" type="number" id="wd-amount" min="${minVal}" max="${this.balance}" step="0.01" value="${canWithdraw ? minVal : ''}" required placeholder="Min NGN ${minVal}">
            </div>

            <div class="form-group">
              <label class="form-label" for="wd-bank">Bank Name</label>
              <input class="form-control" type="text" id="wd-bank" required placeholder="e.g. Access Bank">
            </div>

            <div class="form-group">
              <label class="form-label" for="wd-acct-no">Account Number</label>
              <input class="form-control" type="text" id="wd-acct-no" required placeholder="10 digits account number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10">
            </div>

            <div class="form-group">
              <label class="form-label" for="wd-acct-name">Account Name</label>
              <input class="form-control" type="text" id="wd-acct-name" required placeholder="Enter beneficiary name">
            </div>

            <button class="btn-primary" type="submit" id="wd-submit" ${!canWithdraw ? 'disabled' : ''} style="margin-top: 8px;">
              Submit Withdrawal Request
            </button>
          </form>

          <!-- Request History List -->
          <div>
            <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px;">Payout Request History</h4>
            <div class="tx-list">
              ${this.history.length === 0 ? `
                <div style="text-align: center; padding: 32px 16px; border: 1.5px dashed var(--color-border); border-radius: var(--radius-md); color: var(--color-text-muted); font-size: 0.8rem; font-weight: 600;">
                  No payout transactions recorded.
                </div>
              ` : this.history.map(row => `
                <div class="tx-item">
                  <div class="tx-left">
                    <span class="tx-title">${row.bank_name} - ${row.account_number}</span>
                    <span class="tx-meta">${row.created_at}</span>
                  </div>
                  <div class="tx-right" style="text-align: right;">
                    <span class="tx-amount" style="font-family: monospace;">NGN ${Number(row.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                    <span class="badge badge-${row.status === 'approved' ? 'success' : row.status === 'pending' ? 'warning' : 'danger'}" style="font-size: 0.65rem; margin-top: 4px; display: inline-block;">
                      ${row.status.toUpperCase()}
                    </span>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;

    document.getElementById('withdrawal-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('withdrawal-feedback');
    const submitBtn = document.getElementById('wd-submit');

    const amount = document.getElementById('wd-amount').value;
    const bank_name = document.getElementById('wd-bank').value.trim();
    const account_number = document.getElementById('wd-acct-no').value.trim();
    const account_name = document.getElementById('wd-acct-name').value.trim();

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting Request...';

    const payload = { amount, bank_name, account_number, account_name };
    const response = await Api.post('/user/withdrawals.php', payload);

    if (response.success) {
      feedback.innerHTML = `<div class="alert alert-success">${response.message}</div>`;
      if (window.Logger) {
        window.Logger.triggerHaptic('success');
      }
      setTimeout(() => {
        this.loadData();
      }, 1500);
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">${response.message || 'Submission failed.'}</div>`;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit Withdrawal Request';
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenWithdrawals = ScreenWithdrawals;
