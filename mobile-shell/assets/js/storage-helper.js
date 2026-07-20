const StorageHelper = {
  // 1. Favorites/Beneficiaries Management
  getFavorites(type) {
    const list = JSON.parse(localStorage.getItem('gemdata_favorites') || '[]');
    return list.filter(fav => fav.type === type);
  },

  addFavorite(type, value, label) {
    const list = JSON.parse(localStorage.getItem('gemdata_favorites') || '[]');
    // Prevent duplicate entries
    const exists = list.some(fav => fav.type === type && fav.value === value);
    if (!exists) {
      list.push({
        id: 'fav_' + Date.now(),
        type,
        value: trimString(value),
        label: trimString(label)
      });
      localStorage.setItem('gemdata_favorites', JSON.stringify(list));
      if (window.Logger) {
        window.Logger.triggerHaptic('success');
      }
    }
  },

  removeFavorite(id) {
    let list = JSON.parse(localStorage.getItem('gemdata_favorites') || '[]');
    list = list.filter(fav => fav.id !== id);
    localStorage.setItem('gemdata_favorites', JSON.stringify(list));
  },

  // Renders a dropdown list of favorites for forms
  renderFavoritesDropdown(type, selectId, onSelectCallbackName) {
    const list = this.getFavorites(type);
    if (list.length === 0) return '';

    return `
      <div class="form-group" style="margin-top: 8px;">
        <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--color-text-muted);">Quick Select Favorite</label>
        <select class="form-control" id="${selectId}" onchange="${onSelectCallbackName}(this.value)" style="padding: 8px 12px; font-size: 0.8rem; border-radius: 8px;">
          <option value="" selected>-- Select Saved Beneficiary --</option>
          ${list.map(fav => `
            <option value="${fav.value}">${fav.label} (${fav.value})</option>
          `).join('')}
        </select>
      </div>
    `;
  },

  // 2. Recent Purchases Management
  getRecent() {
    return JSON.parse(localStorage.getItem('gemdata_recent_purchases') || '[]');
  },

  addRecent(service, payload, description) {
    const list = JSON.parse(localStorage.getItem('gemdata_recent_purchases') || '[]');
    // Filter out previous entry with exact same description to avoid redundancy
    let filtered = list.filter(item => item.description !== description);
    
    filtered.unshift({
      id: 'rec_' + Date.now(),
      service,
      payload,
      description,
      timestamp: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    });

    if (filtered.length > 5) {
      filtered.pop(); // Keep only last 5 recent purchases
    }

    localStorage.setItem('gemdata_recent_purchases', JSON.stringify(filtered));
  },

  // Renders horizontal widget of recent purchases for dashboard
  renderRecentPurchasesWidget() {
    const list = this.getRecent();
    if (list.length === 0) return '';

    return `
      <div style="margin-top: 24px; margin-bottom: 8px;">
        <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px;">Quick Re-purchase</h4>
        <div style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 8px; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory;">
          ${list.map(item => `
            <div onclick="StorageHelper.triggerRepeatPurchase('${item.id}')" style="flex: 0 0 160px; scroll-snap-align: start; background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: 12px; padding: 12px; cursor: pointer; transition: var(--transition-smooth); text-align: left; box-shadow: var(--shadow-sm);">
              <span style="display: block; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: var(--color-primary);">${item.service}</span>
              <span style="display: block; font-size: 0.78rem; font-weight: 700; color: var(--color-text); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.description}</span>
              <span style="display: block; font-size: 0.65rem; color: var(--color-text-muted); margin-top: 8px;">Repeat: ${item.timestamp}</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  },

  triggerRepeatPurchase(id) {
    const list = this.getRecent();
    const item = list.find(r => r.id === id);
    if (!item) return;

    if (window.Logger) {
      window.Logger.triggerHaptic('light');
    }

    // Direct routing based on service type
    const query = new URLSearchParams(item.payload).toString();
    if (item.service === 'airtime') {
      Router.navigate(`#/buy?service=airtime&repeat=${item.id}`);
    } else if (item.service === 'data') {
      Router.navigate(`#/buy?service=data&repeat=${item.id}`);
    } else if (item.service === 'cable') {
      Router.navigate(`#/cable?repeat=${item.id}`);
    } else if (item.service === 'electricity') {
      Router.navigate(`#/electricity?repeat=${item.id}`);
    } else if (item.service === 'exam') {
      Router.navigate(`#/exam?repeat=${item.id}`);
    } else if (item.service === 'recharge') {
      Router.navigate(`#/recharge?repeat=${item.id}`);
    } else if (item.service === 'bulksms') {
      Router.navigate(`#/bulksms?repeat=${item.id}`);
    }
  }
};

function trimString(val) {
  return typeof val === 'string' ? val.trim() : '';
}

window.StorageHelper = StorageHelper;
