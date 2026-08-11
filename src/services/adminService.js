import api from './api';

export const getAdminDashboard = async () => (await api.get('?route=admin&action=dashboard')).data;
export const getAdminUsers = async (role = '') => (await api.get(`?route=admin&action=users${role ? `&role=${encodeURIComponent(role)}` : ''}`)).data;
export const updateUserStatus = async (user_id, status) => (await api.post('?route=admin&action=user-status', { user_id, status })).data;
export const getAdminRiders = async () => (await api.get('?route=admin&action=riders')).data;
export const verifyRider = async (user_id, verification_status) => (await api.post('?route=admin&action=verify-rider', { user_id, verification_status })).data;
export const getAdminReports = async () => (await api.get('?route=admin&action=reports')).data;
export const getAdminBranches = async () => (await api.get('?route=admin&action=branches')).data;
export const getNotifications = async () => (await api.get('?route=admin&action=notifications')).data;
