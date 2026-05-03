<?php
/**
 * Independent System Settings - FLAT ARCHITECTURE
 * No tree structure. Every entry is standalone.
 */
?>
<div class="space-y-8 max-w-[1600px] mx-auto p-6 animate-in fade-in duration-500">
    <!-- Header -->
    <div class="flex items-center justify-between pb-6 border-b border-white/10">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">System Settings</h1>
            <p class="text-sm text-muted-foreground mt-1 uppercase tracking-widest font-bold text-green-500">Independent Flat Model</p>
        </div>
        <div class="flex gap-3">
            <button onclick="loadSettingsData()" class="btn-outline px-4 py-2 text-xs flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Sync Data
            </button>
        </div>
    </div>

    <!-- Independent Grid: 100% Standalone Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- 1. CATEGORIES -->
        <div class="bg-[#1a1c1e] border border-white/10 rounded-2xl overflow-hidden flex flex-col shadow-2xl">
            <div class="p-5 border-b border-white/5 flex items-center justify-between bg-white/5">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                        <i data-lucide="globe" class="w-5 h-5"></i>
                    </div>
                    <span class="font-bold tracking-wider uppercase text-[11px] text-muted-foreground">Categories</span>
                </div>
                <span id="category_count" class="bg-white/10 px-2 py-0.5 rounded-full text-[10px] font-bold">0</span>
            </div>
            <div class="p-4 border-b border-white/5">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                    <input type="text" onkeyup="filterList('category', this.value)" placeholder="Search categories..." 
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-xs focus:ring-2 focus:ring-green-500/50 outline-none transition-all">
                </div>
            </div>
            <div id="category_list" class="flex-grow p-4 space-y-2 min-h-[400px] max-h-[600px] overflow-y-auto custom-scrollbar">
                <!-- Data populated here -->
            </div>
            <div class="p-4 bg-white/5 border-t border-white/5">
                <button onclick="openAddModal('category')" class="w-full bg-[#0f172a] hover:bg-[#1e293b] text-white py-3 rounded-xl text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 transition-all border border-white/5">
                    <i data-lucide="plus" class="w-4 h-4 text-green-500"></i> New Category
                </button>
            </div>
        </div>

        <!-- 2. SUB-CATEGORIES (INDEPENDENT) -->
        <div class="bg-[#1a1c1e] border border-white/10 rounded-2xl overflow-hidden flex flex-col shadow-2xl">
            <div class="p-5 border-b border-white/5 flex items-center justify-between bg-white/5">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-500/10 rounded-lg text-blue-500">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                    <span class="font-bold tracking-wider uppercase text-[11px] text-muted-foreground">Sub-Categories</span>
                </div>
                <span id="subcategory_count" class="bg-white/10 px-2 py-0.5 rounded-full text-[10px] font-bold">0</span>
            </div>
            <div class="p-4 border-b border-white/5">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                    <input type="text" onkeyup="filterList('subcategory', this.value)" placeholder="Search sub-categories..." 
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-xs focus:ring-2 focus:ring-blue-500/50 outline-none transition-all">
                </div>
            </div>
            <div id="subcategory_list" class="flex-grow p-4 space-y-2 min-h-[400px] max-h-[600px] overflow-y-auto custom-scrollbar">
                <!-- Data populated here -->
            </div>
            <div class="p-4 bg-white/5 border-t border-white/5">
                <button onclick="openAddModal('subcategory')" class="w-full bg-[#0f172a] hover:bg-[#1e293b] text-white py-3 rounded-xl text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 transition-all border border-white/5">
                    <i data-lucide="plus" class="w-4 h-4 text-blue-500"></i> New Sub-Category
                </button>
            </div>
        </div>

        <!-- 3. PROVIDERS (INDEPENDENT) -->
        <div class="bg-[#1a1c1e] border border-white/10 rounded-2xl overflow-hidden flex flex-col shadow-2xl">
            <div class="p-5 border-b border-white/5 flex items-center justify-between bg-white/5">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-500/10 rounded-lg text-purple-500">
                        <i data-lucide="box" class="w-5 h-5"></i>
                    </div>
                    <span class="font-bold tracking-wider uppercase text-[11px] text-muted-foreground">Providers</span>
                </div>
                <span id="provider_count" class="bg-white/10 px-2 py-0.5 rounded-full text-[10px] font-bold">0</span>
            </div>
            <div class="p-4 border-b border-white/5">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                    <input type="text" onkeyup="filterList('provider', this.value)" placeholder="Search providers..." 
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-xs focus:ring-2 focus:ring-purple-500/50 outline-none transition-all">
                </div>
            </div>
            <div id="provider_list" class="flex-grow p-4 space-y-2 min-h-[400px] max-h-[600px] overflow-y-auto custom-scrollbar">
                <!-- Data populated here -->
            </div>
            <div class="p-4 bg-white/5 border-t border-white/5">
                <button onclick="openAddModal('provider')" class="w-full bg-[#0f172a] hover:bg-[#1e293b] text-white py-3 rounded-xl text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 transition-all border border-white/5">
                    <i data-lucide="plus" class="w-4 h-4 text-purple-500"></i> New Provider
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Independent Entry -->
<div id="addModal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4">
    <div class="bg-[#1a1c1e] border border-white/10 rounded-3xl w-full max-w-md shadow-2xl animate-in zoom-in duration-200">
        <div class="p-8 border-b border-white/10 flex items-center justify-between">
            <h3 id="modalTitle" class="text-xl font-bold text-white">Add New Independent Item</h3>
            <button onclick="closeAddModal()" class="p-2 hover:bg-white/5 rounded-full text-muted-foreground transition-all"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div class="p-8 space-y-6">
            <div>
                <label class="text-[11px] uppercase font-bold text-muted-foreground tracking-widest block mb-3">Item Name</label>
                <input id="newItemName" type="text" 
                       class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 px-5 text-sm focus:ring-2 focus:ring-green-500/50 outline-none transition-all text-white" 
                       placeholder="Enter independent entry name...">
            </div>
            <p class="text-[10px] text-muted-foreground bg-white/5 p-3 rounded-xl border border-white/5">
                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                This entry will be created as a standalone entity with no parent dependencies.
            </p>
        </div>
        <div class="p-8 border-t border-white/10 flex justify-end gap-4 bg-white/5 rounded-b-3xl">
            <button onclick="closeAddModal()" class="px-8 py-3 rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-white/5 transition-all text-white/70">Cancel</button>
            <button id="saveBtn" onclick="submitNewItem()" class="bg-green-500 hover:bg-green-600 text-black px-10 py-3 rounded-2xl text-xs font-bold uppercase tracking-widest shadow-xl shadow-green-500/20 transition-all transform hover:-translate-y-0.5">Save Independent Entry</button>
        </div>
    </div>
</div>

<script>
let currentEntityType = '';

function openAddModal(type) {
    currentEntityType = type;
    const labels = {
        'category': 'Category',
        'subcategory': 'Sub-Category',
        'provider': 'Provider'
    };
    document.getElementById('modalTitle').innerText = 'New ' + labels[type];
    document.getElementById('addModal').classList.remove('hidden');
    document.getElementById('newItemName').focus();
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
    document.getElementById('newItemName').value = '';
}

function submitNewItem() {
    const name = document.getElementById('newItemName').value.trim();
    if (!name) return;

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerText = 'SAVING...';

    fetch(`<?= BASE_URL ?>/api-proxy.php?action=${currentEntityType}s`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name })
    }).then(res => {
        if (!res.ok) throw new Error('Failed to save');
        return res.json();
    }).then(() => {
        closeAddModal();
        loadSettingsData();
    }).catch(err => {
        alert('Error: ' + err.message);
    }).finally(() => {
        btn.disabled = false;
        btn.innerText = 'Save Independent Entry';
    });
}

function filterList(type, query) {
    const list = document.getElementById(type + '_list');
    const items = list.getElementsByClassName('settings-item');
    const q = query.toLowerCase();
    
    Array.from(items).forEach(item => {
        const text = item.querySelector('.item-name').innerText.toLowerCase();
        item.style.display = text.includes(q) ? '' : 'none';
    });
}

function loadSettingsData() {
    const types = ['category', 'subcategory', 'provider'];
    
    types.forEach(type => {
        const list = document.getElementById(type + '_list');
        list.innerHTML = '<div class="flex items-center justify-center h-40 opacity-50"><i data-lucide="loader" class="w-6 h-6 animate-spin"></i></div>';
        lucide.createIcons();

        fetch(`<?= BASE_URL ?>/api-proxy.php?action=${type}s`)
            .then(res => res.json())
            .then(data => {
                document.getElementById(type + '_count').innerText = data.length;
                
                if (data.length === 0) {
                    list.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-40 text-muted-foreground opacity-30">
                            <i data-lucide="plus-circle" class="w-10 h-10 mb-2"></i>
                            <p class="text-[10px] uppercase font-bold tracking-widest text-center">No ${type}s found.<br>Create the first one below.</p>
                        </div>
                    `;
                    lucide.createIcons();
                    return;
                }

                list.innerHTML = data.map(item => `
                    <div class="settings-item group flex items-center justify-between p-4 bg-white/5 border border-white/5 rounded-2xl hover:border-white/20 hover:bg-white/10 transition-all">
                        <span class="item-name text-sm font-medium text-white/90">${item.name}</span>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all">
                            <button title="Edit" class="p-2 hover:bg-white/10 rounded-xl text-blue-400 transition-all"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                            <button title="Delete" class="p-2 hover:bg-red-500/20 rounded-xl text-red-500 transition-all"><i data-lucide="trash" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                `).join('');
                lucide.createIcons();
            });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadSettingsData();
    lucide.createIcons();
});
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
</style>
