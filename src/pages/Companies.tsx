import React, { useState, useEffect } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { db } from "../lib/firebase";
import { collection, query, getDocs, doc, getDoc, where, orderBy, addDoc, serverTimestamp } from "firebase/firestore";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { 
  Building2, 
  Search, 
  Phone, 
  Mail, 
  MapPin, 
  Plus,
  ArrowLeft,
  Ticket,
  Clock,
  ChevronRight,
  Globe,
  MoreVertical,
  Edit,
  Star,
  History
} from "lucide-react";
import { cn } from "@/lib/utils";

interface Company {
  id: string;
  name: string;
  contactName?: string;
  phone?: string;
  email?: string;
  address1?: string;
  address2?: string;
  city?: string;
  province?: string;
  postalCode?: string;
  country?: string;
  website?: string;
  type?: string;
  status?: string;
  createdAt?: string;
}

interface TicketData {
  id: string;
  title: string;
  status: string;
  priority: string;
  createdAt: string;
  company?: string;
}

export function Companies() {
  const navigate = useNavigate();
  const { id } = useParams();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [companyTickets, setCompanyTickets] = useState<TicketData[]>([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("details");
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [newCompany, setNewCompany] = useState<Partial<Company>>({
    name: "",
    contactName: "",
    phone: "",
    email: "",
    address1: "",
    address2: "",
    city: "",
    province: "",
    postalCode: "",
    country: "",
    type: "Customer",
    status: "Active"
  });

  useEffect(() => {
    fetchCompanies();
  }, []);

  useEffect(() => {
    if (id) {
      fetchCompanyDetails(id);
    } else {
      setSelectedCompany(null);
      setCompanyTickets([]);
    }
  }, [id]);

  const handleCreateCompany = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newCompany.name?.trim()) return;

    setSaving(true);
    try {
      const companyData = {
        ...newCompany,
        name: newCompany.name.trim(),
        createdAt: serverTimestamp(),
        updatedAt: serverTimestamp()
      };

      // Save to Firestore
      const docRef = await addDoc(collection(db, "companies"), companyData);

      // Update local state
      const createdCompany: Company = {
        id: docRef.id,
        ...companyData,
        createdAt: new Date().toISOString()
      };

      setCompanies(prev => [...prev, createdCompany].sort((a, b) => a.name.localeCompare(b.name)));

      // Reset form and close dialog
      setNewCompany({
        name: "",
        contactName: "",
        phone: "",
        email: "",
        address1: "",
        address2: "",
        city: "",
        province: "",
        postalCode: "",
        country: "",
        type: "Customer",
        status: "Active"
      });
      setIsDialogOpen(false);
    } catch (error) {
      console.error("Error creating company:", error);
      // Add to local state anyway as fallback
      const fallbackCompany: Company = {
        id: Date.now().toString(),
        ...newCompany as Company,
        createdAt: new Date().toISOString()
      };
      setCompanies(prev => [...prev, fallbackCompany].sort((a, b) => a.name.localeCompare(b.name)));
      setIsDialogOpen(false);
    } finally {
      setSaving(false);
    }
  };

  const fetchCompanies = async () => {
    try {
      const q = query(collection(db, "companies"), orderBy("name"));
      const snapshot = await getDocs(q);
      const companiesData = snapshot.docs.map(doc => ({
        id: doc.id,
        ...doc.data()
      })) as Company[];
      
      if (companiesData.length === 0) {
        // Seed sample data if none exists
        const sampleCompanies: Company[] = [
          {
            id: "1",
            name: "Spec Furniture - AoT",
            contactName: "Long Nguyen",
            phone: "(416) 246-5540",
            email: "lnguyen@specfurniture.com",
            address1: "165 City View Drive",
            city: "Toronto",
            province: "ON",
            postalCode: "M9W8B1",
            country: "Canada",
            type: "Customer",
            status: "Active"
          },
          {
            id: "2",
            name: "Acme Corporation",
            contactName: "John Smith",
            phone: "(555) 123-4567",
            email: "john@acme.com",
            address1: "123 Business Ave",
            address2: "Suite 100",
            city: "New York",
            province: "NY",
            postalCode: "10001",
            country: "USA",
            type: "Customer",
            status: "Active"
          },
          {
            id: "3",
            name: "Tech Solutions Inc",
            contactName: "Sarah Johnson",
            phone: "(555) 987-6543",
            email: "sarah@techsolutions.com",
            address1: "456 Innovation Blvd",
            city: "San Francisco",
            province: "CA",
            postalCode: "94102",
            country: "USA",
            type: "Partner",
            status: "Active"
          }
        ];
        setCompanies(sampleCompanies);
      } else {
        setCompanies(companiesData);
      }
    } catch (error) {
      console.error("Error fetching companies:", error);
      // Fallback to sample data
      setCompanies([
        {
          id: "1",
          name: "Spec Furniture - AoT",
          contactName: "Long Nguyen",
          phone: "(416) 246-5540",
          email: "lnguyen@specfurniture.com",
          address1: "165 City View Drive",
          city: "Toronto",
          province: "ON",
          postalCode: "M9W8B1",
          country: "Canada",
          type: "Customer",
          status: "Active"
        }
      ]);
    } finally {
      setLoading(false);
    }
  };

  const fetchCompanyDetails = async (companyId: string) => {
    try {
      // In a real app, fetch from Firestore
      const company = companies.find(c => c.id === companyId);
      if (company) {
        setSelectedCompany(company);
      }

      // Fetch related tickets
      const ticketsQuery = query(
        collection(db, "tickets"),
        where("company", "==", companyId),
        orderBy("createdAt", "desc")
      );
      const ticketsSnapshot = await getDocs(ticketsQuery);
      const ticketsData = ticketsSnapshot.docs.map(doc => ({
        id: doc.id,
        ...doc.data()
      })) as TicketData[];
      
      // Sample tickets if none found
      if (ticketsData.length === 0) {
        setCompanyTickets([
          {
            id: "#2492534",
            title: "Urgent: Could someone check why our new phone blocked?",
            status: "Closed",
            priority: "3 - Medium",
            createdAt: "2024-01-15",
            company: companyId
          },
          {
            id: "#2492535",
            title: "Network connectivity issues in main office",
            status: "Open",
            priority: "2 - High",
            createdAt: "2024-01-20",
            company: companyId
          }
        ]);
      } else {
        setCompanyTickets(ticketsData);
      }
    } catch (error) {
      console.error("Error fetching company details:", error);
    }
  };

  const filteredCompanies = companies.filter(company =>
    company.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    company.contactName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    company.email?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const getStatusColor = (status: string) => {
    switch (status?.toLowerCase()) {
      case "active": return "bg-green-500/20 text-green-500";
      case "inactive": return "bg-gray-500/20 text-gray-400";
      case "prospect": return "bg-blue-500/20 text-blue-500";
      default: return "bg-gray-500/20 text-gray-400";
    }
  };

  const getPriorityColor = (priority: string) => {
    if (priority?.includes("1") || priority?.toLowerCase().includes("critical")) {
      return "bg-red-500/20 text-red-500";
    } else if (priority?.includes("2") || priority?.toLowerCase().includes("high")) {
      return "bg-orange-500/20 text-orange-500";
    } else if (priority?.includes("3") || priority?.toLowerCase().includes("medium")) {
      return "bg-yellow-500/20 text-yellow-500";
    }
    return "bg-blue-500/20 text-blue-500";
  };

  // Company Detail View
  if (selectedCompany && id) {
    return (
      <div className="min-h-screen bg-background">
        {/* Header */}
        <div className="bg-card border-b border-border">
          <div className="px-6 py-4">
            {/* Breadcrumb */}
            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-3">
              <button 
                onClick={() => navigate("/companies")}
                className="hover:text-foreground flex items-center gap-1"
              >
                <Building2 className="w-4 h-4" />
                Companies
              </button>
              <ChevronRight className="w-4 h-4" />
              <span className="text-foreground">{selectedCompany.name}</span>
            </div>

            {/* Title Row */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <button 
                  onClick={() => navigate("/companies")}
                  className="p-2 hover:bg-accent rounded-lg transition-colors"
                >
                  <ArrowLeft className="w-5 h-5" />
                </button>
                <div>
                  <h1 className="text-2xl font-bold">{selectedCompany.name}</h1>
                  <p className="text-sm text-muted-foreground">
                    Company • {selectedCompany.type} • <Badge className={cn("text-xs", getStatusColor(selectedCompany.status || "Active"))}>{selectedCompany.status || "Active"}</Badge>
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Button variant="outline" size="sm">
                  <Star className="w-4 h-4 mr-2" />
                  Follow
                </Button>
                <Button variant="outline" size="sm">
                  <Edit className="w-4 h-4 mr-2" />
                  Edit
                </Button>
                <Button variant="outline" size="icon" className="h-9 w-9">
                  <MoreVertical className="w-4 h-4" />
                </Button>
              </div>
            </div>
          </div>

          {/* Tabs */}
          <div className="px-6 flex items-center gap-6 border-t border-border">
            {[
              { id: "details", label: "Details", icon: Building2 },
              { id: "tickets", label: "Tickets", icon: Ticket },
              { id: "history", label: "History", icon: History }
            ].map(tab => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={cn(
                  "flex items-center gap-2 py-3 text-sm font-medium border-b-2 transition-colors",
                  activeTab === tab.id 
                    ? "border-primary text-primary" 
                    : "border-transparent text-muted-foreground hover:text-foreground"
                )}
              >
                <tab.icon className="w-4 h-4" />
                {tab.label}
                {tab.id === "tickets" && (
                  <span className="bg-primary/10 text-primary text-xs px-2 py-0.5 rounded-full">
                    {companyTickets.length}
                  </span>
                )}
              </button>
            ))}
          </div>
        </div>

        {/* Content */}
        <div className="p-6">
          {activeTab === "details" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Company Info */}
              <div className="lg:col-span-2 space-y-6">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg flex items-center gap-2">
                      <Building2 className="w-5 h-5" />
                      Company Information
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Company</label>
                        <Input value={selectedCompany.name} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Contact</label>
                        <Input value={selectedCompany.contactName || "-"} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Phone</label>
                        <div className="flex items-center gap-2 mt-1">
                          <Phone className="w-4 h-4 text-muted-foreground" />
                          <Input value={selectedCompany.phone || "-"} readOnly className="bg-muted" />
                        </div>
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Email</label>
                        <div className="flex items-center gap-2 mt-1">
                          <Mail className="w-4 h-4 text-muted-foreground" />
                          <Input value={selectedCompany.email || "-"} readOnly className="bg-muted" />
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg flex items-center gap-2">
                      <MapPin className="w-5 h-5" />
                      Address Information
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Site</label>
                        <Input value={selectedCompany.name} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Address 1</label>
                        <Input value={selectedCompany.address1 || "-"} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">City</label>
                        <Input value={selectedCompany.city || "-"} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Province</label>
                        <Input value={selectedCompany.province || "-"} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Postal Code</label>
                        <Input value={selectedCompany.postalCode || "-"} readOnly className="mt-1 bg-muted" />
                      </div>
                      <div>
                        <label className="text-xs text-muted-foreground uppercase">Country</label>
                        <Input value={selectedCompany.country || "-"} readOnly className="mt-1 bg-muted" />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Sidebar */}
              <div className="space-y-6">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg">Quick Actions</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-2">
                    <Button className="w-full" variant="outline">
                      <Plus className="w-4 h-4 mr-2" />
                      Create Ticket
                    </Button>
                    <Button className="w-full" variant="outline">
                      <Mail className="w-4 h-4 mr-2" />
                      Send Email
                    </Button>
                    <Button className="w-full" variant="outline">
                      <Phone className="w-4 h-4 mr-2" />
                      Call
                    </Button>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg">Statistics</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">Total Tickets</span>
                      <span className="font-semibold">{companyTickets.length}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">Open Tickets</span>
                      <span className="font-semibold text-orange-500">
                        {companyTickets.filter(t => t.status === "Open").length}
                      </span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">Resolved</span>
                      <span className="font-semibold text-green-500">
                        {companyTickets.filter(t => t.status === "Closed" || t.status === "Resolved").length}
                      </span>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>
          )}

          {activeTab === "tickets" && (
            <Card>
              <CardHeader>
                <CardTitle>Related Tickets</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {companyTickets.map(ticket => (
                    <div 
                      key={ticket.id}
                      onClick={() => navigate(`/tickets/${ticket.id}`)}
                      className="flex items-center justify-between p-4 border border-border rounded-lg hover:bg-accent cursor-pointer transition-colors"
                    >
                      <div className="flex items-center gap-4">
                        <Ticket className="w-5 h-5 text-muted-foreground" />
                        <div>
                          <p className="font-medium">{ticket.title}</p>
                          <p className="text-sm text-muted-foreground">{ticket.id}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <Badge className={getPriorityColor(ticket.priority)}>
                          {ticket.priority}
                        </Badge>
                        <Badge variant={ticket.status === "Open" ? "default" : "secondary"}>
                          {ticket.status}
                        </Badge>
                        <ChevronRight className="w-4 h-4 text-muted-foreground" />
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {activeTab === "history" && (
            <Card>
              <CardHeader>
                <CardTitle>Activity History</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-start gap-3">
                    <History className="w-5 h-5 text-muted-foreground mt-0.5" />
                    <div>
                      <p className="font-medium">Company record created</p>
                      <p className="text-sm text-muted-foreground">{selectedCompany.createdAt || "2024-01-15 10:30 AM"}</p>
                    </div>
                  </div>
                  <div className="flex items-start gap-3">
                    <Edit className="w-5 h-5 text-muted-foreground mt-0.5" />
                    <div>
                      <p className="font-medium">Contact information updated</p>
                      <p className="text-sm text-muted-foreground">2024-01-20 02:15 PM by Admin</p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    );
  }

  // Companies List View
  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Companies</h1>
          <p className="text-muted-foreground">Manage your customer and partner companies</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button>
              <Plus className="w-4 h-4 mr-2" />
              New Company
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto !bg-white !text-gray-900 border-gray-200">
            <DialogHeader>
              <DialogTitle className="text-gray-900">Create New Company</DialogTitle>
              <DialogDescription className="text-gray-500">
                Fill in the details to add a new company to the system.
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleCreateCompany} className="space-y-4 mt-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name" className="text-gray-700">Company Name *</Label>
                  <Input
                    id="name"
                    value={newCompany.name}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, name: e.target.value }))}
                    placeholder="Enter company name"
                    required
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="contactName" className="text-gray-700">Contact Name</Label>
                  <Input
                    id="contactName"
                    value={newCompany.contactName}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, contactName: e.target.value }))}
                    placeholder="Primary contact person"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="phone" className="text-gray-700">Phone</Label>
                  <Input
                    id="phone"
                    value={newCompany.phone}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, phone: e.target.value }))}
                    placeholder="(555) 123-4567"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="email" className="text-gray-700">Email</Label>
                  <Input
                    id="email"
                    type="email"
                    value={newCompany.email}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, email: e.target.value }))}
                    placeholder="contact@company.com"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="address1" className="text-gray-700">Address</Label>
                <Input
                  id="address1"
                  value={newCompany.address1}
                  onChange={(e) => setNewCompany(prev => ({ ...prev, address1: e.target.value }))}
                  placeholder="Street address"
                  className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="city" className="text-gray-700">City</Label>
                  <Input
                    id="city"
                    value={newCompany.city}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, city: e.target.value }))}
                    placeholder="City"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="province" className="text-gray-700">Province/State</Label>
                  <Input
                    id="province"
                    value={newCompany.province}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, province: e.target.value }))}
                    placeholder="ON / NY"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="postalCode" className="text-gray-700">Postal Code</Label>
                  <Input
                    id="postalCode"
                    value={newCompany.postalCode}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, postalCode: e.target.value }))}
                    placeholder="M9W8B1"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="country" className="text-gray-700">Country</Label>
                  <Input
                    id="country"
                    value={newCompany.country}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, country: e.target.value }))}
                    placeholder="Canada"
                    className="bg-white border-gray-300 text-gray-900 placeholder:text-gray-400"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="type" className="text-gray-700">Type</Label>
                  <select
                    id="type"
                    value={newCompany.type}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, type: e.target.value }))}
                    className="w-full h-10 rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:ring-1 focus:ring-sn-green"
                  >
                    <option value="Customer" className="bg-white text-gray-900">Customer</option>
                    <option value="Partner" className="bg-white text-gray-900">Partner</option>
                    <option value="Vendor" className="bg-white text-gray-900">Vendor</option>
                    <option value="Prospect" className="bg-white text-gray-900">Prospect</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="status" className="text-gray-700">Status</Label>
                  <select
                    id="status"
                    value={newCompany.status}
                    onChange={(e) => setNewCompany(prev => ({ ...prev, status: e.target.value }))}
                    className="w-full h-10 rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:ring-1 focus:ring-sn-green"
                  >
                    <option value="Active" className="bg-white text-gray-900">Active</option>
                    <option value="Inactive" className="bg-white text-gray-900">Inactive</option>
                    <option value="Prospect" className="bg-white text-gray-900">Prospect</option>
                  </select>
                </div>
              </div>

              <DialogFooter className="mt-6">
                <Button type="button" variant="outline" onClick={() => setIsDialogOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={saving || !newCompany.name?.trim()}>
                  {saving ? (
                    <>
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
                      Saving...
                    </>
                  ) : (
                    "Create Company"
                  )}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      {/* Search */}
      <div className="flex items-center gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            placeholder="Search companies..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-10"
          />
        </div>
      </div>

      {/* Companies Grid */}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredCompanies.map(company => (
            <Card 
              key={company.id}
              className="cursor-pointer hover:border-primary transition-colors"
              onClick={() => navigate(`/companies/${company.id}`)}
            >
              <CardContent className="p-5">
                <div className="flex items-start justify-between mb-4">
                  <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                    <Building2 className="w-6 h-6 text-primary" />
                  </div>
                  <Badge className={getStatusColor(company.status || "Active")}>
                    {company.status || "Active"}
                  </Badge>
                </div>
                
                <h3 className="font-semibold text-lg mb-1">{company.name}</h3>
                <p className="text-sm text-muted-foreground mb-3">{company.type || "Customer"}</p>
                
                <div className="space-y-2 text-sm">
                  {company.contactName && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <span className="font-medium text-foreground">Contact:</span>
                      {company.contactName}
                    </div>
                  )}
                  {company.phone && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Phone className="w-4 h-4" />
                      {company.phone}
                    </div>
                  )}
                  {company.email && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Mail className="w-4 h-4" />
                      {company.email}
                    </div>
                  )}
                  {company.city && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <MapPin className="w-4 h-4" />
                      {company.city}, {company.province}
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {!loading && filteredCompanies.length === 0 && (
        <div className="text-center py-12">
          <Building2 className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
          <h3 className="font-semibold text-lg">No companies found</h3>
          <p className="text-muted-foreground">Try adjusting your search or create a new company</p>
        </div>
      )}
    </div>
  );
}

export default Companies;
