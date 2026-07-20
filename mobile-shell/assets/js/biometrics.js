const BiometricsEngine = {
  // Check if biometric authentication hardware is available and enrolled on the device.
  // Updated for @aparajita/capacitor-biometric-auth v10+ API (uses checkBiometry(), not checkHardware).
  async isAvailable() {
    if (!window.Capacitor) return false;
    const { BiometricAuth } = window.Capacitor.Plugins;
    if (!BiometricAuth) return false;

    try {
      const result = await BiometricAuth.checkBiometry();
      // biometryType === 0 means BiometryType.none (no biometric hardware / not enrolled)
      // deviceIsSecure must be true (screen lock is configured)
      return result.biometryType !== 0 && result.deviceIsSecure === true;
    } catch (e) {
      console.warn('[BiometricsEngine] checkBiometry error:', e);
      return false;
    }
  },

  // Return the biometry type label for display (fingerprint / face id / etc.)
  async biometryLabel() {
    if (!window.Capacitor) return 'Fingerprint';
    const { BiometricAuth } = window.Capacitor.Plugins;
    if (!BiometricAuth) return 'Fingerprint';
    try {
      const result = await BiometricAuth.checkBiometry();
      // biometryType: 1=TouchID, 2=FaceID, 3=Fingerprint, 4=FaceAuth, 5=IrisAuth
      const labels = { 1: 'Touch ID', 2: 'Face ID', 3: 'Fingerprint', 4: 'Face Auth', 5: 'Iris' };
      return labels[result.biometryType] || 'Biometrics';
    } catch (e) {
      return 'Fingerprint';
    }
  },

  // Verify biometrics and save sign-in credentials securely.
  // In v10, authenticate() returns void — throws BiometryError on failure.
  async enable(email, password) {
    if (!window.Capacitor) return false;
    const { BiometricAuth, Preferences } = window.Capacitor.Plugins;
    if (!BiometricAuth || !Preferences) return false;

    const available = await this.isAvailable();
    if (!available) {
      alert('Biometrics are not set up or not available on this device.');
      return false;
    }

    try {
      // Authenticate first (throws on cancel or failure)
      await BiometricAuth.authenticate({
        reason: 'Verify your identity to enable biometric sign in.',
        cancelTitle: 'Cancel',
        allowDeviceCredential: false
      });

      // Reached here = authentication succeeded
      await Preferences.set({ key: 'gemdata_biometric_email', value: email });
      await Preferences.set({ key: 'gemdata_biometric_password', value: password });
      localStorage.setItem('gemdata_biometric_enabled', '1');
      return true;
    } catch (e) {
      // User cancelled or biometric failed
      if (e && e.code !== 10) { // 10 = user cancelled — don't alert on cancel
        console.error('[BiometricsEngine] enable error:', e);
        alert('Biometric enrollment failed. Please try again.');
      }
      return false;
    }
  },

  // Delete saved biometric sign-in credentials.
  async disable() {
    if (!window.Capacitor) return;
    const { Preferences } = window.Capacitor.Plugins;
    if (!Preferences) return;

    try {
      await Preferences.remove({ key: 'gemdata_biometric_email' });
      await Preferences.remove({ key: 'gemdata_biometric_password' });
      localStorage.removeItem('gemdata_biometric_enabled');
    } catch (e) {
      console.error('[BiometricsEngine] disable error:', e);
    }
  },

  // Trigger biometrics prompt and retrieve saved login credentials.
  // Returns { email, password } on success, or null on failure/cancel.
  async authenticate() {
    if (!window.Capacitor) return null;
    const { BiometricAuth, Preferences } = window.Capacitor.Plugins;
    if (!BiometricAuth || !Preferences) return null;

    try {
      // authenticate() returns void in v10 — throws BiometryError on failure
      await BiometricAuth.authenticate({
        reason: 'Sign in to your GemData account securely.',
        cancelTitle: 'Use Password',
        allowDeviceCredential: false
      });

      // Reached here = authentication succeeded — retrieve saved credentials
      const emailResult = await Preferences.get({ key: 'gemdata_biometric_email' });
      const passwordResult = await Preferences.get({ key: 'gemdata_biometric_password' });

      if (emailResult.value && passwordResult.value) {
        return { email: emailResult.value, password: passwordResult.value };
      }
      return null;
    } catch (e) {
      // User cancelled or biometric failed — silent return
      console.info('[BiometricsEngine] authenticate cancelled or failed:', e?.code, e?.message);
      return null;
    }
  }
};

window.BiometricsEngine = BiometricsEngine;
