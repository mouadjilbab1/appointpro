# AppointPro – Payment Configuration Guide

AppointPro includes a modular payment architecture supporting both online and offline transactions.

## 1. Offline Payments (Cash & Bank Wire)
1. Go to **AppointPro** -> **Settings** -> **Payment Settings**.
2. Toggle **Enable Pay Locally / Bank Transfer**.
3. Customers choosing this method will have their booking confirmed with an `unpaid` status, allowing payment collection in person.

## 2. Stripe Integration (Credit Cards, Apple Pay, Google Pay)
1. Go to your [Stripe Dashboard](https://dashboard.stripe.com/).
2. Retrieve your **Publishable Key** and **Secret Key**.
3. Paste both keys into **AppointPro** -> **Settings** -> **Payment Settings**.
4. Configure Webhooks:
   - Endpoint URL: `https://yourdomain.com/wp-json/appointpro/v1/payments/webhook/stripe`
   - Events to send: `payment_intent.succeeded`
   - Copy the Signing Secret into AppointPro to enable HMAC signature verification.

## 3. PayPal Integration
1. Log in to the [PayPal Developer Portal](https://developer.paypal.com/).
2. Create an App under REST API apps.
3. Copy the **Client ID** and **Secret Key**.
4. Choose **Sandbox Mode** for testing or **Live Mode** for production.
5. In AppointPro, save your credentials under Payment Settings.

## 4. Partial Deposits
Each service can define deposit rules:
- **None**: Full amount paid at booking or collected offline.
- **Fixed Amount**: A fixed dollar amount required to secure the slot (e.g. $25).
- **Percentage**: A percentage of the final price (e.g. 30%).
Remaining balances are tracked automatically in reporting.
