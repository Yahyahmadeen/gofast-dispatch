import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function ProtectedRoute({ allowedRoles, children }) {
  const { isAuthenticated, loading, user } = useAuth();
  const location = useLocation();
  if (loading) return <div className="page-loader"><div className="loader-ring" /><span>Loading GOFAST...</span></div>;
  if (!isAuthenticated) return <Navigate to="/login" replace state={{ from: location }} />;
  if (!allowedRoles.includes(user?.role)) return <Navigate to="/unauthorized" replace />;
  return children;
}
