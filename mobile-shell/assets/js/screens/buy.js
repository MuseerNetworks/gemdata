const ScreenBuy = {
  container: null,
  service: 'airtime',
  selectedNetwork: '',
  catalog: [],
  
  mount(container, params) {
    this.container = container;
    this.service = params.service === 'data' ? 'data' : 'airtime';
    this.selectedNetwork = '';
    
    // Load data plan catalog from cache
    const cache = localStorage.getItem('gemdata_dashboard_cache');
    this.catalog = [];
    if (cache) {
      try {
        this.catalog = JSON.parse(cache).data_plan_catalog || [];
      } catch (e) {
        console.error(e);
      }
    }

    this.renderForm();
  },

  renderForm() {
    const isData = this.service === 'data';
    const title = isData ? 'Buy Mobile Data' : 'Buy Airtime';
    const subText = isData ? 'Select a network and data plan bundle' : 'Top up any phone network instantly';

    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader(title)}
        
        <main class="app-main">
          <div style="margin-bottom: 20px;">
            <p style="font-size: 0.88rem; color: var(--color-text-muted);">${subText}</p>
          </div>

          <div id="buy-feedback"></div>

          <form id="buy-form" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Network buttons -->
            <div class="form-group">
              <span class="form-label">Select Network</span>
              <div class="grid-cols-2" style="grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 4px;">
                ${['mtn', 'airtel', 'glo', '9mobile'].map(net => `
                  <button type="button" class="net-btn" data-network="${net}" onclick="ScreenBuy.selectNetwork('${net}')" style="background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: 12px; padding: 12px 6px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; cursor: pointer; transition: var(--transition-smooth); color: var(--color-text-muted);">
                    ${net === '9mobile' ? '9Mob' : net}
                  </button>
                `).join('')}
              </div>
            </div>

            <!-- Recipient Input -->
            <div class="form-group">
              <label class="form-label" for="buy-phone">Phone Number</label>
              <input class="form-control" type="tel" id="buy-phone" required placeholder="e.g. 08030000000">
            </div>

            <!-- Conditional Airtime / Data inputs -->
            ${isData ? `
              <div class="form-group" id="plan-group" style="display: none;">
                <label class="form-label" for="buy-plan">Select Data Plan</label>
                <select class="form-control" id="buy-plan" required onchange="ScreenBuy.handlePlanChange()">
                  <option value="">-- Choose a network first --</option>
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label" for="buy-amount">Amount (NGN)</label>
                <input class="form-control" type="text" id="buy-amount" readonly placeholder="Select a plan first">
              </div>
            ` : `
              <div class="form-group">
                <label class="form-label" for="buy-amount">Amount (NGN)</label>
                <input class="form-control" type="number" id="buy-amount" required min="10" placeholder="e.g. 500">
              </div>
            `}

            <button class="btn-primary" type="button" onclick="ScreenBuy.showPinModal()" style="margin-top: 12px;">
              Purchase Now
            </button>
          </form>
        </main>

        <!-- Wallet PIN Modal -->
        <div id="pin-modal" class="modal-overlay" style="display: none;">
          <div class="modal-content">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; color: var(--color-text);">Confirm Wallet PIN</h3>
            <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-bottom: 16px;">Enter your transaction PIN to authorize this purchase.</p>
            
            <div class="form-group" style="margin-bottom: 20px;">
              <input class="form-control" type="password" id="wallet-pin" maxlength="6" placeholder="Enter PIN" style="text-align: center; font-size: 1.5rem; letter-spacing: 6px;">
            </div>

            <div style="display: flex; gap: 8px;">
              <button class="btn-primary" onclick="ScreenBuy.submitTransaction()" id="pin-confirm-btn" style="flex: 1; padding: 12px;">
                Confirm
              </button>
              <button class="btn-primary" onclick="ScreenBuy.hidePinModal()" style="flex: 1; background: var(--color-surface-soft); border: 1px solid var(--color-border); color: var(--color-text-muted); padding: 12px; box-shadow: none;">
                Cancel
              </button>
            </div>
          </div>
        </div>

        ${App.renderNavigation('#/buy')}
      </div>
    `;

    // Load active balance into header from cache
    const cache = localStorage.getItem('gemdata_dashboard_cache');
    if (cache) {
      try {
        App.updateHeaderBalance(JSON.parse(cache).wallet.balance);
      } catch (e) {}
    }
  },

  selectNetwork(net) {
    this.selectedNetwork = net;
    
    // Toggle active styles on network buttons
    document.querySelectorAll('.net-btn').forEach(btn => {
      if (btn.getAttribute('data-network') === net) {
        btn.style.borderColor = 'var(--color-primary)';
        btn.style.color = 'var(--color-primary)';
        btn.style.background = 'var(--color-primary-soft)';
      } else {
        btn.style.borderColor = 'var(--color-border)';
        btn.style.color = 'var(--color-text-muted)';
        btn.style.background = 'var(--color-surface)';
      }
    });

    if (this.service === 'data') {
      const planGroup = document.getElementById('plan-group');
      const planSelect = document.getElementById('buy-plan');
      
      if (planGroup && planSelect) {
        planGroup.style.display = 'flex';
        
        // Filter catalog plans matching selected network
        const filtered = this.catalog.filter(p => p.network.toLowerCase() === net.toLowerCase());
        
        if (filtered.length > 0) {
          planSelect.innerHTML = `
            <option value="">-- Select Data Bundle --</option>
            ${filtered.map(p => `
              <option value="${p.value}" data-amount="${p.amount}">
                ${p.label} - NGN ${p.amount.toLocaleString()} (${p.validity})
              </option>
            `).join('')}
          `;
        } else {
          planSelect.innerHTML = `<option value="">No active plans for this network</option>`;
        }
      }
    }
  },

  handlePlanChange() {
    const planSelect = document.getElementById('buy-plan');
    const amountInput = document.getElementById('buy-amount');
    if (planSelect && amountInput) {
      const selectedOption = planSelect.options[planSelect.selectedIndex];
      const amount = selectedOption ? selectedOption.getAttribute('data-amount') : '';
      amountInput.value = amount || '';
    }
  },

  showPinModal() {
    // Inputs validation before showing modal
    const network = this.selectedNetwork;
    const phone = document.getElementById('buy-phone').value.trim();
    const amount = document.getElementById('buy-amount').value.trim();

    if (!network) {
      alert('Please select a network provider.');
      return;
    }
    if (!phone) {
      alert('Recipient phone number is required.');
      return;
    }
    if (!amount || Number(amount) <= 0) {
      alert('Please specify a valid amount.');
      return;
    }

    document.getElementById('pin-modal').style.display = 'grid';
    document.getElementById('wallet-pin').focus();
  },

  hidePinModal() {
    document.getElementById('pin-modal').style.display = 'none';
    document.getElementById('wallet-pin').value = '';
  },

  async submitTransaction() {
    const pinBtn = document.getElementById('pin-confirm-btn');
    const pin = document.getElementById('wallet-pin').value.trim();
    if (!pin) {
      alert('Please enter your wallet PIN.');
      return;
    }

    pinBtn.disabled = true;
    pinBtn.textContent = 'Verifying...';

    const network = this.selectedNetwork;
    const phone = document.getElementById('buy-phone').value.trim();
    const amount = document.getElementById('buy-amount').value.trim();
    const plan = this.service === 'data' ? document.getElementById('buy-plan').value : '';

    const payload = {
      network,
      phone,
      amount,
      security_pin: pin
    };
    if (this.service === 'data') {
      payload.plan = plan;
    }

    const endpoint = this.service === 'data' ? '/services/buy-data.php' : '/services/buy-airtime.php';
    const result = await Api.post(endpoint, payload);

    this.hidePinModal();
    pinBtn.disabled = false;
    pinBtn.textContent = 'Confirm';

    const feedback = document.getElementById('buy-feedback');
    if (feedback) {
      if (result.success) {
        feedback.innerHTML = `
          <div class="alert alert-success">
            <strong>Success!</strong> ${result.message} <br>
            Reference: ${result.data.reference}
          </div>
        `;
        
        // Reset form inputs
        document.getElementById('buy-phone').value = '';
        document.getElementById('buy-amount').value = '';
        if (this.service === 'data') {
          document.getElementById('buy-plan').value = '';
        }
        
        // Update header balance
        App.updateHeaderBalance(result.data.wallet_balance);
        
        // Update cache with fresh balance
        const cache = localStorage.getItem('gemdata_dashboard_cache');
        if (cache) {
          try {
            const parsed = JSON.parse(cache);
            parsed.wallet.balance = result.data.wallet_balance;
            localStorage.setItem('gemdata_dashboard_cache', JSON.stringify(parsed));
          } catch(e) {}
        }
      } else {
        feedback.innerHTML = `
          <div class="alert alert-danger">
            <strong>Transaction Failed:</strong> ${result.message}
          </div>
        `;
      }
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenBuy = ScreenBuy;
