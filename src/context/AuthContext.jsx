import {
    createContext,
    useContext,
    useEffect,
    useState,
} from "react";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const handleUnauthorized = () => {
            setToken(null);
            setUser(null);
        };
        window.addEventListener("gofast:unauthorized", handleUnauthorized);

        const savedToken = localStorage.getItem("gofast_token");
        const savedUser = localStorage.getItem("gofast_user");

        if (savedToken && savedUser) {
            try {
                const parsedUser = JSON.parse(savedUser);

                setToken(savedToken);
                setUser(parsedUser);
            } catch (error) {
                console.error("Failed to restore GOFAST user:", error);

                localStorage.removeItem("gofast_token");
                localStorage.removeItem("gofast_user");
            }
        }

        setLoading(false);
        return () => window.removeEventListener("gofast:unauthorized", handleUnauthorized);
    }, []);

    const login = (loginData) => {
        if (!loginData) {
            throw new Error("Invalid login response.");
        }

        const savedToken = loginData.token;
        const savedUser = loginData.user;

        if (!savedToken || !savedUser) {
            console.error("Invalid login data:", loginData);
            throw new Error("Login response did not contain a token and user.");
        }

        localStorage.setItem("gofast_token", savedToken);
        localStorage.setItem(
            "gofast_user",
            JSON.stringify(savedUser)
        );

        setToken(savedToken);
        setUser(savedUser);
    };

    const logout = () => {
        localStorage.removeItem("gofast_token");
        localStorage.removeItem("gofast_user");

        setToken(null);
        setUser(null);
    };

    const isAuthenticated = Boolean(token && user);

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