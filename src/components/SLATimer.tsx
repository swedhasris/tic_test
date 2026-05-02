import React, { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

interface SLATimerProps {
  label: string;
  deadline: string;
  metAt?: string;
  isPaused?: boolean;
  onHoldStart?: string;
  totalPausedTime?: number;
  waitUntil?: string | null;
}

export function SLATimer({
  label,
  deadline,
  metAt,
  isPaused = false,
  onHoldStart,
  totalPausedTime = 0,
  waitUntil,
}: SLATimerProps) {
  const [displayTime, setDisplayTime] = useState("");
  const [status, setStatus] = useState<"waiting" | "met" | "breached" | "active" | "paused">("active");
  const [percentage, setPercentage] = useState(0);

  useEffect(() => {
    // SLA already met — freeze the display
    if (metAt) {
      setStatus("met");
      setDisplayTime("MET ✓");
      setPercentage(100);
      return;
    }

    // Resolution SLA: waiting for first response before starting
    if (waitUntil !== undefined && (waitUntil === null || waitUntil === "")) {
      setStatus("waiting");
      setDisplayTime("—");
      return;
    }

    const deadlineMs = new Date(deadline).getTime();
    if (isNaN(deadlineMs)) {
      setDisplayTime("--:--:--");
      return;
    }

    const tick = () => {
      const now = Date.now();
      const effectiveNow =
        isPaused && onHoldStart
          ? new Date(onHoldStart).getTime()
          : now;

      const diff = deadlineMs - effectiveNow + (totalPausedTime || 0);

      if (diff <= 0) {
        setStatus("breached");
        const over = Math.abs(diff);
        const h = Math.floor(over / 3_600_000);
        const m = Math.floor((over % 3_600_000) / 60_000);
        const s = Math.floor((over % 60_000) / 1_000);
        setDisplayTime(
          `-${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`
        );
        setPercentage(100);
      } else {
        setStatus(isPaused ? "paused" : "active");
        const h = Math.floor(diff / 3_600_000);
        const m = Math.floor((diff % 3_600_000) / 60_000);
        const s = Math.floor((diff % 60_000) / 1_000);
        setDisplayTime(
          `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`
        );
        // rough % used — not perfect without startTime, but good enough for UI
        const rough = Math.min(Math.max(((24 * 3_600_000 - diff) / (24 * 3_600_000)) * 100, 0), 99);
        setPercentage(rough);
      }
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [deadline, metAt, isPaused, onHoldStart, totalPausedTime, waitUntil]);

  const barColor =
    status === "met"
      ? "bg-green-500"
      : status === "breached"
      ? "bg-red-500"
      : status === "paused"
      ? "bg-orange-400"
      : "bg-sn-green";

  const textColor =
    status === "met"
      ? "text-green-600"
      : status === "breached"
      ? "text-red-600"
      : status === "paused"
      ? "text-orange-500"
      : status === "waiting"
      ? "text-gray-400"
      : "text-blue-600";

  return (
    <div className="flex flex-col gap-0.5 min-w-[90px]">
      <div className="flex items-center justify-between gap-1">
        <span className="text-[9px] uppercase text-muted-foreground font-bold leading-none">
          {label}
        </span>
        {status === "paused" && (
          <span className="text-[8px] font-black text-orange-500 uppercase animate-pulse">
            PAUSED
          </span>
        )}
      </div>
      <span
        className={cn(
          "text-[13px] font-mono font-bold leading-tight tracking-tight",
          textColor
        )}
      >
        {displayTime}
      </span>
      {/* Progress bar */}
      <div className="w-full h-1 bg-muted rounded-full overflow-hidden mt-0.5">
        <div
          className={cn("h-full transition-all duration-500", barColor)}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
}
