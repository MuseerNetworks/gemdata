# GemData Smoke Test Checklist

## Public entry
- Landing page loads on mobile and desktop.
- Hero and CTA links route correctly.

## User flows
- Register a user account.
- Log in successfully.
- Start forgot-password flow and open the reset link.
- Create a wallet funding request.
- Confirm the wallet is credited only after callback processing.
- Complete one airtime or data purchase.

## Admin flows
- Log in as admin.
- Open dashboard, users, transactions, providers, and reports.
- Confirm wallet activity reflects the verified funding credit.

## API
- Call one authenticated API endpoint with valid headers.
- Confirm invalid credentials are rejected cleanly.
