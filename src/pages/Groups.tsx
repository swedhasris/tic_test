import React, { useEffect, useState } from "react";
import { collection, onSnapshot, doc, writeBatch, arrayUnion, arrayRemove, setDoc } from "firebase/firestore";
import { db } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { Search, Plus, Trash2, Edit2, Users as UsersIcon, X } from "lucide-react";

// A simple fallback Button component since @/components/ui/button doesn't exist
const Button = ({ className, ...props }: any) => (
  <button
    className={`px-4 py-2 rounded font-bold hover:opacity-90 transition disabled:opacity-50 ${className}`}
    {...props}
  />
);

export function Groups() {
  const { profile } = useAuth();
  const [groups, setGroups] = useState<any[]>([]);
  const [users, setUsers] = useState<any[]>([]);
  const [search, setSearch] = useState("");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isMembersModalOpen, setIsMembersModalOpen] = useState(false);
  const [selectedGroup, setSelectedGroup] = useState<any>(null);
  
  const [form, setForm] = useState({ name: '', description: '', email: '' });
  const [availableSearch, setAvailableSearch] = useState("");
  const [memberSearch, setMemberSearch] = useState("");

  useEffect(() => {
    const unsubGroups = onSnapshot(collection(db, "groups"), snap => 
      setGroups(snap.docs.map(d => ({ id: d.id, ...d.data() })))
    );
    const unsubUsers = onSnapshot(collection(db, "users"), snap => 
      setUsers(snap.docs.map(d => ({ id: d.id, ...d.data() })))
    );
    return () => { unsubGroups(); unsubUsers(); };
  }, []);

  const handleCreateOrUpdate = async () => {
    if (!form.name) return;
    try {
      if (selectedGroup) {
        const batch = writeBatch(db);
        batch.update(doc(db, "groups", selectedGroup.id), { 
          name: form.name, 
          description: form.description,
          email: form.email 
        });
        await batch.commit();
      } else {
        const newRef = doc(collection(db, "groups"));
        await setDoc(newRef, {
          name: form.name,
          description: form.description,
          email: form.email,
          memberIds: [],
          memberCount: 0,
          createdAt: new Date().toISOString()
        });
      }
      setIsModalOpen(false);
    } catch (e) {
      console.error(e);
      alert("Error saving group.");
    }
  };

  const handleDelete = async (group: any) => {
    if (!confirm(`Delete group ${group.name}?`)) return;
    try {
      const batch = writeBatch(db);
      // Remove this group from all users
      (group.memberIds || []).forEach((userId: string) => {
        batch.update(doc(db, "users", userId), { groupIds: arrayRemove(group.id) });
      });
      batch.delete(doc(db, "groups", group.id));
      await batch.commit();
    } catch (e) {
      console.error(e);
    }
  };

  const handleAddMember = async (userId: string) => {
    // Optimistic UI: Update local state immediately
    const updatedMemberIds = [...(selectedGroup.memberIds || []), userId];
    const updatedGroup = { ...selectedGroup, memberIds: updatedMemberIds, memberCount: updatedMemberIds.length };
    
    // Update both main list and current selected group
    setSelectedGroup(updatedGroup);
    setGroups(prev => prev.map(g => g.id === selectedGroup.id ? updatedGroup : g));

    try {
      const batch = writeBatch(db);
      batch.update(doc(db, "groups", selectedGroup.id), { 
        memberIds: arrayUnion(userId),
        memberCount: updatedMemberIds.length
      });
      batch.update(doc(db, "users", userId), { 
        groupIds: arrayUnion(selectedGroup.id) 
      });
      await batch.commit();
    } catch (e) {
      console.error(e);
      // Rollback on error (simplified: fetch fresh data via onSnapshot)
      alert("Failed to add member.");
    }
  };

  const handleRemoveMember = async (userId: string) => {
    // Optimistic UI: Update local state immediately
    const updatedMemberIds = (selectedGroup.memberIds || []).filter((id: string) => id !== userId);
    const updatedGroup = { ...selectedGroup, memberIds: updatedMemberIds, memberCount: updatedMemberIds.length };
    
    setSelectedGroup(updatedGroup);
    setGroups(prev => prev.map(g => g.id === selectedGroup.id ? updatedGroup : g));

    try {
      const batch = writeBatch(db);
      batch.update(doc(db, "groups", selectedGroup.id), { 
        memberIds: arrayRemove(userId),
        memberCount: updatedMemberIds.length
      });
      batch.update(doc(db, "users", userId), { 
        groupIds: arrayRemove(selectedGroup.id) 
      });
      await batch.commit();
    } catch (e) {
      console.error(e);
      alert("Failed to remove member.");
    }
  };

  const filtered = groups.filter(g => !search || g.name?.toLowerCase().includes(search.toLowerCase()));

  return (
    <div className="space-y-6 w-full max-w-none">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-sn-dark">Group Management</h1>
          <p className="text-muted-foreground text-sm">Manage assignment groups and members</p>
        </div>
        <Button onClick={() => { setSelectedGroup(null); setForm({name:'', description:'', email:''}); setIsModalOpen(true); }} className="bg-sn-green text-sn-dark font-bold">
          <Plus className="w-4 h-4 mr-2" /> Create Group
        </Button>
      </div>

      <div className="flex items-center gap-3">
        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <input type="text" placeholder="Search groups..." value={search} onChange={e => setSearch(e.target.value)}
            className="pl-9 pr-4 py-2 border border-border rounded-lg text-sm w-56 outline-none focus:ring-2 focus:ring-sn-green" />
        </div>
        <span className="text-sm text-muted-foreground">{filtered.length} groups</span>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {filtered.map(group => (
          <div key={group.id} className="bg-white border border-border rounded-lg p-5 flex flex-col justify-between hover:border-sn-green/50 transition">
            <div>
              <div className="flex justify-between items-start">
                <h3 className="text-lg font-bold text-sn-dark">{group.name}</h3>
                <span className="bg-muted text-muted-foreground text-xs px-2 py-1 rounded-full font-bold">
                  {(group.memberIds || []).length} members
                </span>
              </div>
              <p className="text-sm text-muted-foreground mt-2 line-clamp-2">{group.description || 'No description'}</p>
            </div>
            <div className="mt-6 flex justify-between items-center border-t border-border pt-4">
              <button onClick={() => { setSelectedGroup(group); setIsMembersModalOpen(true); }} className="text-sn-green text-sm hover:underline font-bold flex items-center gap-1">
                <UsersIcon className="w-4 h-4" /> Manage Members
              </button>
              <div className="flex items-center gap-2">
                <button onClick={() => { setSelectedGroup(group); setForm({name: group.name, description: group.description, email: group.email || ''}); setIsModalOpen(true); }} className="text-blue-500 hover:text-blue-700">
                  <Edit2 className="w-4 h-4" />
                </button>
                <button onClick={() => handleDelete(group)} className="text-red-500 hover:text-red-700">
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
            <div className="p-4 border-b border-border flex justify-between items-center">
              <h3 className="font-bold text-lg">{selectedGroup ? 'Edit Group' : 'Create Group'}</h3>
              <button onClick={() => setIsModalOpen(false)}><X className="w-5 h-5" /></button>
            </div>
            <div className="p-4 space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1 text-sn-dark">Group Name</label>
                <input value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="w-full border rounded p-2 text-sm text-sn-dark" />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-sn-dark">Group Email</label>
                <input type="email" value={form.email} onChange={e => setForm({...form, email: e.target.value})} className="w-full border rounded p-2 text-sm text-sn-dark" placeholder="group@company.com" />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-sn-dark">Description</label>
                <textarea value={form.description} onChange={e => setForm({...form, description: e.target.value})} className="w-full border rounded p-2 text-sm h-24 text-sn-dark" />
              </div>
            </div>
            <div className="p-4 border-t border-border flex justify-end gap-2">
              <Button variant="outline" onClick={() => setIsModalOpen(false)}>Cancel</Button>
              <Button onClick={handleCreateOrUpdate} className="bg-sn-green text-sn-dark font-bold">Save Group</Button>
            </div>
          </div>
        </div>
      )}

      {isMembersModalOpen && selectedGroup && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[85vh] animate-in fade-in zoom-in duration-200">
            <div className="p-4 border-b border-border flex justify-between items-center bg-muted/20">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-lg bg-sn-green/10 flex items-center justify-center">
                  <UsersIcon className="w-6 h-6 text-sn-green" />
                </div>
                <div>
                  <h3 className="font-bold text-xl text-sn-dark">Manage Members</h3>
                  <p className="text-xs text-muted-foreground flex items-center gap-1">
                    <span className="px-1.5 py-0.5 bg-muted rounded font-mono">{selectedGroup.name}</span>
                    <span>• {selectedGroup.email || 'No email set'}</span>
                  </p>
                </div>
              </div>
              <button onClick={() => setIsMembersModalOpen(false)} className="hover:bg-muted p-1.5 rounded-full transition-colors">
                <X className="w-6 h-6 text-muted-foreground" />
              </button>
            </div>

            <div className="flex-grow overflow-hidden grid grid-cols-2 divide-x divide-border bg-muted/5">
              {/* Current Members Column */}
              <div className="flex flex-col h-full overflow-hidden p-6">
                <div className="flex items-center justify-between mb-4">
                  <h4 className="font-bold text-sm uppercase tracking-wider text-muted-foreground">
                    Current Members ({(selectedGroup.memberIds || []).length})
                  </h4>
                </div>
                <div className="relative mb-4">
                  <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                  <input 
                    type="text" 
                    placeholder="Filter current members..." 
                    value={memberSearch}
                    onChange={e => setMemberSearch(e.target.value)}
                    className="w-full pl-9 pr-4 py-2 bg-white border border-border rounded-lg text-xs outline-none focus:ring-2 focus:ring-sn-green"
                  />
                </div>
                <div className="flex-grow overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                  {users
                    .filter(u => (selectedGroup.memberIds || []).includes(u.id))
                    .filter(u => !memberSearch || u.name?.toLowerCase().includes(memberSearch.toLowerCase()) || u.email?.toLowerCase().includes(memberSearch.toLowerCase()))
                    .map(u => (
                      <div key={u.id} className="group flex justify-between items-center p-3 bg-white border border-border rounded-lg hover:border-red-200 transition-all shadow-sm">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-sn-dark/5 flex items-center justify-center text-[10px] font-bold text-sn-dark uppercase">
                            {u.name?.split(' ').map((n:string) => n[0]).join('') || u.email[0]}
                          </div>
                          <div>
                            <div className="text-sm font-semibold text-sn-dark">{u.name || u.email}</div>
                            <div className="text-[10px] text-muted-foreground">{u.email}</div>
                          </div>
                        </div>
                        <button 
                          onClick={() => handleRemoveMember(u.id)} 
                          className="opacity-0 group-hover:opacity-100 text-red-500 hover:text-white hover:bg-red-500 text-[10px] font-bold px-3 py-1.5 rounded-md transition-all border border-red-200"
                        >
                          Remove
                        </button>
                      </div>
                    ))}
                  {(selectedGroup.memberIds || []).length === 0 && (
                    <div className="h-32 flex flex-col items-center justify-center border-2 border-dashed border-border rounded-lg bg-muted/5 text-muted-foreground italic text-sm">
                      No members assigned
                    </div>
                  )}
                </div>
              </div>

              {/* Available Users Column */}
              <div className="flex flex-col h-full overflow-hidden p-6">
                <div className="flex items-center justify-between mb-4">
                  <h4 className="font-bold text-sm uppercase tracking-wider text-muted-foreground">Available Users</h4>
                </div>
                <div className="relative mb-4">
                  <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                  <input 
                    type="text" 
                    placeholder="Search available users..." 
                    value={availableSearch}
                    onChange={e => setAvailableSearch(e.target.value)}
                    className="w-full pl-9 pr-4 py-2 bg-white border border-border rounded-lg text-xs outline-none focus:ring-2 focus:ring-sn-green"
                  />
                </div>
                <div className="flex-grow overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                  {users
                    .filter(u => !(selectedGroup.memberIds || []).includes(u.id))
                    .filter(u => !availableSearch || u.name?.toLowerCase().includes(availableSearch.toLowerCase()) || u.email?.toLowerCase().includes(availableSearch.toLowerCase()))
                    .map(u => (
                      <div 
                        key={u.id} 
                        className="group flex justify-between items-center p-3 bg-white border border-border rounded-lg hover:border-sn-green/50 hover:bg-sn-green/5 cursor-pointer transition-all shadow-sm"
                        onClick={() => handleAddMember(u.id)}
                      >
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-sn-green/10 flex items-center justify-center text-[10px] font-bold text-sn-green uppercase">
                            {u.name?.split(' ').map((n:string) => n[0]).join('') || u.email[0]}
                          </div>
                          <div>
                            <div className="text-sm font-semibold text-sn-dark">{u.name || u.email}</div>
                            <div className="text-[10px] text-muted-foreground">{u.email}</div>
                          </div>
                        </div>
                        <div className="w-6 h-6 rounded-full border border-border flex items-center justify-center group-hover:border-sn-green group-hover:bg-sn-green group-hover:text-white transition-all">
                          <Plus className="w-3.5 h-3.5" />
                        </div>
                      </div>
                    ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
