import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';
import Login from '../pages/auth/Login';
import Register from '../pages/auth/Register';
import VerifyEmail from '../pages/auth/VerifyEmail';
import RiderEarnings from '../pages/rider/RiderEarnings';
import RiderProfile from '../pages/rider/RiderProfile';
import DispatcherPayouts from '../pages/dispatcher/DispatcherPayouts';
import AdminRiders from '../pages/admin/AdminRiders';
import AdminCustomers from '../pages/admin/AdminCustomers';
import AdminStaff from '../pages/admin/AdminStaff';
import AppShell from '../components/common/AppShell';
import CustomerOverview from '../pages/customer/CustomerOverview';
import NewDelivery from '../pages/customer/NewDelivery';
import CustomerOrders from '../pages/customer/CustomerOrders';
import RiderOverview from '../pages/rider/RiderOverview';
import RiderDeliveries from '../pages/rider/RiderDeliveries';
import DispatcherOverview from '../pages/dispatcher/DispatcherOverview';
import LiveOrders from '../pages/dispatcher/LiveOrders';
import AdminOverview from '../pages/admin/AdminOverview';
import WorkspacePage from '../pages/common/WorkspacePage';
import SupportPage from '../pages/common/SupportPage';

function RoleWorkspace({ role, children }) {
  return <ProtectedRoute allowedRoles={[role]}><AppShell role={role}>{children}</AppShell></ProtectedRoute>;
}

const Placeholder = ({ title, description }) => <WorkspacePage title={title} description={description} />;

export default function AppRoutes() {
  return <Routes>
    <Route path="/" element={<Navigate to="/login" replace />} />
    <Route path="/login" element={<Login />} />
    <Route path="/register" element={<Register />} />
    <Route path="/verify-email" element={<VerifyEmail />} />
    <Route path="/unauthorized" element={<Placeholder title="Access restricted" description="Your account does not have permission to open this workspace." />} />

    <Route path="/customer" element={<RoleWorkspace role="customer" />}>
      <Route index element={<CustomerOverview />} />
      <Route path="new-delivery" element={<NewDelivery />} />
      <Route path="active" element={<CustomerOrders />} />
      <Route path="orders" element={<CustomerOrders />} />
      <Route path="addresses" element={<Placeholder title="Saved addresses" description="Manage your frequently used pickup and drop-off locations." />} />
      <Route path="notifications" element={<Placeholder title="Notifications" description="Delivery updates and operational messages will appear here." />} />
      <Route path="profile" element={<Placeholder title="Profile" description="Manage your customer account and contact details." />} />
      <Route path="settings" element={<Placeholder title="Account settings" description="Manage your account preferences and security settings." />} />
      <Route path="support" element={<SupportPage role="Customer" />} />
    </Route>

    <Route path="/rider" element={<RoleWorkspace role="rider" />}>
      <Route index element={<RiderOverview />} />
      <Route path="orders" element={<RiderDeliveries />} />
      <Route path="deliveries" element={<RiderDeliveries />} />
      <Route path="earnings" element={<RiderEarnings />} />
      <Route path="availability" element={<Placeholder title="Availability" description="Control whether you are available for dispatch." />} />
      <Route path="profile" element={<RiderProfile />} />
      <Route path="settings" element={<Placeholder title="Account settings" description="Manage rider account preferences and security settings." />} />
      <Route path="notifications" element={<Placeholder title="Notifications" description="Operational updates and account notifications." />} />
      <Route path="support" element={<SupportPage role="Rider" />} />
    </Route>

    <Route path="/dispatcher" element={<RoleWorkspace role="dispatcher" />}>
      <Route index element={<DispatcherOverview />} />
      <Route path="orders" element={<LiveOrders />} />
      <Route path="assignment" element={<LiveOrders />} />
      <Route path="cash" element={<DispatcherPayouts />} />
      <Route path="incidents" element={<Placeholder title="Incident log" description="Record and resolve delivery incidents." />} />
      <Route path="activity" element={<Placeholder title="Activity log" description="Review order status changes and operational actions." />} />
      <Route path="notifications" element={<Placeholder title="Notifications" description="Operational updates and dispatch alerts." />} />
      <Route path="profile" element={<Placeholder title="Profile" description="Manage your dispatcher account." />} />
      <Route path="settings" element={<Placeholder title="Account settings" description="Manage dispatcher account preferences." />} />
      <Route path="support" element={<SupportPage role="Dispatcher" />} />
    </Route>

    <Route path="/admin" element={<RoleWorkspace role="admin" />}>
      <Route index element={<AdminOverview />} />
      <Route path="orders" element={<LiveOrders />} />
      <Route path="customers" element={<AdminCustomers />} />
      <Route path="riders" element={<AdminRiders />} />
      <Route path="staff" element={<AdminStaff />} />
      <Route path="branches" element={<Placeholder title="Branches" description="Manage Yola and future operating branches." />} />
      <Route path="reports" element={<Placeholder title="Reports & analytics" description="Review revenue, delivery success and performance." />} />
      <Route path="settings" element={<Placeholder title="System settings" description="Manage branding, notifications and platform settings." />} />
      <Route path="notifications" element={<Placeholder title="Notifications" description="System alerts and administrative notifications." />} />
      <Route path="profile" element={<Placeholder title="Profile" description="Manage your administrator account." />} />
      <Route path="support" element={<SupportPage role="Admin" />} />
    </Route>

    <Route path="*" element={<Navigate to="/login" replace />} />
  </Routes>;
}
