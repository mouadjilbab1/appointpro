# AppointPro – WooCommerce Integration Guide

AppointPro operates completely independently as a standalone system, but optionally integrates seamlessly with WooCommerce when installed.

## Features
- **Booking to Cart**: Customers can add appointment reservations to their WooCommerce shopping cart.
- **Unified Checkout**: Combine physical products, subscriptions, and appointment bookings in a single checkout.
- **All Payment Gateways**: Leverage 100+ WooCommerce payment gateways (Authorize.Net, Klarna, Mollie, Square, etc.).
- **Order Synchronization**: When a WooCommerce order completes, the corresponding AppointPro appointment is automatically marked as `confirmed` and `paid`.
- **Refund Synchronization**: Automatic refund propagation when WooCommerce orders are refunded.

## Setup Instructions
1. Ensure the free **WooCommerce** plugin is activated.
2. Go to **AppointPro** -> **Integrations** -> **WooCommerce**.
3. Toggle **Enable WooCommerce Checkout Bridge**.
4. AppointPro will automatically create a hidden virtual booking product to route cart transactions.
