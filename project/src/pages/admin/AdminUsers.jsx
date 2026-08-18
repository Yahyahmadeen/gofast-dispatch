import { useEffect, useMemo, useState } from "react";
import { getUsers } from "../../services/managementService";

export default function AdminUsers({ filter = "all", title = "User management" }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const load = async () => {
    setLoading(true); setError("");
    try { const result = await getUsers(); if (result.success) setUsers(result.data?.users || []); else setError(result.message || "Unable to load users"); }
    catch (e) { setError(e.response?.data?.message || "Unable to load users. Sign in as an administrator."); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);
  const visible = useMemo(() => filter === "all" ? users : users.filter((u) => u.role === filter), [users, filter]);
  return <section>
    <div className="breadcrumb">Administration / <strong>{title}</strong></div>
    <div className="page-title-row"><div><span className="eyebrow">PEOPLE & ACCESS</span><h1>{title}</h1><p>Review GOFAST accounts, roles, verification and access status from one place.</p></div><button className="ghost-btn" onClick={load}>↻ Refresh</button></div>
    <div className="module-stats">
      <div className="module-stat"><span>Customers</span><strong>{users.filter(u=>u.role==='customer').length}</strong><small>Registered accounts</small></div>
      <div className="module-stat"><span>Riders</span><strong>{users.filter(u=>u.role==='rider').length}</strong><small>Fleet accounts</small></div>
      <div className="module-stat"><span>Staff</span><strong>{users.filter(u=>['dispatcher','admin'].includes(u.role)).length}</strong><small>Operations access</small></div>
    </div>
    {error && <div className="alert error">{error}</div>}
    <div className="panel"><div className="panel-head"><div><span className="eyebrow">ACCOUNT DIRECTORY</span><h2>{visible.length} account{visible.length===1?'':'s'}</h2></div></div>
      {loading ? <div className="loading-state"><div className="loader-ring"/><span>Loading accounts…</span></div> : visible.length === 0 ? <div className="empty-state"><div className="empty-icon">◎</div><h3>No accounts found</h3><p>New registrations will appear here when they are created.</p></div> : <div className="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Phone</th><th>Email</th><th>Status</th><th>Created</th></tr></thead><tbody>{visible.map(u=><tr key={u.id}><td><strong>{u.full_name}</strong><br/><small>#{u.id}</small></td><td><span className="status blue">{u.role}</span></td><td>{u.phone || '—'}</td><td>{u.email || '—'}<br/><small>{Number(u.email_verified)===1?'Email verified':'Email pending'}</small></td><td><span className={`status ${u.status==='active'?'green':u.status==='suspended'?'red':'orange'}`}>{u.status}</span></td><td>{u.created_at ? new Date(u.created_at).toLocaleDateString() : '—'}</td></tr>)}</tbody></table></div>}
    </div>
  </section>;
}
