<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (user()) {
    redirect(base_url('user/dashboard.php'));
}

$errors = [];
$input = [
    'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
];
if (is_post()) {
    verify_csrf();
    $validator = app(\GemData\Classes\Validator::class);
    $errors = $validator->validate(array_merge($_POST, $input), [
        'full_name' => ['required', 'minlen:3'],
        'email' => ['required', 'email'],
        'phone' => ['required', 'phone'],
        'password' => ['required', 'minlen:8'],
    ]);

    if ($_POST['password'] !== ($_POST['password_confirmation'] ?? '')) {
        $errors['password_confirmation'][] = 'Passwords do not match.';
    }

    if ($errors === []) {
        try {
            db()->beginTransaction();
            db()->execute(
                'INSERT INTO users (full_name, email, phone, password_hash) VALUES (:full_name, :email, :phone, :password_hash)',
                [
                    'full_name' => $input['full_name'],
                    'email' => $input['email'],
                    'phone' => $input['phone'],
                    'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                ]
            );
            $userId = db()->lastInsertId();
            app(\GemData\Classes\Wallet::class)->ensure($userId);
            db()->commit();

            flash('success', 'Registration successful. Please sign in.');
            redirect(base_url('user/login.php'));
        } catch (Throwable $throwable) {
            db()->rollBack();
            $errors['general'][] = 'Unable to create your account right now.';
        }
    }
}

$fieldError = static function (array $errors, string $key): ?string {
    return $errors[$key][0] ?? null;
};
$fieldInvalid = static function (array $errors, string $key): string {
    return isset($errors[$key]) ? ' is-invalid' : '';
};
$ariaInvalid = static function (array $errors, string $key): string {
    return isset($errors[$key]) ? ' aria-invalid="true"' : '';
};

render_header('Register');
?>
<div class="gd-auth-wrap gd-auth-grid gd-auth-grid-two">
    <section class="gd-auth-panel">
        <p class="eyebrow">GemData Workspace</p>
        <h1 class="gd-auth-title">Create your GemData account.</h1>
        <p class="gd-auth-copy max-w-xl">Open your wallet, buy VTU services, and scale toward reseller API usage from a single Nigerian-focused platform.</p>
        <div class="mt-8 space-y-4">
            <div class="gd-auth-tip">
                <p class="font-semibold">Use your Nigerian phone number</p>
                <p class="mt-2 text-sm text-white/75">Format as `080...`, `081...`, `070...`, `090...`, or `091...`.</p>
            </div>
            <div class="gd-auth-tip">
                <p class="font-semibold">Secure your password</p>
                <p class="mt-2 text-sm text-white/75">Use at least 8 characters with uppercase, lowercase, and a number.</p>
            </div>
        </div>
    </section>
    <section class="gd-auth-card">
    <p class="eyebrow">Quick onboarding</p>
    <h1 class="gd-auth-title">Create your account</h1>
    <p class="gd-auth-copy">Open a wallet, buy VTU services, and request reseller API access later.</p>
    <?php if (!empty($errors)): ?>
        <div class="notice notice-error mt-6">Please correct the highlighted fields and try again.</div>
    <?php endif; ?>
    <form method="post" class="mt-6 gd-form-grid gd-form-grid-two" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <div class="gd-field md:col-span-2<?= e($fieldInvalid($errors, 'full_name')); ?>">
            <label>Full name</label>
            <input class="gd-input<?= e($fieldInvalid($errors, 'full_name')); ?>" name="full_name" value="<?= e($input['full_name']); ?>" placeholder="Enter your full name" autocomplete="name"<?= $ariaInvalid($errors, 'full_name'); ?>>
            <?php if ($message = $fieldError($errors, 'full_name')): ?><p class="mt-2 text-sm text-gem-red"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div class="gd-field<?= e($fieldInvalid($errors, 'email')); ?>">
            <label>Email</label>
            <input class="gd-input<?= e($fieldInvalid($errors, 'email')); ?>" name="email" type="email" value="<?= e($input['email']); ?>" placeholder="you@example.com" autocomplete="email"<?= $ariaInvalid($errors, 'email'); ?>>
            <?php if ($message = $fieldError($errors, 'email')): ?><p class="mt-2 text-sm text-gem-red"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div class="gd-field<?= e($fieldInvalid($errors, 'phone')); ?>">
            <label>Phone</label>
            <input class="gd-input<?= e($fieldInvalid($errors, 'phone')); ?>" name="phone" value="<?= e($input['phone']); ?>" placeholder="08030000000" autocomplete="tel"<?= $ariaInvalid($errors, 'phone'); ?>>
            <?php if ($message = $fieldError($errors, 'phone')): ?><p class="mt-2 text-sm text-gem-red"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div class="gd-field<?= e($fieldInvalid($errors, 'password')); ?>">
            <label>Password</label>
            <div class="password-field<?= e($fieldInvalid($errors, 'password')); ?>">
                <input class="gd-input<?= e($fieldInvalid($errors, 'password')); ?>" name="password" type="password" placeholder="Create a password" autocomplete="new-password"<?= $ariaInvalid($errors, 'password'); ?>>
                <button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
            </div>
            <?php if ($message = $fieldError($errors, 'password')): ?><p class="mt-2 text-sm text-gem-red"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div class="gd-field<?= e($fieldInvalid($errors, 'password_confirmation')); ?>">
            <label>Confirm password</label>
            <div class="password-field<?= e($fieldInvalid($errors, 'password_confirmation')); ?>">
                <input class="gd-input<?= e($fieldInvalid($errors, 'password_confirmation')); ?>" name="password_confirmation" type="password" placeholder="Repeat your password" autocomplete="new-password"<?= $ariaInvalid($errors, 'password_confirmation'); ?>>
                <button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
            </div>
            <?php if ($message = $fieldError($errors, 'password_confirmation')): ?><p class="mt-2 text-sm text-gem-red"><?= e($message); ?></p><?php endif; ?>
        </div>
        <button class="gd-auth-button" type="submit" data-loading-label="Creating account...">Register</button>
    </form>
    <div class="mt-5 text-sm text-gem-muted">
        Already have an account? <a class="text-gem-blue font-bold" href="<?= e(base_url('user/login.php')); ?>">Sign in</a>
    </div>
    </section>
</div>
<?php render_footer(); ?>
