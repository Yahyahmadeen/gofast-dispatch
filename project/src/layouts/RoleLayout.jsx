import { NavLink, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Icon from "../components/common/Icon";

const roleConfig = {
  rider: { label: "Rider Portal", nav: [["/rider", "dashboard", "Overview"], ["/rider", "truck", "Deliveries"]] },
  dispatcher: { label: "Dispatcher Portal", nav: [["/dispatcher", "dashboard", "Overview"], ["/dispatcher", "box", "Live Orders"], ["/dispatcher", "truck", "Riders"], ["/dispatcher", "chart", "Reconciliation"]] },
  admin: { label: "Admin Console", nav: [["/admin", "dashboard", "Overview"], ["/admin", "users", "Users"], ["/admin", "truck", "Riders"], ["/admin", "chart", "Reports"], ["/admin", "settings", "Settings"]] },
};

export default function RoleLayout({ role, children }) {
  const { user, logout } = useAuth();
  const location = useLocation();
  const config = roleConfig[role];
  const firstName = user?.full_name?.split(" ")[0] || "there";

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand-block">
          <div className="brand-logo">G</div>
          <div><strong>GOFAST</strong><span>Dispatch & Logistics</span></div>
        </div>
        <div className="portal-label">{config.label}</div>
        <nav className="side-nav">
          {config.nav.map(([to, icon, label]) => (
            <NavLink key={label} to={to} end={to === `/${role}`} className={({ isActive }) => isActive || location.pathname.startsWith(`${to}/`) ? "nav-item active" : "nav-item"}>
              <Icon name={icon} /> <span>{label}</span>
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="mini-user"><div className="avatar">{user?.full_name?.[0]?.toUpperCase()}</div><div><strong>{user?.full_name}</strong><span>{role}</span></div></div>
          <button className="logout-link" onClick={logout}><Icon name="logout" /> Sign out</button>
        </div>
      </aside>
      <main className="main-shell">
        <header className="topbar">
          <div><span className="eyebrow">GOFAST • {config.label}</span><h1>Good to see you, {firstName}</h1></div>
          <div className="topbar-actions"><button className="icon-button" aria-label="Notifications"><Icon name="bell" /></button><div className="top-avatar">{user?.full_name?.[0]?.toUpperCase()}</div></div>
        </header>
        <div className="page-content">{children || <Outlet />}</div>
      </main>
    </div>
  );
}
