import api from "./api";

export const checkApiHealth = async () => {
    const response = await api.get("");
    return response.data;
};
