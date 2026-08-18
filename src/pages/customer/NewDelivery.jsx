import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { createOrder } from "../../services/orderService";
import { initializePayment } from "../../services/financeService";

const initialForm = {
  pickup_address: "",
  dropoff_address: "",
  recipient_name: "",
  recipient_phone: "",
  package_description: "",
  delivery_fee: "",
  branch: "Yola",
  notes: "",
};

export default function NewDelivery() {
  const navigate = useNavigate();
  const [form, setForm] = useState(initialForm);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const change = (event) => {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
  };

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    setMessage("");

    try {
      const response = await createOrder(form);
      if (!response?.success) {
        setError(response?.message || "Unable to create delivery order.");
        return;
      }

      const orderId = response.data?.order_id;
      if (!orderId) {
        setError("Order was created but a payment session could not be started.");
        return;
      }

      // GOFAST uses Paystack's hosted checkout. The backend initializes the
      // transaction with the secret key and returns a secure checkout URL.
      const payment = await initializePayment(Number(orderId));
      if (!payment?.success || !payment.data?.authorization_url) {
        setError(payment?.message || "Delivery was created, but Paystack checkout could not be started. You can retry payment from My Orders.");
        return;
      }

      window.location.assign(payment.data.authorization_url);
    } catch (err) {
      console.error("Create order error:", err);
      setError(err.response?.data?.message || "Unable to connect to the GOFAST server. Please sign in again if your session has expired.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="customer-page">
      <div className="delivery-hero">
        <div className="delivery-hero-copy">
          <div className="breadcrumb">Customer / <strong>New delivery</strong></div>
          <span className="eyebrow">BOOK A DELIVERY</span>
          <h1>Send a package</h1>
          <p>Create a delivery request in a few steps. A dispatcher assigns an available rider and GOFAST keeps the journey visible from pickup to proof of delivery.</p>
          <div className="delivery-benefits">
            <span>✓ Fast dispatch</span><span>✓ Live tracking</span><span>✓ Secure payment</span><span>✓ Delivery proof</span>
          </div>
        </div>
        <div className="delivery-route-art" aria-hidden="true">
          <div className="route-node"><b>A</b><span>Pickup</span></div><i /><div className="route-node"><b>B</b><span>Drop-off</span></div><strong>GOFAST LIVE ROUTE</strong>
        </div>
      </div>

      {message && <div className="success-message">✓ {message}</div>}
      {error && <div className="error-message">{error}</div>}

      <div className="delivery-layout">
        <form className="delivery-form" onSubmit={submit}>
          <section className="form-section">
            <div className="section-heading"><h2>01 · Route</h2><p>Tell us where the package starts and where it is going.</p></div>
            <div className="form-grid">
              <Field label="Pickup location" name="pickup_address" value={form.pickup_address} onChange={change} placeholder="e.g. Jimeta Market" required />
              <Field label="Drop-off location" name="dropoff_address" value={form.dropoff_address} onChange={change} placeholder="e.g. Yola Market" required />
              <div className="form-field"><label htmlFor="branch">Branch</label><select id="branch" name="branch" value={form.branch} onChange={change}><option>Yola</option><option>Gombe</option><option>Jalingo</option><option>Mubi</option></select></div>
              <Field label="Delivery fee (₦)" name="delivery_fee" type="number" min="0" value={form.delivery_fee} onChange={change} placeholder="4500" required />
            </div>
          </section>

          <section className="form-section">
            <div className="section-heading"><h2>02 · Recipient & package</h2><p>Give the rider enough information to complete the handover correctly.</p></div>
            <div className="form-grid">
              <Field label="Recipient name" name="recipient_name" value={form.recipient_name} onChange={change} placeholder="Full name" required />
              <Field label="Recipient phone" name="recipient_phone" type="tel" value={form.recipient_phone} onChange={change} placeholder="08012345678" required />
              <div className="form-field full-width"><label htmlFor="package_description">Package description</label><textarea id="package_description" name="package_description" value={form.package_description} onChange={change} placeholder="e.g. Clothes, documents, electronics..." required /></div>
              <Field label="Delivery notes" name="notes" value={form.notes} onChange={change} placeholder="Optional instructions for the rider" />
            </div>
          </section>

          <div className="payment-note"><strong>Payment required before dispatch.</strong><span>GOFAST accepts online payment only. After you submit this delivery, you will be taken to the secure Paystack checkout.</span></div>

          <div className="form-actions">
            <button type="button" className="ghost-btn" onClick={() => navigate("/customer")} disabled={busy}>Cancel</button>
            <button type="submit" className="primary-btn" disabled={busy}>{busy ? "Opening secure payment…" : "Continue to payment →"}</button>
          </div>
        </form>

        <aside className="delivery-side-panel">
          <span className="eyebrow">HOW IT WORKS</span><h2>One clear journey.</h2>
          <p>Your dispatcher handles rider assignment while you stay in control of the delivery.</p>
          <Journey number="01" title="Book" text="Enter route, recipient and package details." />
          <Journey number="02" title="Dispatch" text="An available, approved rider receives the job." />
          <Journey number="03" title="Track" text="Follow status changes until delivery is completed." />
          <div className="delivery-help"><strong>Need help?</strong><small>Our support team can help with delayed deliveries, payments and account issues.</small><button type="button" onClick={() => navigate("/customer/support")}>Contact support →</button></div>
        </aside>
      </div>
    </section>
  );
}

function Field({ label, name, type = "text", value, onChange, placeholder, required = false, min }) {
  return <div className="form-field"><label htmlFor={name}>{label}</label><input id={name} name={name} type={type} value={value} onChange={onChange} placeholder={placeholder} required={required} min={min} /></div>;
}

function Journey({ number, title, text }) {
  return <div className="journey-step"><b>{number}</b><span><strong>{title}</strong><small>{text}</small></span></div>;
}
