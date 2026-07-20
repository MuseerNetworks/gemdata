const ScreenReceipt = {
  container: null,

  async mount(container, params) {
    this.container = container;
    const reference = params.reference || '';

    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Transaction Receipt')}
        
        <main class="app-main" style="display: flex; flex-direction: column; gap: 20px; align-items: center; justify-content: center; padding: 24px 20px;">
          <div id="receipt-feedback" style="width: 100%;"></div>
          
          <div id="receipt-card-container" style="width: 100%; display: none;">
            <!-- Premium Receipt Invoice Wrapper -->
            <div id="receipt-invoice-card" style="background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px 24px; box-shadow: var(--shadow-lg); width: 100%; position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 20px;">
              <!-- Small Notch effect styling -->
              <div style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); width: 24px; height: 20px; background: var(--color-bg); border-radius: 99px; border: 1.5px solid var(--color-border);"></div>
              
              <!-- Branding Header -->
              <div style="text-align: center; border-bottom: 2px dashed var(--color-border); padding-bottom: 20px; margin-bottom: 8px;">
                <h2 style="font-size: 1.6rem; font-weight: 900; color: var(--color-primary); letter-spacing: -0.5px; margin: 0;">GemData</h2>
                <p style="font-size: 0.78rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Transaction Invoice</p>
              </div>

              <!-- Main Amount display -->
              <div style="text-align: center;">
                <div id="receipt-amount" style="font-size: 2.25rem; font-weight: 900; color: var(--color-text); letter-spacing: -1px;">NGN 0.00</div>
                <div id="receipt-service" style="font-size: 0.88rem; font-weight: 700; color: var(--color-text-muted); margin-top: 4px;">Service Purchase</div>
                <span id="receipt-status-badge" class="badge" style="margin-top: 8px; font-size: 0.72rem; padding: 4px 12px; border-radius: 12px;">PENDING</span>
              </div>

              <!-- Prepaid Token display box -->
              <div id="receipt-token-section" style="display: none; background: var(--color-primary-soft); border: 1px solid var(--color-primary); padding: 16px; border-radius: var(--radius-md); text-align: center; flex-direction: column; gap: 6px;">
                <span style="font-size: 0.72rem; font-weight: 800; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.5px;">Meter Token Code</span>
                <strong id="receipt-token" style="font-size: 1.3rem; font-family: monospace; color: var(--color-primary); letter-spacing: 1px; word-break: break-all;">-</strong>
                <button onclick="ScreenReceipt.copyText('receipt-token')" style="border: 0; background: none; color: var(--color-primary); font-size: 0.78rem; font-weight: 800; cursor: pointer; text-decoration: underline; margin-top: 4px;">Copy Token</button>
              </div>

              <!-- Exam PINs list display box -->
              <div id="receipt-exam-section" style="display: none; background: var(--color-success-soft); border: 1px solid var(--color-success); padding: 14px; border-radius: var(--radius-md); flex-direction: column; gap: 8px;">
                <span style="font-size: 0.72rem; font-weight: 800; color: var(--color-success); text-transform: uppercase; letter-spacing: 0.5px; text-align: center; display: block;">Exam PIN Codes</span>
                <div id="receipt-pins-list" style="display: flex; flex-direction: column; gap: 6px; font-family: monospace; font-size: 0.9rem; color: var(--color-success); font-weight: 800; text-align: center;"></div>
              </div>

              <!-- Detail Rows -->
              <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.88rem;">
                <div style="display: flex; justify-content: space-between;">
                  <span style="color: var(--color-text-muted); font-weight: 600;">Recipient:</span>
                  <strong id="receipt-recipient" style="color: var(--color-text);"></strong>
                </div>
                <div id="receipt-customer-row" style="display: flex; justify-content: space-between;">
                  <span style="color: var(--color-text-muted); font-weight: 600;">Customer Name:</span>
                  <strong id="receipt-customer-name" style="color: var(--color-text);"></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                  <span style="color: var(--color-text-muted); font-weight: 600;">Date:</span>
                  <span id="receipt-date" style="color: var(--color-text); font-weight: 700;"></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                  <span style="color: var(--color-text-muted); font-weight: 600;">Transaction Ref:</span>
                  <span id="receipt-ref" style="color: var(--color-text); font-weight: 700; font-family: monospace;"></span>
                </div>
                <div id="receipt-prov-row" style="display: flex; justify-content: space-between;">
                  <span style="color: var(--color-text-muted); font-weight: 600;">Provider Ref:</span>
                  <span id="receipt-prov-ref" style="color: var(--color-text); font-weight: 700; font-family: monospace;"></span>
                </div>
              </div>
            </div>

            <!-- Sharing/Actions Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%; margin-top: 24px;">
              <button class="btn-primary" onclick="ScreenReceipt.shareReceipt()" style="background: var(--color-surface); border: 1.5px solid var(--color-primary); color: var(--color-primary); box-shadow: none;">
                Share Details
              </button>
              <button class="btn-primary" onclick="Router.navigate('#/dashboard')">
                Back to Home
              </button>
            </div>
          </div>

          <!-- Loading Shimmer Placeholder -->
          <div id="receipt-shimmer" style="width: 100%;">
            <div class="shimmer-skeleton skeleton-card" style="width: 100%; margin-bottom: 16px;"></div>
            <div class="shimmer-skeleton skeleton-text" style="width: 60%; margin: 0 auto 12px;"></div>
            <div class="shimmer-skeleton skeleton-text" style="width: 80%; margin: 0 auto 24px;"></div>
            <div class="shimmer-skeleton skeleton-list-item" style="width: 100%;"></div>
          </div>
        </main>

        ${App.renderNavigation('#/transactions')}
      </div>
    `;

    await this.fetchDetails(reference);
  },

  async fetchDetails(reference) {
    const feedback = document.getElementById('receipt-feedback');
    const cardContainer = document.getElementById('receipt-card-container');
    const shimmer = document.getElementById('receipt-shimmer');

    if (!reference) {
      shimmer.style.display = 'none';
      feedback.innerHTML = `<div class="alert alert-danger">No transaction reference provided.</div>`;
      return;
    }

    const response = await Api.get(`/user/transaction-detail.php?reference=${reference}`);
    
    shimmer.style.display = 'none';

    if (response.success && response.data) {
      const data = response.data;
      this.populateData(data);
      cardContainer.style.display = 'block';
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">${response.message || 'Unable to load receipt.'}</div>`;
    }
  },

  populateData(tx) {
    const statusClasses = {
      pending: 'badge-pending',
      successful: 'badge-success',
      failed: 'badge-failed',
      refunded: 'badge-failed'
    };

    document.getElementById('receipt-amount').textContent = `NGN ${Number(tx.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    document.getElementById('receipt-service').textContent = tx.service;
    
    const badge = document.getElementById('receipt-status-badge');
    badge.textContent = tx.status.toUpperCase();
    badge.className = `badge ${statusClasses[tx.status] || 'badge-pending'}`;

    document.getElementById('receipt-recipient').textContent = tx.recipient;
    
    const customerRow = document.getElementById('receipt-customer-row');
    if (tx.customer_name) {
      document.getElementById('receipt-customer-name').textContent = tx.customer_name;
      customerRow.style.display = 'flex';
    } else {
      customerRow.style.display = 'none';
    }

    document.getElementById('receipt-date').textContent = tx.created_at;
    document.getElementById('receipt-ref').textContent = tx.reference;

    const provRow = document.getElementById('receipt-prov-row');
    if (tx.provider_reference) {
      document.getElementById('receipt-prov-ref').textContent = tx.provider_reference;
      provRow.style.display = 'flex';
    } else {
      provRow.style.display = 'none';
    }

    // Token check for prepaid meters
    const tokenSection = document.getElementById('receipt-token-section');
    if (tx.token) {
      document.getElementById('receipt-token').textContent = tx.token;
      tokenSection.style.display = 'flex';
    } else {
      tokenSection.style.display = 'none';
    }

    // PIN check for WAEC/NECO pins
    const examSection = document.getElementById('receipt-exam-section');
    const pinsList = document.getElementById('receipt-pins-list');
    if (tx.pin_list && tx.pin_list.length > 0) {
      pinsList.innerHTML = tx.pin_list.map((pin, i) => `<div>PIN ${i + 1}: ${pin}</div>`).join('');
      examSection.style.display = 'flex';
    } else {
      examSection.style.display = 'none';
    }
  },

  copyText(elementId) {
    const el = document.getElementById(elementId);
    if (el) {
      navigator.clipboard.writeText(el.textContent).then(() => {
        alert('PIN/Token copied successfully.');
      }).catch(err => {
        console.warn('Clipboard write failed:', err);
      });
    }
  },

  async shareReceipt() {
    const amount = document.getElementById('receipt-amount').textContent;
    const service = document.getElementById('receipt-service').textContent;
    const status = document.getElementById('receipt-status-badge').textContent;
    const recipient = document.getElementById('receipt-recipient').textContent;
    const ref = document.getElementById('receipt-ref').textContent;
    const token = document.getElementById('receipt-token').textContent;

    let text = `GemData Receipt\nService: ${service}\nAmount: ${amount}\nRecipient: ${recipient}\nStatus: ${status}\nReference: ${ref}`;
    
    const tokenSection = document.getElementById('receipt-token-section');
    if (tokenSection.style.display === 'flex') {
      text += `\nMeter Token: ${token}`;
    }

    if (navigator.share) {
      try {
        await navigator.share({
          title: 'GemData Transaction Receipt',
          text: text
        });
      } catch (err) {
        console.warn('Share sheet canceled or failed:', err);
      }
    } else {
      // Fallback
      await navigator.clipboard.writeText(text);
      alert('Transaction details copied to clipboard to share.');
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenReceipt = ScreenReceipt;
