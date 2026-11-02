# KNOWN ISSUES & CREDENTIAL REQUIREMENTS – AppointPro

## External Credential Dependencies
In strict accordance with development guidelines, the following external integrations are fully implemented at the architectural and code level, but require buyers to configure their own API credentials in the settings panel to connect with live external third-party cloud services:

1. **Google Calendar 2-Way Sync**:
   - Status: Adapter and OAuth handling implemented.
   - Requirement: Google Cloud Console OAuth Client ID and Secret are needed to authenticate with Google's API in a live environment.
2. **Stripe Payment Gateway**:
   - Status: PaymentIntents and Webhook signature checking implemented.
   - Requirement: Stripe Live Publishable and Secret keys are needed for live transaction processing.
3. **PayPal REST Gateway**:
   - Status: Orders API v2 and Sandbox/Live endpoints implemented.
   - Requirement: PayPal Client ID and Secret are needed to process live payments.
4. **AI Booking Assistant**:
   - Status: Provider abstraction and real-data prompt grounding implemented.
   - Requirement: Site administrator must provide an OpenAI, Gemini, or Claude API key.
