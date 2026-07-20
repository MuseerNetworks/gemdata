const ScreenServices = {
  container: null,
  searchQuery: '',

  // Define dynamic services list
  catalog: [
    {
      id: 'airtime',
      title: 'Buy Airtime',
      desc: 'Instant airtime recharge across all networks',
      route: '#/buy?service=airtime',
      iconColor: 'var(--color-primary)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`
    },
    {
      id: 'data',
      title: 'Buy Data',
      desc: 'Top up high-speed internet bundles',
      route: '#/buy?service=data',
      iconColor: 'var(--color-success)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`
    },
    {
      id: 'cable',
      title: 'Cable TV',
      desc: 'DStv, GOtv, and StarTimes renewals',
      route: '#/cable',
      iconColor: 'hsl(271, 70%, 55%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18"/></svg>`
    },
    {
      id: 'electricity',
      title: 'Electricity',
      desc: 'Pay prepaid & postpaid electricity bills',
      route: '#/electricity',
      iconColor: 'var(--color-warning)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>`
    },
    {
      id: 'exam',
      title: 'Exam PINs',
      desc: 'WAEC, NECO, and JAMB registration tokens',
      route: '#/exam',
      iconColor: 'hsl(199, 90%, 48%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>`
    },
    {
      id: 'recharge',
      title: 'Recharge Cards',
      desc: 'Generate & print physical network recharge pins',
      route: '#/recharge',
      iconColor: 'hsl(342, 85%, 55%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>`
    },
    {
      id: 'bulksms',
      title: 'Bulk SMS',
      desc: 'Broadcast bulk SMS notifications instantly',
      route: '#/bulksms',
      iconColor: 'hsl(162, 75%, 40%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>`
    },
    {
      id: 'withdrawals',
      title: 'Withdrawals',
      desc: 'Transfer commission bonus to main wallet',
      route: '#/withdrawals',
      iconColor: 'hsl(28, 95%, 55%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
    },
    {
      id: 'referrals',
      title: 'Refer & Earn',
      desc: 'View referrals, commissions and code',
      route: '#/referrals',
      iconColor: 'hsl(205, 90%, 48%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`
    },
    {
      id: 'support',
      title: 'Support WhatsApp',
      desc: 'Chat directly with official customer care lines',
      route: 'whatsapp',
      iconColor: 'hsl(142, 70%, 45%)',
      iconSvg: `<svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>`
    }
  ],

  mount(container) {
    this.container = container;
    this.searchQuery = '';
    this.render();
    
    // Enable Pull-to-Refresh Gesture
    const scrollEl = this.container.querySelector('.app-main');
    App.enablePullToRefresh(scrollEl, async () => {
      // Reload lists or clear query
      this.searchQuery = '';
      const input = document.getElementById('services-search');
      if (input) input.value = '';
      this.filterServices();
    });
  },

  render() {
    this.container.innerHTML = `
      <div class="screen-view" style="display: flex; flex-direction: column; min-height: 100vh;">
        ${App.renderHeader('Services')}
        
        <main class="app-main ptr-container" style="flex: 1;">
          <div style="margin-bottom: 16px;">
            <input type="search" id="services-search" class="form-control" placeholder="Search services..." oninput="ScreenServices.handleSearch(event)" style="padding: 12px 16px; border-radius: 12px;">
          </div>

          <!-- Services grid container -->
          <div id="services-grid" style="display: flex; flex-direction: column; gap: 12px;">
            ${this.renderGrid(this.catalog)}
          </div>
        </main>

        ${App.renderNavigation('#/services')}
      </div>
    `;
  },

  renderGrid(list) {
    if (list.length === 0) {
      return `
        <div style="text-align: center; padding: 40px 16px; color: var(--color-text-muted);">
          <div style="font-size: 3rem; margin-bottom: 12px;">🔍</div>
          <h4 style="font-weight: 700; color: var(--color-text);">No services found</h4>
          <p style="font-size: 0.82rem; margin-top: 4px;">Try searching for another service, e.g. "Data" or "SMS"</p>
        </div>
      `;
    }

    return list.map(svc => {
      const clickAction = svc.route === 'whatsapp' ? 'App.openWhatsAppSupport()' : `Router.navigate('${svc.route}')`;
      return `
        <div onclick="${clickAction}" style="display: flex; align-items: center; background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; cursor: pointer; transition: transform 0.2s, border-color 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
          <div style="width: 48px; height: 48px; border-radius: 12px; background: ${svc.iconColor}15; color: ${svc.iconColor}; display: grid; place-items: center; margin-right: 16px; shrink-0;">
            ${svc.iconSvg}
          </div>
          <div style="flex: 1;">
            <span style="display: block; font-weight: 800; font-size: 0.95rem; color: var(--color-text);">${svc.title}</span>
            <span style="display: block; font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px;">${svc.desc}</span>
          </div>
          <div style="color: var(--color-border);">
            <svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          </div>
        </div>
      `;
    }).join('');
  },

  handleSearch(e) {
    this.searchQuery = e.target.value.toLowerCase().trim();
    this.filterServices();
  },

  filterServices() {
    const grid = document.getElementById('services-grid');
    if (!grid) return;

    const filtered = this.catalog.filter(svc => 
      svc.title.toLowerCase().includes(this.searchQuery) || 
      svc.desc.toLowerCase().includes(this.searchQuery)
    );

    grid.innerHTML = this.renderGrid(filtered);
  },

  unmount() {
    this.container = null;
  }
};

window.ScreenServices = ScreenServices;
