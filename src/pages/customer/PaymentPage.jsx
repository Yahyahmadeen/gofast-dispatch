import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { initializePayment, verifyPayment } from "../../services/financeService";
import { getCustomerOrders } from "../../services/orderService";

export default function PaymentPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const orderId = params.get("order_id");
  const reference = params.get("reference");
  const [order, setOrder] = useState(null);
  const [state, setState] = useState(reference ? "verifying" : "loading");
  const [message, setMessage] = useState("");

  useEffect(() => {
    const run = async () => {
      try {
        const orders = await getCustomerOrders();
        const found = (orders?.data?.orders || []).find((item) => String(item.id) === String(orderId));
        if (found) setOrder(found);

        if (reference) {
          const result = await verifyPayment(reference);
          if (result.success && result.data?.status === "paid") {
            setState("paid");
            setMessage("Payment confirmed. Your delivery is now ready for dispatch.");
          } else {
            setState("failed");
            setMessage(result.message || "We could not confirm this payment yet.");
          }
          return;
        }
        setState("ready");
      } catch (error) {
        console.error("Payment page error:", error);
        setState("failed");
        setMessage(error.response?.data?.message || "Unable to load the payment details.");
      }
    };
    run();
  }, [orderId, reference]);

  const pay = async () => {
    if (!orderId) return;
    setState("starting");
    setMessage("");
    try {
      const result = await initializePayment(Number(orderId));
      if (result.success && result.data?.authorization_url) {
        window.location.assign(result.data.authorization_url);
        return;
      }
      setState("failed");
      setMessage(result.message || "Unable to start Paystack checkout.");
    } catch (error) {
      setState("failed");
      setMessage(error.response?.data?.message || "Unable to start payment.");
    }
  };

  const amount = Number(order?.delivery_fee || 0);

  return (
    <section className="customer-page payment-page">
      <div className="breadcrumb">Customer / <strong>Secure payment</strong></div>
      <div className="payment-hero">
        <div>
          <span className="eyebrow">GOFAST SECURE CHECKOUT</span>
          <h1>Pay before we dispatch your rider.</h1>
          <p>Your delivery is only released to dispatch after the delivery fee has been successfully paid.</p>
        </div>
        <div className="payment-lock">🔒<span>Secure Paystack checkout</span></div>
      </div>

      {message && <div className={state === "paid" ? "success-message" : "error-message"}>{state === "paid" ? "✓ " : ""}{message}</div>}

      <div className="payment-grid">
        <div className="payment-card">
          <div className="payment-card-head"><span className="eyebrow">ORDER SUMMARY</span><span className="payment-status">{state === "paid" ? "PAID" : "PAYMENT REQUIRED"}</span></div>
          <h2>{order?.tracking_number || "GOFAST delivery"}</h2>
          <div className="payment-route"><div><small>Pickup</small><strong>{order?.pickup_address || "Loading…"}</strong></div><b>→</b><div><small>Drop-off</small><strong>{order?.dropoff_address || "Loading…"}</strong></div></div>
          <div className="payment-total"><span>Delivery fee</span><strong>₦{amount.toLocaleString()}</strong></div>
          <div className="payment-methods"><span>💳 Card</span><span>🏦 Bank</span><span>↗ Bank transfer</span><span>◉ USSD</span></div>
          {state !== "paid" && <button className="primary-btn wide" onClick={pay} disabled={!order || state === "loading" || state === "starting" || state === "verifying"}>{state === "starting" ? "Opening secure checkout…" : "Pay ₦" + amount.toLocaleString() + " securely →"}</button>}
          {state === "paid" && <button className="primary-btn wide" onClick={() => navigate("/customer/orders")}>View my orders →</button>}
          <button className="ghost-btn wide" onClick={() => navigate("/customer/new-delivery")}>Back to delivery details</button>
        </div>

        <aside className="payment-info">
          <span className="eyebrow">HOW PAYMENT WORKS</span>
          <Step n="01" title="Review" text="Confirm your route and delivery fee." />
          <Step n="02" title="Pay" text="Pay through Paystack using the methods available to your account." />
          <Step n="03" title="Verify" text="GOFAST verifies the transaction before releasing the order to dispatch." />
          <Step n="04" title="Dispatch" text="Once paid, the dispatcher can assign an approved rider." />
          <small className="payment-disclaimer">GOFAST does not offer cash-on-delivery for this workflow.</small>
        </aside>
      </div>
    </section>
  );
}

function Step({ n, title, text }) { return <div className="payment-step"><b>{n}</b><div><strong>{title}</strong><span>{text}</span></div></div>; }
