import api from "./api";

export const createOrder = async (payload) => {
    const response = await api.post("", payload, {
        params: {
            route: "orders",
            action: "create",
        },
    });

    return response.data;
};

export const getCustomerOrders = async () => {
    const response = await api.get("", {
        params: {
            route: "orders",
            action: "customer",
        },
    });

    return response.data;
};

export const getDispatcherOrders = async () => {
    const response = await api.get("", {
        params: {
            route: "orders",
            action: "dispatcher",
        },
    });

    return response.data;
};

export const getRiderOrders = async () => {
    const response = await api.get("", {
        params: {
            route: "orders",
            action: "rider",
        },
    });

    return response.data;
};

export const assignOrder = async (order_id, rider_user_id) => {
    const response = await api.post(
        "",
        {
            order_id,
            rider_user_id,
        },
        {
            params: {
                route: "orders",
                action: "assign",
            },
        }
    );

    return response.data;
};

export const updateOrderStatus = async (
    order_id,
    status,
    proof_type = "none",
    note = ""
) => {
    const response = await api.post(
        "",
        {
            order_id,
            status,
            proof_type,
            note,
        },
        {
            params: {
                route: "orders",
                action: "status",
            },
        }
    );

    return response.data;
};

export const acceptOrder = async (order_id) => {
    const response = await api.post(
        "",
        {
            order_id,
        },
        {
            params: {
                route: "orders",
                action: "accept",
            },
        }
    );

    return response.data;
};
export const getAvailableRiders = async () => {
    const response = await api.get("", {
        params: {
            route: "orders",
            action: "available-riders",
        },
    });
    return response.data;
};
