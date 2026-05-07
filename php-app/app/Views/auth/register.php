<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Connect IT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#22c55e' } } } }</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <div class="bg-slate-800/80 backdrop-blur border border-white/10 rounded-2xl shadow-2xl p-8">

        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 bg-brand rounded-2xl items-center justify-center mb-4">
                <i class="fas fa-user-plus text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Create Account</h1>
            <p class="text-slate-400 text-sm mt-1">Join Connect IT</p>
        </div>

        <?php $error = \Core\Application::$app->session->getFlash('error'); ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-2 bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg px-4 py-3 text-sm mb-6">
            <i class="fas fa-exclamation-circle shrink-0"></i>
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <?php $d = $data ?? []; ?>
        <form action="/php-app/register" method="POST" class="space-y-4">
            <?= \Core\CSRF::field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Full Name</label>
                <input type="text" name="name" required
                       value="<?= htmlspecialchars($d['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="John Doe"
                       class="w-full bg-slate-700/50 border border-white/10 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($d['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="name@company.com"
                       class="w-full bg-slate-700/50 border border-white/10 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                <input type="password" name="password" required minlength="8"
                       placeholder="Min. 8 characters"
                       class="w-full bg-slate-700/50 border border-white/10 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm Password</label>
                <input type="password" name="password_confirm" required
                       placeholder="Repeat password"
                       class="w-full bg-slate-700/50 border border-white/10 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
            </div>

            <button type="submit"
                    class="w-full bg-brand hover:bg-green-600 text-white font-semibold py-2.5 rounded-lg transition-all mt-2">
                Create Account
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            Already have an account?
            <a href="/php-app/login" class="text-brand hover:underline font-medium">Sign in</a>
        </p>
    </div>
</div>
</body>
</html>
