import api from "./api";

export const registerCustomer = async (userData) => {
    const response = await api.post(
        "?route=auth&action=register",
        userData
    );

    return response.data;
};

export const loginUser = async (credentials) => {
    const response = await api.post(
        "?route=auth&action=login",
        credentials
    );

    return response.data;
};