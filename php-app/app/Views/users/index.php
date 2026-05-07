<?php $f = $filters ?? []; ?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
        <p class="text-gray-500 text-sm mt-1">Manage system users, roles, and access permissions.</p>
    </div>
    <a href="/php-app/users/create"
       class="inline-flex items-center gap-2 bg-brand hover:bg-green-600 text-white font-semibold px-4 py-2.5 rounded-lg transition shadow-sm text-sm">
        <i class="fas fa-user-plus"></i> Add User
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5">
    <form action="/php-app/users" method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Role</label>
            <select name="role" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand bg-white">
                <option value="">All Roles</option>
                <?php foreach (['user','agent','admin','super_admin','ultra_super_admin'] as $r): ?>
                <option value="<?= $r ?>" <?= ($f['role'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($f['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Name or email…"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                <i class="fas fa-filter text-xs"></i> Filter
            </button>
            <a href="/php-app/users" class="inline-flex items-center gap-1.5 border border-gray-200 text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
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
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">User</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Email</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Role</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Department</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Last Login</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No users found.</td></tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-brand/10 flex items-center justify-center text-brand font-bold text-sm shrink-0">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($u['title']): ?>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($u['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-5 py-3">
                        <?php
                        $roleColors = ['user'=>'bg-gray-100 text-gray-600','agent'=>'bg-blue-100 text-blue-700','admin'=>'bg-purple-100 text-purple-700','super_admin'=>'bg-orange-100 text-orange-700','ultra_super_admin'=>'bg-red-100 text-red-700'];
                        $rc = $roleColors[$u['role']] ?? 'bg-gray-100 text-gray-600';
                        ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold <?= $rc ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_',' ',$u['role'])), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600 text-xs"><?= htmlspecialchars($u['department'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-5 py-3">
                        <?php if ($u['is_active']): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-red-500">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Inactive
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-gray-400 text-xs">
                        <?= $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="/php-app/users/edit?uid=<?= htmlspecialchars($u['uid'], ENT_QUOTES, 'UTF-8') ?>"
                               class="text-xs text-gray-500 hover:text-brand font-medium transition">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="/php-app/users/toggle" method="POST" class="inline">
                                <?= \Core\CSRF::field() ?>
                                <input type="hidden" name="uid" value="<?= htmlspecialchars($u['uid'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="text-xs <?= $u['is_active'] ? 'text-yellow-500 hover:text-yellow-700' : 'text-green-500 hover:text-green-700' ?> font-medium transition" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fas <?= $u['is_active'] ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                </button>
                            </form>
                            <?php if (($u['uid'] ?? '') !== ($_SESSION['user']['uid'] ?? '')): ?>
                            <form action="/php-app/users/delete" method="POST" class="inline"
                                  onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($u['name']), ENT_QUOTES, 'UTF-8') ?>? This cannot be undone.')">
                                <?= \Core\CSRF::field() ?>
                                <input type="hidden" name="uid" value="<?= htmlspecialchars($u['uid'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium transition">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($users)): ?>
    <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-400">
        <?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> found
    </div>
    <?php endif; ?>
</div>
