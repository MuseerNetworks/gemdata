const ScreenCable = {
  container: null,
  plans: [],
  providers: [],

  // Load cable TV providers from dashboard cache (service_networks.cable_tv)
  loadProviders() {
    try {
      const cache = localStorage.getItem('gemdata_dashboard_cache');
      if (cache) {
        const parsed = JSON.parse(cache);
        this.providers = (parsed.service_networks && parsed.service_networks.cable_tv) || [];
      }
    } catch (e) {
      this.providers = [];
    }
  },

  async mount(container) {
    this.container = container;
    
    // Render initial form structure
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Cable TV')}
        
        <main class="app-main">
          <div id="cable-feedback"></div>
          
          <!-- Favorites Select -->
          ${StorageHelper.renderFavoritesDropdown('decoder', 'cable-favs-dropdown', 'ScreenCable.selectFavorite')}

          <form id="cable-form" style="display: flex; flex-direction: column; gap: 16px; margin-top: 8px;">
            <div class="form-group">
              <label class="form-label" for="cable-provider">Select Provider</label>
              <select class="form-control" id="cable-provider" required>
                <option value="">Choose Provider</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="cable-smartcard">IUC / Smartcard Number</label>
              <div style="display: flex; gap: 8px;">
                <input class="form-control" type="tel" id="cable-smartcard" required placeholder="e.g. 1023456789" style="flex: 1;">
                <button class="btn-primary" type="button" id="btn-cable-verify" style="width: auto; padding: 0 16px; margin: 0; box-shadow: none;">Verify</button>
              </div>
            </div>

            <!-- Save Favorite Checkbox -->
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; margin-top: -6px; margin-bottom: 8px;">
              <input type="checkbox" id="cable-save-fav" style="cursor: pointer;">
              <label for="cable-save-fav" style="color: var(--color-text-muted); cursor: pointer; user-select: none;">Save as Favorite</label>
            </div>

            <div id="cable-verify-status" style="margin-bottom: 8px; display: none;"></div>

            <div class="form-group" id="cable-package-group">
              <label class="form-label" for="cable-package">Select Subscription Package</label>
              <select class="form-control" id="cable-package" required disabled>
                <option value="">Select provider first</option>
              </select>
            </div>

            <div class="form-group" id="cable-summary-group" style="display: none; justify-content: space-between; align-items: center; background: var(--color-surface-soft); padding: 12px 16px; border-radius: var(--radius-md);">
              <span style="font-size: 0.88rem; font-weight: 700; color: var(--color-text-muted);">Package Price:</span>
              <strong id="cable-price-display" style="font-size: 1rem; color: var(--color-primary); font-weight: 800;">NGN 0.00</strong>
            </div>

            <div class="form-group">
              <label class="form-label" for="cable-pin">Security PIN</label>
              <input class="form-control" type="password" id="cable-pin" required placeholder="Enter Wallet PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off">
            </div>

            <button class="btn-primary" type="submit" id="cable-submit" style="margin-top: 8px;">
              Renew Subscription
            </button>
          </form>
        </main>

        ${App.renderNavigation('#/buy')}
      </div>
    `;

    // Populate provider list from dashboard cache before fetching plans
    this.loadProviders();
    this.renderProviders();

    // Hook listeners
    document.getElementById('cable-provider').addEventListener('change', () => this.handleProviderChange());
    document.getElementById('cable-package').addEventListener('change', () => this.handlePackageChange());
    document.getElementById('btn-cable-verify').addEventListener('click', () => this.handleVerify());
    document.getElementById('cable-form').addEventListener('submit', (e) => this.handleSubmit(e));

    // Fetch plan catalog in background (non-blocking)
    this.fetchPlans();
  },

  renderProviders() {
    const providerSelect = document.getElementById('cable-provider');
    if (!providerSelect) return;

    if (this.providers.length === 0) {
      providerSelect.innerHTML = '<option value="">Providers unavailable — refresh dashboard first</option>';
      return;
    }

    providerSelect.innerHTML = '<option value="">Choose Provider</option>' +
      this.providers.map(p =>
        `<option value="${p.network_code}">${p.network_name}</option>`
      ).join('');
  },

  async fetchPlans() {
    const feedback = document.getElementById('cable-feedback');
    const response = await Api.get('/services/plans.php?service=cable_tv');
    if (response.success) {
      this.plans = response.data || [];
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">Unable to load packages from the server.</div>`;
    }
  },

  handleProviderChange() {
    const provider = document.getElementById('cable-provider').value;
    const packageSelect = document.getElementById('cable-package');
    const summaryGroup = document.getElementById('cable-summary-group');
    
    // Clear selection
    packageSelect.innerHTML = '<option value="">Choose Package</option>';
    summaryGroup.style.display = 'none';

    if (!provider) {
      packageSelect.disabled = true;
      return;
    }

    // Filter packages matching provider
    const filtered = this.plans.filter(p => p.network_code === provider);
    if (filtered.length > 0) {
      filtered.forEach(p => {
        const option = document.createElement('option');
        option.value = p.local_plan_code;
        option.textContent = `${p.local_plan_name} - NGN ${Number(p.amount).toLocaleString()}`;
        option.dataset.price = p.amount;
        packageSelect.appendChild(option);
      });
      packageSelect.disabled = false;
    } else {
      packageSelect.innerHTML = '<option value="">No packages available for this provider</option>';
      packageSelect.disabled = true;
    }
  },

  handlePackageChange() {
    const packageSelect = document.getElementById('cable-package');
    const summaryGroup = document.getElementById('cable-summary-group');
    const priceDisplay = document.getElementById('cable-price-display');
    const selectedOption = packageSelect.options[packageSelect.selectedIndex];

    if (selectedOption && selectedOption.dataset.price) {
      const price = Number(selectedOption.dataset.price);
      priceDisplay.textContent = `NGN ${price.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
      summaryGroup.style.display = 'flex';
    } else {
      summaryGroup.style.display = 'none';
    }
  },

  async handleVerify() {
    const provider = document.getElementById('cable-provider').value;
    const smartcard = document.getElementById('cable-smartcard').value;
    const verifyBtn = document.getElementById('btn-cable-verify');
    const statusDiv = document.getElementById('cable-verify-status');

    if (!provider) {
      alert('Please select a cable provider first.');
      return;
    }
    if (!smartcard) {
      alert('Please enter your IUC or smartcard number.');
      return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = '...';
    statusDiv.style.display = 'none';

    const response = await Api.post('/services/verify-customer.php', {
      service_slug: 'cable_tv',
      provider,
      smartcard_number: smartcard
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
              <input type="checkbox" id="cable-iuc-confirmed" value="1" checked required>
              I confirm the card number is correct
            </label>
          </div>
        `;
      } else {
        statusDiv.innerHTML = `
          <div class="alert alert-success" style="font-size: 0.82rem; margin: 0; padding: 10px;">
            <strong>Customer:</strong> ${response.data.customer_name}
            <input type="hidden" id="cable-iuc-confirmed" value="1">
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
      document.getElementById('cable-smartcard').value = val;
      if (window.Logger) {
        window.Logger.triggerHaptic('light');
      }
    }
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('cable-feedback');
    const submitBtn = document.getElementById('cable-submit');

    const provider = document.getElementById('cable-provider').value;
    const smartcard = document.getElementById('cable-smartcard').value;
    const packageCode = document.getElementById('cable-package').value;
    const pin = document.getElementById('cable-pin').value;
    const iucConfirmedCheck = document.getElementById('cable-iuc-confirmed');

    if (iucConfirmedCheck && !iucConfirmedCheck.checked) {
      feedback.innerHTML = `<div class="alert alert-danger">Please confirm that your smartcard number is correct.</div>`;
      return;
    }

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';

    const payload = {
      provider,
      smartcard_number: smartcard,
      plan: packageCode,
      security_pin: pin,
      cable_validation_status: iucConfirmedCheck ? 'unavailable' : 'success',
      cable_iuc_confirmed: '1'
    };

    if (!navigator.onLine) {
      const description = `Cable TV: ${provider.toUpperCase()} (${smartcard})`;
      SyncEngine.enqueue('/services/buy-cable.php', payload, description);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Renew Subscription';
      feedback.innerHTML = `
        <div class="alert alert-warning">
          <strong>Offline Mode!</strong> Your Cable TV subscription has been queued and will process once online.
        </div>
      `;
      return;
    }

    const response = await Api.post('/services/buy-cable.php', payload);

    if (response.success) {
      feedback.innerHTML = `
        <div class="alert alert-success">
          Subscription transaction accepted successfully!
        </div>
      `;
      
      // Handle saving favorite beneficiary if checked
      const saveFav = document.getElementById('cable-save-fav')?.checked;
      if (saveFav) {
        const label = prompt('Enter a label for this saved decoder:', provider.toUpperCase());
        if (label && label.trim() !== '') {
          StorageHelper.addFavorite('decoder', smartcard, label);
        }
      }

      // Cache this transaction inside Recents list
      const recDescription = `${provider.toUpperCase()} (${smartcard})`;
      StorageHelper.addRecent('cable', { provider, smartcard_number: smartcard, plan: packageCode }, recDescription);

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
      submitBtn.textContent = 'Renew Subscription';
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenCable = ScreenCable;
