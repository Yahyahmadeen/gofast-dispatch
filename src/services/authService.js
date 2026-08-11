import api from "./api";

export const registerUser = async (userData) => (await api.post("?route=auth&action=register", userData)).data;
export const registerCustomer = async (userData) => registerUser({ ...userData, role: "customer" });
export const registerRider = async (userData) => registerUser({ ...userData, role: "rider" });
export const loginUser = async (credentials) => (await api.post("?route=auth&action=login", credentials)).data;
export const verifyEmail = async (token) => (await api.get(`?route=auth&action=verify-email&token=${encodeURIComponent(token)}`)).data;
