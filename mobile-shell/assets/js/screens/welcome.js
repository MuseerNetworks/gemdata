const ScreenWelcome = {
  container: null,
  currentSlide: 0,
  totalSlides: 3,
  touchStartX: 0,
  touchEndX: 0,

  mount(container) {
    this.container = container;
    this.currentSlide = 0;

    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh; background: var(--color-bg); justify-content: space-between; padding: 24px; box-sizing: border-box;">
        <!-- Top Action Row -->
        <div style="display: flex; justify-content: flex-end; height: 32px; align-items: center;">
          <button id="welcome-skip" style="border: 0; background: none; color: var(--color-primary); font-size: 0.9rem; font-weight: 800; cursor: pointer; transition: opacity 0.2s;">
            Skip
          </button>
        </div>

        <!-- Slides Wrapper -->
        <div id="welcome-slides-container" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; overflow: hidden; position: relative;">
          <!-- Slide 1 -->
          <div class="welcome-slide active" data-slide="0" style="display: flex; flex-direction: column; align-items: center; width: 100%;">
            <!-- Illustration 1: Phone & Data waves -->
            <div style="position: relative; width: 160px; height: 160px; margin-bottom: 32px; display: grid; place-items: center;">
              <div style="position: absolute; width: 120px; height: 120px; border-radius: 99px; background: var(--color-primary-soft); animation: pulse 2s infinite;"></div>
              <div style="position: relative; width: 68px; height: 120px; border: 4px solid var(--color-primary); border-radius: 16px; background: var(--color-surface); box-shadow: var(--shadow-md); display: flex; flex-direction: column; justify-content: space-between; padding: 8px; box-sizing: border-box;">
                <div style="width: 16px; height: 4px; background: var(--color-border); border-radius: 99px; margin: 0 auto;"></div>
                <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 4px; margin-top: 8px;">
                  <div style="width: 24px; height: 24px; border-radius: 6px; background: var(--color-success); display: grid; place-items: center; color: #fff; font-size: 0.8rem; font-weight: 900;">₦</div>
                  <div style="width: 32px; height: 4px; background: var(--color-primary-soft); border-radius: 2px;"></div>
                  <div style="width: 20px; height: 4px; background: var(--color-primary-soft); border-radius: 2px;"></div>
                </div>
                <div style="width: 8px; height: 8px; border-radius: 99px; background: var(--color-border); margin: 0 auto;"></div>
              </div>
              <div style="position: absolute; right: 10px; top: 20px; width: 36px; height: 36px; border-radius: 10px; background: var(--color-primary); color: #fff; display: grid; place-items: center; box-shadow: var(--shadow-sm);">
                <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
              </div>
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 900; color: var(--color-text); letter-spacing: -0.5px;">Fast Utility Recharges</h2>
            <p style="font-size: 0.95rem; color: var(--color-text-muted); line-height: 1.5; margin-top: 10px; max-width: 280px;">
              Top up Airtime and activate high-speed Data bundles instantly across all mobile networks.
            </p>
          </div>

          <!-- Slide 2 -->
          <div class="welcome-slide" data-slide="1" style="display: none; flex-direction: column; align-items: center; width: 100%;">
            <!-- Illustration 2: Bills payment -->
            <div style="position: relative; width: 160px; height: 160px; margin-bottom: 32px; display: grid; place-items: center;">
              <div style="position: absolute; width: 120px; height: 120px; border-radius: 99px; background: hsla(271, 70%, 55%, 0.08); animation: pulse 2.5s infinite;"></div>
              <!-- TV Screen -->
              <div style="position: relative; width: 110px; height: 72px; border: 4px solid hsl(271, 70%, 55%); border-radius: 12px; background: var(--color-surface); box-shadow: var(--shadow-md); display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 4px;">
                <div style="width: 48px; height: 28px; border-radius: 4px; background: var(--color-surface-soft); display: grid; place-items: center;">
                  <svg style="width: 16px; height: 16px; color: hsl(271, 70%, 55%);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18"/></svg>
                </div>
              </div>
              <!-- Stand -->
              <div style="width: 24px; height: 12px; background: hsl(271, 70%, 45%); margin-top: -4px; border-radius: 0 0 4px 4px;"></div>
              <!-- Bulb -->
              <div style="position: absolute; left: 15px; bottom: 30px; width: 36px; height: 36px; border-radius: 99px; background: var(--color-warning); color: #fff; display: grid; place-items: center; box-shadow: var(--shadow-sm); border: 2px solid #fff;">
                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
              </div>
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 900; color: var(--color-text); letter-spacing: -0.5px;">Easy Bills Payment</h2>
            <p style="font-size: 0.95rem; color: var(--color-text-muted); line-height: 1.5; margin-top: 10px; max-width: 280px;">
              Pay Electricity tokens and renew Cable TV subscriptions with customer validation warnings.
            </p>
          </div>

          <!-- Slide 3 -->
          <div class="welcome-slide" data-slide="2" style="display: none; flex-direction: column; align-items: center; width: 100%;">
            <!-- Illustration 3: Premium Tier Reseller shield -->
            <div style="position: relative; width: 160px; height: 160px; margin-bottom: 32px; display: grid; place-items: center;">
              <div style="position: absolute; width: 120px; height: 120px; border-radius: 99px; background: hsla(342, 85%, 55%, 0.08); animation: pulse 3s infinite;"></div>
              <!-- Shield -->
              <div style="position: relative; width: 80px; height: 96px; background: hsl(342, 85%, 55%); border-radius: 0 0 40px 40px; display: grid; place-items: center; box-shadow: var(--shadow-lg);">
                <div style="width: 60px; height: 76px; border: 2.5px dashed rgba(255,255,255,0.4); border-radius: 0 0 30px 30px; display: grid; place-items: center;">
                  <svg style="width: 32px; height: 32px; color: #fff;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </div>
              </div>
              <div style="position: absolute; right: 15px; bottom: 25px; width: 36px; height: 36px; border-radius: 10px; background: var(--color-success); color: #fff; display: grid; place-items: center; box-shadow: var(--shadow-sm); border: 2px solid #fff;">
                <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
              </div>
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 900; color: var(--color-text); letter-spacing: -0.5px;">Premium Tier Access</h2>
            <p style="font-size: 0.95rem; color: var(--color-text-muted); line-height: 1.5; margin-top: 10px; max-width: 280px;">
              Upgrade to Reseller or API Partner tiers to enjoy higher discounts and custom endpoint access.
            </p>
          </div>
        </div>

        <!-- Bottom Action Row -->
        <div style="display: flex; flex-direction: column; gap: 20px; align-items: center;">
          <!-- Slide indicators (dots) -->
          <div style="display: flex; gap: 8px;">
            ${Array.from({ length: this.totalSlides }).map((_, i) => `
              <div class="welcome-dot ${i === 0 ? 'active' : ''}" data-dot="${i}" style="width: ${i === 0 ? '20px' : '8px'}; height: 8px; border-radius: 99px; background: ${i === 0 ? 'var(--color-primary)' : 'var(--color-border)'}; transition: var(--transition-smooth);"></div>
            `).join('')}
          </div>

          <!-- CTA Action button -->
          <button id="welcome-next-btn" class="btn-primary" style="width: 100%; max-width: 320px;">
            Continue
          </button>
        </div>
      </div>
    `;

    // Hook listeners
    document.getElementById('welcome-skip').addEventListener('click', () => this.finishWelcome());
    document.getElementById('welcome-next-btn').addEventListener('click', () => this.handleNext());

    // Setup swipe gesture listeners
    const slidesContainer = document.getElementById('welcome-slides-container');
    slidesContainer.addEventListener('touchstart', (e) => this.touchStartX = e.changedTouches[0].screenX);
    slidesContainer.addEventListener('touchend', (e) => {
      this.touchEndX = e.changedTouches[0].screenX;
      this.handleSwipe();
    });
  },

  handleNext() {
    if (this.currentSlide < this.totalSlides - 1) {
      this.goToSlide(this.currentSlide + 1);
    } else {
      this.finishWelcome();
    }
  },

  handleSwipe() {
    const threshold = 50; // swipe minimum distance in px
    if (this.touchStartX - this.touchEndX > threshold) {
      // Swiped left -> next slide
      if (this.currentSlide < this.totalSlides - 1) {
        this.goToSlide(this.currentSlide + 1);
      }
    } else if (this.touchEndX - this.touchStartX > threshold) {
      // Swiped right -> previous slide
      if (this.currentSlide > 0) {
        this.goToSlide(this.currentSlide - 1);
      }
    }
  },

  goToSlide(index) {
    this.currentSlide = index;

    // Show/hide slide templates
    document.querySelectorAll('.welcome-slide').forEach(slide => {
      const idx = parseInt(slide.dataset.slide);
      if (idx === index) {
        slide.style.display = 'flex';
        slide.classList.add('active');
      } else {
        slide.style.display = 'none';
        slide.classList.remove('active');
      }
    });

    // Update dot indicator styles
    document.querySelectorAll('.welcome-dot').forEach(dot => {
      const idx = parseInt(dot.dataset.dot);
      if (idx === index) {
        dot.style.width = '20px';
        dot.style.background = 'var(--color-primary)';
      } else {
        dot.style.width = '8px';
        dot.style.background = 'var(--color-border)';
      }
    });

    // Update CTA button labels
    const btn = document.getElementById('welcome-next-btn');
    const skipBtn = document.getElementById('welcome-skip');
    if (index === this.totalSlides - 1) {
      btn.textContent = 'Get Started';
      skipBtn.style.opacity = '0';
      skipBtn.style.pointerEvents = 'none';
    } else {
      btn.textContent = 'Continue';
      skipBtn.style.opacity = '1';
      skipBtn.style.pointerEvents = 'auto';
    }
  },

  finishWelcome() {
    // Set persistent welcome flag in localStorage so it doesn't open on future app restarts
    localStorage.setItem('gemdata_welcome_seen', '1');
    Router.navigate('#/login');
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenWelcome = ScreenWelcome;
