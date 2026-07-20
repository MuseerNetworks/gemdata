const Logger = {
  init() {
    // Intercept uncaught JS exceptions
    window.onerror = (message, source, lineno, colno, error) => {
      this.logError({
        message,
        source,
        lineno,
        colno,
        stack: error ? error.stack : ''
      }, 'JAVASCRIPT_EXCEPTION');
      return false; // Let browser process error as normal
    };

    // Intercept unhandled promise rejections
    window.onunhandledrejection = (event) => {
      this.logError({
        message: event.reason ? event.reason.message : 'Unhandled Promise Rejection',
        stack: event.reason ? event.reason.stack : ''
      }, 'UNHANDLED_REJECTION');
    };

    console.info('Centralized telemetry logger initialized.');
  },

  async logError(errorData, type = 'API_ERROR') {
    const payload = {
      type,
      timestamp: new Date().toISOString(),
      url: window.location.href,
      user_agent: navigator.userAgent,
      error: errorData
    };

    console.error(`[Telemetry Logger - ${type}]:`, errorData);

    // Save locally to session logs for debugging
    const logs = JSON.parse(localStorage.getItem('gemdata_debug_logs') || '[]');
    logs.push(payload);
    if (logs.length > 50) logs.shift(); // Cap at last 50 logs
    localStorage.setItem('gemdata_debug_logs', JSON.stringify(logs));

    // Try posting to backend endpoint if online
    if (navigator.onLine) {
      try {
        // Suppress circular dependency inside Api object calls
        const headers = {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        };
        const token = localStorage.getItem('gemdata_refresh_token');
        if (token) {
          headers['Authorization'] = `Bearer ${token}`;
        }
        
        fetch('/gemdata/api/v1/mobile/user/log-error.php', {
          method: 'POST',
          headers,
          body: JSON.stringify(payload),
          credentials: 'include'
        });
      } catch (e) {
        // Fail silently
      }
    }
  },

  // Centralized Haptic Feedback launcher
  triggerHaptic(type = 'light') {
    if (window.Capacitor) {
      const { Haptics } = window.Capacitor.Plugins;
      if (Haptics) {
        try {
          if (type === 'light') {
            Haptics.impact({ style: 'LIGHT' });
          } else if (type === 'medium') {
            Haptics.impact({ style: 'MEDIUM' });
          } else if (type === 'heavy') {
            Haptics.impact({ style: 'HEAVY' });
          } else if (type === 'success') {
            Haptics.notification({ type: 'SUCCESS' });
          } else if (type === 'error') {
            Haptics.notification({ type: 'ERROR' });
          } else if (type === 'warning') {
            Haptics.notification({ type: 'WARNING' });
          } else {
            Haptics.vibrate();
          }
        } catch (e) {
          // Native device does not support it
        }
      }
    }
  }
};

window.Logger = Logger;
Logger.init();
