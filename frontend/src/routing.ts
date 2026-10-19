import type { User } from "./api";

export function roleHome(role: User["role"]): string {
  return role === "admin"
    ? "/app/admin"
    : role === "personnel"
      ? "/app/work"
      : "/app/requester";
}
