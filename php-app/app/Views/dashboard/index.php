<?php
$user = $_SESSION['user'] ?? [];

// Helper: priority badge
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
?>

<!-- Page header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Incident Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Welcome back, <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>! Here's what's happening today.</p>
</div>

<!-- Stats cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Open</p>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?= (int)($openCount ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-ticket-alt text-brand"></i>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Active incidents requiring attention</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Assigned to Me</p>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?= (int)($assignedToMe ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-user-check text-blue-600"></i>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Your active workload</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Resolved Today</p>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?= (int)($resolvedToday ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-emerald-600"></i>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Tickets closed today</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">SLA Breaches</p>
                <p class="text-3xl font-bold <?= ($slaBreaches ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' ?> mt-1"><?= (int)($slaBreaches ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3"><?= ($slaBreaches ?? 0) > 0 ? 'Requires immediate attention' : 'All SLAs on track' ?></p>
    </div>
</div>

<!-- Main grid -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Recent Incidents -->
    <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Recent Incidents</h3>
            <a href="/php-app/tickets" class="text-xs text-brand hover:underline font-medium">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left bg-gray-50 border-b border-gray-100">
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Number</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Title</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Priority</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($recentTickets)): ?>
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No tickets yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($recentTickets as $t): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3">
                            <a href="/php-app/tickets/detail?id=<?= $t['id'] ?>" class="font-mono text-brand font-semibold hover:underline text-xs">
                                <?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td class="px-5 py-3 font-medium text-gray-800 max-w-xs truncate">
                            <?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="px-5 py-3"><?= priorityBadge($t['priority']) ?></td>
                        <td class="px-5 py-3"><?= statusBadge($t['status']) ?></td>
                        <td class="px-5 py-3 text-gray-400 text-xs"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right column -->
    <div class="space-y-5">

        <!-- Leaderboard -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Leaderboard</h3>
                <i class="fas fa-trophy text-yellow-400"></i>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($leaderboard)): ?>
                <p class="text-sm text-gray-400 text-center py-4">No data yet.</p>
                <?php else: ?>
                <?php $medals = ['🥇','🥈','🥉']; ?>
                <?php foreach ($leaderboard as $i => $agent): ?>
                <div class="flex items-center gap-3">
                    <span class="text-lg w-6 text-center"><?= $medals[$i] ?? ($i + 1) ?></span>
                    <div class="w-8 h-8 rounded-full bg-brand/10 flex items-center justify-center text-brand font-bold text-xs shrink-0">
                        <?= strtoupper(substr($agent['name'], 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($agent['name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-gray-400"><?= number_format($agent['points']) ?> pts · <?= $agent['tickets_resolved'] ?> resolved</p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status breakdown -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Status Breakdown</h3>
            </div>
            <div class="p-4 space-y-2">
                <?php
                $statusColors = [
                    'New'              => 'bg-blue-500',
                    'In Progress'      => 'bg-purple-500',
                    'On Hold'          => 'bg-yellow-500',
                    'Resolved'         => 'bg-green-500',
                    'Closed'           => 'bg-gray-400',
                    'Canceled'         => 'bg-red-400',
                    'Pending Approval' => 'bg-orange-500',
                ];
                $totalTickets = array_sum(array_column($statusCounts ?? [], 'cnt'));
                foreach ($statusCounts ?? [] as $sc):
                    $pct = $totalTickets > 0 ? round($sc['cnt'] / $totalTickets * 100) : 0;
                    $color = $statusColors[$sc['status']] ?? 'bg-gray-400';
                ?>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-600 font-medium"><?= htmlspecialchars($sc['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-gray-400"><?= $sc['cnt'] ?></span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full <?= $color ?> rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>
