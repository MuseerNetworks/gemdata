const spinnerMarkup = '<span class="gd-spinner" aria-hidden="true"></span>';

const setButtonLoading = (button, loading, label = '') => {
    if (!button) {
        return;
    }
    if (loading) {
        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }
        button.disabled = true;
        button.classList.add('gd-button-loading');
        button.innerHTML = `${spinnerMarkup}<span>${label || button.dataset.loadingLabel || 'Processing...'}</span>`;
        return;
    }
    button.disabled = false;
    button.classList.remove('gd-button-loading');
    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
        delete button.dataset.originalHtml;
    }
};

const escapeHTML = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const safeAttr = escapeHTML;

const setupPasswordToggles = (scope = document) => {
    scope.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.passwordToggleBound === 'true') {
            return;
        }
        button.dataset.passwordToggleBound = 'true';
        button.addEventListener('click', () => {
            const field = button.closest('.password-field')?.querySelector('input');
            if (!field) {
                return;
            }
            const showing = field.type === 'text';
            field.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.classList.toggle('is-visible', !showing);
        });
    });
};

const setupLoadingForms = (scope = document) => {
    scope.querySelectorAll('form[data-loading-form]').forEach((form) => {
        if (form.dataset.loadingBound === 'true') {
            return;
        }
        form.dataset.loadingBound = 'true';
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) {
                return;
            }
            setButtonLoading(form.querySelector('button[type="submit"]'), true);
        });
    });
};

const setupSkeletonReadiness = () => {
    document.querySelectorAll('[data-skeleton-scope]').forEach((scope) => {
        scope.classList.add('is-loaded');
    });
};

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
            if (!navigator.onLine && document.body.dataset.appSection === 'user' && form.dataset.offlineQueue === 'true') {
                queueOfflineAction(form, button, target);
                return;
            }

            setButtonLoading(button, true, button?.dataset.loadingLabel || 'Processing...');

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
                    const message = escapeHTML(payload.message || '');
                    const errorList = payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().filter(Boolean).map((error) => `<li>${escapeHTML(String(error))}</li>`).join('')
                        : '';
                    const actionLink = payload.meta?.redirect_url
                        ? `<a class="inline-flex mt-3 font-bold underline" href="${safeAttr(payload.meta.redirect_url)}">Open security settings</a>`
                        : '';
                    target.innerHTML = `
                        <div class="notice ${payload.status === 'success' ? 'notice-success' : 'notice-error'}">
                            <strong>${message}</strong>
                            ${errorList ? `<ul class="mt-3 list-disc pl-5 text-xs">${errorList}</ul>` : ''}
                            ${actionLink}
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
                setButtonLoading(button, false);
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

        const networkField = form.querySelector('[data-plan-network], [data-package-network]');
        const categoryField = form.querySelector('[data-plan-category]');
        const planField = form.querySelector('[data-data-plan-select], [data-provider-plan-select]');
        const amountField = form.querySelector('[data-plan-amount]');
        if (!networkField || !planField) {
            return;
        }

        form.dataset.providerPlanBound = 'true';
        const refreshPlans = () => {
            const network = (networkField.value || '').toLowerCase();
            const category = (categoryField?.value || '').toLowerCase();
            const requiresNetwork = planField.dataset.requireNetwork === 'true';
            let currentStillValid = false;
            const priceDisplay = form.querySelector('[data-plan-price-display]');

            Array.from(planField.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const optionNetwork = (option.dataset.network || '').toLowerCase();
                const optionCategory = (option.dataset.category || '').toLowerCase();
                const matchesNetwork = network === '' || optionNetwork === '' || optionNetwork === network;
                const matchesCategory = category === '' || optionCategory === '' || optionCategory === category;
                const visible = matchesNetwork && matchesCategory;
                option.hidden = !visible;
                if (visible && option.value === planField.value) {
                    currentStillValid = true;
                }
            });

            if (!currentStillValid) {
                planField.value = '';
            }

            const visibleOptions = Array.from(planField.options).filter((option, index) => index > 0 && !option.hidden);
            planField.disabled = (requiresNetwork && network === '') || ((network !== '' || category !== '') && visibleOptions.length === 0);
            const emptyState = form.querySelector('[data-package-empty-state]');
            if (emptyState) {
                emptyState.hidden = (network === '' && category === '') || visibleOptions.length > 0;
            }

            if (amountField) {
                const selected = planField.selectedOptions[0];
                amountField.value = selected?.dataset.amount || '';
                if (priceDisplay) {
                    priceDisplay.textContent = selected?.dataset.displayAmount || (selected?.dataset.amount ? `NGN ${Number(selected.dataset.amount).toLocaleString()}` : 'NGN 0');
                }
            } else if (priceDisplay) {
                const selected = planField.selectedOptions[0];
                priceDisplay.textContent = selected?.dataset.displayAmount || (selected?.dataset.amount ? `NGN ${Number(selected.dataset.amount).toLocaleString()}` : 'NGN 0');
            }
        };

        networkField.addEventListener('change', refreshPlans);
        categoryField?.addEventListener('change', refreshPlans);
        planField.addEventListener('change', refreshPlans);
        refreshPlans();
    });
};

const setupCableVerification = (scope = document) => {
    scope.querySelectorAll('[data-cable-verify]').forEach((button) => {
        if (button.dataset.cableVerifyBound === 'true') {
            return;
        }
        button.dataset.cableVerifyBound = 'true';
        const form = button.closest('form');
        if (!form) {
            return;
        }

        const message = form.querySelector('[data-cable-verify-message]');
        const fallbackConfirm = form.querySelector('[data-cable-fallback-confirm]');
        const fallbackCheckbox = fallbackConfirm?.querySelector('input[type="checkbox"]');
        const validationStatus = form.querySelector('[data-cable-validation-status]');

        const setVerificationMessage = (text, state) => {
            if (!message) {
                return;
            }
            message.textContent = text;
            message.dataset.state = state;
            message.hidden = text === '';
        };

        const resetVerification = () => {
            if (validationStatus) {
                validationStatus.value = '';
            }
            if (fallbackConfirm) {
                fallbackConfirm.hidden = true;
            }
            if (fallbackCheckbox) {
                fallbackCheckbox.checked = false;
            }
            setVerificationMessage('', '');
        };

        ['provider', 'smartcard_number'].forEach((name) => {
            const field = form.elements[name];
            if (field) {
                field.addEventListener('input', resetVerification);
                field.addEventListener('change', resetVerification);
            }
        });

        button.addEventListener('click', async () => {
            const provider = form.elements.provider?.value || '';
            const smartcard = form.elements.smartcard_number?.value || '';
            if (!provider || !smartcard) {
                setVerificationMessage('Select a provider and enter your smartcard/IUC number first.', 'error');
                return;
            }

            setButtonLoading(button, true, 'Verifying...');

            try {
                const body = new FormData();
                body.set('csrf_token', form.elements.csrf_token?.value || '');
                body.set('service_slug', 'cable_tv');
                body.set('provider', provider);
                body.set('smartcard_number', smartcard);

                const response = await fetch(button.dataset.endpoint || '', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': form.elements.csrf_token?.value || '',
                    },
                    body,
                });
                const payload = await response.json();
                if (payload.status === 'success' && payload.data?.validation_status === 'verified') {
                    if (validationStatus) {
                        validationStatus.value = 'verified';
                    }
                    if (fallbackConfirm) {
                        fallbackConfirm.hidden = true;
                    }
                    setVerificationMessage(payload.data?.customer_name ? `Verified: ${payload.data.customer_name}` : payload.message, 'success');
                    return;
                }

                if (validationStatus) {
                    validationStatus.value = 'unavailable';
                }
                if (fallbackConfirm) {
                    fallbackConfirm.hidden = false;
                }
                setVerificationMessage(payload.message || 'Verification temporarily unavailable. Please confirm your smartcard/IUC number before payment.', 'warning');
            } catch (error) {
                if (validationStatus) {
                    validationStatus.value = 'unavailable';
                }
                if (fallbackConfirm) {
                    fallbackConfirm.hidden = false;
                }
                setVerificationMessage('Verification temporarily unavailable. Please confirm your smartcard/IUC number before payment.', 'warning');
            } finally {
                setButtonLoading(button, false);
            }
        });

        form.addEventListener('submit', (event) => {
            if (!validationStatus || validationStatus.value !== 'unavailable') {
                return;
            }
            if (fallbackCheckbox && fallbackCheckbox.checked) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            setVerificationMessage('Please confirm your smartcard/IUC number before payment.', 'error');
        }, true);
    });
};

const setupElectricityVerification = (scope = document) => {
    scope.querySelectorAll('[data-electricity-verify]').forEach((button) => {
        if (button.dataset.electricityVerifyBound === 'true') {
            return;
        }
        button.dataset.electricityVerifyBound = 'true';
        const form = button.closest('form');
        if (!form) {
            return;
        }

        const message = form.querySelector('[data-electricity-verify-message]');
        const fallbackConfirm = form.querySelector('[data-electricity-fallback-confirm]');
        const fallbackCheckbox = fallbackConfirm?.querySelector('input[type="checkbox"]');
        const validationStatus = form.querySelector('[data-electricity-validation-status]');
        const detailsBox = form.querySelector('[data-electricity-customer-details]');
        const customerName = form.querySelector('[data-electricity-customer-name]');

        const setVerificationMessage = (text, state) => {
            if (!message) {
                return;
            }
            message.textContent = text;
            message.dataset.state = state;
            message.hidden = text === '';
        };

        const resetVerification = () => {
            if (validationStatus) {
                validationStatus.value = '';
            }
            if (fallbackConfirm) {
                fallbackConfirm.hidden = true;
            }
            if (fallbackCheckbox) {
                fallbackCheckbox.checked = false;
            }
            if (detailsBox) {
                detailsBox.hidden = true;
            }
            if (customerName) {
                customerName.textContent = 'Awaiting verification';
            }
            setVerificationMessage('', '');
        };

        ['disco', 'meter_type', 'meter_number'].forEach((name) => {
            const field = form.elements[name];
            if (field) {
                field.addEventListener('input', resetVerification);
                field.addEventListener('change', resetVerification);
            }
        });

        button.addEventListener('click', async () => {
            const disco = form.elements.disco?.value || '';
            const meterType = form.elements.meter_type?.value || '';
            const meterNumber = form.elements.meter_number?.value || '';
            if (!disco || !meterType || !meterNumber) {
                setVerificationMessage('Select a provider, meter type, and enter your meter number first.', 'error');
                return;
            }

            setButtonLoading(button, true, 'Verifying...');

            try {
                const body = new FormData();
                body.set('csrf_token', form.elements.csrf_token?.value || '');
                body.set('service_slug', 'electricity');
                body.set('disco', disco);
                body.set('meter_type', meterType);
                body.set('meter_number', meterNumber);

                const response = await fetch(button.dataset.endpoint || '', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': form.elements.csrf_token?.value || '',
                    },
                    body,
                });
                const payload = await response.json();
                if (payload.status === 'success' && payload.data?.validation_status === 'verified') {
                    if (validationStatus) {
                        validationStatus.value = 'verified';
                    }
                    if (fallbackConfirm) {
                        fallbackConfirm.hidden = true;
                    }
                    if (detailsBox) {
                        detailsBox.hidden = false;
                    }
                    if (customerName) {
                        customerName.textContent = payload.data?.customer_name || 'Meter verified';
                    }
                    setVerificationMessage(payload.data?.customer_name ? `Verified: ${payload.data.customer_name}` : payload.message, 'success');
                    return;
                }

                if (validationStatus) {
                    validationStatus.value = 'unavailable';
                }
                if (fallbackConfirm) {
                    fallbackConfirm.hidden = false;
                }
                setVerificationMessage(payload.message || 'Verification temporarily unavailable. Please confirm your meter number before payment.', 'warning');
            } catch (error) {
                if (validationStatus) {
                    validationStatus.value = 'unavailable';
                }
                if (fallbackConfirm) {
                    fallbackConfirm.hidden = false;
                }
                setVerificationMessage('Verification temporarily unavailable. Please confirm your meter number before payment.', 'warning');
            } finally {
                setButtonLoading(button, false);
            }
        });

        form.addEventListener('submit', (event) => {
            if (!validationStatus || validationStatus.value !== 'unavailable') {
                return;
            }
            if (fallbackCheckbox && fallbackCheckbox.checked) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            setVerificationMessage('Please confirm your meter number before payment.', 'error');
        }, true);
    });
};

const parseBulkSmsRecipients = (value) => {
    const seen = new Set();
    return String(value || '')
        .split(/[,\n;\s]+/)
        .map((item) => item.trim())
        .filter(Boolean)
        .filter((item) => {
            const normalized = item.replace(/[^\d+]/g, '');
            if (!normalized || seen.has(normalized)) {
                return false;
            }
            seen.add(normalized);
            return true;
        });
};

const setupBulkSmsEstimator = (scope = document) => {
    scope.querySelectorAll('[data-bulk-sms-estimator]').forEach((box) => {
        if (box.dataset.bulkSmsEstimatorBound === 'true') {
            return;
        }
        box.dataset.bulkSmsEstimatorBound = 'true';
        const form = box.closest('form');
        if (!form) {
            return;
        }

        const recipientsField = form.querySelector('[data-bulk-sms-recipients]');
        const messageField = form.querySelector('[data-bulk-sms-message]');
        const amountField = form.querySelector('[data-bulk-sms-amount]');
        const recipientCount = box.querySelector('[data-bulk-sms-recipient-count]');
        const pagesCount = box.querySelector('[data-bulk-sms-pages-count]');
        const costTarget = box.querySelector('[data-bulk-sms-cost]');
        const submitButton = form.querySelector('button[type="submit"]');
        const pricingState = form.querySelector('[data-bulk-sms-pricing-state]');
        const rate = Number(box.dataset.rate || 0);

        const formatMoney = (value) => `NGN ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        const refresh = () => {
            const recipients = parseBulkSmsRecipients(recipientsField?.value || '');
            const pages = Math.max(0, Math.ceil(String(messageField?.value || '').length / 160));
            const cost = rate > 0 ? recipients.length * Math.max(1, pages) * rate : 0;
            if (recipientCount) recipientCount.textContent = String(recipients.length);
            if (pagesCount) pagesCount.textContent = String(pages);
            if (costTarget) costTarget.textContent = formatMoney(cost);
            if (amountField) amountField.value = cost > 0 ? String(cost.toFixed(2)) : '';
            if (submitButton && rate <= 0) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-disabled', 'true');
            }
            if (pricingState) {
                pricingState.hidden = rate > 0;
            }
            return { recipients, pages, cost };
        };

        recipientsField?.addEventListener('input', refresh);
        messageField?.addEventListener('input', refresh);

        form.addEventListener('submit', (event) => {
            const state = refresh();
            if (recipientsField && state.recipients.length > 0) {
                recipientsField.value = state.recipients.join(',');
            }
            if (rate <= 0 || state.recipients.length === 0 || state.pages === 0 || state.cost <= 0) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const target = document.querySelector(form.dataset.target || '');
                if (target) {
                    target.innerHTML = '<div class="notice notice-error">Complete recipients and message body, and make sure Bulk SMS pricing is available.</div>';
                }
            }
        }, true);

        refresh();
    });
};

const setupRechargeCardCalculator = (scope = document) => {
    scope.querySelectorAll('[data-recharge-denomination]').forEach((denominationField) => {
        if (denominationField.dataset.rechargeCalculatorBound === 'true') {
            return;
        }
        denominationField.dataset.rechargeCalculatorBound = 'true';
        const form = denominationField.closest('form');
        if (!form) {
            return;
        }
        const quantityField = form.querySelector('[data-recharge-quantity]');
        const amountField = form.querySelector('[data-recharge-amount]');
        const totalTarget = form.querySelector('[data-recharge-total]');
        const formatMoney = (value) => `NGN ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        const refresh = () => {
            const denomination = Number(denominationField.value || 0);
            const quantity = Math.max(1, Number.parseInt(quantityField?.value || '1', 10) || 1);
            const total = denomination * quantity;
            if (amountField) amountField.value = total > 0 ? String(total.toFixed(2)) : '';
            if (totalTarget) totalTarget.textContent = formatMoney(total);
        };

        denominationField.addEventListener('change', refresh);
        quantityField?.addEventListener('input', refresh);
        refresh();
    });
};

const setupSegmentedControls = (scope = document) => {
    const escapeSelector = (value) => window.CSS && typeof window.CSS.escape === 'function'
        ? window.CSS.escape(value)
        : String(value).replace(/["\\]/g, '\\$&');

    scope.querySelectorAll('[data-segmented-option]').forEach((button) => {
        if (button.dataset.segmentedBound === 'true') {
            return;
        }
        button.dataset.segmentedBound = 'true';
        button.addEventListener('click', () => {
            const target = button.dataset.segmentedOption || '';
            const root = button.closest('form, fieldset, section, body') || document;
            const escapedTarget = escapeSelector(target);
            const input = root.querySelector(`[data-segmented-input="${escapedTarget}"]`) || document.querySelector(`[data-segmented-input="${escapedTarget}"]`);
            if (!input) {
                return;
            }
            input.value = button.dataset.value || '';
            document.querySelectorAll(`[data-segmented-option="${escapedTarget}"]`).forEach((item) => {
                item.classList.toggle('is-selected', item === button);
                item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
            });
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
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
const OFFLINE_QUEUE_KEY = 'gemdata-offline-queue';
let deferredInstallPrompt = null;

const createSplashController = () => {
    const splash = document.querySelector('[data-app-splash]');
    if (!splash) {
        return { ready() {} };
    }

    const message = splash.querySelector('[data-app-splash-message]');
    const slowTimer = window.setTimeout(() => {
        if (message && !splash.classList.contains('is-exiting')) {
            message.textContent = 'Preparing your dashboard…';
        }
    }, 4500);

    let hasStartedExit = false;
    const ready = () => {
        if (hasStartedExit) {
            return;
        }
        hasStartedExit = true;
        window.clearTimeout(slowTimer);

        const exit = () => {
            splash.classList.add('is-exiting');
            splash.setAttribute('aria-hidden', 'true');
            window.setTimeout(() => {
                splash.remove();
            }, 220);
        };

        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(exit);
        });
    };

    return { ready };
};

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
        airtime: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3.75h3m-6 3.75H9"/></svg>',
        data: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 20.25h.008v.008H12v-.008z"/></svg>',
        electricity: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>',
        cable_tv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>',
        exam_pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>',
        recharge_card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20M7 15h.01M11 15h2"/></svg>',
        data_card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20"/></svg>',
        bulk_sms: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>',
    };
    return icons[slug] || icons.airtime;
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

const isStandaloneApp = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

const setInstallButtonsVisible = (visible) => {
    const shouldShow = visible && !isStandaloneApp();
    document.querySelectorAll('[data-install-trigger]').forEach((button) => {
        button.hidden = !shouldShow;
    });
};

const maybeShowIosInstallMessage = () => {
    const ua = window.navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(ua);
    if (isIos && !isStandaloneApp()) {
        alert('To install GemData on iPhone or iPad, open the Share menu in Safari and choose "Add to Home Screen".');
        return true;
    }
    return false;
};

const setupInstallPrompt = () => {
    const ua = window.navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(ua);
    setInstallButtonsVisible(false);

    if (isStandaloneApp()) {
        return;
    }

    if (isIos) {
        setInstallButtonsVisible(true);
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        setInstallButtonsVisible(true);
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
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
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            setInstallButtonsVisible(false);
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

    // Always register SW at the root scope for production.
    // On production: /service-worker.js with scope '/'
    // The runtime.baseUrl may be full URL (e.g. https://gemdata.com.ng) — strip origin.
    const swPath = '/service-worker.js';
    const swScope = '/';

    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register(swPath, {
                scope: swScope
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

const setupCopyButtons = () => {
    document.querySelectorAll('[data-copy-value]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyValue || '';
            if (!value) {
                return;
            }
            const originalText = button.textContent;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = value;
                    textarea.setAttribute('readonly', 'readonly');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    textarea.remove();
                }
                button.classList.add('is-copied');
                button.setAttribute('aria-label', 'Copied');
                window.setTimeout(() => {
                    button.classList.remove('is-copied');
                    button.setAttribute('aria-label', originalText ? `Copy ${originalText.trim()}` : 'Copy');
                }, 1600);
            } catch (error) {
                console.error('Copy failed', error);
            }
        });
    });
};

const setupPurchaseModal = () => {
    const configNode = document.getElementById('purchase-service-config');
    const modal = document.querySelector('[data-purchase-modal]');
    const form = document.querySelector('[data-purchase-form]');
    if (!configNode || !modal || !form) {
        return;
    }

    let config = { services: {}, endpoint: '' };
    try {
        config = JSON.parse(configNode.textContent || '{}');
    } catch (error) {
        console.error('Purchase config could not be parsed', error);
        return;
    }

    const panel = modal.querySelector('.purchase-modal-panel');
    const fieldsTarget = modal.querySelector('[data-purchase-fields]');
    const summaryTarget = modal.querySelector('[data-purchase-summary]');
    const titleTarget = modal.querySelector('[data-purchase-title]');
    const descriptionTarget = modal.querySelector('[data-purchase-description]');
    const iconTarget = modal.querySelector('[data-purchase-icon]');
    const messageTarget = modal.querySelector('[data-purchase-message]');
    const submitButton = modal.querySelector('[data-purchase-submit]');
    const backButton = modal.querySelector('[data-purchase-back]');
    const walletTargets = document.querySelectorAll('[data-wallet-balance]');
    let activeService = null;
    let step = 'form';
    let lastFocused = null;
    let statusPollTimer = null;
    let statusPollStartedAt = 0;

    const formatMoney = (amount) => `NGN ${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const makeIdempotencyKey = () => generateIdempotencyKey('txn');

    const setMessage = (text = '', tone = 'error', asHtml = false) => {
        if (!messageTarget) return;
        messageTarget.hidden = text === '';
        if (asHtml) {
            messageTarget.innerHTML = text;
        } else {
            messageTarget.textContent = text;
        }
        messageTarget.className = `purchase-modal-message is-${tone}`;
    };

    const setStep = (nextStep) => {
        step = nextStep;
        modal.querySelectorAll('[data-step-dot]').forEach((dot) => {
            dot.classList.toggle('is-active', dot.dataset.stepDot === step);
        });
        fieldsTarget.hidden = step !== 'form';
        summaryTarget.hidden = step !== 'confirm';
        backButton.hidden = step === 'form' || step === 'result';
        submitButton.textContent = step === 'confirm' ? activeService.actionLabel : 'Continue';
    };

    const fieldValue = (name) => (new FormData(form).get(name) || '').toString().trim();
    const selectedLabel = (field, value) => {
        if (field.type === 'data_plan') {
            return (activeService.plans || []).find((plan) => plan.value === value)?.label || value;
        }
        const options = field.options || activeService.networks || [];
        return options.find((option) => option.value === value)?.label || value;
    };

    const fieldInputType = (type) => {
        if (type === 'tel') return 'tel';
        if (type === 'amount' || type === 'number') return 'number';
        return 'text';
    };

    const renderOptionButtons = (field, options) => {
        const buttons = (options || []).map((option) => `
            <button class="purchase-choice" type="button" data-choice-name="${safeAttr(field.name)}" data-choice-value="${safeAttr(option.value)}">
                <span>${escapeHTML(option.label)}</span>
            </button>
        `).join('');
        return `
            <div class="purchase-field" data-field-wrap="${safeAttr(field.name)}">
                <span class="purchase-label">${escapeHTML(field.label)}</span>
                <input type="hidden" name="${safeAttr(field.name)}" ${field.required ? 'required' : ''}>
                <div class="purchase-choice-grid">${buttons || '<div class="purchase-empty">No options available right now.</div>'}</div>
                <small class="purchase-field-error" data-field-error="${safeAttr(field.name)}"></small>
            </div>
        `;
    };

    const renderDataPlanField = (field) => `
        <div class="purchase-field" data-field-wrap="${safeAttr(field.name)}">
            <span class="purchase-label">${escapeHTML(field.label)}</span>
            <input type="hidden" name="${safeAttr(field.name)}" ${field.required ? 'required' : ''}>
            <div class="purchase-plan-list" data-plan-list>
                <div class="purchase-empty">Select a network to see available plans.</div>
            </div>
            <small class="purchase-field-error" data-field-error="${safeAttr(field.name)}"></small>
        </div>
    `;

    const renderField = (field) => {
        if (field.type === 'network_buttons') {
            return renderOptionButtons(field, activeService.networks || []);
        }
        if (field.type === 'option_buttons') {
            return renderOptionButtons(field, field.options || []);
        }
        if (field.type === 'data_plan') {
            return renderDataPlanField(field);
        }
        if (field.type === 'select') {
            const options = (field.options || []).map((option) => `<option value="${safeAttr(option.value)}">${escapeHTML(option.label)}</option>`).join('');
            return `<label class="purchase-field">${escapeHTML(field.label)}<select name="${safeAttr(field.name)}" ${field.required ? 'required' : ''}><option value="">Select ${escapeHTML(String(field.label).toLowerCase())}</option>${options}</select><small class="purchase-field-error" data-field-error="${safeAttr(field.name)}"></small></label>`;
        }
        if (field.type === 'textarea') {
            return `<label class="purchase-field">${escapeHTML(field.label)}<textarea name="${safeAttr(field.name)}" rows="4" placeholder="${safeAttr(field.placeholder || '')}" ${field.required ? 'required' : ''}></textarea><small class="purchase-field-error" data-field-error="${safeAttr(field.name)}"></small></label>`;
        }
        const readonly = field.type === 'readonly_amount' ? 'readonly' : '';
        return `<label class="purchase-field">${escapeHTML(field.label)}<input name="${safeAttr(field.name)}" type="${fieldInputType(field.type)}" inputmode="${field.type === 'amount' ? 'decimal' : ''}" placeholder="${safeAttr(field.placeholder || '')}" ${readonly} ${field.required ? 'required' : ''}><small class="purchase-field-error" data-field-error="${safeAttr(field.name)}"></small></label>`;
    };

    const renderPlans = () => {
        const planList = modal.querySelector('[data-plan-list]');
        if (!planList || !activeService) return;
        const network = fieldValue('network');
        const plans = (activeService.plans || []).filter((plan) => !network || String(plan.network).toLowerCase() === network.toLowerCase());
        if (!network) {
            planList.innerHTML = '<div class="purchase-empty">Select a network to see available plans.</div>';
            return;
        }
        if (plans.length === 0) {
            planList.innerHTML = '<div class="purchase-empty">No available plans for this network right now.</div>';
            return;
        }
        planList.innerHTML = plans.map((plan) => `
            <button class="purchase-plan-card" type="button" data-plan-value="${safeAttr(plan.value)}" data-plan-amount="${safeAttr(plan.amount)}">
                <span><strong>${escapeHTML(plan.label)}</strong><small>${escapeHTML(plan.validity || 'Available plan')}</small></span>
                <b>${escapeHTML(plan.displayAmount || formatMoney(plan.amount))}</b>
            </button>
        `).join('');
    };

    const clearErrors = () => {
        form.querySelectorAll('.purchase-field-error').forEach((node) => { node.textContent = ''; });
        form.querySelectorAll('.has-error').forEach((node) => node.classList.remove('has-error'));
    };

    const validateForm = () => {
        clearErrors();
        const errors = [];
        (activeService.fields || []).forEach((field) => {
            const value = fieldValue(field.name);
            if (field.required && !value) {
                errors.push([field.name, `${field.label} is required.`]);
            }
        });
        const pin = fieldValue('security_pin');
        if (config.pin_configured !== false && !pin) {
            errors.push(['security_pin', 'Wallet PIN is required.']);
        }
        errors.forEach(([name, text]) => {
            const wrap = form.querySelector(`[data-field-wrap="${name}"]`) || form.querySelector(`[name="${name}"]`)?.closest('.purchase-field');
            const error = form.querySelector(`[data-field-error="${name}"]`);
            wrap?.classList.add('has-error');
            if (error) error.textContent = text;
        });
        return errors.length === 0;
    };

    const buildSummary = () => {
        const rows = (activeService.fields || [])
            .filter((field) => field.name !== 'amount' || fieldValue(field.name))
            .map((field) => {
                const value = fieldValue(field.name);
                return `<div><span>${escapeHTML(field.label)}</span><strong>${field.name === 'amount' ? escapeHTML(formatMoney(value)) : escapeHTML(selectedLabel(field, value))}</strong></div>`;
            }).join('');
        summaryTarget.innerHTML = `<p>Confirm these details before we debit your wallet.</p>${rows}`;
    };

    const openModal = (slug) => {
        activeService = config.services?.[slug];
        if (!activeService) return;
        lastFocused = document.activeElement;
        titleTarget.textContent = activeService.label;
        descriptionTarget.textContent = activeService.description || 'Complete your transaction from your wallet.';
        if (iconTarget) {
            iconTarget.innerHTML = serviceIcon(activeService.slug);
        }
        form.elements.service_slug.value = activeService.slug;
        form.elements.idempotency_key.value = makeIdempotencyKey();
        submitButton.onclick = null;
        submitButton.disabled = false;
        const pinMarkup = config.pin_configured === false
            ? `<div class="purchase-empty-state">Set your Wallet PIN before making purchases. <a class="font-bold text-gem-blue" href="${safeAttr(config.pin_settings_url || 'user/settings.php#security')}">Open security settings</a></div>`
            : `<label class="purchase-field" data-field-wrap="security_pin">Wallet PIN<input name="security_pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="Enter wallet PIN" required><small class="purchase-field-error" data-field-error="security_pin"></small></label>`;
        fieldsTarget.innerHTML = [
            ...(activeService.fields || []).map(renderField),
            pinMarkup
        ].join('');
        summaryTarget.innerHTML = '';
        setMessage('');
        setStep('form');
        modal.hidden = false;
        document.body.classList.add('purchase-modal-open');
        window.setTimeout(() => panel?.focus(), 30);
        renderPlans();
    };

    const closeModal = () => {
        if (statusPollTimer) {
            window.clearTimeout(statusPollTimer);
            statusPollTimer = null;
        }
        modal.hidden = true;
        document.body.classList.remove('purchase-modal-open');
        setMessage('');
        form.reset();
        lastFocused?.focus?.();
    };

    const statusTone = (status) => {
        const normalized = String(status || 'pending').toLowerCase();
        if (normalized === 'successful') return 'green';
        if (['failed', 'refunded', 'reversed'].includes(normalized)) return 'red';
        return 'amber';
    };

    const renderRecentRows = (transactions = []) => {
        const recentTarget = document.querySelector('[data-recent-transactions]');
        if (!recentTarget) return;
        if (!Array.isArray(transactions) || transactions.length === 0) {
            recentTarget.innerHTML = '<div class="user-empty-state px-5 py-8 text-center text-[13px] text-gem-muted">No transactions yet. Fund your wallet and buy your first service.</div>';
            return;
        }

        recentTarget.innerHTML = transactions.map((tx) => {
            const status = String(tx.status || 'pending').toLowerCase();
            const tone = statusTone(status);
            const textClass = tone === 'amber' ? 'amber-600' : `gem-${tone}`;
            const dotClass = tone === 'amber' ? 'amber-500' : `gem-${tone}`;
            const receipt = tx.receipt_url ? `<a class="mt-1 inline-flex text-[11px] font-bold text-gem-blue hover:underline" href="${safeAttr(tx.receipt_url)}">View Receipt</a>` : '';
            return `
                <div class="user-list-row grid grid-cols-1 sm:grid-cols-5 gap-2 sm:gap-4 px-5 py-4 hover:bg-gem-gray/50 transition-colors" data-search-item data-search="${safeAttr(`${tx.reference || ''} ${tx.service || ''} ${status}`)}">
                    <div class="col-span-2 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-600">${serviceIcon(tx.service_slug || 'services')}</div>
                        <div><div class="text-[13px] font-semibold text-gem-text">${escapeHTML(tx.service || 'Transaction')}</div><div class="text-[11px] text-gem-muted">${escapeHTML(tx.recipient || tx.reference || '')}</div>${receipt}</div>
                    </div>
                    <div class="sm:flex sm:items-center"><span class="text-[13px] font-bold text-gem-text font-mono">${escapeHTML(tx.amount_formatted || formatMoney(tx.amount))}</span></div>
                    <div class="sm:flex sm:items-center"><span class="inline-flex items-center gap-1 bg-${tone}-50 text-${textClass} text-[11px] font-semibold px-2.5 py-1 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-${dotClass}"></span>${escapeHTML(status.charAt(0).toUpperCase() + status.slice(1))}</span></div>
                    <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted" title="${safeAttr(tx.created_at_full || tx.created_at || '')}">${escapeHTML(tx.created_at_display || 'Just now')}</span></div>
                </div>
            `;
        }).join('');
    };

    const renderProcessingState = (tx, message = 'Transaction is processing with the provider.') => {
        const status = String(tx?.status || 'pending').toLowerCase();
        const isDone = ['successful', 'failed', 'refunded', 'reversed'].includes(status);
        const receipt = tx?.receipt_url && status === 'successful'
            ? `<a class="primary-action inline-flex justify-center" href="${safeAttr(tx.receipt_url)}">View Receipt</a>`
            : '';
        const close = isDone ? '<button class="secondary-action" type="button" data-purchase-close>Close</button>' : '';
        summaryTarget.hidden = false;
        summaryTarget.innerHTML = `
            <div class="purchase-result-success purchase-processing-state">
                <strong>${escapeHTML(status === 'successful' ? 'Transaction successful' : (isDone ? status.charAt(0).toUpperCase() + status.slice(1) : 'Processing transaction'))}</strong>
                <span>Reference: ${escapeHTML(tx?.reference || 'Pending')}</span>
                <p>${escapeHTML(message)}</p>
                ${(receipt || close) ? `<div class="purchase-result-actions">${receipt}${close}</div>` : ''}
            </div>
        `;
    };

    const pollTransactionStatus = (reference) => {
        if (!reference || !config.status_endpoint) return;
        const elapsed = Date.now() - statusPollStartedAt;
        if (elapsed > 300000) {
            renderProcessingState({ reference, status: 'pending' }, 'Still processing. You can close this window and check Recent Transactions shortly.');
            return;
        }

        const url = `${config.status_endpoint}${config.status_endpoint.includes('?') ? '&' : '?'}reference=${encodeURIComponent(reference)}`;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((payload) => {
                if (payload.status !== 'success') {
                    throw new Error(payload.message || 'Could not refresh transaction status.');
                }
                const data = payload.data || {};
                const tx = data.transaction || { reference, status: 'pending' };
                if (Array.isArray(data.recent_transactions)) {
                    renderRecentRows(data.recent_transactions);
                }
                renderProcessingState(tx, payload.message || data.message || 'Transaction is processing with the provider.');
                const status = String(tx.status || 'pending').toLowerCase();
                if (['successful', 'failed', 'refunded', 'reversed'].includes(status)) {
                    statusPollTimer = null;
                    return;
                }
                const nextDelay = elapsed < 60000 ? 5000 : 10000;
                statusPollTimer = window.setTimeout(() => pollTransactionStatus(reference), nextDelay);
            })
            .catch(() => {
                const nextDelay = elapsed < 60000 ? 5000 : 10000;
                statusPollTimer = window.setTimeout(() => pollTransactionStatus(reference), nextDelay);
            });
    };

    const updateDashboard = (payload) => {
        if (payload.wallet_balance_formatted) {
            walletTargets.forEach((target) => { target.textContent = payload.wallet_balance_formatted; });
            document.querySelectorAll('.wallet-card .font-mono').forEach((node, index) => {
                if (index === 0) node.textContent = payload.wallet_balance_formatted;
            });
        }
        if (Array.isArray(payload.recent_transactions)) {
            renderRecentRows(payload.recent_transactions);
        } else if (payload.transaction) {
            const recentTarget = document.querySelector('[data-recent-transactions]');
            if (!recentTarget) return;
            const tx = payload.transaction;
            const status = tx.status || 'pending';
            const statusClass = statusTone(status);
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 sm:grid-cols-5 gap-2 sm:gap-4 px-5 py-4 bg-gem-blue/5';
            row.innerHTML = `
                <div class="col-span-2 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-600">${serviceIcon(activeService.slug)}</div>
                    <div><div class="text-[13px] font-semibold text-gem-text">${escapeHTML(tx.service || activeService.label)}</div><div class="text-[11px] text-gem-muted">${escapeHTML(tx.recipient || tx.reference || '')}</div></div>
                </div>
                <div class="sm:flex sm:items-center"><span class="text-[13px] font-bold text-gem-text font-mono">${escapeHTML(formatMoney(tx.amount))}</span></div>
                <div class="sm:flex sm:items-center"><span class="inline-flex items-center gap-1 bg-${statusClass}-50 text-${statusClass === 'amber' ? 'amber-600' : `gem-${statusClass}`} text-[11px] font-semibold px-2.5 py-1 rounded-full">${escapeHTML(status.charAt(0).toUpperCase() + status.slice(1))}</span></div>
                <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted">Just now</span></div>
            `;
            recentTarget.prepend(row);
        }
    };

    document.querySelectorAll('[data-purchase-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            if (!config.services?.[trigger.dataset.serviceSlug]) return;
            event.preventDefault();
            openModal(trigger.dataset.serviceSlug);
        });
    });

    modal.addEventListener('click', (event) => {
        const choice = event.target.closest('[data-choice-name]');
        if (choice) {
            const input = form.querySelector(`[name="${choice.dataset.choiceName}"]`);
            if (input) {
                input.value = choice.dataset.choiceValue || '';
                form.querySelectorAll(`[data-choice-name="${choice.dataset.choiceName}"]`).forEach((item) => item.classList.toggle('is-selected', item === choice));
                if (choice.dataset.choiceName === 'network') {
                    const planInput = form.querySelector('[name="plan"]');
                    const amountInput = form.querySelector('[name="amount"]');
                    if (planInput) planInput.value = '';
                    if (amountInput?.readOnly) amountInput.value = '';
                    renderPlans();
                }
            }
        }
        const plan = event.target.closest('[data-plan-value]');
        if (plan) {
            const planInput = form.querySelector('[name="plan"]');
            const amountInput = form.querySelector('[name="amount"]');
            if (planInput) planInput.value = plan.dataset.planValue || '';
            if (amountInput) amountInput.value = plan.dataset.planAmount || '';
            form.querySelectorAll('[data-plan-value]').forEach((item) => item.classList.toggle('is-selected', item === plan));
        }
        if (event.target.closest('[data-purchase-close]')) {
            closeModal();
        }
    });

    backButton?.addEventListener('click', () => setStep('form'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!activeService || submitButton.disabled) return;
        setMessage('');
        if (step === 'form') {
            if (config.pin_configured === false) {
                setMessage('Set your Wallet PIN before making purchases.', 'error');
                return;
            }
            if (!validateForm()) {
                setMessage('Please complete the highlighted fields.', 'error');
                return;
            }
            buildSummary();
            setStep('confirm');
            return;
        }

        setButtonLoading(submitButton, true, 'Processing...');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': form.elements.csrf_token.value,
                },
                body: new FormData(form),
            });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'success') {
                if (payload.meta?.redirect_url) {
                    setMessage(`${escapeHTML(payload.message || 'Action required.')} <a class="font-bold underline" href="${safeAttr(payload.meta.redirect_url)}">Open security settings</a>`, 'error', true);
                    return;
                }
                throw new Error(payload.message || 'Transaction could not be completed.');
            }
            updateDashboard(payload.data || {});
            setStep('result');
            const tx = payload.data?.transaction || {};
            const reference = tx.reference || payload.data?.reference || '';
            renderProcessingState({ reference, status: tx.status || 'pending', receipt_url: tx.receipt_url || null }, 'Transaction accepted. We are processing it with the provider now.');
            if (reference) {
                statusPollStartedAt = Date.now();
                statusPollTimer = window.setTimeout(() => pollTransactionStatus(reference), 5000);
            }
            setButtonLoading(submitButton, false);
            submitButton.textContent = 'Done';
            submitButton.onclick = (clickEvent) => {
                clickEvent.preventDefault();
                closeModal();
            };
        } catch (error) {
            setMessage(error.message || 'Something went wrong while processing the request.', 'error');
        } finally {
            setButtonLoading(submitButton, false);
            if (step !== 'result') {
                submitButton.textContent = activeService.actionLabel;
            }
        }
    });
};

const setupAdminActionIcons = (scope = document) => {
    if (document.body?.dataset.appSection !== 'admin') {
        return;
    }

    const icons = {
        view: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/><circle cx="12" cy="12" r="2.75"/></svg>',
        edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10.5-10.5a2.12 2.12 0 00-3-3L5 17v3z"/><path stroke-linecap="round" stroke-linejoin="round" d="m14.5 7.5 2 2"/></svg>',
        approve: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>',
        reject: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>',
        retry: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 17v-5h5"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.1 9A7 7 0 0118.6 7.8L20 12M4 12l1.4 4.2A7 7 0 0017.9 15"/></svg>',
        refund: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h11a5 5 0 010 10h-1"/></svg>',
        archive: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="4" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4"/></svg>',
        filter: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4"/></svg>',
        export: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 19h14"/></svg>',
        manage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m4-4H8"/><circle cx="12" cy="12" r="8"/></svg>',
        reset: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M20 20v-6h-6"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 10a7 7 0 0111.9-3M18 14a7 7 0 01-11.9 3"/></svg>',
        save: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3h12l2 2v16H5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v6h8M8 17h8"/></svg>',
    };

    const classify = (label) => {
        const text = label.toLowerCase();
        if (/\b(export|download csv|csv)\b/.test(text)) return 'export';
        if (/\b(filter|apply filters|apply)\b/.test(text)) return 'filter';
        if (/\b(reset|clear|clear maintenance|reset circuit)\b/.test(text)) return 'reset';
        if (/\b(view|details|open|load view)\b/.test(text)) return 'view';
        if (/\b(manage|review queue|check providers|manage providers)\b/.test(text)) return 'manage';
        if (/\b(edit|save|save changes|save setting|save webhook|save limits|save promo|save banner|save announcement|save campaign|send broadcast|generate invite)\b/.test(text)) return 'save';
        if (/\b(approve|activate|enable|mark paid)\b/.test(text)) return 'approve';
        if (/\b(retry|re-queue)\b/.test(text)) return 'retry';
        if (/\b(refund)\b/.test(text)) return 'refund';
        if (/\b(delete|archive|disable|deactivate|suspend|reject|maintenance)\b/.test(text)) return 'archive';
        return '';
    };

    scope.querySelectorAll('a.primary-action, a.secondary-action, button.primary-action, button.secondary-action, a.btn, button.btn, button.rounded-lg, a.rounded-lg').forEach((button) => {
        if (button.dataset.adminActionIcon === 'true' || button.querySelector('.admin-action-icon')) {
            return;
        }
        const label = (button.textContent || '').replace(/\s+/g, ' ').trim();
        if (!label || label.length > 40) {
            return;
        }
        const type = classify(label);
        if (!type || !icons[type]) {
            return;
        }
        button.dataset.adminActionIcon = 'true';
        button.classList.add('admin-action-enhanced', `admin-action-${type}`);
        const icon = document.createElement('span');
        icon.className = 'admin-action-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = icons[type];
        button.prepend(icon);
    });
};

const setupReceiptActions = (scope = document) => {
    scope.querySelectorAll('[data-receipt-print]').forEach((button) => {
        if (button.dataset.receiptPrintBound === 'true') {
            return;
        }
        button.dataset.receiptPrintBound = 'true';
        button.addEventListener('click', () => {
            window.print();
        });
    });
};

const initializeGemDataApp = () => {
    const splash = createSplashController();
    try {
        document.body.dataset.standalone = (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) ? 'true' : 'false';
        setupTheme();
        setupPasswordToggles();
        setupLoadingForms();
        setupSegmentedControls();
        setupCableVerification();
        setupElectricityVerification();
        setupBulkSmsEstimator();
        setupRechargeCardCalculator();
        bindAjaxForms();
        bindProviderPlanFields();
        setupSidebar();
        setupGuestNav();
        setupProfileMenu();
        setupSearch();
        setupBulkSelection();
        setupInstallPrompt();
        setupConnectivity();
        setupCopyButtons();
        setupAdminActionIcons();
        setupReceiptActions();
        setupPurchaseModal();
        setupServiceWorker();
        replayOfflineQueue();
        setupSkeletonReadiness();
        if (window.location.hash === '#services') {
            document.querySelectorAll('[data-nav-key]').forEach((item) => {
                item.classList.toggle('is-active', item.dataset.navKey === 'services');
            });
        }
    } finally {
        splash.ready();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeGemDataApp);
} else {
    initializeGemDataApp();
}
