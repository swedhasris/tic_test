import React, { useEffect, useRef, useState } from "react";

interface ChartPanelProps {
  className: string;
  placeholderClassName?: string;
  children: React.ReactNode;
}

export function ChartPanel({ className, placeholderClassName, children }: ChartPanelProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    const element = containerRef.current;
    if (!element) return;

    const updateReady = () => {
      const rect = element.getBoundingClientRect();
      setIsReady(rect.width > 0 && rect.height > 0);
    };

    updateReady();

    const observer = new ResizeObserver(updateReady);
    observer.observe(element);

    return () => observer.disconnect();
  }, []);

  return (
    <div ref={containerRef} className={className}>
      {isReady ? children : <div className={placeholderClassName || "h-full w-full animate-pulse rounded bg-muted/30"} />}
    </div>
  );
}
