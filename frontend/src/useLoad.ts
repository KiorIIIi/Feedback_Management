import { useEffect, useState } from "react";
import { api } from "./api";

export function useLoad<T>(path: string) {
  const [result, setResult] = useState<{ path: string; data?: T; error?: unknown; loading: boolean }>({ path, loading: true });
  const [tick, setTick] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    setResult(previous => ({ path, data: previous.path === path ? previous.data : undefined, loading: true }));
    api<T>(path, { signal: controller.signal })
      .then(data => { if (!controller.signal.aborted) setResult({ path, data, loading: false }); })
      .catch(error => { if (!controller.signal.aborted) setResult({ path, error, loading: false }); });
    return () => controller.abort();
  }, [path, tick]);
  return {
    data: result.path === path ? result.data : undefined,
    error: result.path === path ? result.error : undefined,
    loading: result.path !== path || result.loading,
    reload: () => setTick(t => t + 1),
  };
}
