# Paystack Wallet Funding Service

This folder contains a complete `Node.js + Express` Paystack wallet funding integration for a VTU website.

## Features

- `POST /api/pay` initializes a Paystack transaction from the backend.
- `GET /api/verify/:reference` verifies the payment on the backend and credits the wallet once.
- `POST /api/webhook` handles `charge.success` events safely.
- Duplicate wallet credit is prevented with row-level locking and the `credited_at` guard.
- Amount and customer email are verified before any wallet balance is updated.
- A tiny frontend is included in `public/`.

## Folder Structure

- `src/server.js` - Express app and API routes.
- `src/paystack.js` - Paystack API client and webhook signature verification.
- `src/db.js` - MySQL pool and transaction helper.
- `src/walletService.js` - user lookup, transaction persistence, wallet credit logic.
- `schema.sql` - demo schema for `users` and `transactions`.
- `public/index.html` - example "Fund Wallet" page.
- `public/callback.html` - redirects back from Paystack and triggers backend verification.

## Environment Variables

Copy `.env.example` to `.env` and set:

- `PAYSTACK_SECRET_KEY` - your Paystack secret key.
- `PAYSTACK_CALLBACK_URL` - where Paystack should redirect after payment, for example `http://localhost:3000/callback.html`.
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` - MySQL connection details.
- `PORT` - local server port.

## How It Works

1. The frontend sends `email` and `amount` to `POST /api/pay`.
2. The backend looks up the user by email and initializes Paystack from the server.
3. A pending transaction row is stored with the expected amount and reference.
4. Paystack redirects back to `callback.html`, which calls `GET /api/verify/:reference`.
5. The backend verifies the transaction with Paystack.
6. If the verified amount and customer email match the stored transaction, the user's wallet is credited once.
7. If Paystack sends `charge.success` to the webhook first, the same credit logic runs safely and the later verify call becomes a no-op.

## Test Instructions

1. Create the MySQL database and tables:

```sql
SOURCE c:/xampp/htdocs/gemdata/paystack-node/schema.sql;
```

2. Install dependencies:

```bash
npm install
```

3. Start the server:

```bash
npm start
```

4. Open:

```text
http://localhost:3000
```

5. Use the seeded demo user:

```text
customer@example.com
```

6. Enter an amount and click `Fund Wallet`.

7. After a successful Paystack test payment:
- Paystack redirects to `callback.html`.
- The callback page calls `GET /api/verify/:reference`.
- The wallet is credited only if the backend verification passes.

8. Configure your Paystack dashboard webhook URL to:

```text
http://your-domain-or-tunnel/api/webhook
```

For local webhook testing, use a tunnel such as ngrok or Cloudflare Tunnel.

## Security Notes

- Never credit a wallet from frontend-only success callbacks.
- Always verify with Paystack on the backend.
- Always compare the verified amount to the stored amount before crediting.
- Verify the `x-paystack-signature` header on webhook requests.
- Keep `PAYSTACK_SECRET_KEY` in `.env` only.

## Official Paystack References

- Transactions API: https://paystack.com/docs/api/transaction/
- Verify payments: https://paystack.com/docs/payments/verify-payments/
- Webhooks: https://paystack.com/docs/payments/webhooks
