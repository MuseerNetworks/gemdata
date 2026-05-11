const bindAjaxForms = (scope = document) => {
    scope.querySelectorAll('[data-ajax-form]').forEach((form) => {
        if (form.dataset.ajaxBound === 'true') {
            return;
        }
        form.dataset.ajaxBound = 'true';
        ensureIdempotencyKeys(form);
        bindProviderPlanFields(form);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const target = document.querySelector(form.dataset.target || '');
            const button = form.querySelector('button[type="submit"]');
            const original = button ? button.textContent : '';
            if (button) {
                button.dataset.originalText = original;
            }

            if (!navigator.onLine && document.body.dataset.appSection === 'user' && form.dataset.offlineQueue === 'true') {
                queueOfflineAction(form, button, target);
                return;
            }

            if (button) {
                button.disabled = true;
                button.textContent = 'Processing...';
            }

            try {
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': form.querySelector('[name="csrf_token"]')?.value || '',
                    },
                    body: new FormData(form),
                });
                const payload = await response.json();
                if (target) {
                    target.innerHTML = `
                        <div class="notice ${payload.status === 'success' ? 'notice-success' : 'notice-error'}">
                            <strong>${payload.message}</strong>
                            ${payload.errors ? `<pre class="mt-3 whitespace-pre-wrap text-xs">${JSON.stringify(payload.errors, null, 2)}</pre>` : ''}
                        </div>`;
                }
                if (payload.status === 'success' && form.dataset.resetOnSuccess === 'true') {
                    form.reset();
                    ensureIdempotencyKeys(form, true);
                }
                if (payload.status === 'success') {
                    document.dispatchEvent(new CustomEvent('gemdata:form-success', { detail: { form, payload } }));
                }
                if (payload.meta && payload.meta.redirect_url) {
                    window.location.href = payload.meta.redirect_url;
                    return;
                }
                if (payload.meta && payload.meta.reload) {
                    window.location.reload();
                }
            } catch (error) {
                if (target) {
                    target.innerHTML = '<div class="notice notice-error">Something went wrong while processing the request.</div>';
                }
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = original;
                }
            }
        });
    });
};

const ensureIdempotencyKeys = (scope = document, force = false) => {
    scope.querySelectorAll('[data-idempotency-field]').forEach((field) => {
        if (!force && field.value) {
            return;
        }
        field.value = generateIdempotencyKey(field.dataset.idempotencyPrefix || 'req');
    });
};

const bindProviderPlanFields = (scope = document) => {
    const forms = scope.matches && scope.matches('form')
        ? [scope]
        : Array.from(scope.querySelectorAll('form'));

    forms.forEach((form) => {
        if (form.dataset.providerPlanBound === 'true') {
            return;
        }

        const networkField = form.querySelector('[data-plan-network]');
        const planField = form.querySelector('[data-data-plan-select]');
        const amountField = form.querySelector('[data-plan-amount]');
        if (!networkField || !planField) {
            return;
        }

        form.dataset.providerPlanBound = 'true';
        const refreshPlans = () => {
            const network = (networkField.value || '').toLowerCase();
            let currentStillValid = false;

            Array.from(planField.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const optionNetwork = (option.dataset.network || '').toLowerCase();
                const visible = network === '' || optionNetwork === '' || optionNetwork === network;
                option.hidden = !visible;
                if (visible && option.value === planField.value) {
                    currentStillValid = true;
                }
            });

            if (!currentStillValid) {
                planField.value = '';
            }

            if (amountField) {
                const selected = planField.selectedOptions[0];
                amountField.value = selected?.dataset.amount || amountField.value || '';
            }
        };

        networkField.addEventListener('change', refreshPlans);
        planField.addEventListener('change', refreshPlans);
        refreshPlans();
    });
};

const generateIdempotencyKey = (prefix = 'req') => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return `${prefix}:${window.crypto.randomUUID()}`;
    }
    const random = Math.random().toString(16).slice(2);
    return `${prefix}:${Date.now().toString(16)}${random}`;
};

const THEME_KEY = 'gemdata-theme';
const DEFAULT_THEME = 'light-fintech';
const INSTALL_PROMPT_KEY = 'gemdata-install-dismissed';
const OFFLINE_QUEUE_KEY = 'gemdata-offline-queue';
let deferredInstallPrompt = null;

const applyTheme = (themeName) => {
    const theme = themeName || DEFAULT_THEME;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(THEME_KEY, theme);

    document.querySelectorAll('[data-theme-select]').forEach((select) => {
        if (select.value !== theme) {
            select.value = theme;
        }
    });

    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.themeOption === theme);
    });
};

const setupTheme = () => {
    const stored = localStorage.getItem(THEME_KEY) || DEFAULT_THEME;
    applyTheme(stored);

    document.querySelectorAll('[data-theme-select]').forEach((select) => {
        select.addEventListener('change', () => applyTheme(select.value));
    });

    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.dataset.themeOption));
    });
};

const setupSidebar = () => {
    const shell = document.querySelector('.app-shell');
    const toggle = document.getElementById('sidebar-toggle');
    const backdrop = document.getElementById('sidebar-backdrop');

    if (!shell || !toggle || !backdrop) {
        return;
    }

    const closeSidebar = () => shell.classList.remove('is-sidebar-open');
    toggle.addEventListener('click', () => shell.classList.toggle('is-sidebar-open'));
    backdrop.addEventListener('click', closeSidebar);
    document.querySelectorAll('.sidebar-link').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });
};

const setupGuestNav = () => {
    const toggle = document.querySelector('[data-guest-nav-toggle]');
    const panel = document.querySelector('[data-guest-nav-panel]');
    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', () => {
        const nextState = !panel.classList.contains('is-open');
        panel.classList.toggle('is-open', nextState);
        panel.hidden = !nextState;
    });

    panel.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            panel.classList.remove('is-open');
            panel.hidden = true;
        });
    });
};

const setupProfileMenu = () => {
    document.querySelectorAll('[data-profile-menu]').forEach((menu) => {
        const toggle = menu.querySelector('[data-profile-toggle]');
        if (!toggle) {
            return;
        }
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            menu.classList.toggle('is-open');
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-profile-menu]').forEach((menu) => menu.classList.remove('is-open'));
    });
};

const applySearchFilter = (query) => {
    const normalized = query.trim().toLowerCase();
    document.querySelectorAll('[data-search-item]').forEach((item) => {
        const haystack = (item.dataset.search || item.textContent || '').toLowerCase();
        item.classList.toggle('is-filtered', normalized !== '' && !haystack.includes(normalized));
    });
};

const setupSearch = () => {
    const search = document.querySelector('[data-dashboard-search]');
    if (!search) {
        return;
    }
    search.addEventListener('input', () => applySearchFilter(search.value));
};

const serviceIcon = (slug) => {
    const icons = {
        airtime: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5.5h4"/><path d="M10.5 18h3"/></svg>',
        data: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 9a8 8 0 0 1 16 0"/><path d="M7 12a5 5 0 0 1 10 0"/><path d="M10 15a2 2 0 0 1 4 0"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/></svg>',
        electricity: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 5 14h6l-1 8 8-12h-6z"/></svg>',
        cable_tv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="12" rx="2"/><path d="m9 21 3-4 3 4"/><path d="M10 9h4"/></svg>',
        exam_pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18H6z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>',
        recharge_card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 10h8M8 14h5"/></svg>',
        data_card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 13h8"/></svg>',
        bulk_sms: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v10H7l-3 3z"/><path d="M8 10h8"/></svg>',
    };
    return icons[slug] || icons.airtime;
};

const setupServicePanel = () => {
    const dashboard = document.querySelector('[data-services-dashboard]');
    if (!dashboard) {
        return;
    }

    const overlay = dashboard.querySelector('[data-service-overlay]');
    const panelBody = dashboard.querySelector('[data-service-panel-body]');
    const panelTitle = dashboard.querySelector('[data-service-panel-title]');
    const panelDescription = dashboard.querySelector('[data-service-panel-description]');
    const panelConfidence = dashboard.querySelector('[data-service-panel-confidence]');
    const feedback = dashboard.querySelector('#ajax-feedback');
    const closeButtons = dashboard.querySelectorAll('[data-close-panel]');
    const cards = dashboard.querySelectorAll('[data-service-card]');

    cards.forEach((card) => {
        const iconTarget = card.querySelector('.service-card-icon');
        if (iconTarget) {
            iconTarget.innerHTML = serviceIcon(card.dataset.serviceSlug || '');
        }
    });

    const closePanel = () => {
        overlay.classList.remove('is-open');
        cards.forEach((card) => card.classList.remove('is-active'));
        if (panelBody) {
            panelBody.innerHTML = '';
        }
    };

    const openPanel = (card) => {
        const template = document.getElementById(card.dataset.templateId);
        if (!template || !panelBody) {
            return;
        }
        cards.forEach((item) => item.classList.toggle('is-active', item === card));
        panelTitle.textContent = card.dataset.serviceName || 'Service';
        panelDescription.textContent = card.dataset.serviceDescription || 'Complete the form below to continue.';
        if (panelConfidence) {
            const confidenceTemplate = document.getElementById(card.dataset.confidenceTemplateId || '');
            panelConfidence.innerHTML = confidenceTemplate ? confidenceTemplate.innerHTML : '';
        }
        panelBody.innerHTML = template.innerHTML;
        const iconWrap = panelBody.querySelector('[data-service-form-icon]');
        if (iconWrap) {
            iconWrap.innerHTML = serviceIcon(card.dataset.serviceSlug || '');
        }
        overlay.classList.add('is-open');
        bindAjaxForms(panelBody);
        const firstInput = panelBody.querySelector('input, select, textarea');
        if (firstInput) {
            window.setTimeout(() => firstInput.focus(), 80);
        }
        if (feedback) {
            feedback.innerHTML = '';
        }
    };

    cards.forEach((card) => {
        card.addEventListener('click', () => openPanel(card));
    });

    closeButtons.forEach((button) => button.addEventListener('click', closePanel));
    overlay?.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closePanel();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    document.addEventListener('gemdata:form-success', (event) => {
        const form = event.detail?.form;
        if (form && overlay.contains(form)) {
            window.setTimeout(() => closePanel(), 700);
        }
    });
};

const setupBulkSelection = () => {
    document.querySelectorAll('[data-bulk-form]').forEach((form) => {
        const items = Array.from(document.querySelectorAll(`[data-bulk-item][form="${form.id}"]`));
        const selectPage = document.querySelector(`[data-select-page][data-bulk-target="${form.id}"]`);
        const bulkBar = form.querySelector('[data-bulk-bar]') || form.nextElementSibling;
        const countTarget = form.querySelector('[data-selected-count]');
        const actionSelect = form.querySelector('[data-bulk-action-select]');
        const tierSelect = form.querySelector('[data-bulk-tier-select]');
        const passwordInput = form.querySelector('[data-bulk-password]');

        const resolvedCountTarget = countTarget || bulkBar?.querySelector('[data-selected-count]');
        const resolvedActionSelect = actionSelect || bulkBar?.querySelector('[data-bulk-action-select]');
        const resolvedTierSelect = tierSelect || bulkBar?.querySelector('[data-bulk-tier-select]');
        const resolvedPasswordInput = passwordInput || bulkBar?.querySelector('[data-bulk-password]');

        if (items.length === 0 || !bulkBar || !resolvedCountTarget) {
            return;
        }

        const sync = () => {
            const checked = items.filter((item) => item.checked).length;
            resolvedCountTarget.textContent = String(checked);
            bulkBar.hidden = checked === 0;
            if (selectPage) {
                selectPage.checked = checked === items.length;
            }
            if (resolvedActionSelect && resolvedTierSelect) {
                resolvedTierSelect.hidden = resolvedActionSelect.value !== 'set_tier';
            }
            if (resolvedActionSelect && resolvedPasswordInput) {
                resolvedPasswordInput.hidden = resolvedActionSelect.value !== 'deactivate';
            }
        };

        items.forEach((item) => item.addEventListener('change', sync));
        selectPage?.addEventListener('change', () => {
            items.forEach((item) => {
                item.checked = !!selectPage.checked;
            });
            sync();
        });
        resolvedActionSelect?.addEventListener('change', sync);
        sync();
    });
};

const getRuntime = () => window.GEMDATA_RUNTIME || { baseUrl: '', section: 'user' };

const queueStorage = {
    all() {
        try {
            const parsed = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    },
    save(items) {
        localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(items));
    },
    push(item) {
        const items = queueStorage.all();
        items.push(item);
        queueStorage.save(items);
    },
    clear() {
        queueStorage.save([]);
    }
};

const updateConnectionBanner = (message = '', tone = 'info') => {
    const banner = document.getElementById('connection-banner');
    if (!banner) {
        return;
    }

    if (!message) {
        banner.hidden = true;
        banner.textContent = '';
        banner.className = 'connection-banner';
        return;
    }

    banner.hidden = false;
    banner.textContent = message;
    banner.className = `connection-banner is-${tone}`;
};

const setInstallButtonsVisible = (visible) => {
    document.querySelectorAll('[data-install-trigger]').forEach((button) => {
        button.hidden = !visible;
    });
};

const maybeShowIosInstallMessage = () => {
    const ua = window.navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(ua);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    if (isIos && !isStandalone) {
        alert('To install GemData on iPhone or iPad, open the Share menu in Safari and choose "Add to Home Screen".');
        return true;
    }
    return false;
};

const setupInstallPrompt = () => {
    const dismissed = localStorage.getItem(INSTALL_PROMPT_KEY) === '1';
    const ua = window.navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(ua);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    if (!dismissed) {
        setInstallButtonsVisible(false);
    }

    if (isIos && !isStandalone && !dismissed) {
        setInstallButtonsVisible(true);
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        if (!dismissed) {
            setInstallButtonsVisible(true);
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        localStorage.setItem(INSTALL_PROMPT_KEY, '1');
        setInstallButtonsVisible(false);
    });

    document.querySelectorAll('[data-install-trigger]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (maybeShowIosInstallMessage()) {
                return;
            }
            if (!deferredInstallPrompt) {
                alert('GemData can be installed once your browser reports install availability. Reload the page on Chrome/Android after visiting once online.');
                return;
            }
            deferredInstallPrompt.prompt();
            const result = await deferredInstallPrompt.userChoice;
            if (result.outcome !== 'accepted') {
                localStorage.setItem(INSTALL_PROMPT_KEY, '1');
                setInstallButtonsVisible(false);
            }
            deferredInstallPrompt = null;
        });
    });
};

const serializeFormData = (form) => {
    const fields = [];
    new FormData(form).forEach((value, key) => {
        fields.push([key, value]);
    });
    return fields;
};

const replayOfflineQueue = async () => {
    if (!navigator.onLine) {
        return;
    }

    const runtime = getRuntime();
    if (runtime.section !== 'user') {
        return;
    }

    const queue = queueStorage.all();
    if (queue.length === 0) {
        updateConnectionBanner('');
        return;
    }

    updateConnectionBanner(`Syncing ${queue.length} queued action${queue.length === 1 ? '' : 's'}...`, 'info');
    const remaining = [];
    let processed = 0;

    for (const item of queue) {
        try {
            const formData = new FormData();
            (item.fields || []).forEach(([key, value]) => formData.append(key, value));
            const response = await fetch(item.action, {
                method: item.method || 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': item.csrfToken || '',
                    'X-GemData-Queued': '1'
                },
                body: formData
            });
            const payload = await response.json();
            if (payload.status === 'success') {
                processed += 1;
                continue;
            }
            remaining.push(item);
        } catch (error) {
            remaining.push(item);
        }
    }

    queueStorage.save(remaining);
    if (processed > 0 && remaining.length === 0) {
        updateConnectionBanner(`Queued actions synced successfully. Refresh to see the latest wallet and transaction state.`, 'success');
    } else if (processed > 0) {
        updateConnectionBanner(`${processed} queued action${processed === 1 ? '' : 's'} synced. ${remaining.length} still pending.`, 'warning');
    } else {
        updateConnectionBanner(`${remaining.length} queued action${remaining.length === 1 ? '' : 's'} still pending until the server is reachable.`, 'warning');
    }
};

const setupConnectivity = () => {
    const runtime = getRuntime();
    if (runtime.section !== 'user') {
        return;
    }

    if (queueStorage.all().length > 0) {
        queueStorage.clear();
        updateConnectionBanner('Previously queued actions were cleared. Financial requests now require a live connection for safe processing.', 'warning');
    }

    const syncBanner = () => {
        const queued = queueStorage.all().length;
        if (!navigator.onLine) {
            updateConnectionBanner(`You are offline. ${queued} queued action${queued === 1 ? '' : 's'} will sync when connection returns.`, 'warning');
            return;
        }
        if (queued > 0) {
            updateConnectionBanner(`${queued} queued action${queued === 1 ? '' : 's'} waiting to sync.`, 'info');
        } else {
            updateConnectionBanner('');
        }
    };

    window.addEventListener('online', () => {
        syncBanner();
        replayOfflineQueue();
    });
    window.addEventListener('offline', syncBanner);
    syncBanner();
};

const queueOfflineAction = (form, button, target) => {
    updateConnectionBanner(`Only explicitly safe actions can queue offline. Financial actions require a live connection.`, 'warning');
    if (target) {
        target.innerHTML = '<div class="notice notice-error"><strong>Connection required.</strong> This action was not submitted because it needs a live server connection.</div>';
    }
    if (button) {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
    }
};

const setupServiceWorker = () => {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const runtime = getRuntime();
    const serviceWorkerPath = `${runtime.baseUrl || ''}/service-worker.js`;
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register(serviceWorkerPath, {
                scope: `${runtime.baseUrl || '/'}`
            });

            if (registration.waiting) {
                const shouldRefresh = window.confirm('A new GemData update is ready. Refresh now to use it?');
                if (shouldRefresh) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
            }

            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;
                if (!worker) {
                    return;
                }
                worker.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        const shouldRefresh = window.confirm('GemData has been updated. Reload now to apply the latest version?');
                        if (shouldRefresh && registration.waiting) {
                            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                        }
                    }
                });
            });

            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'QUEUE_SYNC_REQUEST') {
                    replayOfflineQueue();
                }
            });

            let refreshing = false;
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                if (refreshing) {
                    return;
                }
                refreshing = true;
                window.location.reload();
            });
        } catch (error) {
            console.error('Service worker registration failed', error);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.standalone = (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) ? 'true' : 'false';
    setupTheme();
    bindAjaxForms();
    bindProviderPlanFields();
    setupSidebar();
    setupGuestNav();
    setupProfileMenu();
    setupSearch();
    setupServicePanel();
    setupBulkSelection();
    setupInstallPrompt();
    setupConnectivity();
    setupServiceWorker();
    replayOfflineQueue();
    if (window.location.hash === '#services') {
        document.querySelectorAll('[data-nav-key]').forEach((item) => {
            item.classList.toggle('is-active', item.dataset.navKey === 'services');
        });
    }
});
