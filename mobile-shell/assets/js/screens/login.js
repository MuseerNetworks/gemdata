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

          <button class="btn-primary" type="submit" id="login-submit" style="margin-top: 8px;">
            Sign In
          </button>
        </form>
      </div>
    `;

    document.getElementById('login-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('login-feedback');
    const submitBtn = document.getElementById('login-submit');
    
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    feedback.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Verifying Credentials...';

    const result = await Api.post('/auth/login.php', { email, password });

    if (result.success) {
      // Save basic user variables to localStorage cache
      localStorage.setItem('gemdata_user_cache', JSON.stringify(result.data.user));
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
    // Cleanup handlers if any
  }
};

window.ScreenLogin = ScreenLogin;
