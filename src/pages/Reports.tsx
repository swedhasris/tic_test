import React, { useEffect, useState } from "react";
import { collection, query, onSnapshot, where } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { ROLE_HIERARCHY, Role } from "../lib/roles";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { Map } from "lucide-react";
import { ChartPanel } from "../components/ChartPanel";

export function Reports() {
  const { user, profile } = useAuth();
  const [data, setData] = useState<any[]>([]);
  const [categoryData, setCategoryData] = useState<any[]>([]);
  const [slaData, setSlaData] = useState<any[]>([]);

  useEffect(() => {
    if (!user || !profile) return;

    const isAgent = ROLE_HIERARCHY[profile.role as Role] >= ROLE_HIERARCHY["agent"];
    const ticketsRef = collection(db, "tickets");
    const q = isAgent
      ? query(ticketsRef)
      : query(ticketsRef, where("createdBy", "==", user.uid));

    const unsubscribe = onSnapshot(q, (snapshot) => {
      const tickets = snapshot.docs.map((doc) => doc.data());

      const statusCounts: Record<string, number> = {};
      tickets.forEach((ticket: any) => {
        statusCounts[ticket.status] = (statusCounts[ticket.status] || 0) + 1;
      });
      setData(Object.keys(statusCounts).map((status) => ({ name: status, count: statusCounts[status] })));

      const categoryCounts: Record<string, number> = {};
      tickets.forEach((ticket: any) => {
        categoryCounts[ticket.category] = (categoryCounts[ticket.category] || 0) + 1;
      });
      setCategoryData(Object.keys(categoryCounts).map((category) => ({ name: category, value: categoryCounts[category] })));

      const slaCounts = { "Within SLA": 0, "At Risk": 0, "Breached": 0 };
      tickets.forEach((ticket: any) => {
        const resolutionStatus = ticket.resolutionSlaStatus || "In Progress";
        const responseStatus = ticket.responseSlaStatus || "In Progress";

        if (resolutionStatus === "Breached" || responseStatus === "Breached") {
          slaCounts["Breached"]++;
        } else if (resolutionStatus === "At Risk" || responseStatus === "At Risk") {
          slaCounts["At Risk"]++;
        } else {
          slaCounts["Within SLA"]++;
        }
      });
      setSlaData([
        { name: "Within SLA", value: slaCounts["Within SLA"] },
        { name: "At Risk", value: slaCounts["At Risk"] },
        { name: "Breached", value: slaCounts["Breached"] },
      ]);
    }, (error) => {
      handleFirestoreError(error, OperationType.LIST, "tickets");
    });

    return unsubscribe;
  }, [profile, user]);

  const colors = ["#81B532", "#151B26", "#3b82f6", "#ef4444", "#f59e0b"];
  const slaColors = ["#81B532", "#f59e0b", "#ef4444"];
  const slaChartData = slaData.some((item) => item.value > 0) ? slaData : [{ name: "No Data", value: 1 }];

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Reports & Analytics</h1>
        <p className="text-muted-foreground">Visual insights into service desk performance.</p>
      </div>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
        <div className="sn-card">
          <h3 className="mb-6 text-lg font-bold">SLA Compliance Rate</h3>
          <ChartPanel className="relative h-64 w-full min-w-0">
            <ResponsiveContainer width="100%" height="100%" minWidth={1} minHeight={1}>
              <PieChart>
                <Pie
                  data={slaChartData}
                  cx="50%"
                  cy="50%"
                  innerRadius={75}
                  outerRadius={105}
                  paddingAngle={slaData.some((item) => item.value > 0) ? 3 : 0}
                  dataKey="value"
                  startAngle={90}
                  endAngle={-270}
                >
                  {slaChartData.map((entry, index) => (
                    <Cell
                      key={`${entry.name}-${index}`}
                      fill={slaData.some((item) => item.value > 0) ? slaColors[index % slaColors.length] : "#e2e8f0"}
                    />
                  ))}
                </Pie>
                <Tooltip
                  formatter={(value: any, name: any) => [`${value} tickets`, name]}
                  contentStyle={{ borderRadius: "8px", border: "1px solid #e2e8f0", fontSize: "12px" }}
                />
              </PieChart>
            </ResponsiveContainer>
            <div className="pointer-events-none absolute inset-0 flex select-none flex-col items-center justify-center">
              <span className="text-4xl font-bold leading-none text-sn-dark dark:text-white">
                {(() => {
                  const total = slaData.reduce((sum, item) => sum + item.value, 0);
                  const withinSla = slaData[0]?.value ?? 0;
                  return total > 0 ? Math.round((withinSla / total) * 100) : 0;
                })()}%
              </span>
              <span className="mt-1 text-xs font-medium text-muted-foreground">Compliance</span>
            </div>
          </ChartPanel>
          <div className="mt-4 flex justify-center gap-6">
            {slaData.map((entry, index) => (
              <div key={entry.name} className="flex items-center gap-2">
                <div className="h-3 w-3 flex-shrink-0 rounded-full" style={{ backgroundColor: slaColors[index % slaColors.length] }} />
                <span className="text-sm font-medium text-foreground">{entry.name}:&nbsp;<strong>{entry.value}</strong></span>
              </div>
            ))}
          </div>
        </div>

        <div className="sn-card">
          <h3 className="mb-6 text-lg font-bold">Ticket Status Distribution</h3>
          <ChartPanel className="h-80 w-full min-w-0">
            <ResponsiveContainer width="100%" height="100%" minWidth={1} minHeight={1}>
              <BarChart data={data}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" fontSize={12} />
                <YAxis fontSize={12} />
                <Tooltip
                  contentStyle={{ backgroundColor: "#fff", borderRadius: "8px", border: "1px solid #e2e8f0" }}
                />
                <Bar dataKey="count" fill="#81B532" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </ChartPanel>
        </div>

        <div className="sn-card">
          <h3 className="mb-6 text-lg font-bold">Tickets by Category</h3>
          <ChartPanel className="flex h-80 w-full min-w-0 items-center justify-center">
            <ResponsiveContainer width="100%" height="100%" minWidth={1} minHeight={1}>
              <PieChart>
                <Pie
                  data={categoryData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={100}
                  paddingAngle={5}
                  dataKey="value"
                >
                  {categoryData.map((entry, index) => (
                    <Cell key={`${entry.name}-${index}`} fill={colors[index % colors.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </ChartPanel>
          <div className="mt-4 flex flex-wrap justify-center gap-4">
            {categoryData.map((entry, index) => (
              <div key={entry.name} className="flex items-center gap-2">
                <div className="h-3 w-3 rounded-full" style={{ backgroundColor: colors[index % colors.length] }} />
                <span className="text-xs font-medium">{entry.name}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="sn-card lg:col-span-2">
          <h3 className="mb-6 text-lg font-bold">Critical Incidents Map</h3>
          <div className="flex h-96 w-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30 text-muted-foreground">
            <Map className="mb-4 h-12 w-12 opacity-20" />
            <p className="font-medium">Geospatial Incident Visualization</p>
            <p className="text-xs">Integration with Google Maps API pending configuration.</p>
          </div>
        </div>
      </div>
    </div>
  );
}
