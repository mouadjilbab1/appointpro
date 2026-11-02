import React from 'react';
import { createRoot } from 'react-dom/client';
import BookingWizard from './BookingWizard';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('appointpro-booking-wizard-root');
    if (container) {
        const root = createRoot(container);
        root.render(<BookingWizard />);
    }
});
