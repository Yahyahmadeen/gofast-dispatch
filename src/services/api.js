import axios from "axios";

const api = axios.create({
    baseURL: "http://localhost/gofast-dispatch/backend/index.php",
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    timeout: 15000,
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem("gofast_token");
    if (token) {
        config.headers = config.headers || {};
        // X-GOFAST-TOKEN works reliably with Apache/PHP on Windows.
        config.headers["X-GOFAST-TOKEN"] = token;
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem("gofast_token");
            localStorage.removeItem("gofast_user");
            window.dispatchEvent(new Event("gofast:unauthorized"));
        }
        return Promise.reject(error);
    }
);

export default api;
