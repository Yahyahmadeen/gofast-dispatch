const paths = {
  dashboard: "M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-3H4v3Zm10-10h6V7h-6v3Z",
  box: "M12 3 20 7.5v9L12 21l-8-4.5v-9L12 3Zm0 0v9m8-4.5-8 4.5m-8-4.5 8 4.5",
  truck: "M3 6h11v9H3V6Zm11 4h4l3 3v2h-7v-5Zm-8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z",
  users: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm9 3v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75",
  chart: "M4 19V5m0 14h16M7 16v-4m5 4V8m5 8V5",
  settings: "M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm8-3.5-2-.7a6.5 6.5 0 0 0-.4-1l.9-1.9-1.8-1.8-1.9.9a6.5 6.5 0 0 0-1-.4L13 5h-2l-.7 2.1a6.5 6.5 0 0 0-1 .4l-1.9-.9-1.8 1.8.9 1.9a6.5 6.5 0 0 0-.4 1L4 12v2l2.1.7c.1.4.2.7.4 1l-.9 1.9 1.8 1.8 1.9-.9c.3.2.7.3 1 .4L11 21h2l.7-2.1c.4-.1.7-.2 1-.4l1.9.9 1.8-1.8-.9-1.9c.2-.3.3-.7.4-1L20 14v-2Z",
  logout: "M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6",
  bell: "M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4",
  plus: "M12 5v14M5 12h14",
  arrow: "M5 12h13m-6-6 6 6-6 6",
  menu: "M4 7h16M4 12h16M4 17h16",
};

export default function Icon({ name, size = 18, strokeWidth = 1.8 }) {
  const d = paths[name] || paths.dashboard;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={d} />
    </svg>
  );
}
