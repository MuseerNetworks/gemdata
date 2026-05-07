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

            $dedicatedAccounts = app(\GemData\Classes\PaystackDedicatedAccountService::class);
            if ($dedicatedAccounts->shouldAutoAssign()) {
                try {
                    $dedicatedAccounts->ensureForUser($userId);
                } catch (Throwable $assignmentError) {
                    app(\GemData\Classes\ActivityLogger::class)->log(
                        'system',
                        0,
                        'paystack_dedicated_account_registration_failed',
                        'Dedicated account assignment failed after registration.',
                        ['user_id' => $userId, 'error' => $assignmentError->getMessage()]
                    );
                }
            }

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

render_header('Register');
?>
<div class="mx-auto max-w-5xl grid gap-8 lg:grid-cols-[0.92fr,1.08fr]">
    <section class="rounded-3xl border border-white/10 bg-gradient-to-br from-indigo-950 via-slate-900 to-cyan-950/70 p-8 text-white">
        <p class="eyebrow">GemData Workspace</p>
        <h1 class="mt-4 text-4xl font-black">Create your GemData account.</h1>
        <p class="mt-4 max-w-xl text-slate-300">Open your wallet, buy VTU services, and scale toward reseller API usage from a single Nigerian-focused platform.</p>
        <div class="mt-8 space-y-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="font-semibold">Use your Nigerian phone number</p>
                <p class="mt-2 text-sm text-slate-300">Format as `080...`, `081...`, `070...`, `090...`, or `091...`.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="font-semibold">Secure your password</p>
                <p class="mt-2 text-sm text-slate-300">Use at least 8 characters with uppercase, lowercase, and a number.</p>
            </div>
        </div>
    </section>
    <section class="rounded-3xl border border-white/10 bg-white/5 p-8">
    <p class="eyebrow">Quick onboarding</p>
    <h1 class="surface-section-title">Create your account</h1>
    <p class="surface-section-copy">Open a wallet, buy VTU services, and request reseller API access later.</p>
    <?php if (!empty($errors)): ?>
        <div class="notice notice-error mt-6">Please correct the highlighted fields and try again.</div>
    <?php endif; ?>
    <form method="post" class="mt-6 grid gap-4 md:grid-cols-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <div class="md:col-span-2">
            <label class="mb-2 block text-sm">Full name</label>
            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="full_name" value="<?= e($input['full_name']); ?>" placeholder="Enter your full name">
            <?php if ($message = $fieldError($errors, 'full_name')): ?><p class="mt-2 text-sm text-rose-300"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div>
            <label class="mb-2 block text-sm">Email</label>
            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="email" type="email" value="<?= e($input['email']); ?>" placeholder="you@example.com">
            <?php if ($message = $fieldError($errors, 'email')): ?><p class="mt-2 text-sm text-rose-300"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div>
            <label class="mb-2 block text-sm">Phone</label>
            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="phone" value="<?= e($input['phone']); ?>" placeholder="08030000000">
            <?php if ($message = $fieldError($errors, 'phone')): ?><p class="mt-2 text-sm text-rose-300"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div>
            <label class="mb-2 block text-sm">Password</label>
            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password" type="password" placeholder="Create a password">
            <?php if ($message = $fieldError($errors, 'password')): ?><p class="mt-2 text-sm text-rose-300"><?= e($message); ?></p><?php endif; ?>
        </div>
        <div>
            <label class="mb-2 block text-sm">Confirm password</label>
            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password_confirmation" type="password" placeholder="Repeat your password">
            <?php if ($message = $fieldError($errors, 'password_confirmation')): ?><p class="mt-2 text-sm text-rose-300"><?= e($message); ?></p><?php endif; ?>
        </div>
        <button class="rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Register</button>
    </form>
    <div class="mt-5 text-sm text-slate-400">
        Already have an account? <a class="text-cyan-300" href="<?= e(base_url('user/login.php')); ?>">Sign in</a>
    </div>
    </section>
</div>
<?php render_footer(); ?>
