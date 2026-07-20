const PushEngine = {
  // Initialize registration permissions and notification triggers
  async init() {
    if (!window.Capacitor) return;
    const { PushNotifications } = window.Capacitor.Plugins;
    if (!PushNotifications) return;

    try {
      let permStatus = await PushNotifications.checkPermissions();
      if (permStatus.receive === 'prompt') {
        permStatus = await PushNotifications.requestPermissions();
      }

      if (permStatus.receive !== 'granted') {
        console.warn('Push notification permissions not granted.');
        return;
      }

      // Register device with APNS/FCM networks
      await PushNotifications.register();

      // Listeners
      PushNotifications.addListener('registration', async (token) => {
        console.log('Push device token registered successfully:', token.value);
        this.saveTokenToServer(token.value);
      });

      PushNotifications.addListener('registrationError', (error) => {
        console.error('Push token registration failed:', error);
      });

      // Intercept notifications received while the app is active in the foreground
      PushNotifications.addListener('pushNotificationReceived', (notification) => {
        console.log('Push notification received in foreground:', notification);
        this.showLocalNotificationBanner(
          notification.title || 'GemData Notification',
          notification.body || ''
        );
        
        // Refresh dashboard statistics to sync balances instantly on alert arrival
        if (Router.currentScreen && typeof Router.currentScreen.loadData === 'function') {
          Router.currentScreen.loadData();
        }
      });

      // Handle user tapping on notifications
      PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
        console.log('Push notification action clicked:', action);
        // Redirect directly to history/transactions list if appropriate
        Router.navigate('#/transactions');
      });
    } catch (e) {
      console.warn('PushEngine setup skipped or failed:', e);
    }
  },

  // Save/Update push token mapping inside backend server
  async saveTokenToServer(pushToken) {
    const token = localStorage.getItem('gemdata_refresh_token');
    const deviceId = localStorage.getItem('gemdata_device_id');
    if (!token || !deviceId) return; // Wait until authenticated

    try {
      const platform = window.Capacitor.getPlatform();
      await Api.post('/user/register-push.php', {
        push_token: pushToken,
        platform: platform,
        device_id: deviceId
      });
      console.info('FCM token mapped to user account successfully.');
    } catch (e) {
      console.error('Failed to register FCM token with server:', e);
    }
  },

  // Display a premium in-app slide-down banner for foreground alerts
  showLocalNotificationBanner(title, body) {
    const banner = document.createElement('div');
    banner.style.cssText = `
      position: fixed;
      top: 16px;
      left: 16px;
      right: 16px;
      background: var(--color-surface);
      border-left: 5px solid var(--color-primary);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-lg);
      padding: 16px;
      z-index: 999999;
      display: flex;
      flex-direction: column;
      gap: 4px;
      box-sizing: border-box;
      animation: pushSlideDown 0.3s cubic-bezier(0.32, 0.72, 0, 1) forwards;
    `;

    banner.innerHTML = `
      <span style="font-weight: 900; font-size: 0.95rem; color: var(--color-text);">${title}</span>
      <span style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.4;">${body}</span>
    `;

    document.body.appendChild(banner);

    // Play native haptic tap vibrator
    if (window.Capacitor) {
      const { Haptics } = window.Capacitor.Plugins;
      if (Haptics) {
        Haptics.vibrate();
      }
    }

    // Dismiss banner automatically after 5 seconds
    setTimeout(() => {
      banner.style.animation = 'pushSlideUp 0.3s ease forwards';
      setTimeout(() => banner.remove(), 300);
    }, 5000);
  }
};

window.PushEngine = PushEngine;
