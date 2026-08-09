import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useState } from "react";
import { useAuth } from "../../context/AuthContext";

const roleMeta = {
  customer: { label: "Customer", title: "Customer workspace", home: "/customer" },
  rider: { label: "Rider", title: "Rider workspace", home: "/rider" },
  dispatcher: { label: "Dispatcher", title: "Operations workspace", home: "/dispatcher" },
  admin: { label: "Admin", title: "Administration", home: "/admin" },
};

const nav = {
  customer: [
    ["Overview", "▦", ""], ["New delivery", "＋", "/new-delivery"], ["Live tracking", "⌖", "/active"], ["Orders", "▤", "/orders"], ["Addresses", "⌂", "/addresses"], ["Notifications", "◉", "/notifications"], ["Profile", "◯", "/profile"],
  ],
  rider: [
    ["Overview", "▦", ""], ["Incoming orders", "↘", "/orders"], ["My deliveries", "▤", "/deliveries"], ["Earnings", "₦", "/earnings"], ["Availability", "◉", "/availability"], ["Profile & verification", "◯", "/profile"],
  ],
  dispatcher: [
    ["Operations", "▦", ""], ["Live orders", "⌖", "/orders"], ["Rider assignment", "↔", "/assignment"], ["Cash reconciliation", "₦", "/cash"], ["Incidents", "!", "/incidents"], ["Activity log", "◷", "/activity"],
  ],
  admin: [
    ["Dashboard", "▦", ""], ["Orders", "▤", "/orders"], ["Customers", "◯", "/customers"], ["Riders", "🚚", "/riders"], ["Staff", "♟", "/staff"], ["Branches", "⌖", "/branches"], ["Reports", "◫", "/reports"], ["Settings", "⚙", "/settings"],
  ],
};

export default function AppShell({ role }) {
  const { user, logout } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const navigate = useNavigate();
  const meta = roleMeta[role];
  const firstName = user?.full_name?.split(" ")[0] || meta.label;

  const signOut = () => { logout(); navigate("/login"); };

  return (
    <div className="app-shell">
      <div className={mobileOpen ? "mobile-backdrop show" : "mobile-backdrop"} onClick={() => setMobileOpen(false)} />
      <aside className={mobileOpen ? "sidebar mobile-open" : "sidebar"}>
        <button className="brand" onClick={() => navigate(meta.home)}>
          <span className="brand-logo">G</span>
          <span><strong>GOFAST</strong><small>Dispatch & Logistics</small></span>
        </button>

        <div className="workspace-badge"><span className="status-dot" /> {meta.title}</div>

        <nav className="side-nav">
          {nav[role].map(([label, icon, path]) => (
            <NavLink key={label} end={path === ""} to={`${meta.home}${path}`} onClick={() => setMobileOpen(false)} className={({isActive}) => isActive ? "active" : ""}>
              <span className="nav-icon">{icon}</span><span>{label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-bottom">
          <div className="help-card"><span>Need help?</span><small>GOFAST operations support</small><button onClick={() => navigate(`${meta.home}/support`)}>Open support →</button></div>
          <div className="profile-mini">
            <div className="avatar">{firstName.charAt(0).toUpperCase()}</div>
            <div className="profile-copy"><strong>{user?.full_name || "GOFAST User"}</strong><small>{meta.label}</small></div>
            <button className="icon-button" title="Sign out" onClick={signOut}>↪</button>
          </div>
        </div>
      </aside>

      <main className="main-area">
        <header className="topbar">
          <button className="mobile-menu" onClick={() => setMobileOpen(true)} aria-label="Open menu">☰</button>
          <div className="mobile-brand"><span className="brand-logo">G</span><strong>GOFAST</strong></div>
          <div className="topbar-search"><span>⌕</span><input placeholder="Search orders, customers, riders..." /><kbd>⌘ K</kbd></div>
          <div className="topbar-actions"><button className="icon-button" title="Notifications" onClick={() => navigate(`${meta.home}/notifications`)}>◉<i /></button><button className="user-chip" onClick={() => navigate(`${meta.home}/profile`)}><span className="avatar small">{firstName.charAt(0).toUpperCase()}</span><span>{firstName}</span><span>⌄</span></button></div>
        </header>
        <div className="page-container"><Outlet /></div>
      </main>
    </div>
  );
}
