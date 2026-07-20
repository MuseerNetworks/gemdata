const Router = {
  routes: {},
  currentScreen: null,

  // Registers a view state mapper
  add(route, screenObject) {
    this.routes[route] = screenObject;
  },

  // Performs page transition and mounts target script
  async navigate(hash) {
    window.location.hash = hash;
  },

  // Matches path and invokes screen mount lifecycle
  async resolve() {
    const hash = window.location.hash || '#/dashboard';
    const pathParts = hash.split('?');
    const route = pathParts[0];
    
    // Parse query parameters (e.g. ?expired=1)
    const queryParams = {};
    if (pathParts[1]) {
      const searchParams = new URLSearchParams(pathParts[1]);
      for (const [key, value] of searchParams.entries()) {
        queryParams[key] = value;
      }
    }

    const screen = this.routes[route];
    if (!screen) {
      console.warn(`Route ${route} not found. Defaulting to dashboard.`);
      this.navigate('#/dashboard');
      return;
    }

    // Call unmount on existing active screen
    if (this.currentScreen && typeof this.currentScreen.unmount === 'function') {
      this.currentScreen.unmount();
    }

    this.currentScreen = screen;
    const container = document.getElementById('app-container');
    
    // Mount screen views — wrapped in try/finally so the splash ALWAYS hides
    // even if mount() throws an unhandled exception (prevents permanent hang)
    try {
      if (typeof screen.mount === 'function') {
        await screen.mount(container, queryParams);
      }
    } catch (err) {
      console.error('[Router] screen.mount() threw an unhandled error:', err);
    } finally {
      // Dismiss native splash screen unconditionally
      if (window.App && typeof window.App.hideSplashScreen === 'function') {
        window.App.hideSplashScreen();
      }
    }
  },

  init() {
    window.addEventListener('hashchange', () => this.resolve());
    // Initial resolution
    this.resolve();
  }
};

window.Router = Router;
