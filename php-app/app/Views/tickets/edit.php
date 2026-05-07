<?php $t = $ticket ?? []; ?>

<div class="mb-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
        <a href="/php-app/tickets" class="hover:text-brand">Incidents</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="/php-app/tickets/detail?id=<?= $t['id'] ?>" class="hover:text-brand font-mono"><?= htmlspecialchars($t['ticket_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-800 font-medium">Edit</span>
    </div>
    <h1 class="text-2xl font-bold text-gray-900">Edit Incident</h1>
</div>

<form action="/php-app/tickets/edit?id=<?= $t['id'] ?>" method="POST" class="max-w-4xl">
    <?= \Core\CSRF::field() ?>
    <input type="hidden" name="id" value="<?= $t['id'] ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Incident Details</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required
                               value="<?= htmlspecialchars($t['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Caller <span class="text-red-500">*</span></label>
                            <input type="text" name="caller" required
                                   value="<?= htmlspecialchars($t['caller'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                            <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                                <?php foreach (['New','In Progress','On Hold','Resolved','Closed','Canceled','Pending Approval'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($t['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                            <select name="category" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                                <option value="">-- Select --</option>
                                <?php foreach (['Software','Hardware','Network','Database','Security','Access','Email','Printing','Other'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($t['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Subcategory</label>
                            <input type="text" name="subcategory"
                                   value="<?= htmlspecialchars($t['subcategory'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea name="description" rows="6"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand resize-none"><?= htmlspecialchars($t['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Classification</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Priority</label>
                        <select name="priority" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                            <?php foreach (['1 - Critical','2 - High','3 - Moderate','4 - Low'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($t['priority'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Impact</label>
                        <select name="impact" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                            <?php foreach (['1 - High','2 - Medium','3 - Low'] as $i): ?>
                            <option value="<?= $i ?>" <?= ($t['impact'] ?? '') === $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Urgency</label>
                        <select name="urgency" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                            <?php foreach (['1 - High','2 - Medium','3 - Low'] as $u): ?>
                            <option value="<?= $u ?>" <?= ($t['urgency'] ?? '') === $u ? 'selected' : '' ?>><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Assignment</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Assignment Group</label>
                        <input type="text" name="assignment_group"
                               value="<?= htmlspecialchars($t['assignment_group'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Assigned To</label>
                        <select name="assigned_to" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($agents ?? [] as $agent): ?>
                            <option value="<?= htmlspecialchars($agent['uid'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($t['assigned_to'] ?? '') === $agent['uid'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($agent['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <button type="submit" class="w-full bg-brand hover:bg-green-600 text-white font-semibold py-2.5 rounded-lg transition">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
                <a href="/php-app/tickets/detail?id=<?= $t['id'] ?>"
                   class="w-full text-center border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>
