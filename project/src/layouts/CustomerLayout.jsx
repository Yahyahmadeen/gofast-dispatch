import { NavLink, Outlet } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

function CustomerLayout() {
    const { user, logout } = useAuth();

    return (
        <div className="customer-layout">

            <aside className="customer-sidebar">

                <div className="brand">
                    <div className="brand-mark">G</div>

                    <div>
                        <h2>GOFAST</h2>
                        <span>Delivery</span>
                    </div>
                </div>

                <nav className="sidebar-nav">

                    <NavLink to="/customer">
                        Dashboard
                    </NavLink>

                    <NavLink to="/customer/new-delivery">
                        New Delivery
                    </NavLink>

                    <NavLink to="/customer/active">
                        Active Deliveries
                    </NavLink>

                    <NavLink to="/customer/orders">
                        Order History
                    </NavLink>

                    <NavLink to="/customer/addresses">
                        Saved Addresses
                    </NavLink>

                    <NavLink to="/customer/profile">
                        Profile
                    </NavLink>

                </nav>

                <div className="sidebar-bottom">

                    <div className="user-mini">

                        <div className="avatar">
                            {user?.full_name?.charAt(0)?.toUpperCase()}
                        </div>

                        <div>
                            <strong>{user?.full_name}</strong>
                            <small>Customer</small>
                        </div>

                    </div>

                    <button
                        className="logout-button"
                        onClick={logout}
                    >
                        Logout
                    </button>

                </div>

            </aside>

            <main className="customer-main">

                <header className="customer-header">

                    <div>
                        <p className="header-label">
                            CUSTOMER PORTAL
                        </p>

                        <h1>
                            Welcome back, {user?.full_name?.split(" ")[0]}
                        </h1>
                    </div>

                    <div className="header-actions">

                        <button className="notification-button">
                            🔔
                        </button>

                        <div className="header-avatar">
                            {user?.full_name?.charAt(0)?.toUpperCase()}
                        </div>

                    </div>

                </header>

                <section className="customer-content">
                    <Outlet />
                </section>

            </main>

        </div>
    );
}

export default CustomerLayout;