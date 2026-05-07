<?php $d = $data ?? []; ?>

<div class="mb-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
        <a href="/php-app/users" class="hover:text-brand">Users</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-800 font-medium">Create User</span>
    </div>
    <h1 class="text-2xl font-bold text-gray-900">Create New User</h1>
</div>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <form action="/php-app/users/create" method="POST" class="space-y-5">
            <?= \Core\CSRF::field() ?>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($d['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($d['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="8"
                           placeholder="Min. 8 characters"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Role <span class="text-red-500">*</span></label>
                    <select name="role" required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand bg-white">
                        <?php foreach (['user'=>'User','agent'=>'Agent','admin'=>'Admin','super_admin'=>'Super Admin'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($d['role'] ?? 'user') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
                    <input type="text" name="department" value="<?= htmlspecialchars($d['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($d['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($d['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-brand hover:bg-green-600 text-white font-semibold px-6 py-2.5 rounded-lg transition">
                    Create User
                </button>
                <a href="/php-app/users" class="border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium px-6 py-2.5 rounded-lg transition text-sm">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
