import { useEffect, useState } from "react";
import { getRiderWallet, requestPayout, savePayoutAccount } from "../../services/financeService";

const money = (v) => `₦${Number(v || 0).toLocaleString("en-NG", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function RiderEarnings() {
  const [wallet, setWallet] = useState(null);
  const [amount, setAmount] = useState("");
  const [account, setAccount] = useState({ bank_name: "", account_name: "", account_number: "" });
  const [loading, setLoading] = useState(true);
  const [savingAccount, setSavingAccount] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const load = async () => {
    setLoading(true);
    try {
      const r = await getRiderWallet();
      if (r.success) {
        setWallet(r.data);
        if (r.data.account) setAccount({ bank_name: r.data.account.bank_name || "", account_name: r.data.account.account_name || "", account_number: "" });
      } else setError(r.message);
    } catch (e) { setError(e.response?.data?.message || "Unable to load your wallet."); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const payout = async (e) => {
    e.preventDefault(); setMessage(""); setError("");
    try {
      const r = await requestPayout(Number(amount));
      if (!r.success) throw new Error(r.message);
      setMessage(r.message); setAmount(""); await load();
    } catch (e) { setError(e.response?.data?.message || e.message || "Unable to request payout."); }
  };

  const saveAccount = async (e) => {
    e.preventDefault(); setMessage(""); setError(""); setSavingAccount(true);
    try {
      const r = await savePayoutAccount(account.bank_name, account.account_name, account.account_number);
      if (!r.success) throw new Error(r.message);
      setMessage(r.message); setAccount(v => ({ ...v, account_number: "" })); await load();
    } catch (e) { setError(e.response?.data?.message || e.message || "Unable to save payout account."); }
    finally { setSavingAccount(false); }
  };

  const summary = wallet?.summary || {};
  return <section>
    <div className="breadcrumb">Rider / <strong>Earnings & payouts</strong></div>
    <div className="page-title-row">
      <div><span className="eyebrow">RIDER WALLET</span><h1>Earnings & payouts</h1><p>Track what you earn from completed deliveries and request a payout when you're ready.</p></div>
      <button className="ghost-btn" onClick={load}>↻ Refresh</button>
    </div>

    {message && <div className="alert">✓ {message}</div>}
    {error && <div className="alert danger-alert">{error}</div>}

    <div className="stat-grid four">
      <div className="stat-card"><span>Available</span><strong>{money(summary.balance)}</strong><small>After pending requests</small></div>
      <div className="stat-card"><span>Total earned</span><strong>{money(summary.earned)}</strong><small>Completed delivery earnings</small></div>
      <div className="stat-card"><span>Paid out</span><strong>{money(summary.paid)}</strong><small>Completed payouts</small></div>
      <div className="stat-card"><span>Pending requests</span><strong>{(wallet?.payouts || []).filter(p => ["pending", "approved"].includes(p.status)).length}</strong><small>Awaiting dispatcher action</small></div>
    </div>

    <div className="content-grid two-one">
      <div className="panel">
        <div className="panel-head"><div><span className="eyebrow">PAYOUT HISTORY</span><h2>Recent requests</h2></div></div>
        {loading ? <p>Loading wallet...</p> : <div className="table-wrap"><table><thead><tr><th>Amount</th><th>Status</th><th>Requested</th><th>Reference</th></tr></thead><tbody>
          {(wallet?.payouts || []).length ? wallet.payouts.map(p => <tr key={p.id}><td><strong>{money(p.amount)}</strong></td><td><span className={`status ${p.status === "paid" ? "green" : p.status === "rejected" ? "red" : p.status === "approved" ? "blue" : "orange"}`}>{p.status}</span></td><td>{new Date(p.requested_at).toLocaleString()}</td><td>{p.payment_reference || "—"}</td></tr>) : <tr><td colSpan="4"><div className="empty-state"><strong>No payout requests yet</strong><small>Your completed requests will appear here.</small></div></td></tr>}
        </tbody></table></div>}
      </div>

      <div className="panel">
        <div className="panel-head"><div><span className="eyebrow">REQUEST PAYOUT</span><h2>Withdraw earnings</h2></div></div>
        <form onSubmit={payout} className="simple-form">
          <label>Amount (₦)<input type="number" min="1000" max={summary.balance || undefined} step="100" value={amount} onChange={e => setAmount(e.target.value)} placeholder="Minimum ₦1,000" required /></label>
          <div className="summary-box"><span>Available to withdraw</span><strong>{money(summary.balance)}</strong><small>Minimum payout: ₦1,000</small></div>
          <button className="primary-btn wide" disabled={loading || Number(amount) > Number(summary.balance || 0)}>Request payout →</button>
          <small className="muted-copy">Your dispatcher reviews the request and completes the bank transfer. Admin monitors the process.</small>
        </form>
      </div>
    </div>

    <div className="panel" style={{ marginTop: 20 }}>
      <div className="panel-head"><div><span className="eyebrow">BANK DETAILS</span><h2>Payout account</h2><p>Only the last four digits are displayed after saving.</p></div><span className={`status ${wallet?.account?.status === "verified" ? "green" : "orange"}`}>{wallet?.account?.status || "Not configured"}</span></div>
      <form onSubmit={saveAccount} className="form-grid three">
        <label>Bank name<input value={account.bank_name} onChange={e => setAccount({ ...account, bank_name: e.target.value })} placeholder="e.g. GTBank" required /></label>
        <label>Account name<input value={account.account_name} onChange={e => setAccount({ ...account, account_name: e.target.value })} placeholder="Account holder name" required /></label>
        <label>10-digit account number<input value={account.account_number} onChange={e => setAccount({ ...account, account_number: e.target.value.replace(/\D/g, "").slice(0, 10) })} placeholder={wallet?.account ? `••••••${wallet.account.account_number_last4}` : "0123456789"} inputMode="numeric" required /></label>
        <div><button className="primary-btn" disabled={savingAccount}>{savingAccount ? "Saving..." : wallet?.account ? "Update payout account" : "Save payout account"}</button></div>
      </form>
    </div>
  </section>;
}
