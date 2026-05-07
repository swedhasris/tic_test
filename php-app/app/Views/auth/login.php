<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Connect IT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#22c55e' } } } }</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    <!-- Card -->
    <div class="bg-slate-800/80 backdrop-blur border border-white/10 rounded-2xl shadow-2xl p-8">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 bg-brand rounded-2xl items-center justify-center mb-4 shadow-lg shadow-green-500/20">
                <i class="fas fa-headset text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Connect IT</h1>
            <p class="text-slate-400 text-sm mt-1">Sign in to your account</p>
        </div>

        <!-- Flash error -->
        <?php
        $error = \Core\Application::$app->session->getFlash('error');
        $success = \Core\Application::$app->session->getFlash('success');
        ?>
        <?php if ($error): ?>
        <div class="flex items-center gap-2 bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg px-4 py-3 text-sm mb-6">
            <i class="fas fa-exclamation-circle shrink-0"></i>
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="flex items-center gap-2 bg-green-500/10 border border-green-500/20 text-green-400 rounded-lg px-4 py-3 text-sm mb-6">
            <i class="fas fa-check-circle shrink-0"></i>
            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <form action="/php-app/login" method="POST" class="space-y-5">
            <?= \Core\CSRF::field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="email" name="email" required autocomplete="email"
                           value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="name@company.com"
                           class="w-full bg-slate-700/50 border border-white/10 text-white placeholder-slate-500 rounded-lg pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="password" name="password" id="passwordField" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full bg-slate-700/50 border border-white/10 text-white placeholder-slate-500 rounded-lg pl-10 pr-10 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition">
                    <button type="button" onclick="togglePwd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300">
                        <i class="fas fa-eye text-sm" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-brand hover:bg-green-600 text-white font-semibold py-2.5 rounded-lg transition-all hover:shadow-lg hover:shadow-green-500/20 mt-2">
                Sign In
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            Don't have an account?
            <a href="/php-app/register" class="text-brand hover:underline font-medium">Register</a>
        </p>

        <!-- Demo credentials hint -->
        <div class="mt-6 p-3 bg-slate-700/40 rounded-lg border border-white/5">
            <p class="text-xs text-slate-400 text-center font-medium mb-1">Demo Credentials</p>
            <p class="text-xs text-slate-500 text-center">admin@connectit.com / <span class="font-mono">password</span></p>
        </div>
    </div>
</div>

<script>
function togglePwd() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash'); }
    else { f.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>
</body>
</html>
