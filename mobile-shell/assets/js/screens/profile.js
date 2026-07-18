const ScreenProfile = {
  container: null,

  mount(container) {
    this.container = container;
    
    // Retrieve user details from cache
    let name = 'GemData User';
    let email = 'user@domain.com';
    let role = 'Smart User';

    const cache = localStorage.getItem('gemdata_dashboard_cache');
    if (cache) {
      try {
        const parsed = JSON.parse(cache);
        name = parsed.user.full_name;
        email = parsed.user.email;
        role = parsed.user.role_label;
        App.updateHeaderBalance(parsed.wallet.balance);
      } catch (e) {}
    }

    container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Profile & Settings')}
        
        <main class="app-main">
          <!-- Profile Panel -->
          <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 24px; text-align: center; box-shadow: var(--shadow-sm); margin-bottom: 20px;">
            <div style="width: 64px; height: 64px; border-radius: 99px; background: var(--color-primary-soft); color: var(--color-primary); display: grid; place-items: center; font-size: 1.5rem; font-weight: 800; margin: 0 auto 12px;">
              ${name.charAt(0).toUpperCase()}
            </div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--color-text);">${name}</h3>
            <p style="font-size: 0.85rem; color: var(--color-text-muted);">${email}</p>
            <span style="font-size: 0.72rem; font-weight: 800; background: var(--color-primary-soft); color: var(--color-primary); padding: 4px 10px; border-radius: 8px; text-transform: uppercase; margin-top: 8px; display: inline-block;">
              ${role}
            </span>
          </div>

          <!-- Settings list links -->
          <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px;">
            <div class="menu-card" style="flex-direction: row; align-items: center; justify-content: space-between; padding: 18px;">
              <div>
                <div style="font-size: 0.88rem; font-weight: 800;">Wallet Security PIN</div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px;">Update transaction PIN</div>
              </div>
              <span style="color: var(--color-text-muted); font-size: 0.88rem;">&gt;</span>
            </div>

            <div class="menu-card" style="flex-direction: row; align-items: center; justify-content: space-between; padding: 18px;">
              <div>
                <div style="font-size: 0.88rem; font-weight: 800;">Push Notifications</div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px;">Preferences configure</div>
              </div>
              <span style="color: var(--color-text-muted); font-size: 0.88rem;">&gt;</span>
            </div>
          </div>

          <!-- Log out action -->
          <button class="btn-primary" onclick="ScreenProfile.handleLogout()" style="background: var(--color-danger); box-shadow: 0 8px 24px hsla(350, 89%, 60%, 0.25);">
            Logout Account
          </button>
        </main>

        ${App.renderNavigation('#/profile')}
      </div>
    `;
  },

  async handleLogout() {
    if (confirm('Are you sure you want to log out of your GemData account?')) {
      await Api.post('/auth/logout.php');
      localStorage.removeItem('gemdata_user_cache');
      localStorage.removeItem('gemdata_dashboard_cache');
      Router.navigate('#/login');
    }
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenProfile = ScreenProfile;
