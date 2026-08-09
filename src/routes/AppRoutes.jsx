import { Routes, Route, Navigate } from "react-router-dom";
import ProtectedRoute from "./ProtectedRoute";
import Login from "../pages/auth/Login";
import Register from "../pages/auth/Register";
import AppShell from "../components/common/AppShell";
import CustomerOverview from "../pages/customer/CustomerOverview";
import RiderOverview from "../pages/rider/RiderOverview";
import DispatcherOverview from "../pages/dispatcher/DispatcherOverview";
import AdminOverview from "../pages/admin/AdminOverview";
import WorkspacePage from "../pages/common/WorkspacePage";

const workspacePages = (role, pages) =>
  pages.map(([path, title, description]) => (
    <Route
      key={`${role}-${path}`}
      path={path}
      element={
        <WorkspacePage
          role={role}
          title={title}
          description={description}
        />
      }
    />
  ));

function RoleWorkspace({ role, overview, children }) {
  return (
    <ProtectedRoute allowedRoles={[role]}>
      <AppShell role={role} />
    </ProtectedRoute>
  );
}

export default function AppRoutes() {
  return (
    <Routes>
      {/* Public routes */}
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route
        path="/unauthorized"
        element={
          <WorkspacePage
            title="Access restricted"
            description="Your account does not have permission to open this workspace."
          />
        }
      />

      {/* Customer workspace */}
      <Route
        path="/customer"
        element={<RoleWorkspace role="customer" />}
      >
        <Route index element={<CustomerOverview />} />
        {workspacePages("customer", [
          ["new-delivery", "New delivery", "Create and track a new GOFAST delivery."],
          ["active", "Live tracking", "Follow active deliveries and current rider status."],
          ["orders", "Order history", "Review completed, failed and returned deliveries."],
          ["addresses", "Saved addresses", "Manage frequently used pickup and drop-off locations."],
          ["notifications", "Notifications", "Delivery updates and operational messages."],
          ["profile", "Profile", "Manage your customer account and contact details."],
          ["support", "Support", "Contact GOFAST support for an operational issue."],
        ])}
      </Route>

      {/* Rider workspace */}
      <Route path="/rider" element={<RoleWorkspace role="rider" />}>
        <Route index element={<RiderOverview />} />
        {workspacePages("rider", [
          ["orders", "Incoming orders", "Review delivery jobs waiting for your response."],
          ["deliveries", "My deliveries", "Manage accepted deliveries and update statuses."],
          ["earnings", "Earnings", "View daily earnings and completed deliveries."],
          ["availability", "Availability", "Control whether you are available for dispatch."],
          ["support", "Support", "Report a rider or delivery issue."],
          ["notifications", "Notifications", "Operational updates and account notifications."],
          ["profile", "Profile & verification", "Manage your rider account details and verification."],
        ])}
      </Route>

      {/* Dispatcher workspace */}
      <Route path="/dispatcher" element={<RoleWorkspace role="dispatcher" />}>
        <Route index element={<DispatcherOverview />} />
        {workspacePages("dispatcher", [
          ["orders", "Live orders", "Monitor every active order and status change."],
          ["assignment", "Rider assignment", "Assign and reassign orders to available riders."],
          ["cash", "Cash reconciliation", "Review daily rider cash collections."],
          ["incidents", "Incident log", "Record and resolve delivery incidents."],
          ["activity", "Activity log", "Review status changes and operational actions."],
          ["support", "Support", "Contact administration for operational support."],
          ["notifications", "Notifications", "Operational updates and dispatch alerts."],
          ["profile", "Profile", "Manage your dispatcher account."],
        ])}
      </Route>

      {/* Admin workspace */}
      <Route path="/admin" element={<RoleWorkspace role="admin" />}>
        <Route index element={<AdminOverview />} />
        {workspacePages("admin", [
          ["orders", "Order management", "Search, inspect and manage all orders."],
          ["customers", "Customer management", "Manage individual and business customer accounts."],
          ["riders", "Rider management", "Approve, suspend and review rider verification."],
          ["staff", "Staff & permissions", "Create staff accounts and manage dispatcher access."],
          ["branches", "Branches", "Manage Yola and future operating branches."],
          ["reports", "Reports & analytics", "Review revenue, delivery success and performance."],
          ["settings", "System settings", "Manage branding, notifications and platform settings."],
          ["support", "Support", "Administration support center."],
          ["notifications", "Notifications", "System alerts and administrative notifications."],
          ["profile", "Profile", "Manage your administrator account."],
        ])}
      </Route>

      {/* Catch-all */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}
