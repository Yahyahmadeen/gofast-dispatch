export default function Unauthorized() {
    return (
        <div
            style={{
                minHeight: "100vh",
                display: "grid",
                placeItems: "center",
                background: "#07111c",
                color: "white",
                textAlign: "center",
                padding: "24px",
            }}
        >
            <div>
                <h1>Access Denied</h1>

                <p>
                    You don't have permission to access this workspace.
                </p>

                <a href="/login" style={{ color: "#ffae00" }}>
                    Return to login
                </a>
            </div>
        </div>
    );
}