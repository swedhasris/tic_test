<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = $_SESSION['user'] ?? [];
$role = $user['role'] ?? 'user';

function navLink(string $href, string $icon, string $label, string $currentPath): string {
    // Strip base path for comparison
    $path = parse_url($href, PHP_URL_PATH);
    $active = ($currentPath === $path || ($path !== '/php-app/' && strpos($currentPath, $path) === 0));
    $cls = $active
        ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-brand bg-brand/10 border-r-2 border-brand rounded-l-lg mx-2'
        : 'flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-400 hover:text-white hover:bg-white/5 rounded-lg mx-2 transition-colors';
    return "<a href=\"{$href}\" class=\"{$cls}\"><i class=\"{$icon} w-4 text-center\"></i> {$label}</a>";
}
?>

<aside id="sidebar" class="w-64 bg-sidebar flex flex-col shrink-0 transition-transform duration-300 lg:translate-x-0 -translate-x-full fixed lg:relative inset-y-0 left-0 z-40 h-full">

    <!-- Logo -->
    <div class="h-16 flex items-center px-5 border-b border-white/10 shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-brand rounded-lg flex items-center justify-center font-bold text-white text-sm">C</div>
            <span class="text-white font-bold text-lg tracking-tight">Connect IT</span>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-4 space-y-0.5 custom-scrollbar">

        <!-- Favorites -->
        <div class="px-4 mb-1 mt-2">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Favorites</p>
        </div>
        <?= navLink('/php-app/', 'fas fa-th-large', 'Dashboard', $currentPath) ?>
        <?= navLink('/php-app/reports', 'fas fa-chart-bar', 'Reports', $currentPath) ?>

        <!-- Incident Management -->
        <div class="px-4 mb-1 mt-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Incident</p>
        </div>
        <?= navLink('/php-app/tickets/create', 'fas fa-plus-circle', 'Create New', $currentPath) ?>
        <?= navLink('/php-app/tickets?filter=assigned_to_me', 'fas fa-user-check', 'Assigned to Me', $currentPath) ?>
        <?= navLink('/php-app/tickets?filter=open', 'fas fa-folder-open', 'Open Incidents', $currentPath) ?>
        <?= navLink('/php-app/tickets', 'fas fa-list', 'All Incidents', $currentPath) ?>

        <!-- Service Management -->
        <div class="px-4 mb-1 mt-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Service Mgmt</p>
        </div>
        <?= navLink('/php-app/timesheets', 'fas fa-clock', 'Timesheets', $currentPath) ?>
        <?= navLink('/php-app/calendar', 'fas fa-calendar-alt', 'Calendar', $currentPath) ?>
        <?= navLink('/php-app/leaderboard', 'fas fa-trophy', 'Leaderboard', $currentPath) ?>

        <?php if (in_array($role, ['agent', 'admin', 'super_admin', 'ultra_super_admin'])): ?>
        <!-- Knowledge & CMDB -->
        <div class="px-4 mb-1 mt-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Knowledge</p>
        </div>
        <?= navLink('/php-app/knowledge', 'fas fa-book', 'Knowledge Base', $currentPath) ?>
        <?= navLink('/php-app/cmdb', 'fas fa-server', 'CMDB', $currentPath) ?>
        <?= navLink('/php-app/sla', 'fas fa-stopwatch', 'SLA Management', $currentPath) ?>
        <?= navLink('/php-app/approvals', 'fas fa-check-double', 'Approvals', $currentPath) ?>
        <?= navLink('/php-app/changes', 'fas fa-exchange-alt', 'Change Mgmt', $currentPath) ?>
        <?= navLink('/php-app/problems', 'fas fa-bug', 'Problem Mgmt', $currentPath) ?>
        <?= navLink('/php-app/catalog', 'fas fa-store', 'Service Catalog', $currentPath) ?>
        <?php endif; ?>

        <?php if (in_array($role, ['admin', 'super_admin', 'ultra_super_admin'])): ?>
        <!-- Admin -->
        <div class="px-4 mb-1 mt-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Administration</p>
        </div>
        <?= navLink('/php-app/users', 'fas fa-users', 'User Management', $currentPath) ?>
        <?= navLink('/php-app/settings', 'fas fa-cog', 'Settings', $currentPath) ?>
        <?php endif; ?>

    </nav>

    <!-- User footer -->
    <div class="border-t border-white/10 p-4 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-brand flex items-center justify-center text-white font-bold text-xs shrink-0">
                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($user['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a href="/php-app/logout" class="text-slate-400 hover:text-red-400 transition-colors" title="Sign out">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
</style>
