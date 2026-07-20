const ScreenReferrals = {
  container: null,
  referralCode: '',
  referralLink: '',
  referralCount: 0,
  earnings: 0,

  mount(container) {
    this.container = container;
    
    // Parse cached user info
    try {
      const user = JSON.parse(localStorage.getItem('gemdata_user_cache') || '{}');
      this.referralCode = user.username || 'user';
      this.referralLink = `https://gemdata.com.ng/register?ref=${this.referralCode}`;
    } catch (e) {
      this.referralCode = 'user';
      this.referralLink = 'https://gemdata.com.ng/register';
    }

    this.loadData();
  },

  async loadData() {
    this.renderLoading();

    // Query dashboard state to recover counts
    const response = await Api.get('/user/dashboard.php');
    if (response.success && response.data) {
      // Find referral stats
      const stats = response.data.stats || {};
      this.referralCount = stats.total_referrals || 0;
      this.earnings = stats.referral_earnings || 0;
    }
    
    this.render();

    const scrollEl = this.container.querySelector('.app-main');
    App.enablePullToRefresh(scrollEl, async () => {
      await this.loadData();
    });
  },

  renderLoading() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Refer & Earn')}
        <main class="app-main">
          <div class="shimmer-skeleton skeleton-card" style="margin-bottom: 20px;"></div>
          <div class="shimmer-skeleton skeleton-text" style="width: 60%; margin-bottom: 12px;"></div>
          <div class="shimmer-skeleton skeleton-list-item" style="margin-bottom: 10px;"></div>
        </main>
        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  render() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Refer & Earn')}
        
        <main class="app-main ptr-container">
          <!-- Banner card -->
          <div style="background: linear-gradient(135deg, hsl(342, 85%, 55%), hsl(290, 80%, 50%)); color: #fff; border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; text-align: center; box-shadow: var(--shadow-md);">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">🎁</div>
            <h2 style="font-size: 1.35rem; font-weight: 900;">Share the Benefits!</h2>
            <p style="font-size: 0.82rem; opacity: 0.95; margin-top: 6px; line-height: 1.4;">
              Invite your friends to register on GemData and earn commission bonuses on all their utility purchases!
            </p>
          </div>

          <!-- Stats Summary box -->
          <div class="grid-cols-2" style="gap: 12px; margin-bottom: 24px;">
            <div style="background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; text-align: center; box-shadow: var(--shadow-sm);">
              <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--color-text-muted);">Total Referrals</span>
              <strong style="display: block; font-size: 1.6rem; font-weight: 900; margin-top: 4px; color: var(--color-text);">${this.referralCount}</strong>
            </div>
            <div style="background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; text-align: center; box-shadow: var(--shadow-sm);">
              <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--color-text-muted);">Bonus Earned</span>
              <strong style="display: block; font-size: 1.6rem; font-weight: 900; margin-top: 4px; color: var(--color-success); font-family: monospace;">₦${this.earnings.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong>
            </div>
          </div>

          <!-- Link Share Widget -->
          <div style="background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm);">
            <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px;">Your Personal Invitation Link</h4>
            
            <div style="background: var(--color-surface-soft); border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 0.8rem; color: var(--color-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 16px;">
              ${this.referralLink}
            </div>

            <button class="btn-primary" onclick="ScreenReferrals.copyLink()" id="referral-copy-btn">
              Copy Invitation Link
            </button>
          </div>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  copyLink() {
    navigator.clipboard.writeText(this.referralLink).then(() => {
      if (window.Logger) {
        window.Logger.triggerHaptic('success');
      }
      
      const btn = document.getElementById('referral-copy-btn');
      if (btn) {
        btn.textContent = 'Link Copied!';
        btn.style.background = 'var(--color-success)';
        btn.style.borderColor = 'var(--color-success)';
        
        setTimeout(() => {
          btn.textContent = 'Copy Invitation Link';
          btn.style.background = '';
          btn.style.borderColor = '';
        }, 2000);
      }
    }).catch(err => {
      alert('Could not copy link. Copy manually:\n' + this.referralLink);
    });
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenReferrals = ScreenReferrals;
