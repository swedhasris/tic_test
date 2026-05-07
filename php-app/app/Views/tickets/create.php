<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; font-weight: 700;">Create New Incident</h1>
    <p style="color: hsl(var(--muted-foreground)); font-size: 0.9rem;">Fill in the details below to report a new issue.</p>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="/php-app/tickets/create" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div style="grid-column: span 2;">
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Title / Short Description *</label>
            <input type="text" name="title" required style="width: 100%; padding: 0.75rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground));" placeholder="e.g., Cannot access email server">
        </div>

        <div>
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Caller *</label>
            <input type="text" name="caller" required style="width: 100%; padding: 0.75rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground));" value="<?php echo $_SESSION['user']['name'] ?? ''; ?>">
        </div>

        <div>
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Category</label>
            <select name="category" style="width: 100%; padding: 0.75rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground));">
                <option value="Software">Software</option>
                <option value="Hardware">Hardware</option>
                <option value="Network">Network</option>
                <option value="Database">Database</option>
                <option value="Security">Security</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div>
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Urgency</label>
            <select name="urgency" style="width: 100%; padding: 0.75rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground));">
                <option value="1 - High">1 - High</option>
                <option value="2 - Medium">2 - Medium</option>
                <option value="3 - Low" selected>3 - Low</option>
            </select>
        </div>

        <div>
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Impact</label>
            <select name="impact" style="width: 100%; padding: 0.75rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground));">
                <option value="1 - High">1 - High</option>
                <option value="2 - Medium">2 - Medium</option>
                <option value="3 - Low" selected>3 - Low</option>
            </select>
        </div>

        <div style="grid-column: span 2;">
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Detailed Description</label>
            <textarea name="description" rows="5" style="width: 100%; padding: 0.75rem; border: 1px solid hsl(var(--border)); border-radius: 0.5rem; background: hsl(var(--background)); color: hsl(var(--foreground));" placeholder="Provide as much detail as possible..."></textarea>
        </div>

        <div style="grid-column: span 2; display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
            <a href="/php-app/tickets" class="btn" style="background: transparent; color: hsl(var(--muted-foreground));">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Incident</button>
        </div>
    </form>
</div>
