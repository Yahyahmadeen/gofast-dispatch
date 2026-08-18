import { useEffect, useMemo, useState } from 'react';
import { getDispatcherOrders, getAvailableRiders, assignOrder } from '../../services/orderService';

export default function LiveOrders(){
  const [orders,setOrders]=useState([]);
  const [riders,setRiders]=useState([]);
  const [selectedOrder,setSelectedOrder]=useState(null);
  const [search,setSearch]=useState('');
  const [loading,setLoading]=useState(true);
  const [ridersLoading,setRidersLoading]=useState(false);
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState('');

  const load=async()=>{
    setLoading(true); setError('');
    try{
      const r=await getDispatcherOrders();
      if(r.success) setOrders(r.data?.orders||[]); else setError(r.message||'Unable to load orders');
    }catch(e){setError(e.response?.data?.message||'Unable to load live orders');}
    finally{setLoading(false);}
  };

  const openAssign=async(order)=>{
    setSelectedOrder(order); setSearch(''); setError(''); setRidersLoading(true);
    try{
      const r=await getAvailableRiders();
      if(r.success) setRiders(r.data?.riders||[]);
      else setError(r.message||'Unable to load available riders');
    }catch(e){setError(e.response?.data?.message||'Unable to load available riders');}
    finally{setRidersLoading(false);}
  };

  const closeAssign=()=>{if(!busy){setSelectedOrder(null);setSearch('');setError('');}};

  const filteredRiders=useMemo(()=>{
    const term=search.trim().toLowerCase();
    if(!term) return riders;
    return riders.filter(r=>[r.full_name,r.phone,r.vehicle_type,r.vehicle_number].filter(Boolean).join(' ').toLowerCase().includes(term));
  },[riders,search]);

  const assign=async(rider)=>{
    if(!selectedOrder) return;
    setBusy(true); setError('');
    try{
      const r=await assignOrder(selectedOrder.id,Number(rider.user_id));
      if(r.success){
        closeAssign();
        await load();
      } else setError(r.message||'Unable to assign rider');
    }catch(e){setError(e.response?.data?.message||'Unable to assign rider');}
    finally{setBusy(false);}
  };

  useEffect(()=>{load()},[]);

  return <section>
    <div className="breadcrumb">Dispatcher / <strong>Live orders</strong></div>
    <div className="page-title-row">
      <div><span className="eyebrow">OPERATIONS CONTROL</span><h1>Live orders</h1><p>Incoming paid orders and active deliveries across GOFAST.</p></div>
      <button className="ghost-btn" onClick={load} disabled={loading}>↻ Refresh</button>
    </div>

    {error && !selectedOrder && <div className="alert error">{error}</div>}

    <div className="panel">
      <div className="panel-head">
        <div><span className="eyebrow">DISPATCH QUEUE</span><h2>Orders ready for riders</h2></div>
        <span className="status green">{orders.filter(o=>o.status==='pending').length} unassigned</span>
      </div>
      <div className="table-wrap">
        <table><thead><tr><th>Tracking</th><th>Customer</th><th>Route</th><th>Status</th><th>Rider</th><th>Action</th></tr></thead>
        <tbody>
          {loading ? <tr><td colSpan="6" className="table-empty">Loading orders...</td></tr> : orders.length===0 ? <tr><td colSpan="6" className="table-empty">No paid orders are waiting for dispatch.</td></tr> : orders.map(o=><tr key={o.id}>
            <td><strong>{o.tracking_number}</strong><br/><small>₦{Number(o.delivery_fee||0).toLocaleString()}</small></td>
            <td>{o.customer_name}<br/><small>{o.customer_phone}</small></td>
            <td>{o.pickup_address}<br/>→ {o.dropoff_address}</td>
            <td><span className={`status ${o.status==='delivered'?'green':o.status==='pending'?'blue':'orange'}`}>{o.status.replaceAll('_',' ')}</span></td>
            <td>{o.rider_name||'Unassigned'}</td>
            <td>{o.status==='pending'?<button className="primary-btn small" disabled={busy} onClick={()=>openAssign(o)}>Choose rider →</button>:<span className="muted-text">Monitor</span>}</td>
          </tr>)}
        </tbody></table>
      </div>
    </div>

    {selectedOrder && <div className="modal-backdrop" onMouseDown={e=>{if(e.target===e.currentTarget)closeAssign()}}>
      <div className="assign-modal" role="dialog" aria-modal="true" aria-labelledby="assign-title">
        <div className="assign-modal-head">
          <div><span className="eyebrow">RIDER ASSIGNMENT</span><h2 id="assign-title">Choose an available rider</h2><p>{selectedOrder.tracking_number} · {selectedOrder.pickup_address} → {selectedOrder.dropoff_address}</p></div>
          <button className="icon-button" onClick={closeAssign} disabled={busy}>×</button>
        </div>
        <div className="assign-toolbar">
          <div className="assign-search"><span>⌕</span><input value={search} onChange={e=>setSearch(e.target.value)} placeholder="Search rider name, phone or vehicle..." autoFocus/></div>
          <span className="status green">{riders.length} available</span>
        </div>
        {error && <div className="alert error">{error}</div>}
        <div className="rider-picker-list">
          {ridersLoading ? <div className="assign-empty"><div className="loader-ring"/><strong>Finding available riders...</strong></div> : filteredRiders.length===0 ? <div className="assign-empty"><div className="empty-symbol">🚚</div><strong>{riders.length===0?'No riders are available right now':'No riders match your search'}</strong><span>Only active, approved riders marked available can be assigned.</span></div> : filteredRiders.map(r=><button className="rider-picker-card" key={r.user_id} onClick={()=>assign(r)} disabled={busy}>
            <span className="avatar rider-avatar">{r.full_name?.[0]?.toUpperCase()||'R'}</span>
            <span className="rider-picker-info"><strong>{r.full_name}</strong><small>{r.phone||'No phone'} · {r.vehicle_type||'Vehicle'} {r.vehicle_number?`· ${r.vehicle_number}`:''}</small></span>
            <span className="rider-availability"><i className="dot green"/> Available <b>→</b></span>
          </button>)}
        </div>
        <div className="assign-modal-foot"><span>Rider will be moved to <strong>On delivery</strong> after assignment.</span><button className="ghost-btn" onClick={closeAssign} disabled={busy}>Cancel</button></div>
      </div>
    </div>}
  </section>
}
