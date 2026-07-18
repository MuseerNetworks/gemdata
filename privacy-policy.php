<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

render_header('Privacy Policy');
?>
<div class="gd-card p-6 md:p-10 max-w-4xl mx-auto my-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-gem-blue mb-4">Privacy Policy</h1>
    <p class="text-gem-muted mb-6 text-sm">Last updated: July 16, 2026</p>

    <div class="space-y-6 text-sm leading-relaxed text-gem-muted" style="color: var(--gd-launch-text); font-family: 'Plus Jakarta Sans', sans-serif;">
        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">1. Information We Collect</h2>
            <p>We collect personal information that you provide directly to us when creating your account, including your full name, email address, phone number, and security credentials (password and wallet PIN). We also collect transaction data relating to VTU purchases, payments, and wallet funding request logs.</p>
        </section>
        
        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">2. How We Use Your Information</h2>
            <p>Your information is used to process VTU transactions, fund your wallet, authenticate logins, secure your account credentials, and dispatch push notifications for funding confirmations and system alerts.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">3. Third-Party Data Sharing</h2>
            <p>We share necessary data with third-party processors to facilitate operations:</p>
            <ul class="list-disc pl-5 mt-2 space-y-1">
                <li><strong>Integrated Payment Gateways:</strong> For processing automated bank transfers, cards, and instant wallet crediting.</li>
                <li><strong>Firebase Cloud Messaging (FCM):</strong> For routing mobile push notifications and transaction receipts.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">4. Mobile Device Permissions</h2>
            <p>Our mobile app requires specific hardware permissions to function properly:</p>
            <ul class="list-disc pl-5 mt-2 space-y-1">
                <li><strong>Notifications:</strong> To dispatch transaction status alerts and wallet receipt updates.</li>
                <li><strong>Biometric Authentication:</strong> To facilitate Fingerprint or Face ID login. Stored biometric templates never leave your local device storage.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2" style="color: var(--gd-launch-primary);">5. Account Deletion</h2>
            <p>You have the right to delete your account and associated data. To request account deletion, please contact our support team at support@gemdata.com.ng or submit a request through the support desk in your user profile.</p>
        </section>
    </div>
</div>
<?php
render_footer();
?>
