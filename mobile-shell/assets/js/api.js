const Api = {
  baseUrl: (function() {
    // If running inside browser test environment (e.g. http://127.0.0.1:8080/gemdata/mobile-shell/)
    if (window.location.pathname.includes('/gemdata/')) {
      return '/gemdata/api/v1/mobile';
    }
    // Point directly to production live server API domain
    return 'https://gemdata.com.ng/api/v1/mobile';
  })(),

  async request(endpoint, options = {}) {
    const url = endpoint.startsWith('http') ? endpoint : `${this.baseUrl}${endpoint}`;
    
    // Default fetch configurations matching cookie authentication needs
    const config = {
      credentials: 'include', // Force transmission of PHPSESSID cookie
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      ...options
    };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      config.headers['Content-Type'] = 'application/json';
      config.body = JSON.stringify(options.body);
    }

    try {
      const response = await fetch(url, config);
      const payload = await response.json().catch(() => ({}));

      // Intercept expired or invalid session state
      if (response.status === 401) {
        // Clear local credentials cache
        localStorage.removeItem('gemdata_user_cache');
        localStorage.removeItem('gemdata_dashboard_cache');
        
        // Auto-redirect to login screen and render session expiry notice
        window.location.hash = '#/login?expired=1';
        return { success: false, message: payload.message || 'Session expired.' };
      }

      if (!response.ok) {
        return {
          success: false,
          message: payload.message || `HTTP Error ${response.status}`,
          errors: payload.errors || {}
        };
      }

      return payload;
    } catch (error) {
      console.error('Network request failed:', error);
      return {
        success: false,
        message: 'Network connection error. Check your connection and try again.'
      };
    }
  },

  get(endpoint) {
    return this.request(endpoint, { method: 'GET' });
  },

  post(endpoint, body) {
    return this.request(endpoint, { method: 'POST', body });
  }
};

window.Api = Api;
