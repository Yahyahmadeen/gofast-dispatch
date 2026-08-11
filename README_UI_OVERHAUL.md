# GOFAST Dispatch — UI & Admin Overhaul

This build updates the GOFAST React/PHP project with a fuller responsive interface for all four roles:

- Customer
- Rider
- Dispatcher
- Admin

## UI changes

- Removed Notifications from the sidebar navigation.
- Added a notification button beside the profile at the top.
- Added a notification popover.
- Made the top profile avatar/name clickable.
- Added profile dropdown with profile, settings and sign-out actions.
- Improved mobile sidebar and responsive layouts.
- Reworked Customer, Rider, Dispatcher and Admin dashboards so they are not empty.
- Added real admin management screens for customers, staff, riders and branches.
- Added rider verification controls.
- Added admin reports/analytics.
- Added richer dispatcher live-board presentation.
- Added rider delivery queue and shift tools.

## Backend changes

- Added `/backend/routes/admin.php`.
- Added `/backend/controllers/AdminController.php`.
- Added admin dashboard, users, rider verification, reports, branches and notifications endpoints.
- Improved Authorization header detection for Apache/PHP.
- Updated CORS to support Vite on localhost ports 5173 and 5174.

## Run

1. Put the project in `C:\xampp\htdocs\gofast-dispatch`.
2. Make sure Apache and MySQL are running in XAMPP.
3. Make sure the `gofast_dispatch` database is imported.
4. Open a terminal in the project folder.
5. Run `npm install`.
6. Run `npm run dev`.
7. Open the Vite URL shown in the terminal.

The zip intentionally does not include `node_modules` or `.git`; run `npm install` after extracting.
