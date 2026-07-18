<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

render_header('Terms of Service');
?>
<div class="gd-card p-6 md:p-10 max-w-4xl mx-auto my-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-gem-blue mb-4">Terms of Service</h1>
    <p class="text-gem-muted mb-6 text-sm">Last updated: July 16, 2026</p>

    <div class="space-y-6 text-sm leading-relaxed text-gem-muted" style="color: var(--gd-launch-text); font-family: 'Plus Jakarta Sans', sans-serif;">
        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">1. Agreement to Terms</h2>
            <p>By creating an account or accessing the GemData workspace (web or mobile application), you agree to be bound by these Terms of Service. If you do not agree, you must not use our services.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">2. Account Responsibility</h2>
            <p>You are responsible for securing your login credentials, password, and transaction wallet PIN. Any transaction initiated using your credentials will be deemed authorized by you. GemData is not liable for unauthorized access resulting from user negligence.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">3. Wallet Funding & Payments</h2>
            <p>All wallet funding payments processed via our integrated payment channels are final. Double payments or incorrect references must be reported immediately to support with proof of transaction. Wallet credits cannot be refunded back to bank accounts unless explicitly approved by admin review.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">4. VTU & Data Services</h2>
            <p>VTU services, airtime, and utility token generations rely on external telecommunication networks and service providers. While we strive for 100% uptime, GemData is not liable for service delivery failures or delays caused by third-party provider downtime.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">5. Service Abuse</h2>
            <p>We reserve the right to suspend or terminate accounts displaying patterns of API abuse, reverse engineering, rate-limit violations, or fraudulent payment claims.</p>
        </section>
    </div>
</div>
<?php
render_footer();
?>
