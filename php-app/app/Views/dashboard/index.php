<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; font-weight: 700; color: hsl(var(--foreground));">Incident Dashboard</h1>
    <p style="color: hsl(var(--muted-foreground)); font-size: 0.9rem;">Welcome back! Here's what's happening with your tickets today.</p>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="font-size: 0.8rem; font-weight: 600; color: hsl(var(--muted-foreground)); text-transform: uppercase;">Total Open</p>
                <h2 style="font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">24</h2>
            </div>
            <div style="padding: 0.75rem; background: rgba(129, 181, 50, 0.1); color: var(--sn-green); border-radius: 8px;">
                <i class="fas fa-ticket-alt"></i>
            </div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--sn-green);">
            <i class="fas fa-arrow-up"></i> 12% from last week
        </div>
    </div>

    <div class="card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="font-size: 0.8rem; font-weight: 600; color: hsl(var(--muted-foreground)); text-transform: uppercase;">Assigned to Me</p>
                <h2 style="font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">8</h2>
            </div>
            <div style="padding: 0.75rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 8px;">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: #3b82f6;">
            Active workload
        </div>
    </div>

    <div class="card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="font-size: 0.8rem; font-weight: 600; color: hsl(var(--muted-foreground)); text-transform: uppercase;">Resolved Today</p>
                <h2 style="font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">15</h2>
            </div>
            <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 8px;">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: #10b981;">
            Great progress!
        </div>
    </div>

    <div class="card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="font-size: 0.8rem; font-weight: 600; color: hsl(var(--muted-foreground)); text-transform: uppercase;">SLA Breaches</p>
                <h2 style="font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">2</h2>
            </div>
            <div style="padding: 0.75rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 8px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: #ef4444;">
            Requires attention
        </div>
    </div>
</div>

<div class="dashboard-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-weight: 600;">Recent Incidents</h3>
            <a href="/php-app/tickets" style="font-size: 0.8rem; color: var(--sn-green); text-decoration: none;">View all</a>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid hsl(var(--border));">
                    <th style="padding: 0.75rem 0; color: hsl(var(--muted-foreground)); font-weight: 600;">Number</th>
                    <th style="padding: 0.75rem 0; color: hsl(var(--muted-foreground)); font-weight: 600;">Short Description</th>
                    <th style="padding: 0.75rem 0; color: hsl(var(--muted-foreground)); font-weight: 600;">Priority</th>
                    <th style="padding: 0.75rem 0; color: hsl(var(--muted-foreground)); font-weight: 600;">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid hsl(var(--border));">
                    <td style="padding: 1rem 0; font-family: monospace; color: var(--sn-green);">INC0012345</td>
                    <td style="padding: 1rem 0;">Email server down</td>
                    <td style="padding: 1rem 0;"><span style="padding: 0.25rem 0.5rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Critical</span></td>
                    <td style="padding: 1rem 0;"><span style="padding: 0.25rem 0.5rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">New</span></td>
                </tr>
                <tr style="border-bottom: 1px solid hsl(var(--border));">
                    <td style="padding: 1rem 0; font-family: monospace; color: var(--sn-green);">INC0012346</td>
                    <td style="padding: 1rem 0;">Laptop screen flickering</td>
                    <td style="padding: 1rem 0;"><span style="padding: 0.25rem 0.5rem; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Moderate</span></td>
                    <td style="padding: 1rem 0;"><span style="padding: 0.25rem 0.5rem; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">In Progress</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="font-weight: 600; margin-bottom: 1.5rem;">Leaderboard</h3>
        <div class="leaderboard-list">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div style="width: 32px; height: 32px; background: #FFD700; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;">1</div>
                <div style="flex: 1;">
                    <p style="font-size: 0.9rem; font-weight: 600;">Alice Johnson</p>
                    <p style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">2450 points</p>
                </div>
                <div style="font-size: 0.8rem; font-weight: bold; color: var(--sn-green);">+12</div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div style="width: 32px; height: 32px; background: #C0C0C0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;">2</div>
                <div style="flex: 1;">
                    <p style="font-size: 0.9rem; font-weight: 600;">Bob Smith</p>
                    <p style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">2100 points</p>
                </div>
                <div style="font-size: 0.8rem; font-weight: bold; color: var(--sn-green);">+8</div>
            </div>
        </div>
    </div>
</div>
