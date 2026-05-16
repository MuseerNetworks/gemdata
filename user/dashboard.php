<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$wallet = app(\GemData\Classes\Wallet::class)->ensure((int) $user['id']);
$dedicatedAccount = app(\GemData\Classes\PaystackDedicatedAccountService::class)->getForUser((int) $user['id']);
$zenithAccount = app(\GemData\Classes\ZenithPayVirtualAccountService::class)->getForUser((int) $user['id']);
$dedicatedAccountStatus = (string) ($dedicatedAccount['status'] ?? '');
$zenithAccountStatus = (string) ($zenithAccount['status'] ?? '');
$providerPlans = app(\GemData\Classes\ProviderPlanService::class);
$services = db()->query(
    "SELECT * FROM services
     WHERE is_enabled = 1
     ORDER BY FIELD(slug, 'airtime', 'data', 'cable_tv', 'electricity', 'data_card', 'exam_pin', 'recharge_card', 'bulk_sms'), name"
);
$recentTransactions = db()->query(
    'SELECT t.*, s.name AS service_name FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.user_id = :user_id ORDER BY t.id DESC LIMIT 6',
    ['user_id' => $user['id']]
);
$commissionSummary = db()->first(
    'SELECT COALESCE(SUM(commission_amount), 0) AS total FROM transactions WHERE user_id = :user_id',
    ['user_id' => $user['id']]
);
$isApiUser = (int) $user['is_api_user'] === 1;
$serviceMeta = [
    'airtime' => ['tag' => 'Instant top-up', 'fields' => 'Network, phone, amount', 'description' => 'Top up any line instantly from your wallet.', 'summary' => 'Recharge any phone number.', 'expected' => 'Usually completes within seconds.', 'example' => 'Example: MTN, 08030000000, NGN 1000'],
    'data' => ['tag' => 'Bundle purchase', 'fields' => 'Network, plan, phone', 'description' => 'Activate a mobile data plan with wallet payment.', 'summary' => 'Buy mobile data bundles.', 'expected' => 'Popular choice for repeat buyers and resellers.', 'example' => 'Example: Airtel, 2GB SME, 08030000000'],
    'electricity' => ['tag' => 'Meter token', 'fields' => 'Meter type, number, amount', 'description' => 'Pay utility bills for prepaid or postpaid meters.', 'summary' => 'Pay meter bills and tokens.', 'expected' => 'Confirm meter details before submitting.', 'example' => 'Example: Prepaid, 12345678901, NGN 5000'],
    'cable_tv' => ['tag' => 'Subscription', 'fields' => 'Provider, smartcard, plan', 'description' => 'Renew DStv, GOtv, or Startimes packages.', 'summary' => 'Renew TV subscriptions.', 'expected' => 'Great for quick package renewals.', 'example' => 'Example: GOtv, 1234567890, Max'],
    'exam_pin' => ['tag' => 'Education utility', 'fields' => 'Exam type, quantity', 'description' => 'Generate education PINs from one workspace.', 'summary' => 'Generate WAEC, NECO, JAMB PINs.', 'expected' => 'PIN generation appears after successful processing.', 'example' => 'Example: WAEC, Qty 1'],
    'recharge_card' => ['tag' => 'Voucher generation', 'fields' => 'Network, quantity', 'description' => 'Create recharge card batches for reseller use.', 'summary' => 'Print recharge voucher batches.', 'expected' => 'Useful for outlet and reseller batches.', 'example' => 'Example: Glo, Qty 10'],
    'data_card' => ['tag' => 'Bulk data cards', 'fields' => 'Network, plan, quantity', 'description' => 'Prepare bulk data card batches with plan control.', 'summary' => 'Generate bulk data cards.', 'expected' => 'Designed for repeat, high-volume fulfilment.', 'example' => 'Example: MTN, 5GB, Qty 5'],
    'bulk_sms' => ['tag' => 'Messaging', 'fields' => 'Sender ID, recipients', 'description' => 'Send campaign or operational SMS in one flow.', 'summary' => 'Broadcast messages quickly.', 'expected' => 'Double-check recipients before sending.', 'example' => 'Example: GemData, 0803..., promo message'],
];
$serviceNetworksRows = db()->query(
    'SELECT s.slug, sn.network_code, sn.network_name
     FROM service_networks sn
     INNER JOIN services s ON s.id = sn.service_id
     WHERE sn.is_enabled = 1
     ORDER BY s.slug, sn.network_name'
);
$serviceNetworks = [];
foreach ($serviceNetworksRows as $networkRow) {
    $serviceNetworks[$networkRow['slug']][] = $networkRow;
}
$dataPlanCatalog = $providerPlans->catalogForServiceSlug('data');
$hasTransactions = !empty($recentTransactions);
$hasFundedWallet = (float) $wallet['balance'] > 0;
$onboardingSteps = [
    ['label' => 'Account created', 'done' => true, 'href' => base_url('user/settings.php')],
    ['label' => 'Fund wallet', 'done' => $hasFundedWallet, 'href' => base_url('user/fund-wallet.php')],
    ['label' => 'Buy first service', 'done' => $hasTransactions, 'href' => '#services'],
];
$completedOnboarding = count(array_filter($onboardingSteps, static fn(array $step): bool => $step['done']));
$onboardingPercent = (int) round(($completedOnboarding / count($onboardingSteps)) * 100);

render_header('Dashboard', 'user');
?>
<div class="dashboard-stack" data-services-dashboard>
    <section class="dashboard-hero-grid">
        <div class="surface-card overview-card" data-search-item data-search="wallet balance transactions api status commission earned fund wallet <?= e($user['full_name']); ?>">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">Wallet Overview</p>
                    <h2 class="overview-balance"><?= e(money($wallet['balance'])); ?></h2>
                    <p class="overview-copy"><?= e($user['full_name']); ?>, your workspace is ready for airtime, data, and bill payments.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a class="primary-action inline-flex items-center justify-center" href="<?= e(base_url('user/fund-wallet.php')); ?>">Fund Wallet</a>
                    <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('user/transactions.php')); ?>">Transactions</a>
                </div>
            </div>
            <div class="priority-actions">
                <button type="button" class="priority-action-card" data-service-card data-service-name="Buy Data" data-service-description="Fastest route for your most common repeat purchase." data-service-slug="data" data-template-id="service-template-data" data-search-item data-search="buy data quick action repeat purchase">
                    <strong>Buy Data Fast</strong>
                    <span>Jump straight into bundle purchase with the most-used flow for repeat customers.</span>
                </button>
                <button type="button" class="priority-action-card" data-service-card data-service-name="Buy Airtime" data-service-description="Quick top-up flow for everyday wallet usage." data-service-slug="airtime" data-template-id="service-template-airtime" data-search-item data-search="buy airtime quick topup action">
                    <strong>Recharge Airtime</strong>
                    <span>Open the airtime form instantly and complete a top-up in a few taps.</span>
                </button>
            </div>
            <div class="metric-grid metric-grid-compact mt-3">
                <div class="metric-card" data-search-item data-search="transactions <?= count($recentTransactions); ?>">
                    <p class="metric-label">Transactions</p>
                    <p class="metric-value"><?= count($recentTransactions); ?></p>
                </div>
                <div class="metric-card" data-search-item data-search="api status <?= $isApiUser ? 'enabled' : 'pending'; ?>">
                    <p class="metric-label">API Status</p>
                    <p class="metric-value"><?= $isApiUser ? 'Enabled' : 'Available'; ?></p>
                </div>
                <div class="metric-card" data-search-item data-search="commission earned <?= e((string) ($commissionSummary['total'] ?? 0)); ?>">
                    <p class="metric-label">Commission</p>
                    <p class="metric-value"><?= e(money($commissionSummary['total'] ?? 0)); ?></p>
                </div>
            </div>
        </div>

        <div class="surface-card transaction-card">
            <div class="dashboard-section-header">
                <div>
                    <p class="eyebrow">Recent Transactions</p>
                    <h2 class="surface-section-title">Latest activity</h2>
                </div>
                <a class="text-cyan-300" href="<?= e(base_url('user/transactions.php')); ?>">View all</a>
            </div>
            <?php if (empty($recentTransactions)): ?>
                <div class="onboarding-card mt-3" data-search-item data-search="new user onboarding no transactions get started fund wallet first order">
                    <div class="onboarding-progress">
                        <div>
                            <strong>Get started</strong>
                            <div class="timestamp"><?= $completedOnboarding; ?> of <?= count($onboardingSteps); ?> completed</div>
                        </div>
                        <div class="progress-bar" aria-hidden="true"><span style="width: <?= $onboardingPercent; ?>%;"></span></div>
                    </div>
                    <div class="compact-checklist">
                        <?php foreach ($onboardingSteps as $step): ?>
                            <div class="checklist-row">
                                <span class="<?= $step['done'] ? 'text-emerald-300' : 'text-slate-500'; ?>"><?= $step['done'] ? '&#10003;' : '&#9675;'; ?></span>
                                <span><?= e($step['label']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="onboarding-actions">
                        <a class="primary-action inline-flex items-center justify-center" href="<?= e(base_url('user/fund-wallet.php')); ?>">Fund Wallet</a>
                        <a class="secondary-action inline-flex items-center justify-center" href="#services">Buy First Service</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-shell table-shell-compact mt-3">
                    <table>
                        <thead>
                            <tr class="text-slate-400">
                                <th>Reference</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recentTransactions, 0, 4) as $row): ?>
                                <tr data-search-item data-search="<?= e($row['reference'] . ' ' . $row['service_name'] . ' ' . $row['status'] . ' ' . ($row['recipient'] ?? '')); ?>">
                                    <td><?= e($row['reference']); ?></td>
                                    <td><?= e($row['service_name']); ?></td>
                                    <td><span class="status-chip status-<?= e($row['status']); ?>"><?= e(ucfirst($row['status'])); ?></span></td>
                                    <td><?= e(money($row['amount'])); ?></td>
                                    <td><span class="timestamp" title="<?= e($row['created_at']); ?>"><?= e(human_datetime((string) $row['created_at'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="surface-card p-6" data-search-item data-search="dedicated bank transfer account wallet funding paystack">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Transfer Funding</p>
                <h2 class="surface-section-title">Transfer account status</h2>
                <p class="surface-section-copy">Use Paystack or ZenithPay transfer details from your wallet funding page. ZenithPay requires BVN verification before the account can be created.</p>
            </div>
            <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('user/fund-wallet.php')); ?>">Open Wallet Funding</a>
        </div>
        <div class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-5">
                <p class="eyebrow">Paystack</p>
                <?php if ($dedicatedAccountStatus === 'assigned'): ?>
                    <div class="dedicated-account-grid mt-3">
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Account Number</span>
                            <strong><?= e((string) $dedicatedAccount['dedicated_account_number']); ?></strong>
                        </div>
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Bank</span>
                            <strong><?= e((string) $dedicatedAccount['bank_name']); ?></strong>
                        </div>
                    </div>
                    <div class="notice notice-success mt-3">Paystack transfer account is ready.</div>
                <?php elseif ($dedicatedAccountStatus === 'pending'): ?>
                    <div class="notice notice-success mt-3">Paystack is still assigning your transfer account.</div>
                <?php elseif ($dedicatedAccountStatus === 'failed'): ?>
                    <div class="notice notice-error mt-3">Paystack account setup needs attention on the wallet funding page.</div>
                <?php else: ?>
                    <div class="notice notice-error mt-3">No Paystack transfer account is assigned yet.</div>
                <?php endif; ?>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-5">
                <p class="eyebrow">ZenithPay</p>
                <?php if ($zenithAccountStatus === 'assigned'): ?>
                    <div class="dedicated-account-grid mt-3">
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Account Number</span>
                            <strong><?= e((string) $zenithAccount['dedicated_account_number']); ?></strong>
                        </div>
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Bank</span>
                            <strong><?= e((string) $zenithAccount['bank_name']); ?></strong>
                        </div>
                    </div>
                    <div class="notice notice-success mt-3">ZenithPay virtual account is ready.</div>
                <?php elseif ($zenithAccountStatus === 'pending'): ?>
                    <div class="notice notice-success mt-3">ZenithPay account request is still processing.</div>
                <?php elseif ($zenithAccountStatus === 'failed'): ?>
                    <div class="notice notice-error mt-3">ZenithPay setup failed. Open wallet funding to review the latest reason and retry with your BVN.</div>
                <?php else: ?>
                    <div class="notice notice-error mt-3">No ZenithPay account yet. Open wallet funding and submit your BVN to request one.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="services" class="surface-card services-surface">
        <div class="dashboard-section-header">
            <div>
                <p class="eyebrow">Quick Services</p>
                <h2 class="surface-section-title">Tap a service to continue.</h2>
                <p class="services-copy">Fast service access, wallet-safe submission, and a smoother in-panel purchase flow.</p>
            </div>
        </div>
        <div id="ajax-feedback" class="mt-4"></div>
        <div class="services-grid quick-services-grid mt-6">
            <?php foreach ($services as $service): ?>
                <?php
                $meta = $serviceMeta[$service['slug']] ?? ['tag' => 'Utility payment', 'fields' => 'Service form', 'summary' => 'Complete this service in-panel.', 'description' => 'Complete this service in-panel.', 'expected' => 'Processing time depends on provider response.', 'example' => 'Fill the form and review before submission.'];
                $networkSummary = implode(', ', array_map(static fn(array $network): string => (string) $network['network_name'], $serviceNetworks[$service['slug']] ?? []));
                ?>
                <button
                    type="button"
                    class="service-card quick-service-tile"
                    data-service-card
                    data-search-item
                    data-search="<?= e($service['name'] . ' ' . $service['description'] . ' ' . $meta['tag'] . ' ' . $meta['fields'] . ' ' . $meta['summary']); ?>"
                    data-service-name="<?= e($service['name']); ?>"
                    data-service-description="<?= e($meta['description'] ?? $service['description'] ?: $meta['tag']); ?>"
                    data-confidence-template-id="service-confidence-template-<?= e($service['slug']); ?>"
                    data-service-slug="<?= e($service['slug']); ?>"
                    data-template-id="service-template-<?= e($service['slug']); ?>"
                >
                    <span class="service-card-icon"></span>
                    <h3><?= e($service['name']); ?></h3>
                    <p><?= e($meta['summary']); ?></p>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="dashboard-service-overlay" data-service-overlay>
        <div class="dashboard-service-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Service Panel</p>
                    <h3 class="panel-title" data-service-panel-title>Select a service</h3>
                    <p class="panel-description" data-service-panel-description>Pick a service tile to start a quick transaction.</p>
                </div>
                <button class="icon-button panel-close" type="button" data-close-panel><?= icon_svg('close'); ?></button>
            </div>
            <div data-service-panel-confidence>
                <div class="service-confidence">
                    <div class="service-confidence-card">
                        <strong>Clear and safe flow</strong>
                        <span>Select a service to see supported options, example inputs, and what to review before submitting.</span>
                    </div>
                </div>
            </div>
            <div data-service-panel-body></div>
        </div>
    </div>
</div>

<?php foreach ($services as $service): ?>
    <?php
    $meta = $serviceMeta[$service['slug']] ?? ['tag' => 'Utility payment', 'fields' => 'Service form', 'summary' => 'Complete this service in-panel.', 'description' => 'Complete this service in-panel.', 'expected' => 'Processing time depends on provider response.', 'example' => 'Fill the form and review before submission.'];
    $networkSummary = implode(', ', array_map(static fn(array $network): string => (string) $network['network_name'], $serviceNetworks[$service['slug']] ?? []));
    ?>
    <template id="service-confidence-template-<?= e($service['slug']); ?>">
        <div class="service-confidence">
            <div class="service-confidence-card">
                <strong>Expected flow</strong>
                <span><?= e($meta['expected']); ?></span>
            </div>
            <div class="service-confidence-card">
                <strong>Supported options</strong>
                <span><?= e($networkSummary !== '' ? $networkSummary : 'Service-specific options appear in the form below.'); ?></span>
            </div>
            <div class="service-confidence-card">
                <strong>Before you submit</strong>
                <div class="confidence-points">
                    <span><strong>Example:</strong> <?= e($meta['example']); ?></span>
                    <span>Wallet is charged only after a submitted request is processed.</span>
                    <span>Review the recipient details carefully to avoid mistakes.</span>
                </div>
            </div>
        </div>
    </template>
    <template id="service-template-<?= e($service['slug']); ?>">
        <form class="service-panel-form" method="post" action="<?= e(base_url('user/service-action.php')); ?>" data-ajax-form data-target="#ajax-feedback" data-reset-on-success="true" data-offline-queue="false">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="service_slug" value="<?= e($service['slug']); ?>">
            <input type="hidden" name="idempotency_key" value="" data-idempotency-field data-idempotency-prefix="txn">
            <div class="service-card-icon" data-service-form-icon></div>
            <?php if ($service['slug'] === 'airtime'): ?>
                <label>Network
                    <select name="network">
                        <option value="">Select network</option>
                        <?php foreach ($serviceNetworks['airtime'] ?? [] as $network): ?>
                            <option value="<?= e($network['network_code']); ?>"><?= e($network['network_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Phone Number <input name="phone" placeholder="08030000000"></label>
                <label>Amount <input name="amount" placeholder="1000"></label>
                <button class="primary-action" type="submit">Buy Airtime</button>
            <?php elseif ($service['slug'] === 'data'): ?>
                <label>Network
                    <select name="network" data-plan-network>
                        <option value="">Select network</option>
                        <?php foreach ($serviceNetworks['data'] ?? [] as $network): ?>
                            <option value="<?= e($network['network_code']); ?>"><?= e($network['network_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plan
                    <?php if ($dataPlanCatalog !== []): ?>
                        <select name="plan" data-data-plan-select>
                            <option value="">Select plan</option>
                            <?php foreach ($dataPlanCatalog as $plan): ?>
                                <option
                                    value="<?= e($plan['local_plan_code']); ?>"
                                    data-network="<?= e($plan['network_code']); ?>"
                                    data-amount="<?= e((string) $plan['amount']); ?>"
                                ><?= e($plan['local_plan_name'] . ($plan['amount'] > 0 ? ' - ' . money($plan['amount']) : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input name="plan" placeholder="2GB SME">
                    <?php endif; ?>
                </label>
                <label>Phone Number <input name="phone" placeholder="08030000000"></label>
                <label>Amount <input name="amount" placeholder="1500" data-plan-amount></label>
                <button class="primary-action" type="submit">Buy Data</button>
            <?php elseif ($service['slug'] === 'electricity'): ?>
                <label>Meter Type
                    <select name="meter_type">
                        <option value="">Select meter type</option>
                        <option>Prepaid</option>
                        <option>Postpaid</option>
                    </select>
                </label>
                <label>Meter Number <input name="meter_number" placeholder="12345678901"></label>
                <label>Distribution Company <input name="disco" placeholder="Ikeja Electric"></label>
                <label>Amount <input name="amount" placeholder="5000"></label>
                <button class="primary-action" type="submit">Pay Electricity</button>
            <?php elseif ($service['slug'] === 'cable_tv'): ?>
                <label>Provider
                    <select name="provider">
                        <option value="">Select provider</option>
                        <option>DStv</option>
                        <option>GOtv</option>
                        <option>Startimes</option>
                    </select>
                </label>
                <label>Smartcard / IUC Number <input name="smartcard_number" placeholder="1234567890"></label>
                <label>Plan <input name="package" placeholder="Compact Plus"></label>
                <label>Amount <input name="amount" placeholder="8500"></label>
                <button class="primary-action" type="submit">Pay TV</button>
            <?php elseif ($service['slug'] === 'exam_pin'): ?>
                <label>Exam Type <input name="exam_type" placeholder="WAEC"></label>
                <label>Quantity <input name="quantity" placeholder="1"></label>
                <label>Amount <input name="amount" placeholder="4500"></label>
                <button class="primary-action" type="submit">Generate Exam PIN</button>
            <?php elseif ($service['slug'] === 'recharge_card'): ?>
                <label>Network
                    <select name="network">
                        <option value="">Select network</option>
                        <?php foreach ($serviceNetworks['recharge_card'] ?? [] as $network): ?>
                            <option value="<?= e($network['network_code']); ?>"><?= e($network['network_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Quantity <input name="quantity" placeholder="10"></label>
                <label>Amount <input name="amount" placeholder="3000"></label>
                <button class="primary-action" type="submit">Generate Recharge Card</button>
            <?php elseif ($service['slug'] === 'data_card'): ?>
                <label>Network
                    <select name="network">
                        <option value="">Select network</option>
                        <?php foreach ($serviceNetworks['data_card'] ?? [] as $network): ?>
                            <option value="<?= e($network['network_code']); ?>"><?= e($network['network_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plan <input name="plan" placeholder="5GB"></label>
                <label>Quantity <input name="quantity" placeholder="5"></label>
                <label>Amount <input name="amount" placeholder="7000"></label>
                <button class="primary-action" type="submit">Generate Data Card</button>
            <?php elseif ($service['slug'] === 'bulk_sms'): ?>
                <label>Sender ID <input name="sender" placeholder="GemData"></label>
                <label>Recipients <input name="recipients" placeholder="08030000001,08030000002"></label>
                <label>Message <textarea name="message" rows="4" placeholder="Type your message"></textarea></label>
                <label>Amount <input name="amount" placeholder="1200"></label>
                <button class="primary-action" type="submit">Send Bulk SMS</button>
            <?php endif; ?>
        </form>
    </template>
<?php endforeach; ?>
<?php render_footer(); ?>
