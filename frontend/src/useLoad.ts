import { useCallback, useEffect, useRef, useState } from "react";
import { api, ApiError, DATA_CHANGED_EVENT } from "./api";

export function useLoad<T>(path: string, { live = false, paused = false }: { live?: boolean; paused?: boolean } = {}) {
  const [result, setResult] = useState<{ path: string; data?: T; error?: unknown; loading: boolean }>({ path, loading: true });
  const [refreshError, setRefreshError] = useState<unknown>();
  const [tick, setTick] = useState(0);
  const reload = useCallback(() => setTick(t => t + 1), []);
  const pausedRef = useRef(paused);
  pausedRef.current = paused;
  useEffect(() => {
    let disposed = false;
    let inFlight = false;
    let failures = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let request: AbortController | undefined;
    setResult(previous => ({ path, data: previous.path === path ? previous.data : undefined, loading: true }));
    setRefreshError(undefined);
    const available = () => document.visibilityState !== "hidden" && navigator.onLine !== false && !pausedRef.current;
    const schedule = () => {
      if (live && !disposed) timer = setTimeout(() => void load(true), Math.min(30000, 5000 * 2 ** Math.min(failures, 3)));
    };
    async function load(background = false) {
      if (disposed || inFlight) return;
      if (background && !available()) { schedule(); return; }
      inFlight = true;
      request = new AbortController();
      const timeout = setTimeout(() => request?.abort(), 15000);
      try {
        const data = await api<T>(path, { signal: request.signal });
        if (disposed) return;
        failures = 0;
        // An edit can begin while a background read is already in flight.
        if (!background || !pausedRef.current) {
          setResult({ path, data, loading: false });
          setRefreshError(undefined);
        }
      } catch (error) {
        if (disposed) return;
        failures += 1;
        if (background && !(error instanceof ApiError && [401, 403, 404].includes(error.status))) {
          // Keep successfully loaded content and drafts through temporary outages.
          setRefreshError(error);
          setResult(previous => previous.data !== undefined ? previous : { path, error, loading: false });
        } else {
          setResult({ path, error, loading: false });
        }
      } finally {
        clearTimeout(timeout);
        inFlight = false;
        schedule();
      }
    }
    function wake() {
      if (!available()) return;
      clearTimeout(timer);
      void load(true);
    }
    if (live) {
      window.addEventListener("focus", wake);
      window.addEventListener("online", wake);
      document.addEventListener("visibilitychange", wake);
      window.addEventListener(DATA_CHANGED_EVENT, wake);
    }
    void load();
    return () => {
      disposed = true;
      clearTimeout(timer);
      request?.abort();
      window.removeEventListener("focus", wake);
      window.removeEventListener("online", wake);
      document.removeEventListener("visibilitychange", wake);
      window.removeEventListener(DATA_CHANGED_EVENT, wake);
    };
  }, [path, tick, live]);
  return {
    data: result.path === path ? result.data : undefined,
    error: result.path === path ? result.error : undefined,
    loading: result.path !== path || result.loading,
    refreshError,
    reload,
  };
}
