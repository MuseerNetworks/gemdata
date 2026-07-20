const ScreenLogin = {
  mount(container, params) {
    let expiryAlert = '';
    if (params.expired === '1') {
      expiryAlert = `
        <div class="alert alert-danger" role="alert">
          Your session has expired due to inactivity. Please sign in again.
        </div>
      `;
    }

    const isBioEnabled = localStorage.getItem('gemdata_biometric_enabled') === '1';
    let actionButtonsHtml = `
      <button class="btn-primary" type="submit" id="login-submit" style="margin-top: 8px;">
        Sign In
      </button>
    `;

    if (isBioEnabled) {
      actionButtonsHtml = `
        <div style="display: flex; gap: 12px; margin-top: 8px; width: 100%;">
          <button class="btn-primary" type="submit" id="login-submit" style="flex: 1;">
            Sign In
          </button>
          <button type="button" id="login-biometric" class="btn-primary" style="width: 54px; padding: 0; display: grid; place-items: center; background: var(--color-surface-soft); color: var(--color-primary); border: 2px solid var(--color-primary-soft); border-radius: var(--radius-md); transition: background-color 0.2s;">
            <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11a5 5 0 00-10 0c0 1.905.39 3.717 1.096 5.368M20.9 8a9 9 0 01.1 2.9c0 .77-.087 1.53-.25 2.27m-.57-3.07a3 3 0 11-4.9-2.51m5.302-3.736A9.001 9.001 0 0012 3a9 9 0 00-7.043 3.42"/>
            </svg>
          </button>
        </div>
      `;
    }

    container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; justify-content: center; min-height: 100vh; padding: 24px;">
        <div style="text-align: center; margin-bottom: 32px;">
          <h1 style="font-size: 2.25rem; font-weight: 900; color: var(--color-primary); letter-spacing: -1px;">GemData</h1>
          <p style="color: var(--color-text-muted); font-size: 0.95rem; font-weight: 500; margin-top: 6px;">Sign in to manage your mobile utility services.</p>
        </div>

        ${expiryAlert}
        <div id="login-feedback"></div>

        <form id="login-form" style="display: flex; flex-direction: column; gap: 16px;">
          <div class="form-group">
            <label class="form-label" for="login-email">Email Address</label>
            <input class="form-control" type="email" id="login-email" required placeholder="name@domain.com" autocomplete="username">
          </div>
          
          <div class="form-group">
            <label class="form-label" for="login-password">Password</label>
            <input class="form-control" type="password" id="login-password" required placeholder="Enter password" autocomplete="current-password">
          </div>

          ${actionButtonsHtml}
        </form>

        <div style="text-align: center; margin-top: 24px;">
          <p style="font-size: 0.9rem; color: var(--color-text-muted);">
            Don't have an account? 
            <a href="#/register" style="color: var(--color-primary); font-weight: 700; text-decoration: none;">Sign Up</a>
          </p>
        </div>
      </div>
    `;

    document.getElementById('login-form').addEventListener('submit', (e) => this.handleSubmit(e));

    if (isBioEnabled) {
      document.getElementById('login-biometric').addEventListener('click', () => this.handleBiometricLogin());
      // Auto-trigger biometric sign in
      setTimeout(() => {
        this.handleBiometricLogin();
      }, 400);
    }
  },

  async handleBiometricLogin() {
    const creds = await BiometricsEngine.authenticate();
    if (creds && creds.email && creds.password) {
      document.getElementById('login-email').value = creds.email;
      document.getElementById('login-password').value = creds.password;
      
      const form = document.getElementById('login-form');
      if (form) {
        form.dispatchEvent(new Event('submit'));
      }
    }
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('login-feedback');
    const submitBtn = document.getElementById('login-submit');
    
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    let deviceId = localStorage.getItem('gemdata_device_id');
    if (!deviceId) {
      deviceId = 'dev_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
      localStorage.setItem('gemdata_device_id', deviceId);
    }

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Verifying Credentials...';

    const result = await Api.post('/auth/login.php', { 
      email, 
      password,
      device_id: deviceId
    });

    if (result.success) {
      localStorage.setItem('gemdata_refresh_token', result.data.refresh_token);
      localStorage.setItem('gemdata_user_cache', JSON.stringify(result.data.user));
      
      // Auto-save/update biometric credentials if enabled on device
      const isBioEnabled = localStorage.getItem('gemdata_biometric_enabled') === '1';
      if (isBioEnabled) {
        await BiometricsEngine.enable(email, password);
      }
      
      Router.navigate('#/dashboard');
    } else {
      feedback.innerHTML = `
        <div class="alert alert-danger">
          ${result.message || 'Invalid email or password.'}
        </div>
      `;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Sign In';
    }
  },

  unmount() {
    // Cleanup handlers
  }
};

window.ScreenLogin = ScreenLogin;
