<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">User Management</h1>
        <p style="color: hsl(var(--muted-foreground)); font-size: 0.9rem;">Manage system users, roles, and access permissions.</p>
    </div>
    <a href="/php-app/users/create" class="btn btn-primary" style="text-decoration: none; gap: 0.5rem;">
        <i class="fas fa-user-plus"></i> Add User
    </a>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
        <thead>
            <tr style="text-align: left; background: rgba(0,0,0,0.02); border-bottom: 1px solid hsl(var(--border));">
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Name</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Email</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Role</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Status</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Last Login</th>
                <th style="padding: 1rem; text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr style="border-bottom: 1px solid hsl(var(--border));">
                <td style="padding: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: hsl(var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: hsl(var(--secondary-foreground)); font-size: 0.8rem;">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                </td>
                <td style="padding: 1rem; color: hsl(var(--muted-foreground));">
                    <?php echo htmlspecialchars($user['email']); ?>
                </td>
                <td style="padding: 1rem;">
                    <span style="padding: 0.25rem 0.5rem; background: rgba(129, 181, 50, 0.1); color: var(--sn-green); border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                        <?php echo $user['role']; ?>
                    </span>
                </td>
                <td style="padding: 1rem;">
                    <?php if ($user['is_active']): ?>
                        <span style="color: #10b981; font-size: 0.8rem; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> Active
                        </span>
                    <?php else: ?>
                        <span style="color: #ef4444; font-size: 0.8rem; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> Inactive
                        </span>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; color: hsl(var(--muted-foreground)); font-size: 0.8rem;">
                    <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                </td>
                <td style="padding: 1rem; text-align: right;">
                    <button class="btn" style="padding: 0.25rem 0.5rem; background: transparent; color: var(--text-dim);">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
