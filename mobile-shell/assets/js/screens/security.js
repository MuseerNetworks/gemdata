const ScreenSecurity = {
  container: null,

  async mount(container) {
    this.container = container;

    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Security Settings')}
        
        <main class="app-main" style="display: flex; flex-direction: column; gap: 32px;">
          <!-- Biometric Security Section -->
          <div id="biometric-settings-card" style="display: none;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 4px; color: var(--color-text);">Biometric Sign In</h3>
            <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-bottom: 16px;">Use fingerprint or Face ID to sign in securely without typing password.</p>
            
            <div style="display: flex; align-items: center; justify-content: space-between; background: var(--color-surface-soft); padding: 16px; border-radius: var(--radius-md); border: 1px dashed var(--color-border);">
              <div>
                <span style="font-weight: 800; font-size: 0.95rem; color: var(--color-text); display: block;">Fingerprint / Face ID</span>
                <span style="font-size: 0.8rem; color: var(--color-text-muted);" id="biometric-status-text">Disabled</span>
              </div>
              <label class="switch-control" style="position: relative; display: inline-block; width: 46px; height: 26px;">
                <input type="checkbox" id="biometric-toggle" style="opacity: 0; width: 0; height: 0;">
                <span class="switch-slider" style="position: absolute; cursor: pointer; inset: 0; background-color: var(--color-border); border-radius: 34px; transition: 0.2s;"></span>
              </label>
            </div>
            
            <!-- Consent verification password prompt -->
            <div id="biometric-auth-prompt" style="display: none; margin-top: 14px; flex-direction: column; gap: 8px;">
              <p style="font-size: 0.82rem; color: var(--color-warning); font-weight: 700; margin: 0;">Confirm account password to enable biometric sign in:</p>
              <input class="form-control" type="password" id="biometric-verify-password" placeholder="Confirm your password" style="background: var(--color-surface);">
              <button class="btn-primary" type="button" id="biometric-confirm-btn" style="padding: 8px;">Confirm & Link</button>
            </div>
          </div>

          <hr id="biometric-divider" style="display: none; border: 0; border-top: 1px solid var(--color-border);">

          <!-- Password Update Section -->
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 4px; color: var(--color-text);">Update Password</h3>
            <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-bottom: 16px;">Secure your account with a strong password.</p>
            <div id="password-feedback"></div>
            
            <form id="password-form" style="display: flex; flex-direction: column; gap: 14px;">
              <div class="form-group">
                <label class="form-label" for="current-password">Current Password</label>
                <input class="form-control" type="password" id="current-password" required placeholder="Enter current password">
              </div>

              <div class="form-group">
                <label class="form-label" for="new-password">New Password</label>
                <input class="form-control" type="password" id="new-password" required placeholder="Min 8 chars (letters + symbol)">
              </div>

              <div class="form-group">
                <label class="form-label" for="confirm-password">Confirm New Password</label>
                <input class="form-control" type="password" id="confirm-password" required placeholder="Re-enter new password">
              </div>

              <button class="btn-primary" type="submit" id="password-submit">
                Update Password
              </button>
            </form>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--color-border);">

          <!-- Transaction PIN Update Section -->
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 4px; color: var(--color-text);">Wallet Security PIN</h3>
            <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-bottom: 16px;">This PIN is required to authorize all purchases and transfers.</p>
            <div id="pin-feedback"></div>
            
            <form id="pin-form" style="display: flex; flex-direction: column; gap: 14px;">
              <div class="form-group">
                <label class="form-label" for="current-pin">Current PIN (Leave empty if setting for first time)</label>
                <input class="form-control" type="password" id="current-pin" placeholder="Enter current PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6">
              </div>

              <div class="form-group">
                <label class="form-label" for="new-pin">New PIN</label>
                <input class="form-control" type="password" id="new-pin" required placeholder="4 to 6 digits" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6">
              </div>

              <div class="form-group">
                <label class="form-label" for="confirm-pin">Confirm New PIN</label>
                <input class="form-control" type="password" id="confirm-pin" required placeholder="Re-enter new PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6">
              </div>

              <button class="btn-primary" type="submit" id="pin-submit">
                Update Wallet PIN
              </button>
            </form>
          </div>
        </main>

        ${App.renderNavigation('#/profile')}
      </div>
    `;

    // Hook listeners
    document.getElementById('password-form').addEventListener('submit', (e) => this.handlePasswordSubmit(e));
    document.getElementById('pin-form').addEventListener('submit', (e) => this.handlePinSubmit(e));

    // Setup Biometrics view if hardware is available
    if (window.BiometricsEngine) {
      const available = await BiometricsEngine.isAvailable();
      if (available) {
        document.getElementById('biometric-settings-card').style.display = 'block';
        document.getElementById('biometric-divider').style.display = 'block';

        const toggle = document.getElementById('biometric-toggle');
        const statusText = document.getElementById('biometric-status-text');
        const isEnabled = localStorage.getItem('gemdata_biometric_enabled') === '1';

        toggle.checked = isEnabled;
        statusText.textContent = isEnabled ? 'Enabled' : 'Disabled';

        toggle.addEventListener('change', async (e) => {
          if (e.target.checked) {
            document.getElementById('biometric-auth-prompt').style.display = 'flex';
          } else {
            await BiometricsEngine.disable();
            statusText.textContent = 'Disabled';
            document.getElementById('biometric-auth-prompt').style.display = 'none';
            document.getElementById('biometric-verify-password').value = '';
          }
        });

        document.getElementById('biometric-confirm-btn').addEventListener('click', async () => {
          const password = document.getElementById('biometric-verify-password').value.trim();
          if (!password) {
            alert('Please enter your account password.');
            return;
          }

          const userCache = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
          if (!userCache.email) {
            alert('User profile session not found. Please log in again.');
            return;
          }

          const success = await BiometricsEngine.enable(userCache.email, password);
          if (success) {
            statusText.textContent = 'Enabled';
            document.getElementById('biometric-auth-prompt').style.display = 'none';
            document.getElementById('biometric-verify-password').value = '';
          } else {
            toggle.checked = false;
          }
        });
      }
    }
  },

  async handlePasswordSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('password-feedback');
    const submitBtn = document.getElementById('password-submit');

    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    feedback.innerHTML = '';
    
    if (newPassword !== confirmPassword) {
      feedback.innerHTML = `<div class="alert alert-danger">Passwords do not match.</div>`;
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';

    const response = await Api.post('/user/change-password.php', {
      current_password: currentPassword,
      new_password: newPassword,
      password_confirmation: confirmPassword
    });

    submitBtn.disabled = false;
    submitBtn.textContent = 'Update Password';

    if (response.success) {
      feedback.innerHTML = `<div class="alert alert-success">${response.message}</div>`;
      document.getElementById('password-form').reset();
    } else {
      let errMsg = response.message || 'Failed to update password.';
      if (response.errors) {
        const errorsList = Object.values(response.errors).flat();
        if (errorsList.length > 0) errMsg = errorsList.join('<br>');
      }
      feedback.innerHTML = `<div class="alert alert-danger">${errMsg}</div>`;
    }
  },

  async handlePinSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('pin-feedback');
    const submitBtn = document.getElementById('pin-submit');

    const currentPin = document.getElementById('current-pin').value;
    const newPin = document.getElementById('new-pin').value;
    const confirmPin = document.getElementById('confirm-pin').value;

    feedback.innerHTML = '';

    if (newPin !== confirmPin) {
      feedback.innerHTML = `<div class="alert alert-danger">Wallet PINs do not match.</div>`;
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';

    const response = await Api.post('/user/change-pin.php', {
      current_pin: currentPin,
      wallet_pin: newPin,
      wallet_pin_confirmation: confirmPin
    });

    submitBtn.disabled = false;
    submitBtn.textContent = 'Update Wallet PIN';

    if (response.success) {
      feedback.innerHTML = `<div class="alert alert-success">${response.message}</div>`;
      document.getElementById('pin-form').reset();
    } else {
      let errMsg = response.message || 'Failed to update PIN.';
      if (response.errors) {
        const errorsList = Object.values(response.errors).flat();
        if (errorsList.length > 0) errMsg = errorsList.join('<br>');
      }
      feedback.innerHTML = `<div class="alert alert-danger">${errMsg}</div>`;
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenSecurity = ScreenSecurity;
