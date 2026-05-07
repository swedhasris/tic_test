<?php
// Build chart data
$statusLabels = array_column($byStatus ?? [], 'status');
$statusData   = array_column($byStatus ?? [], 'cnt');
$priorityLabels = array_column($byPriority ?? [], 'priority');
$priorityData   = array_column($byPriority ?? [], 'cnt');
$monthLabels  = array_column($monthlyTrend ?? [], 'month');
$monthCreated = array_column($monthlyTrend ?? [], 'created');
$monthResolved= array_column($monthlyTrend ?? [], 'resolved');

$totalTickets = array_sum($statusData);
$slaTotal     = (int)($slaStats['total'] ?? 0);
$slaMet       = (int)($slaStats['met'] ?? 0);
$slaBreached  = (int)($slaStats['breached'] ?? 0);
$slaRate      = $slaTotal > 0 ? round($slaMet / $slaTotal * 100) : 0;
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
    <p class="text-gray-500 text-sm mt-1">Insights into your IT service desk performance.</p>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-gray-900"><?= $totalTickets ?></p>
        <p class="text-xs text-gray-500 mt-1 font-medium uppercase">Total Tickets</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-brand"><?= $slaRate ?>%</p>
        <p class="text-xs text-gray-500 mt-1 font-medium uppercase">SLA Compliance</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-green-600"><?= $slaMet ?></p>
        <p class="text-xs text-gray-500 mt-1 font-medium uppercase">SLA Met</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-red-500"><?= $slaBreached ?></p>
        <p class="text-xs text-gray-500 mt-1 font-medium uppercase">SLA Breached</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    <!-- Status breakdown -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Tickets by Status</h3>
        <?php
        $statusColors = ['New'=>'bg-blue-500','In Progress'=>'bg-purple-500','On Hold'=>'bg-yellow-500','Resolved'=>'bg-green-500','Closed'=>'bg-gray-400','Canceled'=>'bg-red-400','Pending Approval'=>'bg-orange-500'];
        foreach ($byStatus ?? [] as $row):
            $pct = $totalTickets > 0 ? round($row['cnt'] / $totalTickets * 100) : 0;
            $color = $statusColors[$row['status']] ?? 'bg-gray-400';
        ?>
        <div class="mb-3">
            <div class="flex justify-between text-sm mb-1">
                <span class="font-medium text-gray-700"><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-gray-500"><?= $row['cnt'] ?> (<?= $pct ?>%)</span>
            </div>
            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full <?= $color ?> rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Priority breakdown -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Tickets by Priority</h3>
        <?php
        $priorityColors = ['1 - Critical'=>'bg-red-500','2 - High'=>'bg-orange-500','3 - Moderate'=>'bg-yellow-400','4 - Low'=>'bg-gray-300'];
        $totalPriority = array_sum(array_column($byPriority ?? [], 'cnt'));
        foreach ($byPriority ?? [] as $row):
            $pct = $totalPriority > 0 ? round($row['cnt'] / $totalPriority * 100) : 0;
            $color = $priorityColors[$row['priority']] ?? 'bg-gray-300';
        ?>
        <div class="mb-3">
            <div class="flex justify-between text-sm mb-1">
                <span class="font-medium text-gray-700"><?= htmlspecialchars($row['priority'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-gray-500"><?= $row['cnt'] ?> (<?= $pct ?>%)</span>
            </div>
            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full <?= $color ?> rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Monthly trend -->
<?php if (!empty($monthlyTrend)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <h3 class="font-semibold text-gray-800 mb-4">Monthly Trend (Last 6 Months)</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left">
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Month</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Created</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Resolved</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Resolution Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($monthlyTrend as $row):
                    $rate = $row['created'] > 0 ? round($row['resolved'] / $row['created'] * 100) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-medium text-gray-800"><?= htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-2.5 text-gray-600"><?= $row['created'] ?></td>
                    <td class="px-4 py-2.5 text-green-600 font-medium"><?= $row['resolved'] ?></td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full" style="width:<?= $rate ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-500 w-10 text-right"><?= $rate ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Agent performance -->
<?php if (!empty($agentPerf)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <h3 class="font-semibold text-gray-800 mb-4">Agent Performance</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left">
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Agent</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Total Assigned</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Resolved</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Avg Resolution</th>
                    <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Resolution Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($agentPerf as $a):
                    $rate = $a['total'] > 0 ? round($a['resolved'] / $a['total'] * 100) : 0;
                    $avgHrs = $a['avg_resolution_hours'] ? round($a['avg_resolution_hours'], 1) . 'h' : '—';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-brand/10 flex items-center justify-center text-brand font-bold text-xs">
                                <?= strtoupper(substr($a['assigned_to_name'], 0, 1)) ?>
                            </div>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($a['assigned_to_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-gray-600"><?= $a['total'] ?></td>
                    <td class="px-4 py-2.5 text-green-600 font-medium"><?= $a['resolved'] ?></td>
                    <td class="px-4 py-2.5 text-gray-600"><?= $avgHrs ?></td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full" style="width:<?= $rate ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-500 w-10 text-right"><?= $rate ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Category breakdown -->
<?php if (!empty($byCategory)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h3 class="font-semibold text-gray-800 mb-4">Top Categories</h3>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php foreach ($byCategory as $cat): ?>
        <div class="bg-gray-50 rounded-lg p-3 text-center">
            <p class="text-2xl font-bold text-gray-900"><?= $cat['cnt'] ?></p>
            <p class="text-xs text-gray-500 mt-1 font-medium"><?= htmlspecialchars($cat['category'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
