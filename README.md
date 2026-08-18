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


## Online-only customer payments
Customer delivery creation now creates an unpaid order and immediately redirects the customer to Paystack hosted checkout. Dispatchers and riders can only see/assign orders after `payment_status` becomes `paid`. Paystack Checkout is initialized server-side and supports the payment channels enabled for the merchant account (for example card, bank, USSD, QR, mobile money and bank transfer).

Run `database/verification-payments.sql` after `database/dispatch_orders.sql` so the existing database gets the `dispatch_orders.payment_status` column. Set `PAYSTACK_SECRET_KEY` and `GOFAST_APP_URL` in `backend/.env`.
## Paystack hosted checkout (test mode)

GOFAST now uses Paystack's hosted checkout flow. When a customer clicks **Continue to payment**, GOFAST creates the delivery as pending, initializes a Paystack transaction on the PHP backend, and redirects the browser to Paystack's hosted checkout URL. After payment, Paystack returns the customer to `/customer/payment`, where GOFAST verifies the transaction before marking the order as paid.

### Configure the Paystack test secret key

1. Open your Paystack Dashboard and switch to **Test Mode**.
2. Open **Settings → API Keys & Webhooks** and copy the **Test Secret Key** (`sk_test_...`). The public key (`pk_test_...`) is not used for this hosted redirect flow.
3. In `backend/`, copy `.env.example` to `.env`.
4. Put the secret key in `backend/.env`:

```text
PAYSTACK_SECRET_KEY=sk_test_your_real_test_secret_key_here
GOFAST_APP_URL=http://localhost:5174
```

Never commit `backend/.env` or expose the secret key in React/GitHub.

### Database

Run `database/dispatch_orders.sql` and `database/verification-payments.sql` in phpMyAdmin if the payment columns/tables have not already been installed.

### Run locally

Start Apache/MySQL in XAMPP, then from the project root run:

```powershell
npm install
npm run dev
```

If Vite opens on `5173` instead of `5174`, change `GOFAST_APP_URL` in `backend/.env` to the actual Vite URL and restart Vite.

### Payment flow

`New delivery → Continue to payment → GOFAST backend → Paystack hosted checkout → callback to GOFAST → server-side Paystack verification → paid order → dispatcher assignment`


## Rider payout workflow
- Riders earn 70% of the delivery fee when an assigned paid delivery is marked delivered. Configure `RIDER_EARNING_RATE` in `backend/.env`.
- Riders can maintain a masked payout bank account and request withdrawals from their available balance.
- Pending/approved payout requests are reserved so a rider cannot request the same balance twice.
- Dispatchers approve and mark bank transfers as paid; a payout reference is generated for the audit trail.
- Admins have a read-only Rider Payout Monitor.
- Run `database/payout-upgrade.sql` on an existing database before testing the payout module.
