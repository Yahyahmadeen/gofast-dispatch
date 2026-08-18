# GOFAST Dispatch

A React + PHP + MySQL dispatch tracking system for Customer, Rider, Dispatcher and Admin workspaces.

## Local setup

1. Start Apache and MySQL in XAMPP.
2. Make sure the MySQL database is `gofast_dispatch` and your existing schema is imported.
3. Keep the PHP API at:
   `http://localhost/gofast-dispatch/backend/index.php`
4. From the project folder run:

```bash
npm install
npm run dev
```

5. Open the Vite URL shown in the terminal.

## Demo role accounts

For local UI testing, import `database/demo-users.sql` into the `gofast_dispatch` database.

All demo accounts use:

`Password123!`

| Role | Email |
|---|---|
| Admin | admin@gofast.local |
| Dispatcher | dispatcher@gofast.local |
| Rider | rider@gofast.local |
| Customer | customer@gofast.local |

The login page reads the authenticated role returned by the PHP API and routes the user to the correct workspace.

## UI workspaces

- Customer: deliveries, live tracking, orders, addresses, notifications and profile.
- Rider: incoming orders, active deliveries, earnings, availability and verification.
- Dispatcher: live order board, assignment, cash reconciliation, incidents and activity.
- Admin: orders, customers, riders, staff, branches, reports and system settings.

The current UI uses lightweight React/CSS and responsive layouts. The dashboard values and several module screens are intentionally demo data until the corresponding PHP/MySQL endpoints are implemented.
