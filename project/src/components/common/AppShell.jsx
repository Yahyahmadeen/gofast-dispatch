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
  customer: [["Overview","▦",""],["New delivery","＋","/new-delivery"],["Live tracking","⌖","/active"],["Orders","▤","/orders"],["Addresses","⌂","/addresses"],["Profile","◯","/profile"]],
  rider: [["Overview","▦",""],["Incoming orders","↘","/orders"],["My deliveries","▤","/deliveries"],["Earnings & payouts","₦","/earnings"],["Availability","◉","/availability"],["Profile & verification","◯","/profile"]],
  dispatcher: [["Operations","▦",""],["Live orders","⌖","/orders"],["Rider assignment","↔","/assignment"],["Rider payouts","₦","/cash"],["Incidents","!","/incidents"],["Activity log","◷","/activity"]],
  admin: [["Dashboard","▦",""],["Orders","▤","/orders"],["Customers","◯","/customers"],["Riders & verification","🚚","/riders"],["Staff","♟","/staff"],["Branches","⌖","/branches"],["Finance","₦","/finance"],["Reports","◫","/reports"],["Settings","⚙","/settings"]],
};

export default function AppShell({ role }) {
  const { user, logout } = useAuth();
  const [mobileOpen,setMobileOpen]=useState(false); const [profileOpen,setProfileOpen]=useState(false); const [notificationsOpen,setNotificationsOpen]=useState(false);
  const navigate=useNavigate(); const meta=roleMeta[role]; const firstName=user?.full_name?.split(" ")[0]||meta.label;
  const signOut=()=>{logout();navigate("/login")};
  const go=(path)=>{setProfileOpen(false);setNotificationsOpen(false);navigate(`${meta.home}${path}`)};
  return <div className="app-shell">
    <div className={mobileOpen?"mobile-backdrop show":"mobile-backdrop"} onClick={()=>setMobileOpen(false)}/>
    <aside className={mobileOpen?"sidebar mobile-open":"sidebar"}>
      <button className="brand" onClick={()=>navigate(meta.home)}><span className="brand-logo">G</span><span><strong>GOFAST</strong><small>Dispatch & Logistics</small></span></button>
      <div className="workspace-badge"><span className="status-dot"/> {meta.title}</div>
      <nav className="side-nav">{nav[role].map(([label,icon,path])=><NavLink key={label} end={!path} to={`${meta.home}${path}`} onClick={()=>setMobileOpen(false)} className={({isActive})=>isActive?"active":""}><span className="nav-icon">{icon}</span><span>{label}</span>{role==='admin'&&label.startsWith('Riders')&&<span className="nav-count">!</span>}</NavLink>)}</nav>
      <div className="sidebar-bottom"><div className="help-card"><span>Need help?</span><small>GOFAST operations support</small><button onClick={()=>go("/support")}>Open support →</button></div><div className="profile-mini"><div className="avatar">{firstName[0]?.toUpperCase()}</div><div className="profile-copy"><strong>{user?.full_name||"GOFAST User"}</strong><small>{meta.label}</small></div><button className="icon-button" title="Sign out" onClick={signOut}>↪</button></div></div>
    </aside>
    <main className="main-area">
      <header className="topbar">
        <button className="mobile-menu" onClick={()=>setMobileOpen(true)}>☰</button><div className="mobile-brand"><span className="brand-logo">G</span><strong>GOFAST</strong></div>
        <div className="topbar-search"><span>⌕</span><input placeholder="Search orders, customers, riders..."/><kbd>Ctrl K</kbd></div>
        <div className="topbar-actions">
          <div className="notification-wrap"><button className="icon-button notification-button" title="Notifications" onClick={()=>setNotificationsOpen(v=>!v)}>◉<i/></button>{notificationsOpen&&<div className="notification-menu"><div className="notification-head"><strong>Notifications</strong><button onClick={()=>setNotificationsOpen(false)}>Mark read</button></div><div className="notification-item"><span className="notification-dot orange"/><div><strong>GOFAST is running smoothly</strong><small>No new critical alerts.</small></div></div><div className="notification-item"><span className="notification-dot green"/><div><strong>Workspace ready</strong><small>Your latest operational updates appear here.</small></div></div><button className="notification-all" onClick={()=>go("/notifications")}>View all notifications →</button></div>}</div>
          <div className="profile-menu-wrap"><button className="user-chip" onClick={()=>setProfileOpen(v=>!v)}><span className="avatar small">{firstName[0]?.toUpperCase()}</span><span>{firstName}</span><span>⌄</span></button>{profileOpen&&<div className="profile-menu"><div className="profile-menu-head"><div className="avatar">{firstName[0]?.toUpperCase()}</div><div><strong>{user?.full_name}</strong><small>{meta.label}</small></div></div><button onClick={()=>go("/profile")}>My profile</button><button onClick={()=>go("/settings")}>Account settings</button><button onClick={()=>go("/support")}>Need help?</button><button className="danger" onClick={signOut}>Sign out</button></div>}</div>
        </div>
      </header><div className="page-container"><Outlet/></div>
    </main>
  </div>;
}
