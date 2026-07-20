const SyncEngine = {
  queueKey: 'gemdata_offline_queue',

  // Retrieve queued items
  getQueue() {
    try {
      return JSON.parse(localStorage.getItem(this.queueKey) || '[]');
    } catch (e) {
      return [];
    }
  },

  // Save items back to queue
  saveQueue(queue) {
    localStorage.setItem(this.queueKey, JSON.stringify(queue));
  },

  // Add a new transaction payload to the offline queue
  enqueue(endpoint, payload, description) {
    const queue = this.getQueue();
    const id = 'offline_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
    
    queue.push({
      id,
      endpoint,
      payload,
      description,
      timestamp: new Date().toLocaleString()
    });

    this.saveQueue(queue);
    
    // Inject queued indicator into recent transaction list if currently viewed
    this.refreshLocalTransactionLogs();
    return id;
  },

  // Process all queued transactions sequentially once connection is detected
  async processQueue() {
    if (!navigator.onLine) return;

    let queue = this.getQueue();
    if (queue.length === 0) return;

    console.info(`SyncEngine: Processing ${queue.length} offline queued requests.`);
    
    const activeQueue = [...queue];
    let syncedCount = 0;
    let failedList = [];

    for (const item of activeQueue) {
      try {
        const result = await Api.post(item.endpoint, item.payload);
        
        if (result.success) {
          syncedCount++;
          // Remove from local storage queue list
          queue = queue.filter(q => q.id !== item.id);
          this.saveQueue(queue);
        } else {
          // If it is a logic error (e.g. invalid PIN or insufficient funds), remove it to prevent endless retries
          const isNetworkError = result.message && result.message.includes('Network');
          if (!isNetworkError) {
            failedList.push(`${item.description}: ${result.message}`);
            queue = queue.filter(q => q.id !== item.id);
            this.saveQueue(queue);
          }
        }
      } catch (err) {
        console.warn('SyncEngine retry failed due to connection error:', err);
        break; // Stop processing further queue items if connection drops again
      }
    }

    if (syncedCount > 0 || failedList.length > 0) {
      let msg = '';
      if (syncedCount > 0) {
        msg += `${syncedCount} queued transaction(s) processed successfully!\n`;
      }
      if (failedList.length > 0) {
        msg += `Failed offline items:\n${failedList.join('\n')}`;
      }
      
      alert(msg);
      
      // Update dashboard or transactions list state
      if (Router.currentScreen && typeof Router.currentScreen.loadData === 'function') {
        Router.currentScreen.loadData();
      } else if (Router.currentScreen && typeof Router.currentScreen.fetchFreshData === 'function') {
        Router.currentScreen.fetchFreshData();
      }
    }
  },

  // Helper to display offline queued transactions locally in history
  refreshLocalTransactionLogs() {
    const container = document.getElementById('history-tx-container') || document.getElementById('recent-transactions-container');
    if (!container) return;

    const queue = this.getQueue();
    if (queue.length === 0) return;

    // Prepend offline items with status: offline to list container if present
    const offlineHtml = queue.map(item => `
      <div class="tx-item" style="border-color: var(--color-warning); opacity: 0.82;">
        <div class="tx-left">
          <span class="tx-title">${item.description}</span>
          <span class="tx-meta" style="color: var(--color-warning); font-weight: 700;">Queued (Offline)</span>
          <span class="tx-meta">${item.timestamp}</span>
        </div>
        <div class="tx-right">
          <span class="tx-amount">NGN ${Number(item.payload.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
          <div>
            <span class="badge badge-pending">Offline</span>
          </div>
        </div>
      </div>
    `).join('');

    // Prepend to list
    const originalHtml = container.innerHTML;
    if (originalHtml.includes('No transactions found') || originalHtml.includes('Fetching')) {
      container.innerHTML = offlineHtml;
    } else {
      container.innerHTML = offlineHtml + originalHtml;
    }
  },

  init() {
    // Process queue immediately on start if online
    if (navigator.onLine) {
      this.processQueue();
    }

    // Process on status recovery
    window.addEventListener('online', () => this.processQueue());

    // Process on native App resume
    if (window.Capacitor) {
      const { App } = window.Capacitor.Plugins;
      if (App) {
        App.addListener('appStateChange', (state) => {
          if (state.isActive) {
            this.processQueue();
          }
        });
      }
    }
  }
};

window.SyncEngine = SyncEngine;
