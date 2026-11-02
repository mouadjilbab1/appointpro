import React, { useState } from 'react';

export default function BookingWizard() {
    const [step, setStep] = useState(1);

    return (
        <div className="appointpro-wizard-container">
            <div className="ap-steps-progress">
                <span>Step {step} of 7</span>
            </div>
        </div>
    );
}
