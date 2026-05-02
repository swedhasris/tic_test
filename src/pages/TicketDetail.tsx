import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { doc, onSnapshot, updateDoc, serverTimestamp, collection, addDoc, query, orderBy, getDocs } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { ROLE_HIERARCHY, Role } from "../lib/roles";
import { Button } from "@/components/ui/button";
import { ChevronLeft, Send, History, MessageSquare, Save, Trash2, CheckCircle2, Clock, Plus, Star } from "lucide-react";
import { cn } from "@/lib/utils";
import { SLATimer } from "../components/SLATimer";
import { useServiceCatalog } from "../lib/serviceCatalog";
import confetti from "canvas-confetti";

export function TicketDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user, profile } = useAuth();
  const { categories, subcategories, serviceProviders, groups } = useServiceCatalog();

  const [ticket, setTicket] = useState<any>(null);
  const [editedTicket, setEditedTicket] = useState<any>(null);
  const [comments, setComments] = useState<any[]>([]);
  const [newComment, setNewComment] = useState("");
  const [workNote, setWorkNote] = useState("");
  const [isUpdating, setIsUpdating] = useState(false);
  const [agents, setAgents] = useState<any[]>([]);

  const visibleCategories = categories.filter((item) => item.status === 'active');
  const visibleSubcategories = subcategories.filter(s => s.categoryId === editedTicket?.categoryId && s.status === 'active');
  const visibleProviders = serviceProviders.filter(p => p.subcategoryId === editedTicket?.subcategoryId && p.status === 'active');
  const visibleGroups = groups.filter(g => g.serviceProviderId === editedTicket?.serviceId && g.status === 'active');

  useEffect(() => {
    getDocs(collection(db, "users")).then(snap => {
      setAgents(snap.docs.map(d => ({ id: d.id, ...d.data() })).filter((u: any) => ROLE_HIERARCHY[u.role as Role] >= ROLE_HIERARCHY["agent"]));
    }).catch(() => { });
  }, []);

  useEffect(() => {
    if (!id) return;
    const unsubscribe = onSnapshot(doc(db, "tickets", id), (docSnapshot) => {
      if (docSnapshot.exists()) {
        const data = { id: docSnapshot.id, ...docSnapshot.data() };
        setTicket(data);
        setEditedTicket((prev: any) => prev ? prev : data);
      } else {
        navigate("/tickets");
      }
    }, (error) => {
      handleFirestoreError(error, OperationType.GET, `tickets/${id}`);
    });
    return unsubscribe;
  }, [id, navigate]);

  useEffect(() => {
    if (!id) return;
    const q = query(collection(db, "tickets", id, "comments"), orderBy("createdAt", "asc"));
    const unsubscribe = onSnapshot(q, (snapshot) => {
      setComments(snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() })));
    }, (error) => {
      handleFirestoreError(error, OperationType.LIST, `tickets/${id}/comments`);
    });
    return unsubscribe;
  }, [id]);

  const handleUpdate = async () => {
    if (!id || !user || !editedTicket) return;
    setIsUpdating(true);
    try {
      const historyEntries: any[] = [];
      const fields = ["category", "categoryId", "subcategory", "subcategoryId", "service", "serviceId", "serviceProvider", "status", "impact", "urgency", "assignmentGroup", "title", "description", "assignedTo", "affectedUser", "resolutionCode", "resolutionNotes"];

      fields.forEach(field => {
        if (editedTicket[field] !== ticket[field]) {
          historyEntries.push({
            action: `Field ${field} updated from ${ticket[field] || "none"} to ${editedTicket[field] || "none"}`,
            timestamp: new Date().toISOString(),
            user: profile?.name || user.email
          });
        }
      });

      // Special check: If only resolution notes/code were changed, but no other fields in the list, we still want to save.
      // The 'Submit' button should always save editedTicket.

      const { id: _, ...payload } = editedTicket;
      
      const assignedUserName = editedTicket.assignedTo 
        ? agents.find(a => a.id === editedTicket.assignedTo)?.name || editedTicket.assignedToName || "" 
        : "";

      const updates: any = {
        ...payload,
        assignedToName: assignedUserName,
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), ...historyEntries]
      };

      const isResolved = editedTicket.status === "Resolved" || editedTicket.status === "Closed";
      const isPaused = editedTicket.status === "On Hold" || editedTicket.status === "Waiting for Customer";

      if (editedTicket.status !== ticket.status) {
        
        // Stop Response SLA if the state is changed out of "New" (i.e. acknowledging the ticket)
        if (editedTicket.status !== "New" && !ticket.firstResponseAt) {
          updates.firstResponseAt = new Date().toISOString();
          updates.responseSlaStatus = "Completed";
        }

        if (isResolved && !ticket.resolvedAt) {
          updates.resolvedAt = new Date().toISOString();
          updates.resolvedBy = profile?.name || user.email;
          updates.resolutionSlaStatus = "Completed";
          updates.onHoldStart = null;
          
          // Ensure resolution fields are present if being resolved
          if (!editedTicket.resolutionCode) {
            alert("Please select a Resolution Code before resolving.");
            setIsUpdating(false);
            return;
          }
        } else if (!isResolved && ticket.resolvedAt) {
          updates.resolvedAt = null;
          updates.resolvedBy = null;
          updates.resolutionSlaStatus = "In Progress";
        }

        if (isPaused && !isResolved) {
          updates.onHoldStart = new Date().toISOString();
        } else if ((ticket.status === "On Hold" || ticket.status === "Waiting for Customer") && !isPaused) {
          const onHoldStartStr = ticket.onHoldStart || new Date().toISOString();
          const onHoldStart = new Date(onHoldStartStr).getTime();
          const now = new Date().getTime();
          const pauseDuration = Math.max(0, now - onHoldStart);

          const totalPaused = (Number(ticket.totalPausedTime) || 0) + pauseDuration;
          updates.totalPausedTime = totalPaused;
          updates.onHoldStart = null;

          if (ticket.resolutionDeadline) {
            const oldRes = new Date(ticket.resolutionDeadline).getTime();
            if (!isNaN(oldRes)) updates.resolutionDeadline = new Date(oldRes + pauseDuration).toISOString();
          }
          if (ticket.responseDeadline && !ticket.firstResponseAt) {
            const oldResp = new Date(ticket.responseDeadline).getTime();
            if (!isNaN(oldResp)) updates.responseDeadline = new Date(oldResp + pauseDuration).toISOString();
          }
        }
      }

      // Calculate points if resolving
      let pointsAwarded = 0;
      if (isResolved && !ticket.resolvedAt && ticket.resolutionDeadline) {
        const deadline = new Date(ticket.resolutionDeadline).getTime();
        const resolvedAtMs = new Date().getTime();
        const createdAtMs = ticket.createdAt?.seconds ? ticket.createdAt.seconds * 1000 : (typeof ticket.createdAt === 'string' ? new Date(ticket.createdAt).getTime() : 0);
        
        if (createdAtMs > 0 && resolvedAtMs < deadline) {
          const totalSla = deadline - createdAtMs;
          const timeSaved = deadline - resolvedAtMs;
          pointsAwarded = Math.round((timeSaved / totalSla) * 100);
          if (pointsAwarded < 10) pointsAwarded = 10;
        } else {
          pointsAwarded = 5;
        }
      }

      const ticketRef = doc(db, "tickets", id);
      const finalUpdates = {
        ...updates,
        points: pointsAwarded > 0 ? (ticket.points || 0) + pointsAwarded : (ticket.points || 0)
      };

      await updateDoc(ticketRef, finalUpdates);

      if (pointsAwarded > 0) {
        confetti({
          particleCount: 150,
          spread: 70,
          origin: { y: 0.6 },
          colors: ["#22c55e", "#fbbf24", "#3b82f6"]
        });
        alert(`Great job! Ticket resolved. You earned ${pointsAwarded} points! 🏆`);
      } else {
        alert("Incident updated successfully");
      }
    } catch (error: any) {
      console.error("Error updating ticket:", error);
      alert(`Failed to update incident: ${error.message || "Unknown error"}`);
    } finally {
      setIsUpdating(false);
    }
  };

  const handleAddComment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newComment.trim() || !id || !user) return;
    try {
      const now = new Date().toISOString();
      const historyEntry = { action: "Comment Added", timestamp: now, user: profile?.name || user.email };
      const updates: any = {
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), historyEntry]
      };
      if (!ticket.firstResponseAt) {
        updates.firstResponseAt = now;
        updates.responseSlaStatus = "Completed";
      }
      await addDoc(collection(db, "tickets", id, "comments"), {
        userId: user.uid,
        userName: profile?.name || user.email,
        message: newComment,
        type: "comment",
        createdAt: serverTimestamp()
      });
      await updateDoc(doc(db, "tickets", id), updates);
      setNewComment("");
    } catch (error) { console.error(error); }
  };

  const handleAddWorkNote = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!workNote.trim() || !id || !user) return;
    try {
      const historyEntry = { action: "Work Note Added", timestamp: new Date().toISOString(), user: profile?.name || user.email };
      const now = new Date().toISOString();
      const updates: any = {
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), historyEntry]
      };
      
      if (!ticket.firstResponseAt) {
        updates.firstResponseAt = now;
        updates.responseSlaStatus = "Completed";
      }

      await addDoc(collection(db, "tickets", id, "comments"), {
        userId: user.uid,
        userName: profile?.name || user.email,
        message: workNote,
        type: "work_note",
        createdAt: serverTimestamp()
      });
      await updateDoc(doc(db, "tickets", id), updates);
      setWorkNote("");
    } catch (error) { console.error(error); }
  };

  const updateLocalField = (field: string, value: string) => {
    setEditedTicket((prev: any) => ({ ...prev, [field]: value }));
  };

  const formatDate = (date: any) => {
    if (!date) return "-";
    if (typeof date.toDate === "function") return date.toDate().toLocaleString();
    if (typeof date === "string") return new Date(date).toLocaleString();
    if (date.seconds) return new Date(date.seconds * 1000).toLocaleString();
    return "-";
  };

  const [activeTab, setActiveTab] = useState("Notes");

  if (!ticket) return null;

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between bg-white p-3 border border-border rounded-lg shadow-sm">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={() => navigate("/tickets")} className="gap-2 h-8 px-2">
            <ChevronLeft className="w-4 h-4" />
          </Button>
          <div className="flex flex-col">
            <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wider leading-none mb-1">Incident</span>
            <span className="text-sm font-bold leading-none">{ticket.number}</span>
          </div>
          {ticket.points > 0 && (
            <div className="flex items-center gap-1.5 px-2 py-0.5 bg-yellow-400/10 text-yellow-600 border border-yellow-400/20 rounded-full text-[10px] font-black uppercase tracking-widest animate-pulse">
              <Star className="w-3 h-3 fill-current" />
              {ticket.points} Points Earned
            </div>
          )}
        </div>
        <div className="flex items-center gap-6">
          <div className="flex items-center gap-4 border-r border-border pr-6 hidden md:flex">
            <SLATimer 
              label="Resp SLA" 
              deadline={ticket.responseDeadline} 
              metAt={ticket.firstResponseAt || (editedTicket.status !== "New" ? new Date().toISOString() : undefined)} 
              isPaused={editedTicket.status === "On Hold" || editedTicket.status === "Waiting for Customer"}
              onHoldStart={ticket.onHoldStart}
              totalPausedTime={ticket.totalPausedTime}
            />
            <SLATimer 
              label="Res SLA" 
              deadline={ticket.resolutionDeadline} 
              metAt={ticket.resolvedAt || (editedTicket.status === "Resolved" || editedTicket.status === "Closed" ? new Date().toISOString() : undefined)} 
              isPaused={editedTicket.status === "On Hold" || editedTicket.status === "Waiting for Customer"}
              onHoldStart={ticket.onHoldStart}
              totalPausedTime={ticket.totalPausedTime}
              waitUntil={ticket.firstResponseAt || (editedTicket.status !== "New" ? new Date().toISOString() : null)}
            />
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={handleUpdate} disabled={isUpdating} className="h-8 px-4 font-bold border-border bg-white text-sn-dark">Update</Button>
            <Button size="sm" onClick={handleUpdate} disabled={isUpdating} className="h-8 px-4 font-bold bg-sn-green text-sn-dark shadow-sm hover:bg-sn-green/90">Submit</Button>
          </div>
        </div>
      </div>

      {/* Main Form Section */}
      <div className="grid grid-cols-1 lg:grid-cols-1 gap-6">
        <div className="bg-white border border-border rounded-lg shadow-sm p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
            <div className="space-y-4">
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Number</label>
                <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono" value={ticket.number} />
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Reporting User</label>
                <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs" value={ticket.caller || ''} />
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Category</label>
                <select
                  value={editedTicket?.categoryId || ""}
                  onChange={(e) => {
                    const category = visibleCategories.find((item) => item.id === e.target.value);
                    setEditedTicket((prev: any) => ({ ...prev, categoryId: e.target.value, category: category?.name || "", subcategoryId: "", subcategory: "", serviceId: "", service: "", serviceProvider: "", assignmentGroup: "" }));
                  }}
                  className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                >
                  {visibleCategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Subcategory</label>
                <select
                  value={editedTicket?.subcategoryId || ""}
                  onChange={(e) => {
                    const subcategory = visibleSubcategories.find((item) => item.id === e.target.value);
                    setEditedTicket((prev: any) => ({ ...prev, subcategoryId: e.target.value, subcategory: subcategory?.name || "", serviceId: "", service: "", serviceProvider: "", assignmentGroup: "" }));
                  }}
                  className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                >
                  <option value="">-- None --</option>
                  {visibleSubcategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Service Provider</label>
                <select
                  value={editedTicket?.serviceId || ""}
                  onChange={(e) => {
                    const service = visibleProviders.find((item) => item.id === e.target.value);
                    setEditedTicket((prev: any) => ({ ...prev, serviceId: e.target.value, service: service?.name || "", serviceProvider: service?.name || "", assignmentGroup: "" }));
                  }}
                  className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                >
                  <option value="">-- Select Service --</option>
                  {visibleProviders.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </div>
            </div>

            <div className="space-y-4">
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">State</label>
                <select value={editedTicket?.status || ""} onChange={(e) => updateLocalField("status", e.target.value)} className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8">
                  {["New", "In Progress", "On Hold", "Resolved", "Closed", "Canceled"].map(s => <option key={s} value={s}>{s}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Priority</label>
                <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-bold text-blue-600 h-8" value={editedTicket?.priority || ""} />
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assignment group</label>
                <select className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8" value={editedTicket?.assignmentGroup || ""} onChange={(e) => updateLocalField("assignmentGroup", e.target.value)}>
                  <option value="">-- None --</option>
                  {visibleGroups.map((item) => <option key={item.id} value={item.name}>{item.name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-3 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assigned to</label>
                <select className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8" value={editedTicket?.assignedTo || ""} onChange={(e) => updateLocalField("assignedTo", e.target.value)}>
                  <option value="">-- None --</option>
                  {editedTicket?.assignedTo && !agents.some(a => a.id === editedTicket.assignedTo) && (
                    <option value={editedTicket.assignedTo}>{editedTicket.assignedToName || editedTicket.assignedTo} (Legacy)</option>
                  )}
                  {agents.map(agent => <option key={agent.id} value={agent.id}>{agent.name || agent.email}</option>)}
                </select>
              </div>
            </div>

            <div className="col-span-1 md:col-span-2 mt-4 space-y-4">
              <div className="grid grid-cols-6 items-center gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Short description</label>
                <input className="col-span-5 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" value={editedTicket?.title || ""} onChange={(e) => updateLocalField("title", e.target.value)} />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs Section */}
      <div className="bg-white border border-border rounded-lg shadow-sm overflow-hidden mt-6">
        {/* Tab Headers */}
        <div className="flex bg-muted/30 border-b border-border">
          {["Notes", "Related Records", "Resolution Information"].map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => setActiveTab(tab)}
              className={cn(
                "px-6 py-2.5 text-[10px] font-bold uppercase tracking-wider transition-colors border-r border-border h-full",
                activeTab === tab 
                  ? "bg-white text-sn-dark border-b-white -mb-px" 
                  : "text-muted-foreground hover:bg-white/50"
              )}
            >
              {tab}
            </button>
          ))}
          <div className="flex-grow border-b border-border"></div>
        </div>

        {/* Tab Content */}
        <div className="p-6">
          {activeTab === "Notes" ? (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
              {/* Left Column: Notes & Lists */}
              <div className="space-y-6">
                <div className="space-y-3">
                  <div className="grid grid-cols-4 items-center gap-4">
                    <label className="text-[10px] text-right font-bold text-muted-foreground uppercase">Watch list</label>
                    <div className="col-span-3 flex gap-2">
                      <input type="text" placeholder="Add users to watch list..." className="flex-grow p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" />
                      <button type="button" className="p-1.5 border border-border rounded hover:bg-muted"><Plus className="w-4 h-4" /></button>
                    </div>
                  </div>
                  <div className="grid grid-cols-4 items-center gap-4">
                    <label className="text-[10px] text-right font-bold text-muted-foreground uppercase">Worknotes list</label>
                    <div className="col-span-3 flex gap-2">
                      <input type="text" placeholder="Add team members..." className="flex-grow p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" />
                      <button type="button" className="p-1.5 border border-border rounded hover:bg-muted"><Plus className="w-4 h-4" /></button>
                    </div>
                  </div>
                </div>

                <div className="space-y-4 pt-4 border-t border-border">
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <label className="text-[10px] font-bold text-muted-foreground uppercase">Work notes (Private)</label>
                      <label className="flex items-center gap-2 text-[10px] text-muted-foreground cursor-pointer">
                        <input type="checkbox" className="w-3 h-3 rounded" /> Internal only
                      </label>
                    </div>
                    <textarea 
                      value={workNote} 
                      onChange={(e) => setWorkNote(e.target.value)} 
                      placeholder="Type internal worknotes here..." 
                      className="w-full p-3 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-dark min-h-[100px] resize-none bg-yellow-50/20" 
                    />
                  </div>

                  <div className="space-y-2">
                    <label className="text-[10px] font-bold text-muted-foreground uppercase">Additional comments (Customer visible)</label>
                    <textarea 
                      value={newComment} 
                      onChange={(e) => setNewComment(e.target.value)} 
                      placeholder="Type public messages here..." 
                      className="w-full p-3 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green min-h-[100px] resize-none" 
                    />
                  </div>

                  <div className="flex justify-end pt-2">
                    <Button 
                      type="button"
                      onClick={(e) => {
                        if (workNote.trim()) handleAddWorkNote(e);
                        if (newComment.trim()) handleAddComment(e);
                      }} 
                      className="bg-sn-green text-sn-dark font-bold gap-2 px-8 h-10 shadow-lg hover:shadow-sn-green/20"
                    >
                      <Send className="w-4 h-4" /> Post Comment
                    </Button>
                  </div>
                </div>
              </div>

              {/* Right Column: Activity Stream */}
              <div className="space-y-4">
                <div className="flex items-center justify-between border-b border-border pb-2">
                  <h3 className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Activity Stream</h3>
                  <button type="button" className="text-[10px] font-bold text-muted-foreground hover:text-sn-dark uppercase tracking-widest">Filter</button>
                </div>
                <div className="space-y-6 max-h-[500px] overflow-y-auto pr-4 custom-scrollbar">
                  {comments.length === 0 && (
                    <div className="text-center py-12 text-muted-foreground">
                      <History className="w-8 h-8 mx-auto mb-2 opacity-20" />
                      <p className="text-xs">No activity recorded yet</p>
                    </div>
                  )}
                  {comments.map((comment) => (
                    <div key={comment.id} className="relative pl-6 pb-6 last:pb-0 border-l border-border ml-2">
                      <div className={cn(
                        "absolute -left-[9px] top-0 w-4 h-4 rounded-full border-2 border-white flex items-center justify-center",
                        comment.type === "work_note" ? "bg-yellow-400" : "bg-sn-green"
                      )}>
                        {comment.type === "work_note" ? <Save className="w-2 h-2 text-white" /> : <MessageSquare className="w-2 h-2 text-white" />}
                      </div>
                      <div className="flex flex-col gap-1">
                        <div className="flex items-center justify-between">
                          <span className="text-[11px] font-bold text-sn-dark">{comment.userName}</span>
                          <span className="text-[10px] text-muted-foreground">{formatDate(comment.createdAt)}</span>
                        </div>
                        <div className={cn(
                          "p-3 rounded text-xs",
                          comment.type === "work_note" ? "bg-yellow-50 text-sn-dark border border-yellow-200" : "bg-muted/30 text-muted-foreground"
                        )}>
                          {comment.message}
                        </div>
                      </div>
                    </div>
                  ))}
                  <div className="relative pl-6 border-l border-border ml-2">
                    <div className="absolute -left-[9px] top-0 w-4 h-4 rounded-full border-2 border-white bg-blue-500 flex items-center justify-center">
                      <CheckCircle2 className="w-2 h-2 text-white" />
                    </div>
                    <div className="flex flex-col gap-1">
                      <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold text-sn-dark italic">Ticket Created</span>
                        <span className="text-[10px] text-muted-foreground">{formatDate(ticket.createdAt)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ) : activeTab === "Related Records" ? (
            <div className="space-y-8 animate-in fade-in duration-300">
              {/* Task SLAs Section */}
              <div className="space-y-4">
                <div className="flex items-center justify-between border-b border-border pb-2">
                  <div className="flex items-center gap-2">
                    <Clock className="w-3.5 h-3.5 text-muted-foreground" />
                    <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Task SLAs</h3>
                  </div>
                  <button className="text-[10px] font-bold text-sn-green uppercase hover:underline">New</button>
                </div>
                <div className="overflow-x-auto rounded-md border border-border">
                  <table className="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr className="bg-muted/20 border-b border-border text-[10px] uppercase text-muted-foreground font-bold">
                        <th className="px-4 py-2.5">SLA</th>
                        <th className="px-4 py-2.5">Stage</th>
                        <th className="px-4 py-2.5">Breach time</th>
                        <th className="px-4 py-2.5">Business elapsed time</th>
                        <th className="px-4 py-2.5">Actual elapsed time</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      <tr className="hover:bg-muted/10 transition-colors">
                        <td className="px-4 py-3 font-medium text-sn-dark">Response SLA</td>
                        <td className="px-4 py-3">
                          <span className={cn(
                            "px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-tighter",
                            ticket.responseSlaStatus === "Completed" ? "bg-green-100 text-green-700" : "bg-blue-100 text-blue-700"
                          )}>
                            {ticket.responseSlaStatus || "In Progress"}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono">{formatDate(ticket.responseDeadline)}</td>
                        <td className="px-4 py-3 text-muted-foreground">
                          <SLATimer 
                            label="Resp" 
                            deadline={ticket.responseDeadline} 
                            metAt={ticket.firstResponseAt} 
                            isPaused={ticket.status === "On Hold" || ticket.status === "Waiting for Customer"}
                            onHoldStart={ticket.onHoldStart}
                            totalPausedTime={ticket.totalPausedTime}
                          />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono">{ticket.firstResponseAt ? formatDate(ticket.firstResponseAt) : "—"}</td>
                      </tr>
                      <tr className="hover:bg-muted/10 transition-colors">
                        <td className="px-4 py-3 font-medium text-sn-dark">Resolution SLA</td>
                        <td className="px-4 py-3">
                          <span className={cn(
                            "px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-tighter",
                            ticket.resolutionSlaStatus === "Completed" ? "bg-green-100 text-green-700" : "bg-blue-100 text-blue-700"
                          )}>
                            {ticket.resolutionSlaStatus || "In Progress"}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono">{formatDate(ticket.resolutionDeadline)}</td>
                        <td className="px-4 py-3 text-muted-foreground">
                          <SLATimer 
                            label="Res" 
                            deadline={ticket.resolutionDeadline} 
                            metAt={ticket.resolvedAt} 
                            isPaused={ticket.status === "On Hold" || ticket.status === "Waiting for Customer"}
                            onHoldStart={ticket.onHoldStart}
                            totalPausedTime={ticket.totalPausedTime}
                            waitUntil={ticket.firstResponseAt ?? null}
                          />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono">{ticket.resolvedAt ? formatDate(ticket.resolvedAt) : "—"}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Other Related Records */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div className="space-y-4">
                  <div className="flex items-center justify-between border-b border-border pb-2">
                    <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Parent Incident</h3>
                  </div>
                  <div className="p-4 bg-muted/10 rounded border border-border border-dashed text-center">
                    <p className="text-[10px] text-muted-foreground uppercase font-bold">No Parent Incident</p>
                  </div>
                </div>
                <div className="space-y-4">
                  <div className="flex items-center justify-between border-b border-border pb-2">
                    <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Child Incidents</h3>
                  </div>
                  <div className="p-4 bg-muted/10 rounded border border-border border-dashed text-center">
                    <p className="text-[10px] text-muted-foreground uppercase font-bold">No Child Incidents</p>
                  </div>
                </div>
              </div>
            </div>
          ) : (
            <div className="animate-in fade-in duration-300">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-6">
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase">Knowledge</label>
                    <div className="col-span-2 flex items-center gap-2">
                      <input type="checkbox" className="w-3.5 h-3.5 rounded" />
                      <span className="text-[10px] text-muted-foreground uppercase font-bold">Knowledge base</span>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase">Resolution code</label>
                    <select 
                      value={editedTicket?.resolutionCode || ""} 
                      onChange={(e) => updateLocalField("resolutionCode", e.target.value)}
                      className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                    >
                      <option value="">-- None --</option>
                      <option value="solved_workaround">Solved (Workaround)</option>
                      <option value="solved_permanent">Solved (Permanent Fix)</option>
                      <option value="solved_remote">Solved Remotely</option>
                      <option value="not_solved">Not Solved (Duplicate/Cancelled)</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-start gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase mt-1.5">Resolution notes</label>
                    <textarea 
                      value={editedTicket?.resolutionNotes || ""} 
                      onChange={(e) => updateLocalField("resolutionNotes", e.target.value)}
                      className="col-span-2 p-2 border border-border rounded text-xs outline-none min-h-[100px] resize-none" 
                      placeholder="Enter resolution details..."
                    />
                  </div>
                </div>
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase">Resolved by</label>
                    <input readOnly value={ticket.resolvedBy || profile?.name || "-"} className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs" />
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase">Resolved at</label>
                    <input readOnly value={formatDate(ticket.resolvedAt)} className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono" />
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
