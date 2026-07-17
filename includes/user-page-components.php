<?php

declare(strict_types=1);

function render_user_coming_soon_page(string $title, string $subtitle, array $actions = []): void
{
    render_header($title, 'user');
    ?>
    <div class="space-y-6">
        <div class="stagger-1">
            <h1 class="text-2xl font-extrabold text-gem-text"><?= e($title); ?></h1>
            <p class="text-[14px] text-gem-muted mt-0.5"><?= e($subtitle); ?></p>
        </div>
        <section class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border p-5 stagger-2">
            <div class="activate-banner rounded-2xl flex flex-col sm:flex-row items-start sm:items-center gap-4 px-5 py-4">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 text-gem-yellow"><?= icon_svg('shield'); ?></div>
                <div class="flex-1">
                    <div class="text-[14px] font-bold text-gem-text">Coming Soon</div>
                    <div class="text-[13px] text-gem-muted mt-0.5">This module is prepared in the GemData dashboard style and will be connected when the backend workflow is ready.</div>
                </div>
            </div>
            <?php if ($actions !== []): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-5">
                    <?php foreach ($actions as $action): ?>
                        <a class="service-icon user-premium-card user-premium-link bg-white rounded-2xl p-4 shadow-card border border-gem-border" href="<?= e($action['href']); ?>">
                            <div class="user-icon-box user-icon-blue !w-10 !h-10 !rounded-xl mb-3"><?= icon_svg($action['icon'] ?? 'services'); ?></div>
                            <div class="text-[13px] font-bold text-gem-text"><?= e($action['label']); ?></div>
                            <div class="text-[11px] text-gem-muted mt-0.5"><?= e($action['copy'] ?? 'Open module'); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    render_footer();
}

function render_service_shortcut_page(string $title, string $slug, string $copy): void
{
    $user = require_user();
    $dashboard = app(\GemData\Classes\DashboardController::class)->dataFor($user);
    $walletBalance = (float) ($dashboard['wallet']['balance'] ?? 0);
    $service = null;
    foreach ($dashboard['services'] as $candidate) {
        if (($candidate['slug'] ?? '') === $slug) {
            $service = $candidate;
            break;
        }
    }

    render_header($title, 'user');
    ?>
    <div class="purchase-page-shell" data-purchase-page>
        <a class="purchase-back-link" href="<?= e(base_url('user/services.php')); ?>"><?= icon_svg('chevron'); ?> Back to Services</a>
        <section class="purchase-card stagger-1">
            <div class="purchase-heading">
                <div>
                    <p class="text-[12px] font-bold uppercase tracking-wider text-gem-blue">GemData Purchase</p>
                    <h1><?= e($title); ?></h1>
                    <p><?= e($copy); ?></p>
                </div>
            </div>
            <div class="purchase-balance-strip">
                <span>Available Balance</span>
                <strong><?= e(money($walletBalance)); ?></strong>
            </div>
            <div id="purchase-feedback" class="purchase-feedback"></div>
            <?php if ($service): ?>
                <?php render_purchase_form($slug, $service, $dashboard); ?>
            <?php else: ?>
                <div class="text-center py-8 text-[13px] text-gem-muted">This service is not enabled yet.</div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    render_footer();
}

function render_purchase_form(string $slug, array $service, array $dashboard): void
{
    $user = $dashboard['user'] ?? [];
    $pinConfigured = user_wallet_pin_configured((int) ($user['id'] ?? 0));
    $serviceNetworks = $dashboard['service_networks'] ?? [];
    $dataPlanCatalog = $dashboard['data_plan_catalog'] ?? [];
    $cablePlanCatalog = purchase_plan_catalog('cable_tv');
    $examPlanCatalog = purchase_plan_catalog('exam_pin');
    $bulkSmsCatalog = purchase_plan_catalog('bulk_sms');
    $rechargeCardCatalog = purchase_plan_catalog('recharge_card');
    $dataCardCatalog = purchase_plan_catalog('data_card');
    $bulkSmsRate = purchase_first_positive_amount($bulkSmsCatalog);
    $bulkSmsPricingAvailable = $bulkSmsRate > 0;
    $submitDisabled = false;
    $submitLabel = match ($slug) {
        'airtime' => 'Buy Airtime',
        'data' => 'Buy Data',
        'electricity' => 'Pay Electricity Bill Now',
        'cable_tv' => 'Renew Subscription Now',
        'exam_pin' => 'Buy Exam PIN Now',
        'bulk_sms' => 'Send Bulk SMS Now',
        'recharge_card' => 'Generate Recharge Cards',
        'data_card' => 'Generate Data Card',
        default => 'Continue',
    };
    ?>
    <form class="purchase-form" method="post" action="<?= e(base_url('user/service-action.php')); ?>" data-ajax-form data-target="#purchase-feedback" data-reset-on-success="true" data-offline-queue="false">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="service_slug" value="<?= e($slug); ?>">
        <input type="hidden" name="idempotency_key" value="" data-idempotency-field data-idempotency-prefix="txn">

        <?php if ($slug === 'airtime'): ?>
            <?php render_purchase_provider_control('network', 'Network', $serviceNetworks['airtime'] ?? [], 'airtime-network', false); ?>
            <label>Phone Number <input name="phone" inputmode="tel" autocomplete="tel" placeholder="08030000000" required></label>
            <label>Amount <input name="amount" inputmode="decimal" placeholder="1000" required></label>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'data'): ?>
            <?php
            $dataProviders = purchase_provider_options(
                $serviceNetworks['data'] ?? [],
                $dataPlanCatalog,
                purchase_default_mobile_networks()
            );
            $dataOrder = ['mtn' => 0, 'glo' => 1, 'airtel' => 2, '9mobile' => 3, '9mob' => 3, 'etisalat' => 3];
            usort($dataProviders, static fn(array $a, array $b): int => ($dataOrder[strtolower((string) ($a['network_code'] ?? ''))] ?? 50) <=> ($dataOrder[strtolower((string) ($b['network_code'] ?? ''))] ?? 50));
            $dataLabels = ['mtn' => 'MTN', 'glo' => 'GLO', 'airtel' => 'AIRTEL', '9mobile' => '9MOB', '9mob' => '9MOB', 'etisalat' => '9MOB'];
            ?>
            <fieldset class="purchase-fieldset">
                <legend>Select Network Provider</legend>
                <input type="hidden" name="network" value="" data-segmented-input="data-network" data-plan-network required>
                <div class="provider-segment-grid data-network-segment-grid" role="group" aria-label="Select Network Provider">
                    <?php foreach ($dataProviders as $option): ?>
                        <?php $code = strtolower((string) ($option['network_code'] ?? '')); ?>
                        <button type="button" class="provider-segment data-network-segment" data-network-tone="<?= e($code); ?>" data-segmented-option="data-network" data-value="<?= e($code); ?>">
                            <?= e($dataLabels[$code] ?? strtoupper((string) ($option['network_name'] ?? $code))); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <label>Choose Data Plan
                <?php if ($dataPlanCatalog !== []): ?>
                    <select name="plan" data-data-plan-select data-require-network="true" required>
                        <option value="">Select data plan</option>
                        <?php foreach ($dataPlanCatalog as $plan): ?>
                            <?php $amount = (float) ($plan['amount'] ?? 0); ?>
                            <option value="<?= e($plan['local_plan_code']); ?>" data-network="<?= e($plan['network_code']); ?>" data-amount="<?= e((string) $amount); ?>" data-display-amount="<?= e($amount > 0 ? money($amount) : ''); ?>">
                                <?= e(trim((string) $plan['local_plan_name'] . (trim((string) ($plan['validity_label'] ?? '')) !== '' ? ' - ' . trim((string) $plan['validity_label']) : '') . ($amount > 0 ? ' - ' . money($amount) : ''))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select name="plan" disabled required>
                        <option value="">No data plans available right now</option>
                    </select>
                <?php endif; ?>
            </label>
            <?php if ($dataPlanCatalog !== []): ?>
                <div class="purchase-empty-state" data-package-empty-state hidden>No data plans available for this network right now.</div>
            <?php else: ?>
                <div class="purchase-empty-state">No data plans available right now.</div>
            <?php endif; ?>
            <div class="purchase-summary-row"><span>Plan Price</span><strong data-plan-price-display>NGN 0</strong></div>
            <label>Recipient Phone Number <input name="phone" inputmode="tel" autocomplete="tel" placeholder="08012345678" required></label>
            <input type="hidden" name="amount" value="" data-plan-amount>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'electricity'): ?>
            <?php
            $electricityProviders = purchase_provider_options(
                $serviceNetworks['electricity'] ?? [],
                purchase_plan_catalog('electricity'),
                purchase_default_electricity_providers()
            );
            render_purchase_select_control('disco', 'Select Disco / Provider', $electricityProviders, 'electricity-disco', '', true);
            ?>
            <label>Meter Type
                <select name="meter_type" required>
                    <option value="">Select meter type</option>
                    <option value="prepaid">Prepaid</option>
                    <option value="postpaid">Postpaid</option>
                </select>
            </label>
            <div class="purchase-inline-field">
                <label>Meter Number <input name="meter_number" inputmode="numeric" placeholder="12345678901" required></label>
                <button class="purchase-secondary-action" type="button" data-electricity-verify data-endpoint="<?= e(base_url('user/service-verify.php')); ?>">Verify</button>
            </div>
            <div class="purchase-verification-message" data-electricity-verify-message></div>
            <label class="purchase-confirm-check" data-electricity-fallback-confirm hidden>
                <input type="checkbox" name="electricity_meter_confirmed" value="1">
                <span>I confirm this meter number is correct.</span>
            </label>
            <input type="hidden" name="electricity_validation_status" value="" data-electricity-validation-status>
            <div class="purchase-summary-row" data-electricity-customer-details hidden>
                <span>Customer / Meter Details</span>
                <strong data-electricity-customer-name>Awaiting verification</strong>
            </div>
            <label>Amount <input name="amount" inputmode="decimal" placeholder="5000" required></label>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'cable_tv'): ?>
            <?php
            $providers = purchase_provider_options(
                $serviceNetworks['cable_tv'] ?? [],
                $cablePlanCatalog,
                [
                ['network_code' => 'dstv', 'network_name' => 'DStv'],
                ['network_code' => 'gotv', 'network_name' => 'GOtv'],
                ['network_code' => 'startimes', 'network_name' => 'Startimes'],
                ]
            );
            render_purchase_select_control('provider', 'Select Provider', $providers, 'cable-provider', 'data-package-network', true);
            ?>
            <div class="purchase-inline-field">
                <label>IUC / Smartcard Number <input name="smartcard_number" inputmode="numeric" placeholder="1234567890" required></label>
                <button class="purchase-secondary-action" type="button" data-cable-verify data-endpoint="<?= e(base_url('user/service-verify.php')); ?>">Verify</button>
            </div>
            <div class="purchase-verification-message" data-cable-verify-message></div>
            <label class="purchase-confirm-check" data-cable-fallback-confirm hidden>
                <input type="checkbox" name="cable_iuc_confirmed" value="1">
                <span>I confirm this smartcard/IUC number is correct.</span>
            </label>
            <input type="hidden" name="cable_validation_status" value="" data-cable-validation-status>
            <?php if ($cablePlanCatalog !== []): ?>
                <label>Select Subscription Package
                    <select name="package" data-provider-plan-select required>
                        <option value="">Select subscription package</option>
                        <?php foreach ($cablePlanCatalog as $plan): ?>
                            <?php $amount = (float) ($plan['amount'] ?? 0); ?>
                            <option value="<?= e((string) $plan['local_plan_code']); ?>" data-network="<?= e((string) ($plan['network_code'] ?? '')); ?>" data-amount="<?= e((string) $amount); ?>" data-display-amount="<?= e($amount > 0 ? money($amount) : ''); ?>">
                                <?= e((string) $plan['local_plan_name'] . ($amount > 0 ? ' - ' . money($amount) : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="purchase-empty-state" data-package-empty-state hidden>No available packages for this provider right now.</div>
                <div class="purchase-summary-row"><span>Package Price</span><strong data-plan-price-display>Choose a package</strong></div>
                <input type="hidden" name="amount" value="" data-plan-amount>
            <?php else: ?>
                <label>Select Subscription Package
                    <select name="package" disabled required>
                        <option value="">No packages available</option>
                    </select>
                </label>
                <div class="purchase-empty-state">No cable TV packages available right now.</div>
            <?php endif; ?>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'exam_pin'): ?>
            <?php
            $examProviders = purchase_provider_options(
                $serviceNetworks['exam_pin'] ?? [],
                $examPlanCatalog,
                [
                    ['network_code' => 'waec', 'network_name' => 'WAEC'],
                    ['network_code' => 'neco', 'network_name' => 'NECO'],
                    ['network_code' => 'nabteb', 'network_name' => 'NABTEB'],
                    ['network_code' => 'jamb', 'network_name' => 'JAMB'],
                ]
            );
            $submitDisabled = $examPlanCatalog === [];
            ?>
            <?php render_purchase_select_control('exam_provider', 'Select Provider', $examProviders, 'exam-provider', 'data-package-network', true); ?>
            <label>Select Package / Exam Type
                <select name="exam_type" data-provider-plan-select <?= $examPlanCatalog === [] ? 'disabled' : 'required'; ?>>
                    <option value=""><?= $examPlanCatalog === [] ? 'No packages available right now' : 'Select package'; ?></option>
                    <?php foreach ($examPlanCatalog as $plan): ?>
                        <?php $amount = (float) ($plan['amount'] ?? 0); ?>
                        <option value="<?= e((string) $plan['local_plan_code']); ?>" data-network="<?= e((string) ($plan['network_code'] ?? '')); ?>" data-amount="<?= e((string) $amount); ?>" data-display-amount="<?= e($amount > 0 ? money($amount) : ''); ?>">
                            <?= e((string) $plan['local_plan_name'] . ($amount > 0 ? ' - ' . money($amount) : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($examPlanCatalog !== []): ?>
                <div class="purchase-empty-state" data-package-empty-state hidden>No exam PIN packages available for this provider right now.</div>
                <div class="purchase-summary-row"><span>Package Price</span><strong data-plan-price-display>Choose a package</strong></div>
                <input type="hidden" name="amount" value="" data-plan-amount>
            <?php else: ?>
                <div class="purchase-empty-state">No exam PIN providers available right now.</div>
            <?php endif; ?>
            <label>Quantity <input name="quantity" type="number" inputmode="numeric" min="1" step="1" value="1" autocomplete="off" placeholder="1" required></label>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'bulk_sms'): ?>
            <?php $submitDisabled = !$bulkSmsPricingAvailable; ?>
            <div class="purchase-empty-state" data-bulk-sms-pricing-state<?= $bulkSmsPricingAvailable ? ' hidden' : ''; ?>>Bulk SMS pricing is unavailable right now.</div>
            <label>Sender ID (Max 11 Characters) <input name="sender" maxlength="11" autocomplete="off" placeholder="e.g. GemData" required></label>
            <label>Recipients (Comma separated) <textarea name="recipients" rows="4" placeholder="Enter recipient numbers..." required data-bulk-sms-recipients></textarea></label>
            <label>Message Body (160 chars = 1 page) <textarea name="message" rows="5" placeholder="Type your message here..." required data-bulk-sms-message></textarea></label>
            <div class="purchase-calculation-box" data-bulk-sms-estimator data-rate="<?= e((string) $bulkSmsRate); ?>">
                <div><strong data-bulk-sms-recipient-count>0</strong><span>Recipients</span></div>
                <div><strong data-bulk-sms-pages-count>0</strong><span>Pages</span></div>
                <div><strong data-bulk-sms-cost><?= e(money(0)); ?></strong><span>Est. Cost</span></div>
            </div>
            <input type="hidden" name="amount" value="" data-bulk-sms-amount>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'recharge_card'): ?>
            <?php
            $rechargeNetworks = purchase_provider_options(
                $serviceNetworks['recharge_card'] ?? [],
                $rechargeCardCatalog,
                purchase_default_mobile_networks()
            );
            $rechargeDenominations = purchase_recharge_denominations($rechargeCardCatalog);
            render_purchase_select_control('network', 'Network', $rechargeNetworks, 'recharge-network', '', true);
            ?>
            <label>Amount (₦)
                <select name="denomination" data-recharge-denomination required>
                    <option value="">Select amount</option>
                    <?php foreach ($rechargeDenominations as $denomination): ?>
                        <option value="<?= e((string) $denomination); ?>"><?= e(number_format((float) $denomination, 0)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Quantity <input name="quantity" type="number" inputmode="numeric" min="1" step="1" value="1" autocomplete="off" placeholder="1" required data-recharge-quantity></label>
            <label>Name on Card (Business Name) <input name="card_name" autocomplete="organization" placeholder="<?= e((string) ($dashboard['user']['business_name'] ?? $dashboard['user']['full_name'] ?? 'GemData')); ?>"></label>
            <div class="purchase-summary-row"><span>Total Cost</span><strong data-recharge-total><?= e(money(0)); ?></strong></div>
            <input type="hidden" name="amount" value="" data-recharge-amount>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php elseif ($slug === 'data_card'): ?>
            <?php
            $dataCardNetworks = purchase_provider_options(
                $serviceNetworks['data_card'] ?? [],
                $dataCardCatalog,
                purchase_default_mobile_networks()
            );
            render_purchase_provider_control('network', 'Network', $dataCardNetworks, 'data-card-network', true);
            ?>
            <?php if ($dataCardCatalog !== []): ?>
                <label>Plan
                    <select name="plan" data-provider-plan-select data-require-network="true" required>
                        <option value="">Select data card plan</option>
                        <?php foreach ($dataCardCatalog as $plan): ?>
                            <?php $amount = (float) ($plan['amount'] ?? 0); ?>
                            <option value="<?= e((string) $plan['local_plan_code']); ?>" data-network="<?= e((string) ($plan['network_code'] ?? '')); ?>" data-amount="<?= e((string) $amount); ?>" data-display-amount="<?= e($amount > 0 ? money($amount) : ''); ?>">
                                <?= e((string) $plan['local_plan_name'] . ($amount > 0 ? ' - ' . money($amount) : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="purchase-empty-state" data-package-empty-state hidden>No data card plans available for this network right now.</div>
                <div class="purchase-summary-row"><span>Plan Price</span><strong data-plan-price-display>Choose a plan</strong></div>
                <input type="hidden" name="amount" value="" data-plan-amount>
            <?php else: ?>
                <label>Plan <input name="plan" placeholder="Package or denomination" required></label>
                <label>Amount <input name="amount" inputmode="decimal" placeholder="3000" required></label>
                <div class="purchase-empty-state">No data card plans available right now.</div>
            <?php endif; ?>
            <label>Quantity <input name="quantity" inputmode="numeric" placeholder="5" required></label>
            <?php render_security_pin_field($pinConfigured); ?>
        <?php endif; ?>

        <button class="primary-action purchase-submit" type="submit" data-loading-label="Processing..."<?= $submitDisabled ? ' disabled aria-disabled="true"' : ''; ?>><?= e($submitLabel); ?></button>
    </form>
    <?php
}

function render_purchase_provider_control(string $name, string $label, array $options, string $controlId, bool $isPlanNetwork): void
{
    $dataAttr = $isPlanNetwork ? ' data-plan-network' : '';
    if ($options === []) {
        ?>
        <label><?= e($label); ?> <input name="<?= e($name); ?>"<?= $dataAttr; ?> placeholder="<?= e($label); ?>" required></label>
        <?php
        return;
    }

    if (count($options) > 0 && count($options) <= 6) {
        $mobileNetworkLabels = ['mtn' => 'MTN', 'glo' => 'GLO', 'airtel' => 'AIRTEL', '9mobile' => '9MOB', '9mob' => '9MOB', 'etisalat' => '9MOB'];
        $mobileNetworkCodes = array_keys($mobileNetworkLabels);
        $usesMobileNetworkStyle = $name === 'network';
        foreach ($options as $option) {
            $code = strtolower((string) ($option['network_code'] ?? ''));
            if (!in_array($code, $mobileNetworkCodes, true)) {
                $usesMobileNetworkStyle = false;
                break;
            }
        }
        ?>
        <fieldset class="purchase-fieldset">
            <legend><?= e($label); ?></legend>
            <input type="hidden" name="<?= e($name); ?>" value="" data-segmented-input="<?= e($controlId); ?>"<?= $dataAttr; ?> required>
            <div class="provider-segment-grid<?= $usesMobileNetworkStyle ? ' data-network-segment-grid' : ''; ?>" role="group" aria-label="<?= e($label); ?>">
                <?php foreach ($options as $option): ?>
                    <?php $code = strtolower((string) ($option['network_code'] ?? '')); ?>
                    <button type="button" class="provider-segment<?= $usesMobileNetworkStyle ? ' data-network-segment' : ''; ?>"<?= $usesMobileNetworkStyle ? ' data-network-tone="' . e($code) . '"' : ''; ?> data-segmented-option="<?= e($controlId); ?>" data-value="<?= e((string) ($option['network_code'] ?? '')); ?>">
                        <?= e($usesMobileNetworkStyle ? ($mobileNetworkLabels[$code] ?? strtoupper($code)) : (string) ($option['network_name'] ?? $option['network_code'] ?? 'Option')); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
        return;
    }
    ?>
    <label><?= e($label); ?>
        <select name="<?= e($name); ?>"<?= $dataAttr; ?> required>
            <option value="">Select <?= e(strtolower($label)); ?></option>
            <?php foreach ($options as $option): ?>
                <option value="<?= e((string) ($option['network_code'] ?? '')); ?>"><?= e((string) ($option['network_name'] ?? $option['network_code'] ?? 'Option')); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

function render_purchase_select_control(string $name, string $label, array $options, string $controlId, string $dataAttribute = '', bool $required = true): void
{
    $dataAttr = $dataAttribute !== '' ? ' ' . $dataAttribute : '';
    $placeholderLabel = preg_replace('/^select\s+/i', '', $label) ?: $label;
    ?>
    <label><?= e($label); ?>
        <select name="<?= e($name); ?>"<?= $dataAttr; ?><?= $required ? ' required' : ''; ?> data-control-id="<?= e($controlId); ?>">
            <option value="">Select <?= e(strtolower(str_replace('/', ' / ', $placeholderLabel))); ?></option>
            <?php foreach ($options as $option): ?>
                <?php
                $value = (string) ($option['network_code'] ?? $option['value'] ?? '');
                $text = (string) ($option['network_name'] ?? $option['label'] ?? $value);
                ?>
                <option value="<?= e($value); ?>"><?= e($text); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

function purchase_plan_catalog(string $serviceSlug): array
{
    try {
        return app(\GemData\Classes\ProviderPlanService::class)->catalogForServiceSlug($serviceSlug);
    } catch (\Throwable) {
        return [];
    }
}

function purchase_provider_options(array $networkOptions, array $catalog, array $defaults = []): array
{
    $options = [];
    foreach ([$networkOptions, purchase_provider_options_from_catalog($catalog), $defaults] as $source) {
        foreach ($source as $option) {
            $code = strtolower(trim((string) ($option['network_code'] ?? $option['value'] ?? '')));
            if ($code === '' || isset($options[$code])) {
                continue;
            }
            $options[$code] = [
                'network_code' => $code,
                'network_name' => (string) ($option['network_name'] ?? $option['label'] ?? purchase_label_from_code($code)),
            ];
        }
    }

    return array_values($options);
}

function purchase_provider_options_from_catalog(array $catalog): array
{
    $options = [];
    foreach ($catalog as $plan) {
        $code = strtolower(trim((string) ($plan['network_code'] ?? '')));
        if ($code === '') {
            $code = purchase_code_from_plan((string) ($plan['local_plan_code'] ?? ''), (string) ($plan['local_plan_name'] ?? ''));
        }
        if ($code === '') {
            $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string) ($plan['local_plan_code'] ?? ''))) ?? '');
            $code = trim($code, '_');
        }
        if ($code === '' || isset($options[$code])) {
            continue;
        }
        $options[$code] = [
            'network_code' => $code,
            'network_name' => purchase_label_from_code($code) ?: (string) ($plan['local_plan_name'] ?? $code),
        ];
    }

    return array_values($options);
}

function purchase_data_categories(array $catalog): array
{
    $labels = [
        'sme' => 'SME',
        'corporate' => 'Corporate',
        'gifting' => 'Gifting',
        'direct' => 'Direct',
        'other' => 'Other',
    ];
    $found = [];
    foreach ($catalog as $plan) {
        $category = purchase_data_category_for_plan($plan);
        $found[$category] = $labels[$category] ?? purchase_label_from_code($category);
    }

    if ($found === []) {
        return [
            'sme' => 'SME',
            'corporate' => 'Corporate',
            'gifting' => 'Gifting',
            'direct' => 'Direct',
        ];
    }

    $ordered = [];
    foreach (['sme', 'corporate', 'gifting', 'direct', 'other'] as $key) {
        if (isset($found[$key])) {
            $ordered[$key] = $found[$key];
        }
    }

    return $ordered;
}

function purchase_data_category_for_plan(array $plan): string
{
    $haystack = strtolower((string) ($plan['local_plan_name'] ?? '') . ' ' . (string) ($plan['local_plan_code'] ?? ''));
    return match (true) {
        str_contains($haystack, 'sme') => 'sme',
        str_contains($haystack, 'corporate') || str_contains($haystack, 'corp') => 'corporate',
        str_contains($haystack, 'gifting') || str_contains($haystack, 'gift') => 'gifting',
        str_contains($haystack, 'direct') => 'direct',
        default => 'other',
    };
}

function purchase_first_positive_amount(array $catalog): float
{
    foreach ($catalog as $plan) {
        $amount = (float) ($plan['amount'] ?? 0);
        if ($amount > 0) {
            return $amount;
        }
    }

    return 0.0;
}

function purchase_default_mobile_networks(): array
{
    return [
        ['network_code' => 'mtn', 'network_name' => 'MTN'],
        ['network_code' => 'airtel', 'network_name' => 'Airtel'],
        ['network_code' => 'glo', 'network_name' => 'Glo'],
        ['network_code' => '9mobile', 'network_name' => '9mobile'],
    ];
}

function purchase_default_electricity_providers(): array
{
    return [
        ['network_code' => 'aedc', 'network_name' => 'AEDC'],
        ['network_code' => 'ikedc', 'network_name' => 'IKEDC'],
        ['network_code' => 'ekedc', 'network_name' => 'EKEDC'],
        ['network_code' => 'kedco', 'network_name' => 'KEDCO'],
        ['network_code' => 'phed', 'network_name' => 'PHED'],
        ['network_code' => 'ibedc', 'network_name' => 'IBEDC'],
        ['network_code' => 'bedc', 'network_name' => 'BEDC'],
        ['network_code' => 'eedc', 'network_name' => 'EEDC'],
        ['network_code' => 'jed', 'network_name' => 'JED'],
        ['network_code' => 'kaedco', 'network_name' => 'KAEDCO'],
        ['network_code' => 'yedc', 'network_name' => 'YEDC'],
    ];
}

function purchase_recharge_denominations(array $catalog): array
{
    $denominations = [];
    foreach ($catalog as $plan) {
        $amount = (float) ($plan['amount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        $denominations[(string) (int) $amount] = $amount;
    }

    if ($denominations === []) {
        foreach ([100, 200, 500, 1000] as $amount) {
            $denominations[(string) $amount] = (float) $amount;
        }
    }

    ksort($denominations, SORT_NUMERIC);
    return array_values($denominations);
}

function purchase_code_from_plan(string $planCode, string $planName): string
{
    $haystack = strtolower($planCode . ' ' . $planName);
    foreach (['dstv', 'gotv', 'startimes', 'waec', 'neco', 'nabteb', 'jamb', 'mtn', 'airtel', 'glo', '9mobile', 'aedc', 'ikedc', 'ekedc', 'kedco', 'phed', 'ibedc', 'bedc', 'eedc', 'jed', 'kaedco', 'yedc'] as $code) {
        if (str_contains($haystack, $code)) {
            return $code;
        }
    }

    return '';
}

function purchase_label_from_code(string $code): string
{
    return match (strtolower($code)) {
        'dstv' => 'DStv',
        'gotv' => 'GOtv',
        'startimes' => 'StarTimes',
        'waec' => 'WAEC',
        'neco' => 'NECO',
        'nabteb' => 'NABTEB',
        'jamb' => 'JAMB',
        'mtn' => 'MTN',
        'airtel' => 'Airtel',
        'glo' => 'Glo',
        '9mobile' => '9mobile',
        'aedc' => 'AEDC',
        'ikedc' => 'IKEDC',
        'ekedc' => 'EKEDC',
        'kedco' => 'KEDCO',
        'phed' => 'PHED',
        'ibedc' => 'IBEDC',
        'bedc' => 'BEDC',
        'eedc' => 'EEDC',
        'jed' => 'JED',
        'kaedco' => 'KAEDCO',
        'yedc' => 'YEDC',
        default => strtoupper(str_replace(['_', '-'], ' ', $code)),
    };
}

function user_wallet_pin_configured(int $userId): bool
{
    if ($userId <= 0 || !db()->columnExists('users', 'transaction_pin_hash')) {
        return false;
    }

    $row = db()->first('SELECT transaction_pin_hash FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
    return trim((string) ($row['transaction_pin_hash'] ?? '')) !== '';
}

function render_security_pin_field(bool $pinConfigured): void
{
    if (!$pinConfigured) {
        ?>
        <div class="purchase-empty-state">
            Set your Wallet PIN before making purchases.
            <a class="font-bold text-gem-blue" href="<?= e(base_url('user/settings.php#security')); ?>">Open security settings</a>
        </div>
        <?php
        return;
    }
    ?>
    <label>Security PIN <input name="security_pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="Enter PIN"></label>
    <?php
}
