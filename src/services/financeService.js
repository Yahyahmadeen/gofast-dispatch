import api from "./api";
export const initializePayment = async (order_id) => (await api.post("?route=finance&action=initialize", { order_id })).data;
export const verifyPayment = async (reference) => (await api.get(`?route=finance&action=verify&reference=${encodeURIComponent(reference)}`)).data;
export const getRiderWallet = async () => (await api.get("?route=finance&action=wallet")).data;
export const requestPayout = async (amount, note = "") => (await api.post("?route=finance&action=payout-request", { amount, note })).data;
export const getPayouts = async () => (await api.get("?route=finance&action=payouts")).data;
export const processPayout = async (payout_request_id, action, note = "") => (await api.post("?route=finance&action=process-payout", { payout_request_id, action, note })).data;

export const savePayoutAccount = async (bank_name, account_name, account_number) => (await api.post("?route=finance&action=payout-account", { bank_name, account_name, account_number })).data;
export const getAdminPayouts = async () => (await api.get("?route=finance&action=admin-payouts")).data;
