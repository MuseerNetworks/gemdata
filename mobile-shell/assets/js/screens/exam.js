const ScreenExam = {
  container: null,
  plans: [],
  selectedPlan: null,

  async mount(container) {
    this.container = container;
    this.plans = [];
    this.selectedPlan = null;
    this.renderLoading();
    await this.loadPlans();
  },

  renderLoading() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Exam PINs')}
        <main class="app-main">
          <div style="margin-bottom: 20px;">
            <p style="font-size: 0.88rem; color: var(--color-text-muted);">Purchase WAEC, NECO, JAMB, NABTEB, and other exam PINs instantly.</p>
          </div>
          <div class="shimmer-skeleton skeleton-card" style="margin-bottom: 16px;"></div>
          <div class="shimmer-skeleton skeleton-text" style="width: 60%; margin-bottom: 12px;"></div>
          <div class="shimmer-skeleton skeleton-list-item" style="margin-bottom: 10px;"></div>
          <div class="shimmer-skeleton skeleton-list-item" style="margin-bottom: 10px;"></div>
        </main>
        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  async loadPlans() {
    const result = await Api.get('/services/plans.php?service=exam_pin');
    if (result.success && result.data && result.data.length > 0) {
      this.plans = result.data;
    } else {
      // Try dashboard cache as secondary source (provider_plan_catalogs.exam_pin)
      try {
        const cache = localStorage.getItem('gemdata_dashboard_cache');
        if (cache) {
          const parsed = JSON.parse(cache);
          const cached = (parsed.provider_plan_catalogs && parsed.provider_plan_catalogs.exam_pin) || [];
          if (cached.length > 0) {
            // Normalize cached plan fields to match API response format
            this.plans = cached.map(p => ({
              id: p.local_plan_code || p.network_code,
              name: p.local_plan_name || p.network_name,
              amount: Number(p.amount || 0),
              description: p.validity_label || ''
            }));
          }
        }
      } catch (e) {
        console.warn('[ScreenExam] Failed to read dashboard cache:', e);
      }

      if (this.plans.length === 0) {
        this.renderError('Exam plans are currently unavailable. Please check your connection and retry.');
        return;
      }
    }
    this.renderForm();
  },

  renderError(msg = 'Failed to load exam plans. Please try again.') {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Exam PINs')}
        <main class="app-main" style="text-align: center; padding-top: 40px;">
          <div style="font-size: 3rem; margin-bottom: 16px;">📋</div>
          <h4 style="font-weight: 700; color: var(--color-text);">Plans Unavailable</h4>
          <p style="color: var(--color-text-muted); font-size: 0.82rem; margin-top: 4px; padding: 0 20px;">${msg}</p>
          <button class="btn-primary" onclick="ScreenExam.loadPlans()" style="margin-top: 20px; width: auto; padding: 10px 24px;">Retry</button>
        </main>
        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  renderForm() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Exam PINs')}

        <main class="app-main">
          <div style="margin-bottom: 20px;">
            <p style="font-size: 0.88rem; color: var(--color-text-muted);">Purchase WAEC, NECO, JAMB, NABTEB, and other exam PINs instantly.</p>
          </div>

          <div id="exam-feedback"></div>

          <form id="exam-form" style="display: flex; flex-direction: column; gap: 16px;">

            <!-- Exam Type Select -->
            <div class="form-group">
              <span class="form-label">Select Exam Type</span>
              <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
                ${this.plans.map(plan => `
                  <button type="button" class="exam-plan-btn" id="exam-plan-${plan.id}"
                    onclick="ScreenExam.selectPlan(${JSON.stringify(plan).replace(/"/g, '&quot;')})"
                    style="display: flex; justify-content: space-between; align-items: center; background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 14px 16px; cursor: pointer; transition: var(--transition-smooth); text-align: left; width: 100%;">
                    <div>
                      <div style="font-size: 0.95rem; font-weight: 800; color: var(--color-text);">${plan.name || plan.plan_name || plan.id}</div>
                      ${plan.description ? `<div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 2px;">${plan.description}</div>` : ''}
                    </div>
                    <div style="font-size: 0.9rem; font-weight: 900; color: var(--color-primary); white-space: nowrap; margin-left: 12px;">
                      NGN ${Number(plan.amount || plan.price || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </div>
                  </button>
                `).join('')}
              </div>
            </div>

            <!-- Quantity Input -->
            <div class="form-group" id="exam-qty-group" style="display: none;">
              <label class="form-label" for="exam-quantity">Quantity (Max 10 per order)</label>
              <input class="form-control" type="number" id="exam-quantity" min="1" max="10" value="1"
                required oninput="ScreenExam.handleCalculations()">
            </div>

            <!-- Summary Card -->
            <div id="exam-summary-card" style="display: none; background: var(--color-surface-soft); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; justify-content: space-between; align-items: center; font-size: 0.88rem;">
              <span style="font-weight: 700; color: var(--color-text-muted);">Total Cost:</span>
              <span style="font-weight: 900; color: var(--color-primary); font-size: 1.05rem;" id="exam-total-price">NGN 0.00</span>
            </div>

            <!-- Security PIN -->
            <div class="form-group" id="exam-pin-group" style="display: none;">
              <label class="form-label" for="exam-pin">Wallet Security PIN</label>
              <input class="form-control" type="password" id="exam-pin" required
                placeholder="Enter transaction PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6">
            </div>

            <button class="btn-primary" type="submit" id="exam-submit" style="margin-top: 8px; display: none;">
              Purchase Exam PIN
            </button>
          </form>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;

    document.getElementById('exam-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  selectPlan(plan) {
    this.selectedPlan = plan;

    // Reset all button styles
    document.querySelectorAll('.exam-plan-btn').forEach(btn => {
      btn.style.borderColor = 'var(--color-border)';
      btn.style.background = 'var(--color-surface)';
    });

    // Highlight selected button
    const activeBtn = document.getElementById(`exam-plan-${plan.id}`);
    if (activeBtn) {
      activeBtn.style.borderColor = 'var(--color-primary)';
      activeBtn.style.background = 'var(--color-primary-soft)';
    }

    // Show quantity, summary, PIN, and submit button
    ['exam-qty-group', 'exam-pin-group'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'block';
    });

    const submitBtn = document.getElementById('exam-submit');
    if (submitBtn) submitBtn.style.display = 'block';

    this.handleCalculations();

    if (window.Logger) {
      window.Logger.triggerHaptic('light');
    }
  },

  handleCalculations() {
    if (!this.selectedPlan) return;

    const qty = parseInt(document.getElementById('exam-quantity')?.value) || 1;
    const pricePerPin = Number(this.selectedPlan.amount || this.selectedPlan.price || 0);
    const total = pricePerPin * qty;

    const summaryCard = document.getElementById('exam-summary-card');
    const totalEl = document.getElementById('exam-total-price');

    if (summaryCard) summaryCard.style.display = 'flex';
    if (totalEl) totalEl.textContent = `NGN ${total.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('exam-feedback');
    const submitBtn = document.getElementById('exam-submit');

    if (!this.selectedPlan) {
      feedback.innerHTML = '<div class="alert alert-danger">Please select an exam type.</div>';
      return;
    }

    const quantity = parseInt(document.getElementById('exam-quantity').value) || 1;
    const pin = document.getElementById('exam-pin').value;
    const pricePerPin = Number(this.selectedPlan.amount || this.selectedPlan.price || 0);
    const amount = pricePerPin * quantity;
    const examType = this.selectedPlan.id || this.selectedPlan.plan_id || this.selectedPlan.name;

    if (!pin) {
      feedback.innerHTML = '<div class="alert alert-danger">Wallet PIN is required.</div>';
      return;
    }

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';

    const payload = {
      exam_type: examType,
      quantity,
      amount,
      security_pin: pin
    };

    if (!navigator.onLine) {
      const description = `${this.selectedPlan.name} Exam PIN (Qty: ${quantity})`;
      SyncEngine.enqueue('/services/buy-exam.php', payload, description);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Purchase Exam PIN';
      feedback.innerHTML = `
        <div class="alert alert-warning">
          <strong>Offline Mode!</strong> Your Exam PIN request has been queued and will process once online.
        </div>
      `;
      return;
    }

    const response = await Api.post('/services/buy-exam.php', payload);

    if (response.success) {
      // Save to recent purchases
      if (window.StorageHelper) {
        StorageHelper.addRecent('exam', payload, `${this.selectedPlan.name} PIN x${quantity}`);
      }

      if (window.Logger) {
        window.Logger.triggerHaptic('success');
      }

      if (response.data?.wallet_balance) {
        App.updateHeaderBalance(response.data.wallet_balance);
        const userCache = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
        userCache.balance = response.data.wallet_balance;
        localStorage.setItem('gemdata_user_cache', JSON.stringify(userCache));
      }

      feedback.innerHTML = `
        <div class="alert alert-success">
          Exam PIN purchased successfully! Redirecting to receipt...
        </div>
      `;

      setTimeout(() => {
        Router.navigate(`#/receipt?reference=${response.data.reference}`);
      }, 1500);
    } else {
      feedback.innerHTML = `<div class="alert alert-danger">${response.message || 'Transaction failed. Please try again.'}</div>`;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Purchase Exam PIN';
    }
  },

  unmount() {
    this.container = null;
    this.plans = [];
    this.selectedPlan = null;
  }
};

window.ScreenExam = ScreenExam;