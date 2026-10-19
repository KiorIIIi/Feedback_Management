import {
  cloneElement,
  isValidElement,
  useId,
  type ReactElement,
  type ReactNode,
} from "react";
import { Link } from "react-router-dom";
import { ApiError } from "./api";
export function Asset({
  screen = "services",
  name,
  className = "",
  alt = "",
}: {
  screen?: string;
  name: string;
  className?: string;
  alt?: string;
}) {
  return (
    <img
      className={className}
      src={`/assets/figma/${screen}/${name}.${name === "imgClsuLogo" ? "png" : "svg"}`}
      alt={alt}
    />
  );
}
const icons: Record<string, string> = {
  dashboard: "imgIcon10",
  requests: "imgIcon11",
  personnel: "imgIcon12",
  feedback: "imgIcon13",
  reports: "imgIcon14",
  assistant: "imgIcon15",
  settings: "imgIcon16",
  evaluation: "imgIcon17",
  bell: "imgIcon",
  plus: "imgIcon2",
  calendar: "imgIcon3",
  download: "imgIcon5",
  search: "imgIcon6",
  filter: "imgIcon7",
  arrow: "imgIcon8",
};
export function Icon({ name }: { name: string }) {
  const stat: Record<string, string> = {
    total: "imgIcon8",
    waiting: "imgIcon10",
    completed: "imgIcon12",
    satisfaction: "imgIcon14",
  };
  return (
    <span className="icon">
      {name === "requests" ? (
        <Asset screen="admin" name="imgIcon23" />
      ) : stat[name] ? (
        <Asset screen="admin" name={stat[name]} />
      ) : (
        <Asset name={icons[name] || "imgIcon8"} />
      )}
    </span>
  );
}
export function Identity() {
  return (
    <Link className="identity" to="/">
      <Asset
        screen="portal"
        name="imgClsuLogo"
        alt="Central Luzon State University seal"
      />
      <span>
        <small>CENTRAL LUZON STATE UNIVERSITY</small>
        <strong>MISO Service Portal</strong>
      </span>
    </Link>
  );
}
export function Badge({ value }: { value: string | null }) {
  return (
    <span
      className={`badge ${(value || "Unreviewed").toLowerCase().replaceAll(" ", "-")}`}
    >
      <i />
      {value || "Unreviewed"}
    </span>
  );
}
export function Panel({
  title,
  subtitle,
  children,
  action,
  className = "",
}: {
  title?: string;
  subtitle?: string;
  children: ReactNode;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <section className={`panel ${className}`}>
      {title && (
        <div className="panel-heading">
          <div>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
          </div>
          {action}
        </div>
      )}
      {children}
    </section>
  );
}
export function ErrorNotice({ error }: { error: unknown }) {
  if (!error) return null;
  return (
    <div className="notice error" role="alert">
      <strong>{error instanceof Error ? error.message : String(error)}</strong>
      {error instanceof ApiError && error.errors && (
        <ul>
          {Object.entries(error.errors).map(([k, v]) => (
            <li key={k}>{v.join(" ")}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
export function Empty({
  title = "No records yet",
  children,
}: {
  title?: string;
  children?: ReactNode;
}) {
  return (
    <div className="empty">
      <Icon name="requests" />
      <h3>{title}</h3>
      <p>{children || "Your records will appear here when available."}</p>
    </div>
  );
}
export function Loading() {
  return (
    <div className="loading" role="status">
      <span />
      Loading your workspace…
    </div>
  );
}
export function Help() {
  return (
    <aside className="help">
      <Icon name="evaluation" />
      <h3>A little guidance?</h3>
      <p>Get help with requests, access, or understanding an update.</p>
      <Link to="/app/help">
        Help & support <Icon name="arrow" />
      </Link>
    </aside>
  );
}
export function Field({
  label,
  children,
  hint,
  inputId,
}: {
  label: string;
  children: ReactNode;
  hint?: string;
  inputId?: string;
}) {
  const generatedId = useId();
  const id = inputId || generatedId;
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      {isValidElement(children) &&
      ["input", "select", "textarea"].includes(String(children.type))
        ? cloneElement(
            children as ReactElement<{
              id?: string;
              "aria-describedby"?: string;
            }>,
            { id, "aria-describedby": hint ? id + "-hint" : undefined },
          )
        : children}
      {hint && <small id={id + "-hint"}>{hint}</small>}
    </div>
  );
}
