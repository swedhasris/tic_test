<?php
function priorityBadge(string $p): string {
    $map = [
        '1 - Critical' => 'bg-red-100 text-red-700',
        '2 - High'     => 'bg-orange-100 text-orange-700',
        '3 - Moderate' => 'bg-yellow-100 text-yellow-700',
        '4 - Low'      => 'bg-gray-100 text-gray-600',
    ];
    $cls = $map[$p] ?? 'bg-gray-100 text-gray-600';
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {$cls}\">" . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . "</span>";
}
function statusBadge(string $s): string {
    $map = [
        'New'              => 'bg-blue-100 text-blue-700',
        'In Progress'      => 'bg-purple-100 text-purple-700',
        'On Hold'          => 'bg-yellow-100 text-yellow-700',
        'Resolved'         => 'bg-green-100 text-green-700',
        'Closed'           => 'bg-gray-100 text-gray-600',
        'Canceled'         => 'bg-red-100 text-red-600',
        'Pending Approval' => 'bg-orange-100 text-orange-700',
    ];
    $cls = $map[$s] ?? 'bg-gray-100 text-gray-600';
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {$cls}\">" . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . "</span>";
}
$f = $filters ?? [];
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Incidents</h1>
        <p class="text-gray-500 text-sm mt-1">View and manage all service requests and incidents.</p>
    </div>
    <a href="/php-app/tickets/create"
       class="inline-flex items-center gap-2 bg-brand hover:bg-green-600 text-white font-semibold px-4 py-2.5 rounded-lg transition-all shadow-sm hover:shadow-md text-sm">
        <i class="fas fa-plus"></i> Create New
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5">
    <form action="/php-app/tickets" method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Status</label>
            <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                <option value="">All Statuses</option>
                <?php foreach (['New','In Progress','On Hold','Resolved','Closed','Canceled','Pending Approval'] as $s): ?>
                <option value="<?= $s ?>" <?= ($f['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Priority</label>
            <select name="priority" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                <option value="">All Priorities</option>
                <?php foreach (['1 - Critical','2 - High','3 - Moderate','4 - Low'] as $p): ?>
                <option value="<?= $p ?>" <?= ($f['priority'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Category</label>
            <select name="category" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                <option value="">All Categories</option>
                <?php foreach (['Software','Hardware','Network','Database','Security','Access','Email','Printing','Other'] as $c): ?>
                <option value="<?= $c ?>" <?= ($f['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($f['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Number, title, caller…"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                <i class="fas fa-filter text-xs"></i> Filter
            </button>
            <a href="/php-app/tickets" class="inline-flex items-center gap-1.5 border border-gray-200 text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
                <i class="fas fa-times text-xs"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Number</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Title</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Caller</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Priority</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Assigned To</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Created</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-3 block text-gray-300"></i>
                        No tickets found. <a href="/php-app/tickets/create" class="text-brand hover:underline">Create one?</a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <a href="/php-app/tickets/detail?id=<?= $t['id'] ?>" class="font-mono text-brand font-semibold hover:underline text-xs">
                            <?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </td>
                    <td class="px-5 py-3 font-medium text-gray-800 max-w-xs">
                        <a href="/php-app/tickets/detail?id=<?= $t['id'] ?>" class="hover:text-brand line-clamp-1">
                            <?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </td>
                    <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($t['caller'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-5 py-3"><?= priorityBadge($t['priority']) ?></td>
                    <td class="px-5 py-3"><?= statusBadge($t['status']) ?></td>
                    <td class="px-5 py-3 text-gray-600 text-xs">
                        <?= $t['assigned_to_name'] ? htmlspecialchars($t['assigned_to_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-gray-400 italic">Unassigned</span>' ?>
                    </td>
                    <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    <td class="px-5 py-3 text-right">
                        <a href="/php-app/tickets/detail?id=<?= $t['id'] ?>"
                           class="inline-flex items-center gap-1 text-xs text-brand hover:underline font-medium">
                            View <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($tickets)): ?>
    <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-400">
        Showing <?= count($tickets) ?> ticket<?= count($tickets) !== 1 ? 's' : '' ?>
    </div>
    <?php endif; ?>
</div>
