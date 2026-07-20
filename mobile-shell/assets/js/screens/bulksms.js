const ScreenBulkSMS = {
  container: null,
  rate: 4.00, // Default fallback rate (corresponds to database plan amount)

  mount(container) {
    this.container = container;
    this.renderForm();
  },

  renderForm() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Bulk SMS Dispatch')}
        
        <main class="app-main">
          <div style="margin-bottom: 20px;">
            <p style="font-size: 0.88rem; color: var(--color-text-muted);">Send campaigns or customized announcements to multiple contacts instantly.</p>
          </div>

          <div id="bulksms-feedback"></div>

          <form id="bulksms-form" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Sender ID -->
            <div class="form-group">
              <label class="form-label" for="sms-sender">Sender ID (Max 11 Characters)</label>
              <input class="form-control" type="text" id="sms-sender" maxlength="11" placeholder="e.g. GemData" required autocomplete="off">
            </div>

            <!-- Recipients -->
            <div class="form-group">
              <label class="form-label" for="sms-recipients">Recipients (Comma separated)</label>
              <textarea class="form-control" id="sms-recipients" rows="4" placeholder="e.g. 08030000000, 08120000000" required oninput="ScreenBulkSMS.calculateCost()"></textarea>
            </div>

            <!-- Message Body -->
            <div class="form-group">
              <label class="form-label" for="sms-message">Message Body (160 characters = 1 page)</label>
              <textarea class="form-control" id="sms-message" rows="5" placeholder="Type your message here..." required oninput="ScreenBulkSMS.calculateCost()"></textarea>
            </div>

            <!-- Pricing Estimator box -->
            <div id="sms-estimator" style="background: var(--color-surface-soft); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; display: grid; grid-template-columns: repeat(3, 1fr); text-align: center; gap: 12px; font-size: 0.82rem;">
              <div>
                <strong id="sms-recipient-count" style="display: block; font-size: 1.15rem; font-weight: 800; color: var(--color-text);">0</strong>
                <span style="color: var(--color-text-muted);">Recipients</span>
              </div>
              <div>
                <strong id="sms-pages-count" style="display: block; font-size: 1.15rem; font-weight: 800; color: var(--color-text);">0</strong>
                <span style="color: var(--color-text-muted);">Pages</span>
              </div>
              <div>
                <strong id="sms-estimated-cost" style="display: block; font-size: 1.15rem; font-weight: 800; color: var(--color-primary);">NGN 0.00</strong>
                <span style="color: var(--color-text-muted);">Est. Cost</span>
              </div>
            </div>

            <!-- Security PIN -->
            <div class="form-group">
              <label class="form-label" for="sms-pin">Wallet Security PIN</label>
              <input class="form-control" type="password" id="sms-pin" required placeholder="Enter transaction PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6">
            </div>

            <button class="btn-primary" type="submit" id="sms-submit" style="margin-top: 8px;">
              Send Bulk SMS Now
            </button>
          </form>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;

    document.getElementById('bulksms-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  calculateCost() {
    const recipientsInput = document.getElementById('sms-recipients').value;
    const messageInput = document.getElementById('sms-message').value;

    const recipients = recipientsInput.split(',')
      .map(num => num.trim())
      .filter(num => num.length > 0);
    
    const recipientCount = recipients.length;
    
    // Page count: 160 chars per page
    const charCount = messageInput.length;
    const pageCount = charCount === 0 ? 0 : Math.ceil(charCount / 160);
    
    const totalCost = recipientCount * pageCount * this.rate;

    document.getElementById('sms-recipient-count').textContent = recipientCount;
    document.getElementById('sms-pages-count').textContent = pageCount;
    document.getElementById('sms-estimated-cost').textContent = `NGN ${totalCost.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    
    return { recipientCount, pageCount, totalCost };
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('bulksms-feedback');
    const submitBtn = document.getElementById('sms-submit');

    const sender = document.getElementById('sms-sender').value.trim();
    const recipients = document.getElementById('sms-recipients').value.trim();
    const message = document.getElementById('sms-message').value.trim();
    const pin = document.getElementById('sms-pin').value;

    const calculations = this.calculateCost();
    if (calculations.recipientCount === 0) {
      feedback.innerHTML = '<div class="alert alert-danger">Please enter at least one recipient.</div>';
      return;
    }

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending SMS...';

    const payload = {
      sender,
      recipients,
      message,
      amount: calculations.totalCost,
      security_pin: pin
    };

    if (!navigator.onLine) {
      const desc = `Bulk SMS to ${calculations.recipientCount} Recips (${calculations.pageCount} pgs)`;
      SyncEngine.enqueue('/services/buy-bulksms.php', payload, desc);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Bulk SMS Now';
      feedback.innerHTML = `
        <div class="alert alert-warning">
          <strong>Offline Mode!</strong> Your Bulk SMS request has been queued and will dispatch once online.
        </div>
      `;
      return;
    }

    const response = await Api.post('/services/buy-bulksms.php', payload);
    
    if (response.success) {
      feedback.innerHTML = `
        <div class="alert alert-success">
          Bulk SMS sent successfully!
        </div>
      `;

      if (response.data.wallet_balance) {
        App.updateHeaderBalance(response.data.wallet_balance);
        const userCache = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
        userCache.balance = response.data.wallet_balance;
        localStorage.setItem('gemdata_user_cache', JSON.stringify(userCache));
      }

      setTimeout(() => {
        Router.navigate(`#/receipt?reference=${response.data.reference}`);
      }, 1500);
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">${response.message || 'Transaction failed.'}</div>`;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Bulk SMS Now';
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenBulkSMS = ScreenBulkSMS;
