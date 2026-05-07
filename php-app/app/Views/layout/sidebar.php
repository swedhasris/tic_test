<aside class="sidebar custom-scrollbar">
    <div class="sidebar-header" style="padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); height: 64px; display: flex; align-items: center;">
        <div class="logo" style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 32px; height: 32px; background: var(--sn-green); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--sn-dark);">C</div>
            <span style="font-size: 1.25rem; font-weight: bold;">Connect IT</span>
        </div>
    </div>

    <nav class="sidebar-nav" style="flex: 1; overflow-y: auto; padding: 1rem 0;">
        <!-- Favorites Section -->
        <div class="nav-section">
            <h3 style="padding: 0 1.5rem; margin: 1rem 0 0.5rem; font-size: 0.7rem; font-weight: bold; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em;">Favorites</h3>
            <a href="/php-app/" class="nav-link active">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="/php-app/leaderboard" class="nav-link">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
            <a href="/php-app/calendar" class="nav-link">
                <i class="fas fa-calendar-alt"></i> Calendar
            </a>
        </div>

        <!-- Incident Section -->
        <div class="nav-section">
            <h3 style="padding: 0 1.5rem; margin: 1rem 0 0.5rem; font-size: 0.7rem; font-weight: bold; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em;">Incident</h3>
            <a href="/php-app/tickets/create" class="nav-link">
                <i class="fas fa-plus-circle"></i> Create New
            </a>
            <a href="/php-app/tickets?filter=assigned_to_me" class="nav-link">
                <i class="fas fa-user-check"></i> Assigned to Me
            </a>
            <a href="/php-app/tickets?filter=open" class="nav-link">
                <i class="fas fa-folder-open"></i> Open Incidents
            </a>
            <a href="/php-app/tickets" class="nav-link">
                <i class="fas fa-list"></i> All Incidents
            </a>
        </div>

        <!-- System Administration -->
        <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
        <div class="nav-section">
            <h3 style="padding: 0 1.5rem; margin: 1rem 0 0.5rem; font-size: 0.7rem; font-weight: bold; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em;">System Administration</h3>
            <a href="/php-app/users" class="nav-link">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="/php-app/settings" class="nav-link">
                <i class="fas fa-cog"></i> System Settings
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer" style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="/php-app/logout" class="nav-link" style="color: var(--text-dim);">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<style>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.05);
    color: white;
}

.nav-link.active {
    background: rgba(129, 181, 50, 0.1);
    color: var(--sn-green);
    border-right: 2px solid var(--sn-green);
}

.nav-link i {
    width: 20px;
    text-align: center;
}
</style>
