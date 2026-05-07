<?php
$user = $_SESSION['user'] ?? [];
$isAgent = in_array($user['role'] ?? '', ['agent','admin','super_admin','ultra_super_admin']);

function priorityBadge(string $p): string {
    $map = ['1 - Critical'=>'bg-red-100 text-red-700','2 - High'=>'bg-orange-100 text-orange-700','3 - Moderate'=>'bg-yellow-100 text-yellow-700','4 - Low'=>'bg-gray-100 text-gray-600'];
    $cls = $map[$p] ?? 'bg-gray-100 text-gray-600';
    return "<span class=\"inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {$cls}\">" . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . "</span>";
}
function statusBadge(string $s): string {
    $map = ['New'=>'bg-blue-100 text-blue-700','In Progress'=>'bg-purple-100 text-purple-700','On Hold'=>'bg-yellow-100 text-yellow-700','Resolved'=>'bg-green-100 text-green-700','Closed'=>'bg-gray-100 text-gray-600','Canceled'=>'bg-red-100 text-red-600','Pending Approval'=>'bg-orange-100 text-orange-700'];
    $cls = $map[$s] ?? 'bg-gray-100 text-gray-600';
    return "<span class=\"inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {$cls}\">" . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . "</span>";
}

// SLA progress
$slaResponsePct = 0;
$slaResolutionPct = 0;
$slaResponseRemaining = '';
$slaResolutionRemaining = '';
if ($ticket['response_deadline']) {
    $created = strtotime($ticket['created_at']);
    $deadline = strtotime($ticket['response_deadline']);
    $now = time();
    $total = $deadline - $created;
    $elapsed = $now - $created;
    $slaResponsePct = $total > 0 ? min(100, round($elapsed / $total * 100)) : 100;
    $remaining = $deadline - $now;
    $slaResponseRemaining = $remaining > 0 ? floor($remaining/3600).'h '.floor(($remaining%3600)/60).'m remaining' : 'Breached';
}
if ($ticket['resolution_deadline']) {
    $created = strtotime($ticket['created_at']);
    $deadline = strtotime($ticket['resolution_deadline']);
    $now = time();
    $total = $deadline - $created;
    $elapsed = $now - $created;
    $slaResolutionPct = $total > 0 ? min(100, round($elapsed / $total * 100)) : 100;
    $remaining = $deadline - $now;
    $slaResolutionRemaining = $remaining > 0 ? floor($remaining/3600).'h '.floor(($remaining%3600)/60).'m remaining' : 'Breached';
}
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/php-app/tickets" class="hover:text-brand">Incidents</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-800 font-medium font-mono"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Main column -->
    <div class="xl:col-span-2 space-y-5">

        <!-- Ticket header -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-mono text-brand font-bold text-sm"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?= statusBadge($ticket['status']) ?>
                        <?= priorityBadge($ticket['priority']) ?>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($ticket['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <?php if ($isAgent): ?>
                <div class="flex gap-2 shrink-0">
                    <a href="/php-app/tickets/edit?id=<?= $ticket['id'] ?>"
                       class="inline-flex items-center gap-1.5 border border-gray-200 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-sm font-medium transition">
                        <i class="fas fa-edit text-xs"></i> Edit
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Meta grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Caller</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ticket['caller'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Category</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ticket['category'] ?: '—', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Impact</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ticket['impact'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Urgency</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ticket['urgency'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Created</p>
                    <p class="text-sm font-medium text-gray-800"><?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Updated</p>
                    <p class="text-sm font-medium text-gray-800"><?= date('M d, Y H:i', strtotime($ticket['updated_at'])) ?></p>
                </div>
                <?php if ($ticket['resolved_at']): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Resolved</p>
                    <p class="text-sm font-medium text-gray-800"><?= date('M d, Y H:i', strtotime($ticket['resolved_at'])) ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Channel</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ticket['channel'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <!-- Description -->
            <?php if ($ticket['description']): ?>
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Description</h3>
                <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap bg-gray-50 rounded-lg p-4">
                    <?= htmlspecialchars($ticket['description'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Attachments</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($attachments as $att): ?>
                    <a href="/php-app/public/<?= htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                        <i class="fas fa-paperclip"></i>
                        <?= htmlspecialchars($att['original_name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-gray-400">(<?= round($att['file_size'] / 1024) ?>KB)</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Comments & Activity -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-comments text-brand"></i> Discussion & Activity
            </h3>

            <!-- Add comment form -->
            <form action="/php-app/tickets/comment" method="POST" class="mb-6">
                <?= \Core\CSRF::field() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                <textarea name="message" rows="3" required
                          placeholder="Add a comment or work note…"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand resize-none mb-3"></textarea>
                <div class="flex items-center justify-between">
                    <?php if ($isAgent): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                        <span>Internal note (not visible to caller)</span>
                    </label>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-brand hover:bg-green-600 text-white font-medium px-4 py-2 rounded-lg text-sm transition">
                        <i class="fas fa-paper-plane text-xs"></i> Post
                    </button>
                </div>
            </form>

            <!-- Comments list -->
            <div class="space-y-4">
                <?php if (empty($comments)): ?>
                <p class="text-sm text-gray-400 text-center py-6">No activity recorded yet.</p>
                <?php else: ?>
                <?php foreach ($comments as $c): ?>
                <div class="flex gap-3 <?= $c['is_internal'] ? 'opacity-80' : '' ?>">
                    <div class="w-8 h-8 rounded-full <?= $c['is_internal'] ? 'bg-yellow-100' : 'bg-brand/10' ?> flex items-center justify-center text-xs font-bold <?= $c['is_internal'] ? 'text-yellow-700' : 'text-brand' ?> shrink-0">
                        <?= strtoupper(substr($c['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($c['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-xs text-gray-400"><?= date('M d, Y H:i', strtotime($c['created_at'])) ?></span>
                            <?php if ($c['is_internal']): ?>
                            <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full font-medium">
                                <i class="fas fa-lock text-xs"></i> Internal
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-700 bg-gray-50 rounded-lg px-3 py-2 leading-relaxed whitespace-pre-wrap">
                            <?= htmlspecialchars($c['message'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- History -->
        <?php if (!empty($history)): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-history text-brand"></i> Activity Log
            </h3>
            <div class="space-y-2">
                <?php foreach (array_reverse($history) as $h): ?>
                <div class="flex items-start gap-3 text-sm">
                    <div class="w-1.5 h-1.5 rounded-full bg-brand mt-2 shrink-0"></div>
                    <div class="flex-1">
                        <span class="font-medium text-gray-700"><?= htmlspecialchars($h['action'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($h['field_name'] && $h['old_value'] !== null): ?>
                        <span class="text-gray-500"> — <?= htmlspecialchars($h['field_name'], ENT_QUOTES, 'UTF-8') ?>:
                            <span class="line-through text-red-400"><?= htmlspecialchars($h['old_value'], ENT_QUOTES, 'UTF-8') ?></span>
                            → <span class="text-green-600"><?= htmlspecialchars($h['new_value'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <?php endif; ?>
                        <span class="text-gray-400 ml-2">by <?= htmlspecialchars($h['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap"><?= date('M d H:i', strtotime($h['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right sidebar -->
    <div class="space-y-5">

        <!-- Quick status change -->
        <?php if ($isAgent): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-exchange-alt text-brand"></i> Change Status
            </h3>
            <form action="/php-app/tickets/status" method="POST" class="flex gap-2">
                <?= \Core\CSRF::field() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                <select name="status" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                    <?php foreach (['New','In Progress','On Hold','Resolved','Closed','Canceled','Pending Approval'] as $s): ?>
                    <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-brand hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition">
                    Update
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Assignment -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-user-cog text-brand"></i> Assignment
            </h3>
            <?php if ($isAgent): ?>
            <form action="/php-app/tickets/assign" method="POST" class="space-y-3">
                <?= \Core\CSRF::field() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Assignment Group</label>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($ticket['assignment_group'] ?: 'Service Desk', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Assigned To</label>
                    <select name="assigned_to" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($agents ?? [] as $agent): ?>
                        <option value="<?= htmlspecialchars($agent['uid'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= $ticket['assigned_to'] === $agent['uid'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($agent['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white py-2 rounded-lg text-sm font-medium transition">
                    Save Assignment
                </button>
            </form>
            <?php else: ?>
            <div class="space-y-2">
                <div>
                    <p class="text-xs text-gray-400 uppercase font-semibold mb-0.5">Assigned To</p>
                    <p class="text-sm font-medium text-gray-800"><?= $ticket['assigned_to_name'] ? htmlspecialchars($ticket['assigned_to_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-gray-400 italic">Unassigned</span>' ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-semibold mb-0.5">Group</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ticket['assignment_group'] ?: 'Service Desk', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- SLA Status -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-stopwatch text-brand"></i> SLA Status
            </h3>
            <div class="space-y-4">
                <?php if ($ticket['response_deadline']): ?>
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="font-medium text-gray-600">Response Time</span>
                        <span class="<?= $slaResponsePct >= 100 ? 'text-red-500' : ($slaResponsePct >= 75 ? 'text-yellow-500' : 'text-brand') ?> font-semibold">
                            <?= $slaResponseRemaining ?>
                        </span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all <?= $slaResponsePct >= 100 ? 'bg-red-500' : ($slaResponsePct >= 75 ? 'bg-yellow-400' : 'bg-brand') ?>"
                             style="width:<?= $slaResponsePct ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Deadline: <?= date('M d, Y H:i', strtotime($ticket['response_deadline'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($ticket['resolution_deadline']): ?>
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="font-medium text-gray-600">Resolution Time</span>
                        <span class="<?= $slaResolutionPct >= 100 ? 'text-red-500' : ($slaResolutionPct >= 75 ? 'text-yellow-500' : 'text-brand') ?> font-semibold">
                            <?= $slaResolutionRemaining ?>
                        </span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all <?= $slaResolutionPct >= 100 ? 'bg-red-500' : ($slaResolutionPct >= 75 ? 'bg-yellow-400' : 'bg-brand') ?>"
                             style="width:<?= $slaResolutionPct ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Deadline: <?= date('M d, Y H:i', strtotime($ticket['resolution_deadline'])) ?></p>
                </div>
                <?php endif; ?>

                <div class="pt-2 border-t border-gray-100 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <p class="text-gray-400 font-semibold uppercase mb-0.5">Response SLA</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            <?= $ticket['response_sla_status'] === 'Completed' ? 'bg-green-100 text-green-700' : ($ticket['response_sla_status'] === 'Breached' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                            <?= htmlspecialchars($ticket['response_sla_status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase mb-0.5">Resolution SLA</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            <?= $ticket['resolution_sla_status'] === 'Completed' ? 'bg-green-100 text-green-700' : ($ticket['resolution_sla_status'] === 'Breached' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                            <?= htmlspecialchars($ticket['resolution_sla_status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete (admin only) -->
        <?php if (in_array($user['role'] ?? '', ['admin','super_admin','ultra_super_admin'])): ?>
        <div class="bg-white rounded-xl border border-red-100 shadow-sm p-5">
            <h3 class="font-semibold text-red-600 mb-3 flex items-center gap-2">
                <i class="fas fa-trash-alt"></i> Danger Zone
            </h3>
            <form action="/php-app/tickets/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to permanently delete this ticket? This cannot be undone.')">
                <?= \Core\CSRF::field() ?>
                <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
                <button type="submit"
                        class="w-full bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-medium py-2 rounded-lg text-sm transition">
                    <i class="fas fa-trash-alt mr-1"></i> Delete Ticket
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
