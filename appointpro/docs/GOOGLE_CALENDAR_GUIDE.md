# AppointPro – Google Calendar Integration Guide

## Overview
AppointPro connects directly with the Google Calendar API v3 to enable real-time two-way synchronization:
- Appointments booked in AppointPro appear automatically in the staff member's Google Calendar.
- Busy events created in Google Calendar automatically block off time in AppointPro to eliminate double booking.

## Setup Steps
1. Navigate to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project named "AppointPro Sync".
3. Enable the **Google Calendar API**.
4. Go to **APIs & Services** -> **Credentials** -> **Create Credentials** -> **OAuth Client ID**.
5. Set Application Type to **Web application**.
6. Under **Authorized redirect URIs**, add:
   `https://yourdomain.com/wp-json/appointpro/v1/integrations/google-calendar/callback`
7. Copy the **Client ID** and **Client Secret**.
8. In WordPress, go to **AppointPro** -> **Integrations** -> **Google Calendar** and paste the keys.
9. Click **Connect Google Account** to authenticate.
