<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: hsl(var(--foreground));">Incidents</h1>
        <p style="color: hsl(var(--muted-foreground)); font-size: 0.9rem;">View and manage all service requests and incidents.</p>
    </div>
    <a href="/php-app/tickets/create" class="btn btn-primary" style="text-decoration: none; gap: 0.5rem;">
        <i class="fas fa-plus"></i> Create New
    </a>
</div>

<div class="filters card" style="margin-bottom: 1.5rem; padding: 1rem;">
    <form action="/php-app/tickets" method="GET" style="display: flex; gap: 1rem; align-items: flex-end;">
        <div style="flex: 1;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase; margin-bottom: 0.25rem;">Status</label>
            <select name="status" style="width: 100%; background: hsl(var(--background)); border: 1px solid hsl(var(--border)); padding: 0.5rem; border-radius: 4px; color: hsl(var(--foreground));">
                <option value="">All Statuses</option>
                <option value="New">New</option>
                <option value="In Progress">In Progress</option>
                <option value="On Hold">On Hold</option>
                <option value="Resolved">Resolved</option>
                <option value="Closed">Closed</option>
            </select>
        </div>
        <div style="flex: 1;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase; margin-bottom: 0.25rem;">Priority</label>
            <select name="priority" style="width: 100%; background: hsl(var(--background)); border: 1px solid hsl(var(--border)); padding: 0.5rem; border-radius: 4px; color: hsl(var(--foreground));">
                <option value="">All Priorities</option>
                <option value="1 - Critical">1 - Critical</option>
                <option value="2 - High">2 - High</option>
                <option value="3 - Moderate">3 - Moderate</option>
                <option value="4 - Low">4 - Low</option>
            </select>
        </div>
        <button type="submit" class="btn" style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
        <thead>
            <tr style="text-align: left; background: rgba(0,0,0,0.02); border-bottom: 1px solid hsl(var(--border));">
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Number</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Title</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Caller</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Priority</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Status</th>
                <th style="padding: 1rem; color: hsl(var(--muted-foreground)); font-weight: 600;">Created</th>
                <th style="padding: 1rem; text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="7" style="padding: 3rem; text-align: center; color: hsl(var(--muted-foreground));">
                        No tickets found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                <tr style="border-bottom: 1px solid hsl(var(--border)); transition: background 0.2s;" onmouseover="this.style.background='rgba(129, 181, 50, 0.02)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 1rem; font-family: monospace; color: var(--sn-green); font-weight: 600;">
                        <?php echo $ticket['ticket_number']; ?>
                    </td>
                    <td style="padding: 1rem; font-weight: 500;">
                        <?php echo htmlspecialchars($ticket['title']); ?>
                    </td>
                    <td style="padding: 1rem;">
                        <?php echo htmlspecialchars($ticket['caller']); ?>
                    </td>
                    <td style="padding: 1rem;">
                        <?php 
                        $priorityClass = '';
                        if (strpos($ticket['priority'], '1') !== false) $priorityClass = 'bg-red-100 text-red-600';
                        elseif (strpos($ticket['priority'], '2') !== false) $priorityClass = 'bg-orange-100 text-orange-600';
                        else $priorityClass = 'bg-gray-100 text-gray-600';
                        ?>
                        <span style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; <?php echo $priorityClass === 'bg-red-100 text-red-600' ? 'background: rgba(239, 68, 68, 0.1); color: #ef4444;' : 'background: rgba(0,0,0,0.05); color: #666;'; ?>">
                            <?php echo $ticket['priority']; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem;">
                        <span style="padding: 0.25rem 0.5rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                            <?php echo $ticket['status']; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; color: hsl(var(--muted-foreground)); font-size: 0.8rem;">
                        <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                    </td>
                    <td style="padding: 1rem; text-align: right;">
                        <a href="/php-app/tickets/detail?id=<?php echo $ticket['id']; ?>" style="color: var(--sn-green); text-decoration: none; font-weight: 600; font-size: 0.8rem;">View Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
