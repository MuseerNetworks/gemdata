const ScreenRegister = {
  mount(container, params) {
    container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; justify-content: center; min-height: 100vh; padding: 24px;">
        <div style="text-align: center; margin-bottom: 24px;">
          <h1 style="font-size: 2.25rem; font-weight: 900; color: var(--color-primary); letter-spacing: -1px;">Create Account</h1>
          <p style="color: var(--color-text-muted); font-size: 0.95rem; font-weight: 500; margin-top: 6px;">Sign up to open your wallet and get started.</p>
        </div>

        <div id="register-feedback"></div>

        <form id="register-form" style="display: flex; flex-direction: column; gap: 14px;">
          <div class="form-group">
            <label class="form-label" for="register-name">Full Name</label>
            <input class="form-control" type="text" id="register-name" required placeholder="e.g. John Doe" minlength="3">
          </div>

          <div class="form-group">
            <label class="form-label" for="register-email">Email Address</label>
            <input class="form-control" type="email" id="register-email" required placeholder="e.g. john@example.com">
          </div>

          <div class="form-group">
            <label class="form-label" for="register-phone">Phone Number</label>
            <input class="form-control" type="tel" id="register-phone" required placeholder="e.g. 08012345678" pattern="[0-9]{10,15}">
          </div>
          
          <div class="form-group">
            <label class="form-label" for="register-password">Password</label>
            <input class="form-control" type="password" id="register-password" required placeholder="At least 8 characters" minlength="8" autocomplete="new-password">
          </div>

          <div class="form-group">
            <label class="form-label" for="register-password-confirm">Confirm Password</label>
            <input class="form-control" type="password" id="register-password-confirm" required placeholder="Re-enter password" autocomplete="new-password">
          </div>

          <div class="form-group">
            <label class="form-label" for="register-pin">Wallet Transaction PIN</label>
            <input class="form-control" type="password" id="register-pin" required placeholder="4 to 6 digits" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off">
          </div>

          <div class="form-group">
            <label class="form-label" for="register-pin-confirm">Confirm Wallet PIN</label>
            <input class="form-control" type="password" id="register-pin-confirm" required placeholder="Re-enter Wallet PIN" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off">
          </div>

          <button class="btn-primary" type="submit" id="register-submit" style="margin-top: 8px;">
            Register Account
          </button>
        </form>

        <div style="text-align: center; margin-top: 24px;">
          <p style="font-size: 0.9rem; color: var(--color-text-muted);">
            Already have an account? 
            <a href="#/login" style="color: var(--color-primary); font-weight: 700; text-decoration: none;">Sign In</a>
          </p>
        </div>
      </div>
    `;

    document.getElementById('register-form').addEventListener('submit', (e) => this.handleSubmit(e));
  },

  async handleSubmit(e) {
    e.preventDefault();
    const feedback = document.getElementById('register-feedback');
    const submitBtn = document.getElementById('register-submit');
    
    const fullName = document.getElementById('register-name').value;
    const email = document.getElementById('register-email').value;
    const phone = document.getElementById('register-phone').value;
    const password = document.getElementById('register-password').value;
    const passwordConfirm = document.getElementById('register-password-confirm').value;
    const walletPin = document.getElementById('register-pin').value;
    const walletPinConfirm = document.getElementById('register-pin-confirm').value;

    feedback.innerHTML = '';

    // Frontend validations
    if (password !== passwordConfirm) {
      feedback.innerHTML = `<div class="alert alert-danger">Passwords do not match.</div>`;
      return;
    }

    if (walletPin !== walletPinConfirm) {
      feedback.innerHTML = `<div class="alert alert-danger">Wallet PINs do not match.</div>`;
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating Account...';

    const result = await Api.post('/auth/register.php', {
      full_name: fullName,
      email,
      phone,
      password,
      wallet_pin: walletPin
    });

    if (result.success) {
      feedback.innerHTML = `
        <div class="alert alert-success">
          ${result.message || 'Registration successful! Directing to Login...'}
        </div>
      `;
      setTimeout(() => {
        Router.navigate('#/login');
      }, 2000);
    } else {
      // Parse validation errors if present
      let errorMsg = result.message || 'An error occurred during registration.';
      if (result.errors) {
        const errorList = Object.values(result.errors).flat();
        if (errorList.length > 0) {
          errorMsg = errorList.join('<br>');
        }
      }
      feedback.innerHTML = `
        <div class="alert alert-danger">
          ${errorMsg}
        </div>
      `;
      submitBtn.disabled = false;
      submitBtn.textContent = 'Register Account';
    }
  },

  unmount() {
    // Cleanup handlers
  }
};

window.ScreenRegister = ScreenRegister;
