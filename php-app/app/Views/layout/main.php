<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Connect IT', ENT_QUOTES, 'UTF-8'); ?> — Connect IT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#22c55e',
                            dark:    '#16a34a',
                            light:   '#bbf7d0',
                        },
                        sidebar: '#0f172a',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/php-app/public/css/app.css">
</head>
<body class="h-full bg-gray-50 font-inter text-gray-900 antialiased">

<div class="flex h-screen overflow-hidden">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main content -->
    <div class="flex flex-col flex-1 overflow-hidden">

        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shrink-0 z-10">
            <div class="flex items-center gap-3">
                <!-- Mobile menu toggle -->
                <button id="sidebarToggle" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <h2 class="text-base font-semibold text-gray-700 hidden sm:block">
                    <?php echo htmlspecialchars($title ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?>
                </h2>
            </div>

            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <div class="relative" id="notifContainer">
                    <button id="notifBtn" class="relative text-gray-500 hover:text-gray-700 p-1">
                        <i class="fas fa-bell text-lg"></i>
                        <span id="notifBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center hidden">0</span>
                    </button>
                    <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50">
                        <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                            <span class="font-semibold text-sm">Notifications</span>
                            <button onclick="markAllRead()" class="text-xs text-brand hover:underline">Mark all read</button>
                        </div>
                        <div id="notifList" class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                            <p class="p-4 text-sm text-gray-400 text-center">Loading…</p>
                        </div>
                    </div>
                </div>

                <!-- User menu -->
                <div class="relative" id="userMenuContainer">
                    <button id="userMenuBtn" class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                        <div class="w-8 h-8 rounded-full bg-brand text-white flex items-center justify-center font-bold text-xs">
                            <?php
                            $u = $_SESSION['user'] ?? [];
                            echo strtoupper(substr($u['name'] ?? 'U', 0, 1));
                            ?>
                        </div>
                        <span class="hidden sm:block"><?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                    </button>
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 z-50 py-1">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase"><?php echo htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <a href="/php-app/logout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fas fa-sign-out-alt w-4"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Flash messages -->
        <?php
        $flashSuccess = \Core\Application::$app->session->getFlash('success');
        $flashError   = \Core\Application::$app->session->getFlash('error');
        ?>
        <?php if ($flashSuccess): ?>
        <div class="mx-6 mt-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm" id="flashMsg">
            <i class="fas fa-check-circle text-green-500"></i>
            <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
            <button onclick="document.getElementById('flashMsg').remove()" class="ml-auto text-green-600 hover:text-green-800"><i class="fas fa-times"></i></button>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="mx-6 mt-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm" id="flashMsg">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
            <button onclick="document.getElementById('flashMsg').remove()" class="ml-auto text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button>
        </div>
        <?php endif; ?>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto p-6">
            {{content}}
        </main>
    </div>
</div>

<!-- AI Chatbot bubble -->
<div id="chatbotBubble" class="fixed bottom-6 right-6 z-50">
    <button onclick="toggleChatbot()" class="w-14 h-14 bg-brand hover:bg-brand-dark text-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110">
        <i class="fas fa-robot text-xl" id="chatIcon"></i>
    </button>
</div>

<div id="chatbotPanel" class="hidden fixed bottom-24 right-6 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 flex flex-col" style="height:420px;">
    <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-brand rounded-t-2xl">
        <div class="flex items-center gap-2 text-white">
            <i class="fas fa-robot"></i>
            <span class="font-semibold text-sm">IT Assistant</span>
        </div>
        <button onclick="toggleChatbot()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-3">
        <div class="flex gap-2">
            <div class="w-7 h-7 rounded-full bg-brand flex items-center justify-center shrink-0">
                <i class="fas fa-robot text-white text-xs"></i>
            </div>
            <div class="bg-gray-100 rounded-2xl rounded-tl-none px-3 py-2 text-sm max-w-[85%]">
                Hi! I'm your IT assistant. How can I help you today?
            </div>
        </div>
    </div>
    <div class="p-3 border-t border-gray-100">
        <div class="flex gap-2">
            <input id="chatInput" type="text" placeholder="Type a message…"
                   class="flex-1 text-sm border border-gray-200 rounded-full px-3 py-2 focus:outline-none focus:border-brand"
                   onkeydown="if(event.key==='Enter') sendChat()">
            <button onclick="sendChat()" class="w-8 h-8 bg-brand text-white rounded-full flex items-center justify-center hover:bg-brand-dark">
                <i class="fas fa-paper-plane text-xs"></i>
            </button>
        </div>
    </div>
</div>

<script src="/php-app/public/js/app.js"></script>
<script>
// Dropdown toggles
document.getElementById('notifBtn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('notifDropdown').classList.toggle('hidden');
    document.getElementById('userMenu').classList.add('hidden');
    loadNotifications();
});
document.getElementById('userMenuBtn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('userMenu').classList.toggle('hidden');
    document.getElementById('notifDropdown').classList.add('hidden');
});
document.addEventListener('click', function() {
    document.getElementById('notifDropdown')?.classList.add('hidden');
    document.getElementById('userMenu')?.classList.add('hidden');
});

// Mobile sidebar
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
});

// Notifications
function loadNotifications() {
    fetch('/php-app/api/notifications')
        .then(r => r.json())
        .then(data => {
            const list  = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            if (data.unread_count > 0) {
                badge.textContent = data.unread_count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
            if (!data.data || data.data.length === 0) {
                list.innerHTML = '<p class="p-4 text-sm text-gray-400 text-center">No notifications</p>';
                return;
            }
            list.innerHTML = data.data.map(n => `
                <div class="p-3 hover:bg-gray-50 cursor-pointer ${n.is_read ? '' : 'bg-green-50'}" onclick="markRead(${n.id})">
                    <p class="text-sm font-medium text-gray-800">${escHtml(n.title)}</p>
                    <p class="text-xs text-gray-500 mt-0.5">${escHtml(n.message || '')}</p>
                </div>
            `).join('');
        })
        .catch(() => {});
}
function markRead(id) {
    fetch('/php-app/api/notifications/read', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => loadNotifications());
}
function markAllRead() {
    fetch('/php-app/api/notifications/read', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: 0})
    }).then(() => loadNotifications());
}

// Chatbot
function toggleChatbot() {
    document.getElementById('chatbotPanel').classList.toggle('hidden');
}
function sendChat() {
    const input = document.getElementById('chatInput');
    const msg   = input.value.trim();
    if (!msg) return;
    appendChatMsg(msg, 'user');
    input.value = '';
    fetch('/php-app/api/ai/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({message: msg})
    })
    .then(r => r.json())
    .then(data => appendChatMsg(data.reply || data.error || 'Error', 'bot'))
    .catch(() => appendChatMsg('Connection error.', 'bot'));
}
function appendChatMsg(text, role) {
    const msgs = document.getElementById('chatMessages');
    const isBot = role === 'bot';
    msgs.innerHTML += `
        <div class="flex gap-2 ${isBot ? '' : 'flex-row-reverse'}">
            ${isBot ? '<div class="w-7 h-7 rounded-full bg-brand flex items-center justify-center shrink-0"><i class="fas fa-robot text-white text-xs"></i></div>' : ''}
            <div class="${isBot ? 'bg-gray-100 rounded-2xl rounded-tl-none' : 'bg-brand text-white rounded-2xl rounded-tr-none'} px-3 py-2 text-sm max-w-[85%]">
                ${escHtml(text)}
            </div>
        </div>`;
    msgs.scrollTop = msgs.scrollHeight;
}
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

// Load notification count on page load
loadNotifications();
</script>
</body>
</html>
