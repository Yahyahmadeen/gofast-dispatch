import { useMemo, useState } from "react";

const seedLogs = [
    { time: "17:42", type: "AUTH", user: "GOFAST Administrator", action: "Signed in to admin console", tone: "green" },
    { time: "17:36", type: "ORDER", user: "Dispatcher", action: "Assigned GF-202608-91AF to rider #3", tone: "orange" },
    { time: "17:28", type: "RIDER", user: "GOFAST Administrator", action: "Reviewed rider verification request", tone: "blue" },
    { time: "17:14", type: "ORDER", user: "Customer", action: "Created a new delivery request", tone: "orange" },
    { time: "16:55", type: "SYSTEM", user: "GOFAST API", action: "Session cleanup completed", tone: "green" },
    { time: "16:41", type: "AUTH", user: "Dispatcher", action: "Signed in to operations workspace", tone: "green" },
];

export default function AdminLogs() {
    const [filter, setFilter] = useState("ALL");

    const logs = useMemo(
        () => filter === "ALL"
            ? seedLogs
            : seedLogs.filter((log) => log.type === filter),
        [filter]
    );

    return (
        <section>
            <div className="breadcrumb">
                Administration / <strong>System logs</strong>
            </div>

            <div className="page-title-row">
                <div>
                    <span className="eyebrow">AUDIT & SECURITY</span>
                    <h1>System logs</h1>
                    <p>
                        Review recent authentication, order and operational
                        events across the GOFAST platform.
                    </p>
                </div>
                <span className="status green">Live console</span>
            </div>

            <div className="panel">
                <div className="panel-head">
                    <div>
                        <span className="eyebrow">AUDIT TRAIL</span>
                        <h2>Recent activity</h2>
                    </div>
                    <div className="log-filters">
                        {["ALL", "AUTH", "ORDER", "RIDER", "SYSTEM"].map((item) => (
                            <button
                                type="button"
                                key={item}
                                className={filter === item ? "filter-btn active" : "filter-btn"}
                                onClick={() => setFilter(item)}
                            >
                                {item}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="log-list">
                    {logs.map((log, index) => (
                        <div className="log-row" key={`${log.time}-${index}`}>
                            <time>{log.time}</time>
                            <span className={`log-type ${log.tone}`}>{log.type}</span>
                            <div className="log-copy">
                                <strong>{log.action}</strong>
                                <small>{log.user}</small>
                            </div>
                            <span className="log-arrow">›</span>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
