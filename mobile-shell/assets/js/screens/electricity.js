const ScreenElectricity = {
  container: null,
  providers: [],

  // Load electricity disco providers from dashboard cache (service_networks.electricity)
  loadProviders() {
    try {
      const cache = localStorage.getItem('gemdata_dashboard_cache');
      if (cache) {
        const parsed = JSON.parse(cache);
        this.providers = (parsed.service_networks && parsed.service_networks.electricity) || [];
      }
    } catch (e) {
      this.providers = [];
    }
  },

  async mount(container) {
    this.container = container;

    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Electricity Bill')}
        
        <main class="app-main">
          <div id="electricity-feedback"></div>
          
          <!-- Favorites Select -->
          ${StorageHelper.renderFavoritesDropdown('meter', 'elect-favs-dropdown', 'ScreenElectricity.selectFavorite')}

          <form id="electricity-form" style="display: flex; flex-direction: column; gap: 16px; margin-top: 8px;">
            <div class="form-group">
              <label class="form-label" for="electricity-disco">Select Disco / Provider</label>
              <select class="form-control" id="electricity-disco" required>
                <option value="">Choose Disco Provider</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="electricity-type">Meter Type</label>
              <select class="form-control" id="electricity-type" required>
                <option value="">Choose Meter Type</option>
                <option value="prepaid">Prepaid</option>
                <option value="postpaid">Postpaid</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="electricity-meter">Meter Number</label>
              <div style="display: flex; gap: 8px;">
                <input class="form-control" type="tel" id="electricity-meter" required placeholder="e.g. 01234567890" style="flex: 1;">
                <button class="btn-primary" type="button" id="btn-meter-verify" style="width: auto; padding: 0 16px; margin: 0; box-shadow: none;">Verify</button>
              </div>
            </div>

            <!-- Save Favorite Checkbox -->
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; margin-top: -6px; margin-bottom: 8px;">
              <input type="checkbox" id="elect-save-fav" style="cursor: pointer;">
              <label for="elect-save-fav" style="color: var(--color-text-muted); cursor: pointer; user-select: none;">Save as Favorite</label>
            </div>

            <div id="meter-verify-status" style="margin-bottom: 8px; display: none;"></div>

            <div class="form-group">
              <label class="form-label" for="electricity-amount">Amount (NGN)</label>
              <input class="form-control" type="number" id="electricity-amount" required placeholder="Min 1,000" min="500">
            </div>

            <div class="form-group">
              <label class="form-label" for="electricity-pin">Security PIN</label>
              <input class="form-control" type="password" id="electricity-pin" required placeholder="Enter Wallet PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off">
            </div>

            <button class="btn-primary" type="submit" id="electricity-submit" style="margin-top: 8px;">
              Pay Electricity Bill
            </button>
          </form>
        </main>

        ${App.renderNavigation('#/buy')}
      </div>
    `;

    // Populate disco providers from dashboard cache
    this.loadProviders();
    this.renderProviders();

    // Hook listeners
    document.getElementById('btn-meter-verify').addEventListener('click', () => this.handleVerify());
    document.getElementById('electricity-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  renderProviders() {
    const discoSelect = document.getElementById('electricity-disco');
    if (!discoSelect) return;

    if (this.providers.length === 0) {
      discoSelect.innerHTML = '<option value="">Providers unavailable — refresh dashboard first</option>';
      return;
    }

    discoSelect.innerHTML = '<option value="">Choose Disco Provider</option>' +
      this.providers.map(p =>
        `<option value="${p.network_code}">${p.network_name}</option>`
      ).join('');
  },

  async handleVerify() {
    const disco = document.getElementById('electricity-disco').value;
    const meterType = document.getElementById('electricity-type').value;
    const meterNumber = document.getElementById('electricity-meter').value;
    const verifyBtn = document.getElementById('btn-meter-verify');
    const statusDiv = document.getElementById('meter-verify-status');

    if (!disco) {
      alert('Please select a Disco provider first.');
      return;
    }
    if (!meterType) {
      alert('Please select a meter type.');
      return;
    }
    if (!meterNumber) {
      alert('Please enter your meter number.');
      return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = '...';
    statusDiv.style.display = 'none';

    const response = await Api.post('/services/verify-customer.php', {
      service_slug: 'electricity',
      disco,
      meter_type: meterType,
      meter_number: meterNumber
    });

    verifyBtn.disabled = false;
    verifyBtn.textContent = 'Verify';

    if (response.success) {
      statusDiv.style.display = 'block';
      if (response.data.validation_status === 'unavailable') {
        statusDiv.innerHTML = `
          <div class="alert alert-success" style="font-size: 0.82rem; margin: 0; padding: 10px;">
            ${response.message}
            <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px; font-weight: 700; cursor: pointer;">
              <input type="checkbox" id="electricity-confirmed" value="1" checked required>
              I confirm the meter number is correct
            </label>
          </div>
        `;
      } else {
        statusDiv.innerHTML = `
          <div class="alert alert-success" style="font-size: 0.82rem; margin: 0; padding: 10px;">
            <strong>Customer:</strong> ${response.data.customer_name}
            <input type="hidden" id="electricity-confirmed" value="1">
          </div>
        `;
      }
    } else {
      statusDiv.style.display = 'block';
      statusDiv.innerHTML = `<div class="alert alert-danger" style="font-size: 0.82rem; margin: 0; padding: 10px;">${response.message || 'Verification failed.'}</div>`;
    }
  },

  selectFavorite(val) {
    if (val) {
      document.getElementById('electricity-meter').value = val;
      if (window.Logger) {
        window.Logger.triggerHaptic('light');
      }
    }
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('electricity-feedback');
    const submitBtn = document.getElementById('electricity-submit');

    const disco = document.getElementById('electricity-disco').value;
    const meterType = document.getElementById('electricity-type').value;
    const meterNumber = document.getElementById('electricity-meter').value;
    const amount = document.getElementById('electricity-amount').value;
    const pin = document.getElementById('electricity-pin').value;
    const confirmedCheck = document.getElementById('electricity-confirmed');

    if (confirmedCheck && !confirmedCheck.checked) {
      feedback.innerHTML = `<div class="alert alert-danger">Please confirm that your meter number is correct.</div>`;
      return;
    }

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';

    const payload = {
      disco,
      meter_type: meterType,
      meter_number: meterNumber,
      amount,
      security_pin: pin,
      electricity_validation_status: confirmedCheck ? 'unavailable' : 'success',
      electricity_meter_confirmed: '1'
    };

    if (!navigator.onLine) {
      const description = `Electricity: ${disco.toUpperCase()} (${meterNumber})`;
      SyncEngine.enqueue('/services/buy-electricity.php', payload, description);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Pay Electricity Bill';
      feedback.innerHTML = `
        <div class="alert alert-warning">
          <strong>Offline Mode!</strong> Your bill payment request has been queued and will process once online.
        </div>
      `;
      return;
    }

    const response = await Api.post('/services/buy-electricity.php', payload);

    if (response.success) {
      feedback.innerHTML = `
        <div class="alert alert-success">
          Electricity payment transaction accepted successfully!
        </div>
      `;
      
      // Handle saving favorite beneficiary if checked
      const saveFav = document.getElementById('elect-save-fav')?.checked;
      if (saveFav) {
        const label = prompt('Enter a label for this saved meter:', disco.toUpperCase());
        if (label && label.trim() !== '') {
          StorageHelper.addFavorite('meter', meterNumber, label);
        }
      }

      // Cache this transaction inside Recents list
      const recDescription = `${disco.toUpperCase()} (${meterNumber})`;
      StorageHelper.addRecent('electricity', { disco, meter_type: meterType, meter_number: meterNumber, amount }, recDescription);

      // Update header wallet balance cache
      if (response.data.wallet_balance) {
        App.updateHeaderBalance(response.data.wallet_balance);
        const userCache = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
        userCache.balance = response.data.wallet_balance;
        localStorage.setItem('gemdata_user_cache', JSON.stringify(userCache));
      }

      // Navigate to interactive receipt screen
      setTimeout(() => {
        Router.navigate(`#/receipt?reference=${response.data.reference}`);
      }, 1500);
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">${response.message || 'Transaction failed.'}</div>`;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Pay Electricity Bill';
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenElectricity = ScreenElectricity;
