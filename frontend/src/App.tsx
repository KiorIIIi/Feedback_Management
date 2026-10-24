import {
  createContext,
  useContext,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import {
  Link,
  NavLink,
  Navigate,
  Outlet,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from "react-router-dom";
import {
  api,
  ApiError,
  SESSION_EXPIRED_EVENT,
  type User,
  type Catalog,
  type PortalNotification,
  type NotificationFeed,
} from "./api";
import { useLoad } from "./useLoad";
import { roleHome } from "./routing";
import RequesterDashboard, { RequesterIcon } from "./RequesterDashboard";
import {
  Asset,
  Identity,
  Icon,
  ErrorNotice,
  Field,
  Loading,
} from "./components";
import {
  Dashboard,
  RequestList,
  RequestDetail,
  NewRequest,
  RateService,
  FeedbackPage,
  FeedbackHistory,
  FeedbackDetail,
  Personnel,
  Reports,
  Notifications,
  Reserved,
  HelpPage,
  PersonnelDashboard,
} from "./pages";
type AuthState = {
  user: User | null;
  catalog: Catalog | null;
  refresh: () => Promise<void>;
};
const AuthContext = createContext<AuthState>({
  user: null,
  catalog: null,
  refresh: async () => {},
});
export const useAuth = () => useContext(AuthContext);
const NotificationsContext = createContext<{
  data?: PortalNotification[]; error?: unknown; loading: boolean; reload: () => void;
}>({ loading: true, reload: () => {} });
export const useNotifications = () => useContext(NotificationsContext);
export default function App() {
  const [user, setUser] = useState<User | null>(null),
    [catalog, setCatalog] = useState<Catalog | null>(null),
    [loading, setLoading] = useState(true),
    [sessionError, setSessionError] = useState<unknown>(),
    [restoreAttempt, setRestoreAttempt] = useState(0);
  const sessionEpoch = useRef(0);
  useEffect(() => {
    const expireSession = () => {
      sessionEpoch.current += 1;
      setUser(null);
      setCatalog(null);
      setSessionError(undefined);
    };
    window.addEventListener(SESSION_EXPIRED_EVENT, expireSession);
    return () =>
      window.removeEventListener(SESSION_EXPIRED_EVENT, expireSession);
  }, []);
  async function refresh(signal?: AbortSignal) {
    const epoch = sessionEpoch.current;
    const u = await api<User>("/me", { signal });
    const nextCatalog = await api<Catalog>("/catalog", { signal });
    if (signal?.aborted || epoch !== sessionEpoch.current) return;
    setUser(u);
    setCatalog(nextCatalog);
  }
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setSessionError(undefined);
    refresh(controller.signal)
      .catch((error) => {
        if (controller.signal.aborted) return;
        if (error instanceof ApiError && error.status === 401) {
          setUser(null);
          setCatalog(null);
        } else {
          setSessionError(error);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [restoreAttempt]);
  if (loading) return <Loading />;
  if (sessionError)
    return (
      <main className="public-feedback">
        <h1>Unable to restore your session</h1>
        <p>
          The server could not be reached. Retry to continue with your existing
          session.
        </p>
        <ErrorNotice error={sessionError} />
        <button
          className="button primary"
          onClick={() => setRestoreAttempt((n) => n + 1)}
        >
          Retry connection
        </button>
      </main>
    );
  return (
    <AuthContext.Provider value={{ user, catalog, refresh }}>
      <Routes>
        <Route path="/" element={<Welcome />} />
        <Route
          path="/help"
          element={
            <>
              <header className="public-header">
                <Identity />
              </header>
              <div className="public-feedback">
                <HelpPage />
              </div>
            </>
          }
        />
        <Route path="/login" element={<Welcome login />} />
        <Route
          path="/feedback"
          element={
            <>
              <header className="public-header">
                <Identity />
              </header>
              <div className="public-feedback">
                <FeedbackPage publicPage />
              </div>
            </>
          }
        />
        <Route
          path="/app"
          element={user ? <Shell key={`${user.id}:${user.role}:${user.office_id}`} /> : <Navigate to="/login" replace />}
        >
          <Route index element={<HomeRedirect />} />
          <Route
            path="admin"
            element={
              <RolePage role="admin">
                <Dashboard />
              </RolePage>
            }
          />
          <Route
            path="requester"
            element={
              <RolePage role="requester">
                <RequesterDashboard />
              </RolePage>
            }
          />
          <Route
            path="work"
            element={
              <RolePage role="personnel">
                <PersonnelDashboard />
              </RolePage>
            }
          />
          <Route path="requests" element={<RequestList />} />
          <Route path="requests/new" element={<NewRequest />} />
          <Route path="requests/:id" element={<RequestDetail />} />
          <Route path="requests/:id/rate" element={<RateService />} />
          <Route
            path="feedback/new"
            element={
              <RolePage role="requester">
                <FeedbackPage />
              </RolePage>
            }
          />
          <Route path="feedback" element={<FeedbackHistory />} />
          <Route path="feedback/:kind/:id" element={<FeedbackDetail />} />
          <Route path="personnel" element={<Personnel />} />
          <Route path="reports" element={<Reports />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="assistant" element={<Reserved kind="assistant" />} />
          <Route path="settings" element={<Reserved kind="settings" />} />
          <Route path="evaluation" element={<Reserved kind="evaluation" />} />
          <Route path="help" element={<HelpPage />} />
        </Route>
        <Route
          path="*"
          element={
            <div className="empty">
              <h1>Page not found</h1>
              <Link to="/">Return to the portal</Link>
            </div>
          }
        />
      </Routes>
    </AuthContext.Provider>
  );
}
function HomeRedirect() {
  const { user } = useAuth();
  return <Navigate to={user ? roleHome(user.role) : "/login"} replace />;
}
function RolePage({
  role,
  children,
}: {
  role: User["role"];
  children: ReactNode;
}) {
  const { user } = useAuth();
  return user?.role === role ? children : <HomeRedirect />;
}
function CampusArtwork() {
  const wrapper = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState(1);
  useLayoutEffect(() => {
    const element = wrapper.current;
    if (!element) return;
    const resize = () =>
      setScale(Math.min(1, element.getBoundingClientRect().width / 528));
    resize();
    const observer = new ResizeObserver(resize);
    observer.observe(element);
    return () => observer.disconnect();
  }, []);
  return (
    <div ref={wrapper} className="campus-illustration">
      <div className="campus-scene" style={{ transform: `scale(${scale})` }}>
        <Asset screen="portal" name="imgSun" className="campus-sun" />
        <Asset
          screen="portal"
          name="imgCampusArchitecture"
          className="campus-building"
        />
        <small>ROOTED IN SERVICE. BUILT FOR OUR COMMUNITY.</small>
      </div>
    </div>
  );
}
function Welcome({ login = false }: { login?: boolean }) {
  const { user, refresh } = useAuth();
  const nav = useNavigate();
  const loc = useLocation();
  const mode = new URLSearchParams(loc.search).get("role") || "requester";
  const selectedRole = new URLSearchParams(loc.search).get("role");
  const expectedRole = ["admin", "personnel", "requester", "staff"].includes(
    selectedRole || "",
  )
    ? selectedRole
    : null;
  const [error, setError] = useState<unknown>(),
    [busy, setBusy] = useState(false),
    [show, setShow] = useState(false);
  async function signIn(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const f = new FormData(e.currentTarget);
    try {
      const signedIn = await api<User>("/login", {
        method: "POST",
        body: JSON.stringify({
          email: f.get("email"),
          password: f.get("password"),
          remember: f.has("remember"),
          ...(expectedRole ? { role: expectedRole } : {}),
        }),
      });
      await refresh();
      nav(roleHome(signedIn.role), { replace: true });
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  if (user && login) {
    if (
      !expectedRole ||
      user.role === expectedRole ||
      (expectedRole === "staff" && ["admin", "personnel"].includes(user.role))
    )
      return <Navigate to={roleHome(user.role)} replace />;
    return (
      <>
        <header className="public-header">
          <Identity />
        </header>
        <main className="public-feedback">
          <h1>Switch account to continue</h1>
          <p>
            You are signed in as {user.name} ({user.role}). This sign-in is for
            a {expectedRole} account.
          </p>
          <ErrorNotice error={error} />
          <div className="actions">
            <button
              className="button primary"
              disabled={busy}
              onClick={async () => {
                if (
                  !window.confirm(
                    "Sign out of your current account and switch accounts?",
                  )
                )
                  return;
                setBusy(true);
                try {
                  await api("/logout", { method: "POST", body: "{}" });
                  window.location.assign(loc.pathname + loc.search);
                } catch (e) {
                  setError(e);
                  setBusy(false);
                }
              }}
            >
              Sign out and switch account
            </button>
            <Link className="button" to={roleHome(user.role)}>
              Return to my workspace
            </Link>
          </div>
        </main>
      </>
    );
  }
  return (
    <>
      <header className="public-header">
        <Identity />
      </header>
      <main className="welcome-layout">
        <section className="campus-welcome">
          <div className="campus-brand">
            <small>CENTRAL LUZON STATE UNIVERSITY</small>
            <h2>MISO Service Portal</h2>
            <p>Service requests & feedback</p>
          </div>
          <div className="welcome-message">
            <p className="welcome-label">CONNECTED CAMPUS. BETTER SERVICE.</p>
            <h1>A simpler way to keep campus moving.</h1>
            <p>
              Request support, follow every update, and share feedback that
              helps improve CLSU services.
            </p>
          </div>
          <CampusArtwork />
          <p className="campus-location">
            Science City of Muñoz, Nueva Ecija, Philippines
          </p>
        </section>
        <section className="welcome-content">
          {login ? (
            <>
              <Link to="/" className="back">
                ‹ Back to options
              </Link>
              <p className="eyebrow">
                {mode === "staff"
                  ? "STAFF ACCESS"
                  : mode === "admin"
                    ? "ADMINISTRATOR ACCESS"
                    : mode === "personnel"
                      ? "PERSONNEL ACCESS"
                      : "SERVICE REQUEST"}
              </p>
              <h1>Sign in to continue</h1>
              <p>
                {mode === "staff"
                  ? "Sign in as an administrator or service personnel. Your account determines your workspace."
                  : "Use your authorized account to submit a request and follow updates."}
              </p>
              <div className="notice">
                {mode === "requester"
                  ? "You selected Request. After signing in, you’ll continue to your service workspace."
                  : "Sign in with your assigned university account."}
              </div>
              <form className="login-form" onSubmit={signIn}>
                <h2>Welcome back</h2>
                <ErrorNotice error={error} />
                <Field label="Email address">
                  <input
                    name="email"
                    type="email"
                    autoComplete="username"
                    placeholder="you@clsu.edu.ph"
                    required
                  />
                </Field>
                <Field label="Password" inputId="portal-password">
                  <div className="password-field">
                    <input
                      id="portal-password"
                      aria-label="Password"
                      name="password"
                      type={show ? "text" : "password"}
                      autoComplete="current-password"
                      placeholder="Enter your password"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShow(!show)}
                      aria-label={show ? "Hide password" : "Show password"}
                    >
                      <Asset screen="login" name="imgEye" />
                    </button>
                  </div>
                </Field>
                <div className="session-options">
                  <label>
                    <input name="remember" type="checkbox" />
                    Remember me
                  </label>
                  <Link to="/help">Forgot password</Link>
                </div>
                <button className="button primary full" disabled={busy}>
                  {busy ? "Signing in…" : "Sign in"}
                  <Asset screen="portal" name="imgArrowRight" />
                </button>
              </form>
              <h3>Need an account or help signing in?</h3>
              <p>
                Students, faculty, and staff may use their authorized CLSU
                account. Contact MISO for account assistance.
              </p>
              <a href="mailto:miso@clsu.edu.ph">Contact MISO for access ↗</a>
              <small className="demo-login">
                {import.meta.env.MODE === "hosted-demo"
                  ? "Contact the demo owner for sign-in access. Use synthetic data only."
                  : "Local demo accounts are listed in the project README. Use synthetic data only."}
              </small>
            </>
          ) : (
            <>
              <p className="eyebrow">WELCOME TO MISO SERVICE PORTAL!</p>
              <h1>How can we help?</h1>
              <p>
                Share your experience or request support from a university
                service team.
              </p>
              <div className="option">
                <h2>Share feedback</h2>
                <p>
                  Your voice helps improve campus services. Share a complaint,
                  suggestion, compliment, or other feedback.
                </p>
                <Link className="button primary full" to="/feedback">
                  Feedback
                  <Asset screen="portal" name="imgArrowRight" />
                </Link>
              </div>
              <div className="option">
                <h2>Request a service</h2>
                <p>
                  Get help with equipment, facilities, connectivity, or
                  technical support.
                </p>
                <p>Sign in is required to submit and track a request.</p>
                <Link
                  className="button primary full"
                  to={user ? roleHome(user.role) : "/login?role=requester"}
                >
                  Request
                  <Asset screen="portal" name="imgArrowRight" />
                </Link>
              </div>
              <div className="option">
                <h2>Staff access</h2>
                <p>
                  Administrators and service personnel: manage requests and
                  assigned work.
                </p>
                <Link className="button primary full" to="/login?role=staff">
                  Staff login
                  <Asset screen="portal" name="imgArrowRight" />
                </Link>
              </div>
              <div className="notice">
                One place for better campus service. Choose an option to get
                started.
              </div>
            </>
          )}
          <footer>
            Central Luzon State University
            <br />
            MISO Service Portal · <Link to="/help">Privacy notice</Link>
          </footer>
        </section>
      </main>
    </>
  );
}
const adminLinks = [
  ["", "Dashboard", "dashboard"],
  ["requests", "Service requests", "requests"],
  ["personnel", "Personnel", "personnel"],
  ["feedback", "Feedback & ratings", "feedback"],
  ["reports", "Reports & analytics", "reports"],
  ["assistant", "MCP Assistant", "assistant"],
];
function Shell() {
  const { user } = useAuth();
  const nav = useNavigate();
  const loc = useLocation();
  const [menu, setMenu] = useState(false);
  const [logoutBusy, setLogoutBusy] = useState(false);
  const [logoutError, setLogoutError] = useState<unknown>();
  // Retain array compatibility while an existing server is updated to the feed response.
  const feed = useLoad<NotificationFeed | PortalNotification[]>("/notifications?feed=1", { live: true });
  const notifications = Array.isArray(feed.data) ? feed.data : feed.data?.notifications;
  const unreadCount = Array.isArray(feed.data) ? feed.data.filter(n => !n.read_at).length : feed.data?.unread_count ?? 0;
  const [latestNotice, setLatestNotice] = useState<PortalNotification>();
  const newestSeen = useRef<number | undefined>(undefined);
  useEffect(() => {
    const changed = () => feed.reload();
    window.addEventListener("portal-notifications-changed", changed);
    return () =>
      window.removeEventListener("portal-notifications-changed", changed);
  }, [feed.reload]);
  useEffect(() => {
    if (!notifications) return;
    const newest = notifications.reduce((id, n) => Math.max(id, n.id), 0);
    if (newestSeen.current !== undefined) {
      const next = notifications.find(n => n.id > newestSeen.current! && !n.read_at);
      if (next) setLatestNotice(next);
    }
    newestSeen.current = Math.max(newestSeen.current ?? 0, newest);
  }, [notifications]);
  const admin = user?.role === "admin";
  const personnel = user?.role === "personnel";
  useEffect(() => {
    setMenu(false);
    window.scrollTo(0, 0);
  }, [loc.pathname]);
  async function logout() {
    if (logoutBusy) return;
    if (!window.confirm("Are you sure you want to sign out?")) return;
    setLogoutBusy(true);
    setLogoutError(undefined);
    try {
      await api("/logout", { method: "POST", body: "{}" });
      window.location.assign("/");
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        window.location.assign("/");
        return;
      }
      setLogoutError(error);
      setLogoutBusy(false);
    }
  }
  const label =
    adminLinks.find(
      ([path]) => path && loc.pathname.includes("/app/" + path),
    )?.[1] || "Dashboard";
  const userbox = (
    <div className="userbox">
      <span className="avatar">
        {user?.name
          .split(" ")
          .slice(0, 2)
          .map((n) => n[0])
          .join("")}
      </span>
      <span>
        <strong>{user?.name}</strong>
        <small>
          {admin
            ? "MISO Administrator"
            : personnel
              ? "Service personnel"
              : user?.classification + " · CLSU"}
        </small>
      </span>
      <button
        className="icon-button"
        onClick={logout}
        aria-label="Sign out"
        disabled={logoutBusy}
      >
        {user?.role === "requester" ? <RequesterIcon name="imgLogOut" /> : "↪"}
      </button>
    </div>
  );
  const navLink = ([path, label, icon]: string[]) => (
    <NavLink
      key={path}
      to={path ? "/app/" + path : roleHome(user!.role)}
      end={!path}
    >
      {user?.role === "requester" ? (
        <RequesterIcon
          name={
            (
              {
                dashboard: "imgLayoutDashboard",
                requests: "imgFiles",
                feedback: "imgMessageSquare",
                bell: "imgBell",
              } as Record<string, string>
            )[icon] || "imgArrowRight"
          }
        />
      ) : (
        <Icon name={icon} />
      )}
      {label}
      {path === "notifications" &&
        unreadCount > 0 && (
          <small className="requester-notification-count">{unreadCount}</small>
        )}
      {path === "assistant" && <small className="soon">Soon</small>}
    </NavLink>
  );
  return (
    <div
      className={
        admin
          ? "admin-shell"
          : `requester-shell${user?.role === "requester" && loc.pathname === "/app/requester" ? " requester-dashboard-shell" : ""}`
      }
    >
      <a className="skip-link" href="#main">
        Skip to content
      </a>
      {admin ? (
        <>
          <aside className={`sidebar ${menu ? "open" : ""}`}>
            <Link to="/app" className="sidebar-title">
              Feedback
              <br />
              Management
            </Link>
            <div className="workspace-card">
              <Asset name="imgClsuLogo" alt="CLSU seal" />
              <div>
                <strong>MISO Workspace</strong>
                <small>Administration</small>
              </div>
            </div>
            <p className="nav-label">WORKSPACE</p>
            <nav>{adminLinks.map(navLink)}</nav>
            <p className="nav-label">MANAGEMENT</p>
            <nav>
              {[
                ["settings", "Service settings", "settings"],
                ["evaluation", "System evaluation", "evaluation"],
              ].map(navLink)}
            </nav>
            <div className="sidebar-bottom">
              Central Luzon State University
              <br />
              Prototype · Demo policies
            </div>
          </aside>
          <header className="admin-header">
            <button
              className="mobile-toggle"
              aria-label="Toggle navigation"
              aria-expanded={menu}
              onClick={() => setMenu(!menu)}
            >
              ☰
            </button>
            <span className="breadcrumbs">
              Workspace <span>/</span>
              <strong>{label}</strong>
            </span>
            <div className="header-right">
              <span className="connection">
                <i />
                MCP not connected
              </span>
              <Link
                className="icon-button"
                to="/app/notifications"
                aria-label="Notifications"
              >
                <Icon name="bell" />
                {unreadCount > 0 && <small className="notification-count">{unreadCount > 99 ? "99+" : unreadCount}</small>}
              </Link>
              {userbox}
            </div>
          </header>
        </>
      ) : (
        <>
          <header className="requester-header">
            <Identity />
            <nav>
              {[
                ["", "Dashboard", "dashboard"],
                [
                  "requests",
                  personnel ? "Assigned requests" : "My requests",
                  "requests",
                ],
                ...(!personnel
                  ? [["feedback", "Feedback history", "feedback"]]
                  : []),
                ["notifications", "Notifications", "bell"],
              ].map(navLink)}
            </nav>
            {userbox}
            <button
              className="mobile-toggle"
              aria-label="Toggle navigation"
              aria-expanded={menu}
              onClick={() => setMenu(!menu)}
            >
              ☰
            </button>
          </header>
          {menu && (
            <nav className="mobile-requester-nav">
              {[
                ["", "Dashboard", "dashboard"],
                [
                  "requests",
                  personnel ? "Assigned requests" : "My requests",
                  "requests",
                ],
                ...(!personnel
                  ? [["feedback", "Feedback history", "feedback"]]
                  : []),
                ["notifications", "Notifications", "bell"],
              ].map(navLink)}
            </nav>
          )}
          <div className="portal-strip">
            <span>
              {user?.role === "requester" ? (
                <RequesterIcon name="imgGraduationCap" />
              ) : (
                <Icon name="evaluation" />
              )}
              {personnel ? "Personnel workspace" : "Requester portal"}
              <i />
              Your records, in one place
            </span>
            <span>
              {new Date().toLocaleDateString("en-PH", {
                weekday: "long",
                day: "numeric",
                month: "long",
                year: "numeric",
                timeZone: "Asia/Manila",
              })}
            </span>
          </div>
        </>
      )}
      <main id="main" className="main-content">
        <ErrorNotice error={logoutError} />
        {!!feed.refreshError && <p className="live-update-status" role="status">Live updates interrupted. Reconnecting…</p>}
        <NotificationsContext.Provider value={{ data: notifications, error: feed.error, loading: feed.loading, reload: feed.reload }}>
          <Outlet />
        </NotificationsContext.Provider>
      </main>
      {latestNotice && <aside className="live-notification" aria-label="New notification">
        <div role="status"><strong>{latestNotice.message}</strong>
          <Link to={latestNotice.url || `/app/requests/${latestNotice.service_request_id}`} onClick={() => setLatestNotice(undefined)}>View update <Icon name="arrow" /></Link>
        </div>
        <button type="button" className="button" onClick={() => setLatestNotice(undefined)} aria-label="Dismiss notification">Dismiss</button>
      </aside>}
      {!admin && (
        <footer className="portal-footer">
          Only your authorized records are shown. · Privacy & data use
          <span>Illustrative people & records</span>
        </footer>
      )}
    </div>
  );
}
