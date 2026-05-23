<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (admin_user()) { redirect(base_url('admin/dashboard.php')); }
$errors = [];
$email = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if (is_post()) {
    verify_csrf();
    $validator = app(\GemData\Classes\Validator::class);
    $errors = $validator->validate($_POST, ['full_name' => ['required','minlen:3'], 'email' => ['required','email'], 'password' => ['required','minlen:8']]);
    if (($_POST['password'] ?? '') !== ($_POST['password_confirmation'] ?? '')) {
        $errors['password_confirmation'][] = 'Passwords do not match.';
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', (string) ($_POST['password'] ?? ''))) {
        $errors['password'][] = 'Password must include uppercase, lowercase, number, and symbol.';
    }
    if ($errors === []) {
        try {
            admin_service()->acceptInvite($token, (string) $_POST['full_name'], $email, (string) $_POST['password'], client_ip());
            flash('success', 'Admin account created. You can sign in now.');
            redirect(base_url('admin/login.php'));
        } catch (Throwable $throwable) {
            $errors['general'][] = $throwable->getMessage();
        }
    }
}
render_header('Accept Admin Invite');
?>
<div class="mx-auto max-w-2xl rounded-2xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Accept admin invite</h1>
    <?php if (!empty($errors)): ?><div class="notice notice-error mt-6"><?= e(json_encode($errors)); ?></div><?php endif; ?>
    <form method="post" class="mt-6 grid gap-4 md:grid-cols-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="email" value="<?= e($email); ?>">
        <input type="hidden" name="token" value="<?= e($token); ?>">
        <div class="md:col-span-2"><label class="mb-2 block text-sm">Full name</label><input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="full_name" value="<?= old('full_name'); ?>"></div>
        <div class="md:col-span-2"><label class="mb-2 block text-sm">Email</label><input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" value="<?= e($email); ?>" disabled></div>
        <div><label class="mb-2 block text-sm">Password</label><input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password" type="password"></div>
        <div><label class="mb-2 block text-sm">Confirm password</label><input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password_confirmation" type="password"></div>
        <button class="rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950 md:col-span-2" type="submit">Create Admin Account</button>
    </form>
</div>
<?php render_footer(); ?>
