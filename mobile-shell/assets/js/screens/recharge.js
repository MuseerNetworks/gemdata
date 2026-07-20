const ScreenRecharge = {
  container: null,
  selectedNetwork: '',

  mount(container) {
    this.container = container;
    this.selectedNetwork = '';
    this.renderForm();
  },

  renderForm() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Recharge Card Printing')}
        
        <main class="app-main">
          <div style="margin-bottom: 20px;">
            <p style="font-size: 0.88rem; color: var(--color-text-muted);">Generate, print, or download electronic recharge PINs instantly.</p>
          </div>

          <div id="recharge-feedback"></div>

          <form id="recharge-form" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Network Select -->
            <div class="form-group">
              <span class="form-label">Select Network</span>
              <div class="grid-cols-2" style="grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 4px;">
                ${['mtn', 'airtel', 'glo', '9mobile'].map(net => `
                  <button type="button" class="net-btn" id="recharge-net-${net}" onclick="ScreenRecharge.selectNetwork('${net}')" style="background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: 12px; padding: 12px 6px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; cursor: pointer; transition: var(--transition-smooth); color: var(--color-text-muted);">
                    ${net === '9mobile' ? '9Mob' : net}
                  </button>
                `).join('')}
              </div>
            </div>

            <!-- Denomination Select -->
            <div class="form-group">
              <label class="form-label" for="recharge-amount">Card Denomination</label>
              <select class="form-control" id="recharge-amount" required onchange="ScreenRecharge.handleCalculations()">
                <option value="" disabled selected>-- Select Value --</option>
                <option value="100">₦100 Denomination</option>
                <option value="200">₦200 Denomination</option>
                <option value="500">₦500 Denomination</option>
                <option value="1000">₦1000 Denomination</option>
              </select>
            </div>

            <!-- Quantity Input -->
            <div class="form-group">
              <label class="form-label" for="recharge-quantity">Quantity (Max 50 per order)</label>
              <input class="form-control" type="number" id="recharge-quantity" min="1" max="50" value="1" required oninput="ScreenRecharge.handleCalculations()">
            </div>

            <!-- Total Price Card Summary -->
            <div id="recharge-summary-card" style="display: none; background: var(--color-surface-soft); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px; justify-content: space-between; align-items: center; font-size: 0.88rem;">
              <span style="font-weight: 700; color: var(--color-text-muted);">Total Order Cost:</span>
              <span style="font-weight: 900; color: var(--color-primary); font-size: 1.05rem;" id="recharge-total-price">NGN 0.00</span>
            </div>

            <!-- Transaction PIN -->
            <div class="form-group">
              <label class="form-label" for="recharge-pin">Wallet Security PIN</label>
              <input class="form-control" type="password" id="recharge-pin" required placeholder="Enter transaction PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6">
            </div>

            <button class="btn-primary" type="submit" id="recharge-submit" style="margin-top: 8px;">
              Generate Recharge Cards
            </button>
          </form>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;

    document.getElementById('recharge-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  selectNetwork(network) {
    this.selectedNetwork = network;
    
    // Toggle active styles on buttons
    document.querySelectorAll('.net-btn').forEach(btn => {
      btn.style.borderColor = 'var(--color-border)';
      btn.style.color = 'var(--color-text-muted)';
      btn.style.background = 'var(--color-surface)';
    });

    const activeBtn = document.getElementById(`recharge-net-${network}`);
    if (activeBtn) {
      activeBtn.style.borderColor = 'var(--color-primary)';
      activeBtn.style.color = 'var(--color-primary)';
      activeBtn.style.background = 'var(--color-primary-soft)';
    }

    this.handleCalculations();
  },

  handleCalculations() {
    const amountVal = document.getElementById('recharge-amount').value;
    const qtyVal = parseInt(document.getElementById('recharge-quantity').value) || 1;
    const summaryCard = document.getElementById('recharge-summary-card');
    const totalEl = document.getElementById('recharge-total-price');

    if (amountVal && this.selectedNetwork) {
      const total = Number(amountVal) * qtyVal;
      totalEl.textContent = `NGN ${total.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
      summaryCard.style.display = 'flex';
    } else {
      summaryCard.style.display = 'none';
    }
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('recharge-feedback');
    const submitBtn = document.getElementById('recharge-submit');

    if (!this.selectedNetwork) {
      feedback.innerHTML = '<div class="alert alert-danger">Please select a mobile network.</div>';
      return;
    }

    const amount = document.getElementById('recharge-amount').value;
    const quantity = document.getElementById('recharge-quantity').value;
    const pin = document.getElementById('recharge-pin').value;

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Generating Pins...';

    const payload = {
      network: this.selectedNetwork,
      amount,
      quantity,
      security_pin: pin
    };

    if (!navigator.onLine) {
      const description = `Recharge Card: ${this.selectedNetwork.toUpperCase()} (Qty: ${quantity})`;
      SyncEngine.enqueue('/services/buy-recharge.php', payload, description);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Generate Recharge Cards';
      feedback.innerHTML = `
        <div class="alert alert-warning">
          <strong>Offline Mode!</strong> Your Recharge Card generation request has been queued and will process once online.
        </div>
      `;
      return;
    }

    const response = await Api.post('/services/buy-recharge.php', payload);
    
    if (response.success) {
      feedback.innerHTML = `
        <div class="alert alert-success">
          Recharge PINs generated successfully!
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
      submitBtn.textContent = 'Generate Recharge Cards';
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenRecharge = ScreenRecharge;
