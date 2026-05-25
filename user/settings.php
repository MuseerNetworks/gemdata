<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$db = db();
$role = app(\GemData\Classes\UserRoleManager::class)->roleFor($user);
$walletBalance = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);
$pinColumnExists = $db->columnExists('users', 'transaction_pin_hash');
$securityUser = $db->first(
    'SELECT id, password_hash' . ($pinColumnExists ? ', transaction_pin_hash' : '') . ' FROM users WHERE id = :id LIMIT 1',
    ['id' => (int) $user['id']]
) ?? [];
$hasWalletPin = $pinColumnExists && trim((string) ($securityUser['transaction_pin_hash'] ?? '')) !== '';
$errors = [];

$fieldError = static function (array $errors, string $key): ?string {
    return $errors[$key][0] ?? null;
};

$pinValid = static function (string $pin): bool {
    return preg_match('/^\d{4,6}$/', $pin) === 1;
};

$passwordStrong = static function (string $password): bool {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password) === 1;
};

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $passwordHash = (string) ($securityUser['password_hash'] ?? '');

    if ($currentPassword === '' || !password_verify($currentPassword, $passwordHash)) {
        $errors['current_password'][] = 'Enter your current password to confirm this change.';
    }

    if ($action === 'change_password') {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['password_confirmation'] ?? '');
        if (!$passwordStrong($newPassword)) {
            $errors['new_password'][] = 'Password must include uppercase, lowercase, number, and symbol.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $db->execute(
                'UPDATE users SET password_hash = :password_hash WHERE id = :id',
                ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => (int) $user['id']]
            );
            app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'user_password_changed', 'User changed account password.');
            flash('success', 'Password updated successfully.');
            redirect(base_url('user/settings.php#security'));
        }
    } elseif ($action === 'set_pin') {
        if (!$pinColumnExists) {
            $errors['wallet_pin'][] = 'Wallet PIN storage is not configured yet.';
        }
        if ($hasWalletPin) {
            $errors['wallet_pin'][] = 'A Wallet PIN is already set. Use Change Wallet PIN.';
        }
        $pin = trim((string) ($_POST['wallet_pin'] ?? ''));
        $pinConfirm = trim((string) ($_POST['wallet_pin_confirmation'] ?? ''));
        if (!$pinValid($pin)) {
            $errors['wallet_pin'][] = 'Wallet PIN must be 4 to 6 digits.';
        }
        if ($pin !== $pinConfirm) {
            $errors['wallet_pin_confirmation'][] = 'Wallet PINs do not match.';
        }

        if ($errors === []) {
            $db->execute(
                'UPDATE users SET transaction_pin_hash = :pin_hash WHERE id = :id',
                ['pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'id' => (int) $user['id']]
            );
            app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'wallet_pin_set', 'User set Wallet PIN.');
            flash('success', 'Wallet PIN set successfully.');
            redirect(base_url('user/settings.php#security'));
        }
    } elseif ($action === 'change_pin') {
        if (!$pinColumnExists || !$hasWalletPin) {
            $errors['current_pin'][] = 'Set a Wallet PIN before changing it.';
        }
        $currentPin = trim((string) ($_POST['current_pin'] ?? ''));
        $newPin = trim((string) ($_POST['wallet_pin'] ?? ''));
        $newPinConfirm = trim((string) ($_POST['wallet_pin_confirmation'] ?? ''));
        if ($hasWalletPin && !password_verify($currentPin, (string) ($securityUser['transaction_pin_hash'] ?? ''))) {
            $errors['current_pin'][] = 'Current Wallet PIN is incorrect.';
        }
        if (!$pinValid($newPin)) {
            $errors['wallet_pin'][] = 'Wallet PIN must be 4 to 6 digits.';
        }
        if ($newPin !== $newPinConfirm) {
            $errors['wallet_pin_confirmation'][] = 'Wallet PINs do not match.';
        }

        if ($errors === []) {
            $db->execute(
                'UPDATE users SET transaction_pin_hash = :pin_hash WHERE id = :id',
                ['pin_hash' => password_hash($newPin, PASSWORD_DEFAULT), 'id' => (int) $user['id']]
            );
            app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'wallet_pin_changed', 'User changed Wallet PIN.');
            flash('success', 'Wallet PIN changed successfully.');
            redirect(base_url('user/settings.php#security'));
        }
    } else {
        $errors['general'][] = 'Select a valid security action.';
    }
}

render_header('Settings', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Settings</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Profile, security posture, and account preferences.</p>
    </div>

    <?php if ($message = flash('success')): ?>
        <div class="bg-green-50 border border-green-100 text-gem-green rounded-2xl px-5 py-4 text-[13px] font-semibold"><?= e($message); ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="bg-red-50 border border-red-100 text-gem-red rounded-2xl px-5 py-4 text-[13px] font-semibold">
            <?= e($errors['general'][0] ?? 'Please review the highlighted security fields.'); ?>
        </div>
    <?php endif; ?>

    <section class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border p-5 stagger-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php
            $fields = [
                'Full Name' => $user['full_name'] ?? '',
                'Email Address' => $user['email'] ?? '',
                'Phone Number' => $user['phone'] ?? '',
                'Wallet Balance' => money($walletBalance),
                'Account Type' => ucfirst($role),
                'Date Joined' => human_datetime($user['created_at'] ?? null),
            ];
            ?>
            <?php foreach ($fields as $label => $value): ?>
                <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">
                    <?= e($label); ?>
                    <input class="mt-1.5 w-full rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px] text-gem-text" value="<?= e((string) $value); ?>" readonly>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2 mt-5">
            <?php if ($role === 'api'): ?>
                <a class="bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" href="<?= e(base_url('user/api-keys.php')); ?>">Open API Keys</a>
            <?php else: ?>
                <a class="bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" href="<?= e(base_url('user/upgrade-request.php')); ?>">Upgrade Account</a>
            <?php endif; ?>
            <a class="border border-gem-border text-gem-text text-[13px] font-bold px-4 py-2.5 rounded-xl hover:bg-gem-gray" href="<?= e(base_url('user/notifications.php')); ?>">Manage Alerts</a>
        </div>
    </section>

    <section id="security" class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border p-5 stagger-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-[18px] font-extrabold text-gem-text">Security</h2>
                <p class="text-[13px] text-gem-muted mt-0.5">Manage your password and Wallet PIN for manual purchases.</p>
            </div>
            <span class="inline-flex items-center gap-1 <?= $hasWalletPin ? 'bg-green-50 text-gem-green' : 'bg-amber-50 text-amber-600'; ?> text-[11px] font-semibold px-2.5 py-1 rounded-full">
                <?= $hasWalletPin ? 'Wallet PIN set' : 'Wallet PIN needed'; ?>
            </span>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-5">
            <form method="post" class="rounded-2xl bg-gem-gray border border-gem-border p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="change_password">
                <h3 class="text-[15px] font-bold text-gem-text">Change Password</h3>
                <div class="grid gap-3 mt-4">
                    <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Current Password
                        <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="current_password" type="password" autocomplete="current-password">
                        <?php if ($message = $fieldError($errors, 'current_password')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                    </label>
                    <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">New Password
                        <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="new_password" type="password" autocomplete="new-password">
                        <?php if ($message = $fieldError($errors, 'new_password')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                    </label>
                    <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Confirm New Password
                        <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="password_confirmation" type="password" autocomplete="new-password">
                        <?php if ($message = $fieldError($errors, 'password_confirmation')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                    </label>
                    <button class="bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" type="submit">Update Password</button>
                </div>
            </form>

            <form method="post" class="rounded-2xl bg-gem-gray border border-gem-border p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="<?= $hasWalletPin ? 'change_pin' : 'set_pin'; ?>">
                <h3 class="text-[15px] font-bold text-gem-text"><?= $hasWalletPin ? 'Change Wallet PIN' : 'Set Wallet PIN'; ?></h3>
                <div class="grid gap-3 mt-4">
                    <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Current Password
                        <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="current_password" type="password" autocomplete="current-password">
                        <?php if ($message = $fieldError($errors, 'current_password')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                    </label>
                    <?php if ($hasWalletPin): ?>
                        <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Current Wallet PIN
                            <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="current_pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off">
                            <?php if ($message = $fieldError($errors, 'current_pin')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                        </label>
                    <?php endif; ?>
                    <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider"><?= $hasWalletPin ? 'New Wallet PIN' : 'Wallet PIN'; ?>
                        <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="wallet_pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off">
                        <?php if ($message = $fieldError($errors, 'wallet_pin')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                    </label>
                    <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Confirm Wallet PIN
                        <input class="mt-1.5 w-full rounded-xl bg-white border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="wallet_pin_confirmation" type="password" inputmode="numeric" maxlength="6" autocomplete="off">
                        <?php if ($message = $fieldError($errors, 'wallet_pin_confirmation')): ?><span class="block mt-1 text-[12px] text-gem-red"><?= e($message); ?></span><?php endif; ?>
                    </label>
                    <button class="bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" type="submit"><?= $hasWalletPin ? 'Change Wallet PIN' : 'Set Wallet PIN'; ?></button>
                </div>
            </form>
        </div>
    </section>
</div>
<?php render_footer(); ?>
