import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getAdminUsers, updateUserStatus, getAdminRiders, verifyRider, getAdminBranches } from '../../services/adminService';

const label=s=>(s||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
const tone=s=>s==='active'||s==='approved'?'green':['suspended','rejected','inactive'].includes(s)?'red':s==='pending'?'orange':'blue';

export default function AdminManagement({type}){
  const navigate=useNavigate();
  const [rows,setRows]=useState([]); const [loading,setLoading]=useState(true); const [filter,setFilter]=useState('');
  const config={
    customers:{title:'Customer management', eyebrow:'CUSTOMER DIRECTORY', desc:'Review customer accounts, activity and access.', action:'/admin/orders'},
    staff:{title:'Staff & permissions', eyebrow:'OPERATIONS STAFF', desc:'Manage dispatchers and administrative access.', action:null},
    riders:{title:'Rider management', eyebrow:'FLEET & VERIFICATION', desc:'Approve riders, monitor availability and control fleet access.', action:null},
    branches:{title:'Branch network', eyebrow:'NETWORK MANAGEMENT', desc:'Monitor operating branches and their order volume.', action:null},
  }[type];
  const load=async()=>{setLoading(true);try{let r;if(type==='riders')r=await getAdminRiders();else if(type==='branches')r=await getAdminBranches();else r=await getAdminUsers(type==='customers'?'customer':'dispatcher');if(r.success)setRows(r.data[type==='riders'?'riders':type==='branches'?'branches':'users']||[]);}finally{setLoading(false)}};
  useEffect(()=>{load()},[type]);
  const filtered=useMemo(()=>rows.filter(r=>!filter||JSON.stringify(r).toLowerCase().includes(filter.toLowerCase())),[rows,filter]);
  const setStatus=async(id,status)=>{const r=await updateUserStatus(id,status);if(r.success)load();else alert(r.message)};
  const verify=async(id,status)=>{const r=await verifyRider(id,status);if(r.success)load();else alert(r.message)};
  return <section><div className="breadcrumb">Administration / <strong>{config.title}</strong></div><div className="page-title-row"><div><span className="eyebrow">{config.eyebrow}</span><h1>{config.title}</h1><p>{config.desc}</p></div><div className="title-actions"><button className="ghost-btn" onClick={load}>↻ Refresh</button>{config.action&&<button className="primary-btn" onClick={()=>navigate(config.action)}>View orders →</button>}</div></div><div className="toolbar"><div className="toolbar-search">⌕<input value={filter} onChange={e=>setFilter(e.target.value)} placeholder="Search this workspace..."/></div><span>{filtered.length} records</span></div><div className="panel"><div className="table-wrap">{loading?<div className="empty-state">Loading {config.title.toLowerCase()}...</div>:type==='branches'?<BranchTable rows={filtered}/>:type==='riders'?<RiderTable rows={filtered} verify={verify}/>:<UserTable rows={filtered} setStatus={setStatus}/>}</div></div></section>
}
function UserTable({rows,setStatus}){return <table><thead><tr><th>User</th><th>Contact</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead><tbody>{rows.map(u=><tr key={u.id}><td><strong>{u.full_name}</strong><br/><small>{u.email||'No email'} · #{u.id}</small></td><td>{u.phone||'—'}</td><td><span className={`status ${tone(u.status)}`}>{label(u.status)}</span></td><td>{new Date(u.created_at).toLocaleDateString()}</td><td><select className="inline-select" value={u.status} onChange={e=>setStatus(u.id,e.target.value)}><option value="active">Active</option><option value="suspended">Suspended</option><option value="inactive">Inactive</option><option value="pending">Pending</option></select></td></tr>)}</tbody></table>}
function RiderTable({rows,verify}){return <table><thead><tr><th>Rider</th><th>Vehicle</th><th>Verification</th><th>Availability</th><th>Account</th><th>Action</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td><strong>{r.full_name}</strong><br/><small>{r.phone||r.email||'No contact'}</small></td><td>{r.vehicle_type}<br/><small>{r.vehicle_number}</small></td><td><span className={`status ${tone(r.verification_status)}`}>{label(r.verification_status)}</span></td><td><span className={`status ${r.availability==='available'?'green':r.availability==='on_delivery'?'orange':'blue'}`}>{label(r.availability)}</span></td><td><span className={`status ${tone(r.status)}`}>{label(r.status)}</span></td><td><div className="row-actions"><button className="tiny-btn" onClick={()=>verify(r.id,'approved')}>Approve</button><button className="tiny-btn danger" onClick={()=>verify(r.id,'rejected')}>Reject</button></div></td></tr>)}</tbody></table>}
function BranchTable({rows}){return <table><thead><tr><th>Branch</th><th>Code</th><th>Orders</th><th>Revenue</th><th>Status</th></tr></thead><tbody>{rows.map(b=><tr key={b.id}><td><strong>{b.name}</strong><br/><small>{b.city}</small></td><td>{b.code}</td><td>{Number(b.orders||0).toLocaleString()}</td><td>₦{Number(b.revenue||0).toLocaleString()}</td><td><span className="status green">Operational</span></td></tr>)}</tbody></table>}
