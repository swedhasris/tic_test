import React from "react";
import { Bell, Search, User, Sun, Moon, Monitor } from "lucide-react";
import { useAuth } from "../contexts/AuthContext";
import { useTheme } from "../contexts/ThemeContext";

export function AppNavbar() {
  const { profile } = useAuth();
  const { theme, setTheme, resolvedTheme } = useTheme();

  return (
    <header className="h-16 bg-background border-b border-border flex items-center justify-between gap-3 px-3 sm:px-5 lg:px-8 sticky top-0 z-10">
      <div className="flex min-w-0 flex-1 items-center gap-3 bg-muted/50 px-3 sm:px-4 py-2 rounded-md max-w-xs sm:max-w-sm lg:max-w-md">
        <Search className="w-4 h-4 text-muted-foreground" />
        <input 
          type="text" 
          placeholder="Search tickets, users..." 
          className="bg-transparent border-none outline-none text-sm w-full min-w-0"
        />
      </div>

      <div className="flex shrink-0 items-center gap-2 sm:gap-4 lg:gap-6">
        {/* Theme Toggle */}
        <div className="flex items-center gap-1 bg-muted/50 rounded-lg p-1">
          <button
            onClick={() => setTheme("light")}
            className={`p-1.5 rounded-md transition-colors ${theme === "light" ? "bg-white shadow-sm text-sn-green" : "text-muted-foreground hover:text-foreground"}`}
            title="Light mode"
          >
            <Sun className="w-4 h-4" />
          </button>
          <button
            onClick={() => setTheme("dark")}
            className={`p-1.5 rounded-md transition-colors ${theme === "dark" ? "bg-white shadow-sm text-sn-green" : "text-muted-foreground hover:text-foreground"}`}
            title="Dark mode"
          >
            <Moon className="w-4 h-4" />
          </button>
          <button
            onClick={() => setTheme("system")}
            className={`p-1.5 rounded-md transition-colors ${theme === "system" ? "bg-white shadow-sm text-sn-green" : "text-muted-foreground hover:text-foreground"}`}
            title="System preference"
          >
            <Monitor className="w-4 h-4" />
          </button>
        </div>

        <button className="relative text-muted-foreground hover:text-foreground transition-colors">
          <Bell className="w-5 h-5" />
          <span className="absolute -top-1 -right-1 w-2 h-2 bg-destructive rounded-full" />
        </button>
        
        <div className="flex items-center gap-3 pl-3 sm:pl-6 border-l border-border">
          <div className="text-right hidden sm:block">
            <div className="text-sm font-semibold">{profile?.name || "User"}</div>
            <div className="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{profile?.role || "Guest"}</div>
          </div>
          <div className="w-10 h-10 bg-muted rounded-full flex items-center justify-center">
            <User className="w-6 h-6 text-muted-foreground" />
          </div>
        </div>
      </div>
    </header>
  );
}
