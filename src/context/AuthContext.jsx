import { createContext, useContext, useState, useEffect } from "react";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const savedToken = localStorage.getItem("gofast_token");
        const savedUser = localStorage.getItem("gofast_user");

        if (savedToken && savedUser) {
            try {
                setToken(savedToken);
                setUser(JSON.parse(savedUser));
            } catch (error) {
                console.error("Failed to restore user:", error);

                localStorage.removeItem("gofast_token");
                localStorage.removeItem("gofast_user");
            }
        }

        setLoading(false);
    }, []);

    const login = (loginData) => {
        const { token, user } = loginData;

        localStorage.setItem("gofast_token", token);
        localStorage.setItem("gofast_user", JSON.stringify(user));

        setToken(token);
        setUser(user);
    };

    const logout = () => {
        localStorage.removeItem("gofast_token");
        localStorage.removeItem("gofast_user");

        setToken(null);
        setUser(null);
    };

    const isAuthenticated = !!token && !!user;

    return (
        <AuthContext.Provider
            value={{
                user,
                token,
                loading,
                isAuthenticated,
                login,
                logout,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error(
            "useAuth must be used inside an AuthProvider"
        );
    }

    return context;
}