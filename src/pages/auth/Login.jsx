import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { loginUser } from "../../services/authService";
import { useAuth } from "../../context/AuthContext";

export default function Login() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [form, setForm] = useState({ email: "", password: "" });
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setLoading(true); setMessage("");
    try {
      const result = await loginUser(form);
      if (!result?.success) return setMessage(result?.message || "Login failed.");
      if (!result.data?.token || !result.data?.user) return setMessage("Login response is missing authentication information.");
      login(result.data);
      navigate(`/${result.data.user.role}`);
    } catch (error) {
      setMessage(error.response?.data?.message || (error.request ? "GOFAST server could not be reached. Check that Apache is running." : error.message));
    } finally { setLoading(false); }
  };

  return (
    <div className="auth-page">
      <div className="auth-top-brand"><span className="brand-logo">G</span><span><strong>GOFAST</strong><small>Dispatch & Logistics</small></span></div>
      <div className="auth-split">
        <section className="auth-story">
          <span className="eyebrow">SMARTER DELIVERY • EVERY PACKAGE. ONE CLEAR JOURNEY.</span>
          <h1>Move packages.<br/><em>Move business.</em></h1>
          <p>Manage deliveries, riders and customers from one fast, reliable operations platform. GOFAST gives every package a clear journey from pickup to proof of delivery.</p>
          <div className="story-stats"><div><strong>24/7</strong><span>Delivery visibility</span></div><div><strong>4</strong><span>Role workspaces</span></div><div><strong>1</strong><span>Control center</span></div></div>
          <div className="route-preview"><span>Yola</span><i/><span>Jimeta</span><b>GOFAST LIVE ROUTE</b></div>
          <div className="trust-strip"><span>✓ Verified riders</span><span>✓ Secure payments</span><span>✓ Live operations</span></div>
        </section>

        <section className="auth-card">
          <div className="auth-card-head"><span className="eyebrow">WELCOME BACK</span><h2>Sign in to GOFAST</h2><p>Access your delivery workspace and keep every shipment moving.</p></div>
          {message && <div className="alert error">{message}</div>}
          <form onSubmit={submit}>
            <label>Email address<input type="email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} placeholder="you@example.com" required/></label>
            <label>Password<div className="password-field"><input type={showPassword?"text":"password"} value={form.password} onChange={e=>setForm({...form,password:e.target.value})} placeholder="Enter your password" required/><button type="button" className="password-toggle" onClick={()=>setShowPassword(v=>!v)}>{showPassword?"Hide":"Show"}</button></div></label>
            <div className="form-meta"><label className="check"><input type="checkbox"/> <span>Remember me</span></label><button type="button" className="link-btn" onClick={()=>setMessage("Password reset can be connected to your email service next.")}>Forgot password?</button></div>
            <button className="primary-btn wide auth-submit" disabled={loading}>{loading?"Signing in…":"Sign in →"}</button>
          </form>
          <div className="login-security"><span>🔒 Secure access</span><span>⚡ Fast operations</span></div>
          <div className="auth-footer">New to GOFAST? <Link to="/register">Create an account</Link></div>
        </section>
      </div>
    </div>
  );
}
