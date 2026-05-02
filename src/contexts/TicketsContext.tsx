import React, { createContext, useContext, useEffect, useState } from "react";
import { collection, onSnapshot, query, where } from "firebase/firestore";
import { db } from "../lib/firebase";
import { useAuth } from "./AuthContext";

interface Ticket {
  id: string;
  title: string;
  status: string;
  priority: string;
  assignedTo?: string;
  createdBy: string;
  createdAt: any;
  updatedAt: any;
}

interface TicketsContextType {
  tickets: Ticket[];
  openTicketsCount: number;
  loading: boolean;
  error: string | null;
}

const TicketsContext = createContext<TicketsContextType | undefined>(undefined);

export function useTickets() {
  const context = useContext(TicketsContext);
  if (context === undefined) {
    throw new Error("useTickets must be used within a TicketsProvider");
  }
  return context;
}

export function TicketsProvider({ children }: { children: React.ReactNode }) {
  const { user, profile } = useAuth();
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user || !profile) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    const ticketsRef = collection(db, "tickets");
    
    let q;
    const isAgent = profile.role === "agent" || profile.role === "admin" || profile.role === "super_admin" || profile.role === "ultra_super_admin";
    
    if (isAgent) {
      q = query(ticketsRef, where("status", "not-in", ["Resolved", "Closed"]));
    } else {
      q = query(
        ticketsRef, 
        where("createdBy", "==", user.uid),
        where("status", "not-in", ["Resolved", "Closed"])
      );
    }

    const unsubscribe = onSnapshot(
      q,
      (snapshot) => {
        const ticketsData = snapshot.docs.map(d => ({ id: d.id, ...d.data() } as Ticket));
        setTickets(ticketsData);
        setLoading(false);
      },
      (err) => {
        console.error("Error fetching tickets:", err);
        setError(err.message);
        setLoading(false);
      }
    );

    return unsubscribe;
  }, [user, profile]);

  const openTicketsCount = tickets.length;

  const value: TicketsContextType = {
    tickets,
    openTicketsCount,
    loading,
    error,
  };

  return (
    <TicketsContext.Provider value={value}>
      {children}
    </TicketsContext.Provider>
  );
}
