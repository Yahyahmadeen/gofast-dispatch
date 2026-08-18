import api from "./api";
export const getRiderApplications = async () => (await api.get("?route=management&action=riders")).data;
export const reviewRider = async (rider_user_id, status, reason = "") => (await api.post("?route=management&action=review-rider", { rider_user_id, status, reason })).data;
export const getUsers = async () => (await api.get("?route=management&action=users")).data;
