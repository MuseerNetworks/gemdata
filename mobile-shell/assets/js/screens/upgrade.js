const ScreenUpgrade = {
  container: null,

  async mount(container) {
    this.container = container;

    // Show initial loading structure
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Account Upgrade')}
        
        <main class="app-main" id="upgrade-content-area">
          <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 50vh;">
            <div style="width: 32px; height: 32px; border: 3px solid var(--color-primary-soft); border-top-color: var(--color-primary); border-radius: 99px; animation: spin 800ms linear infinite;"></div>
            <p style="color: var(--color-text-muted); font-size: 0.88rem; margin-top: 12px; font-weight: 500;">Loading upgrade portal...</p>
          </div>
        </main>

        ${App.renderNavigation('#/profile')}
      </div>
    `;

    await this.fetchStatus();
  },

  async fetchStatus() {
    const contentArea = document.getElementById('upgrade-content-area');
    if (!contentArea) return;

    const response = await Api.get('/user/upgrade.php');
    if (response.success) {
      const data = response.data;
      this.renderUpgradeDashboard(contentArea, data);
    } else {
      contentArea.innerHTML = `
        <div class="alert alert-danger" style="margin-top: 16px;">
          ${response.message || 'Unable to retrieve upgrade status.'}
        </div>
      `;
    }
  },

  renderUpgradeDashboard(container, data) {
    const roleLabels = {
      smart: 'Smart User',
      reseller: 'Reseller',
      api: 'API Partner'
    };

    const currentLabel = roleLabels[data.current_role] || data.current_role;
    
    // Check if there is an active pending upgrade request under review
    if (data.latest_request && data.latest_request.status === 'pending') {
      const targetLabel = roleLabels[data.latest_request.to_type] || data.latest_request.to_type;
      container.innerHTML = `
        <div style="text-align: center; padding: 32px 16px;">
          <div style="width: 64px; height: 64px; border-radius: 99px; background: var(--color-warning-soft); color: var(--color-warning); display: grid; place-items: center; margin: 0 auto 16px;">
            <svg style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--color-text);">Upgrade Pending</h2>
          <p style="font-size: 0.88rem; color: var(--color-text-muted); margin-top: 6px; line-height: 1.5;">
            Your request to upgrade from <strong>${currentLabel}</strong> to <strong>${targetLabel}</strong> is under review by our administration.
          </p>
          <div style="background: var(--color-surface-soft); padding: 12px; border-radius: 8px; margin-top: 20px; font-size: 0.8rem; color: var(--color-text-muted);">
            Submitted on: ${data.latest_request.created_at}
          </div>
          <button class="btn-primary" onclick="Router.navigate('#/dashboard')" style="margin-top: 24px;">
            Back to Home
          </button>
        </div>
      `;
      return;
    }

    // If upgraded to highest level
    if (!data.target_role) {
      container.innerHTML = `
        <div style="text-align: center; padding: 32px 16px;">
          <div style="width: 64px; height: 64px; border-radius: 99px; background: var(--color-success-soft); color: var(--color-success); display: grid; place-items: center; margin: 0 auto 16px;">
            <svg style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--color-text);">Tier Limit Reached</h2>
          <p style="font-size: 0.88rem; color: var(--color-text-muted); margin-top: 6px; line-height: 1.5;">
            Your account is currently active as an <strong>${currentLabel}</strong>, which is the highest discount level available on our platform.
          </p>
          <button class="btn-primary" onclick="Router.navigate('#/dashboard')" style="margin-top: 24px;">
            Back to Home
          </button>
        </div>
      `;
      return;
    }

    // Smart -> Reseller upgrade form
    if (data.current_role === 'smart') {
      container.innerHTML = `
        <div style="padding: 4px 0;">
          <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 6px;">Reseller Upgrade</h3>
          <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin-bottom: 20px;">
            Enjoy premium developer rates, higher commission discounts, and custom API integration tokens when you upgrade to Reseller level.
          </p>
          
          <div id="upgrade-feedback"></div>

          <form id="upgrade-form" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="form-group">
              <label class="form-label" for="upgrade-biz">Business or Brand Name</label>
              <input class="form-control" type="text" id="upgrade-biz" required placeholder="e.g. GemData Telecoms">
            </div>

            <div class="form-group">
              <label class="form-label" for="upgrade-phone">Contact Phone Number</label>
              <input class="form-control" type="tel" id="upgrade-phone" required placeholder="e.g. 08034567890">
            </div>

            <div style="background: var(--color-surface-soft); padding: 14px; border-radius: var(--radius-md); border: 1.5px solid var(--color-border);">
              <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.85rem; font-weight: 600; line-height: 1.4; color: var(--color-text);">
                <input type="checkbox" id="upgrade-agreement" required style="margin-top: 3px;">
                <span>I agree to the Reseller Agreement terms, allowing automatic balance deductions for premium VTU service renewals.</span>
              </label>
            </div>

            <button class="btn-primary" type="submit" id="upgrade-submit" style="margin-top: 8px;">
              Upgrade to Reseller
            </button>
          </form>
        </div>
      `;
    } else if (data.current_role === 'reseller') {
      // Reseller -> API User upgrade form
      container.innerHTML = `
        <div style="padding: 4px 0;">
          <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 6px;">API User Upgrade</h3>
          <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin-bottom: 20px;">
            Integrate our high-speed endpoint VTU gateway directly into your own applications, portals, and platforms.
          </p>
          
          <div id="upgrade-feedback"></div>

          <form id="upgrade-form" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="form-group">
              <label class="form-label" for="upgrade-biz">Business or Brand Name</label>
              <input class="form-control" type="text" id="upgrade-biz" required placeholder="e.g. GemData Telecoms">
            </div>

            <div class="form-group">
              <label class="form-label" for="upgrade-phone">Contact Phone Number</label>
              <input class="form-control" type="tel" id="upgrade-phone" required placeholder="e.g. 08034567890">
            </div>

            <div class="form-group">
              <label class="form-label" for="upgrade-web">Developer Website URL</label>
              <input class="form-control" type="url" id="upgrade-web" required placeholder="e.g. https://myportal.com">
            </div>

            <div class="form-group">
              <label class="form-label" for="upgrade-reason">Reason for API Request</label>
              <textarea class="form-control" id="upgrade-reason" required rows="3" placeholder="Briefly describe what services you plan to automate..."></textarea>
            </div>

            <div style="background: var(--color-surface-soft); padding: 14px; border-radius: var(--radius-md); border: 1.5px solid var(--color-border);">
              <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.85rem; font-weight: 600; line-height: 1.4; color: var(--color-text);">
                <input type="checkbox" id="upgrade-agreement" required style="margin-top: 3px;">
                <span>I agree to abide by integration regulations and ensure that my endpoints strictly process authorized transaction payloads.</span>
              </label>
            </div>

            <button class="btn-primary" type="submit" id="upgrade-submit" style="margin-top: 8px;">
              Submit API Partner Request
            </button>
          </form>
        </div>
      `;
    }

    document.getElementById('upgrade-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('upgrade-feedback');
    const submitBtn = document.getElementById('upgrade-submit');

    const biz = document.getElementById('upgrade-biz').value;
    const phone = document.getElementById('upgrade-phone').value;
    const agreement = document.getElementById('upgrade-agreement').checked;

    const webEl = document.getElementById('upgrade-web');
    const reasonEl = document.getElementById('upgrade-reason');

    const payload = {
      business_name: biz,
      phone: phone,
      reseller_agreement: agreement ? '1' : '0',
      api_agreement: agreement ? '1' : '0'
    };

    if (webEl) payload.website_url = webEl.value;
    if (reasonEl) payload.reason = reasonEl.value;

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    const response = await Api.post('/user/upgrade.php', payload);

    if (response.success) {
      feedback.innerHTML = `
        <div class="alert alert-success">
          ${response.message}
        </div>
      `;
      
      // Update local cache details
      const userCache = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
      if (response.data && response.data.current_role) {
        userCache.tier = response.data.current_role;
        localStorage.setItem('gemdata_user_cache', JSON.stringify(userCache));
      }

      // Reload dashboard state
      setTimeout(() => {
        this.fetchStatus();
      }, 1500);
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">${response.message || 'Upgrade request failed.'}</div>`;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit Upgrade';
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenUpgrade = ScreenUpgrade;
