<div class="ticket-detail" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="main-column">
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <span style="font-family: monospace; color: var(--sn-green); font-weight: 600; font-size: 0.9rem;"><?php echo $ticket['ticket_number']; ?></span>
                    <h1 style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo htmlspecialchars($ticket['title']); ?></h1>
                </div>
                <div style="text-align: right;">
                    <span style="display: inline-block; padding: 0.25rem 0.75rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 9999px; font-size: 0.8rem; font-weight: 600;">
                        <?php echo $ticket['status']; ?>
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding: 1.5rem; background: rgba(0,0,0,0.02); border-radius: 0.5rem; margin-bottom: 1.5rem;">
                <div>
                    <p style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Caller</p>
                    <p style="font-weight: 500;"><?php echo htmlspecialchars($ticket['caller']); ?></p>
                </div>
                <div>
                    <p style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Category</p>
                    <p style="font-weight: 500;"><?php echo htmlspecialchars($ticket['category']); ?></p>
                </div>
                <div>
                    <p style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Priority</p>
                    <p style="font-weight: 500;"><?php echo htmlspecialchars($ticket['priority']); ?></p>
                </div>
                <div>
                    <p style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Created At</p>
                    <p style="font-weight: 500;"><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></p>
                </div>
            </div>

            <div class="description-section">
                <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;">Description</h3>
                <div style="white-space: pre-wrap; font-size: 0.95rem; color: hsl(var(--foreground)); line-height: 1.6;">
                    <?php echo htmlspecialchars($ticket['description'] ?: 'No description provided.'); ?>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem;">Discussion & Activity</h3>
            
            <form action="/php-app/tickets/comment" method="POST" style="margin-bottom: 2rem;">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                <textarea name="message" rows="3" style="width: 100%; padding: 1rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground)); margin-bottom: 1rem;" placeholder="Type your message..."></textarea>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </div>
            </form>

            <div class="comments-list">
                <!-- Comments would be looped here -->
                <div style="padding: 1rem; text-align: center; color: var(--text-dim);">
                    No activity recorded yet.
                </div>
            </div>
        </div>
    </div>

    <div class="side-column">
        <div class="card" style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Assignment</h3>
            <div style="margin-bottom: 1rem;">
                <p style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Assigned To</p>
                <p style="font-weight: 500;"><?php echo $ticket['assigned_to_name'] ?: 'Unassigned'; ?></p>
            </div>
            <div style="margin-bottom: 1rem;">
                <p style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Group</p>
                <p style="font-weight: 500;"><?php echo $ticket['assignment_group'] ?: 'Service Desk'; ?></p>
            </div>
        </div>

        <div class="card">
            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">SLA Status</h3>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="font-size: 0.8rem; color: var(--text-dim);">Response Time</span>
                <span style="font-size: 0.8rem; font-weight: 600; color: var(--sn-green);">45m remaining</span>
            </div>
            <div style="height: 4px; background: rgba(0,0,0,0.05); border-radius: 2px; margin-bottom: 1rem;">
                <div style="width: 75%; height: 100%; background: var(--sn-green); border-radius: 2px;"></div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="font-size: 0.8rem; color: var(--text-dim);">Resolution Time</span>
                <span style="font-size: 0.8rem; font-weight: 600; color: var(--sn-green);">3h 20m remaining</span>
            </div>
            <div style="height: 4px; background: rgba(0,0,0,0.05); border-radius: 2px;">
                <div style="width: 40%; height: 100%; background: var(--sn-green); border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</div>
