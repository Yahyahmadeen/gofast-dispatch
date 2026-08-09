import { useNavigate } from "react-router-dom";

export default function WorkspacePage({ role = "GOFAST", title, description }) {
  const navigate = useNavigate();
  return <section className="inner-page">
    <div className="breadcrumb">GOFAST / Workspace / <strong>{title}</strong></div>
    <div className="inner-hero"><div><span className="eyebrow">{role?.toUpperCase()} WORKSPACE</span><h1>{title}</h1><p>{description}</p></div><button className="primary-btn" onClick={() => navigate(-1)}>← Back</button></div>
    <div className="placeholder-grid">
      <div className="feature-panel"><div className="panel-icon">✦</div><h3>Ready for live data</h3><p>This screen is connected to the GOFAST navigation and role permissions. The next step is wiring this module to its PHP/MySQL endpoints.</p><button className="secondary-btn" onClick={() => alert("Module opened. API integration comes next.")}>Explore module</button></div>
      <div className="feature-panel"><div className="panel-icon">↗</div><h3>Fast, responsive operations</h3><p>The interface is designed for desktop dispatch desks and mobile users on low-bandwidth connections.</p><div className="mini-metrics"><span><strong>100%</strong><small>Responsive</small></span><span><strong>4</strong><small>Roles</small></span><span><strong>24/7</strong><small>Visibility</small></span></div></div>
    </div>
  </section>;
}
