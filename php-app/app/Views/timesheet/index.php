<?php
$ts = $currentTimesheet ?? [];
$user = $_SESSION['user'] ?? [];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Timesheets</h1>
        <p class="text-gray-500 text-sm mt-1">Track your time and submit weekly timesheets.</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Current week timesheet -->
    <div class="xl:col-span-2 space-y-5">

        <!-- Current week header -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-gray-800">Current Week</h3>
                    <p class="text-sm text-gray-500 mt-0.5">
                        <?= $ts ? date('M d', strtotime($ts['week_start'])) . ' – ' . date('M d, Y', strtotime($ts['week_end'])) : 'No timesheet' ?>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($ts): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                        <?= $ts['status'] === 'Approved' ? 'bg-green-100 text-green-700' : ($ts['status'] === 'Submitted' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') ?>">
                        <?= htmlspecialchars($ts['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="text-sm font-semibold text-gray-700"><?= number_format((float)($ts['total_hours'] ?? 0), 2) ?> hrs</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add entry form -->
            <?php if ($ts && $ts['status'] === 'Draft'): ?>
            <form action="/php-app/timesheets/entry" method="POST" class="border border-gray-100 rounded-lg p-4 bg-gray-50 mb-4">
                <?= \Core\CSRF::field() ?>
                <input type="hidden" name="timesheet_id" value="<?= $ts['id'] ?>">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Add Time Entry</h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
                        <input type="date" name="entry_date" required
                               value="<?= date('Y-m-d') ?>"
                               min="<?= $ts['week_start'] ?>" max="<?= $ts['week_end'] ?>"
                               class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Hours</label>
                        <input type="number" name="hours_worked" required min="0.25" max="24" step="0.25"
                               placeholder="8.0"
                               class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Start Time</label>
                        <input type="time" name="start_time"
                               class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">End Time</label>
                        <input type="time" name="end_time"
                               class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Task / Ticket</label>
                        <input type="text" name="task" placeholder="e.g., INC1000001 or Meeting"
                               class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Work Type</label>
                        <select name="work_type" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                            <option value="">-- Select --</option>
                            <?php foreach (['Incident','Change','Problem','Project','Meeting','Training','Admin','Other'] as $wt): ?>
                            <option value="<?= $wt ?>"><?= $wt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                    <input type="text" name="short_description" placeholder="Brief description of work done"
                           class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-brand">
                </div>
                <button type="submit" class="inline-flex items-center gap-2 bg-brand hover:bg-green-600 text-white font-medium px-4 py-2 rounded-lg text-sm transition">
                    <i class="fas fa-plus text-xs"></i> Add Entry
                </button>
            </form>
            <?php endif; ?>

            <!-- Entries table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-left">
                            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Date</th>
                            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Task</th>
                            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Description</th>
                            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Type</th>
                            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase text-right">Hours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($entries)): ?>
                        <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">No entries yet. Add your first time entry above.</td></tr>
                        <?php else: ?>
                        <?php foreach ($entries as $e): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap"><?= date('D, M d', strtotime($e['entry_date'])) ?></td>
                            <td class="px-3 py-2.5 font-medium text-gray-800">
                                <?php if ($e['ticket_number']): ?>
                                <a href="/php-app/tickets/detail?id=<?= $e['ticket_id'] ?>" class="text-brand hover:underline font-mono text-xs"><?= htmlspecialchars($e['ticket_number'], ENT_QUOTES, 'UTF-8') ?></a>
                                <?php else: ?>
                                <?= htmlspecialchars($e['task'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 max-w-xs truncate"><?= htmlspecialchars($e['short_description'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2.5 text-gray-500 text-xs"><?= htmlspecialchars($e['work_type'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2.5 text-right font-semibold text-gray-800"><?= number_format((float)$e['hours_worked'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-50 border-t-2 border-gray-200">
                            <td colspan="4" class="px-3 py-2.5 text-right font-semibold text-gray-700">Total Hours:</td>
                            <td class="px-3 py-2.5 text-right font-bold text-brand"><?= number_format((float)($ts['total_hours'] ?? 0), 2) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Submit button -->
            <?php if ($ts && $ts['status'] === 'Draft' && !empty($entries)): ?>
            <div class="mt-4 flex justify-end">
                <form action="/php-app/timesheets/submit" method="POST"
                      onsubmit="return confirm('Submit this timesheet for approval? You will not be able to add more entries.')">
                    <?= \Core\CSRF::field() ?>
                    <input type="hidden" name="timesheet_id" value="<?= $ts['id'] ?>">
                    <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                        <i class="fas fa-paper-plane text-xs"></i> Submit for Approval
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- History -->
    <div class="space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Timesheet History</h3>
            <div class="space-y-2">
                <?php if (empty($timesheets)): ?>
                <p class="text-sm text-gray-400 text-center py-4">No timesheets yet.</p>
                <?php else: ?>
                <?php foreach ($timesheets as $t): ?>
                <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition">
                    <div>
                        <p class="text-sm font-medium text-gray-800">
                            <?= date('M d', strtotime($t['week_start'])) ?> – <?= date('M d', strtotime($t['week_end'])) ?>
                        </p>
                        <p class="text-xs text-gray-400"><?= number_format((float)$t['total_hours'], 2) ?> hrs</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                        <?= $t['status'] === 'Approved' ? 'bg-green-100 text-green-700' : ($t['status'] === 'Submitted' ? 'bg-blue-100 text-blue-700' : ($t['status'] === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600')) ?>">
                        <?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
