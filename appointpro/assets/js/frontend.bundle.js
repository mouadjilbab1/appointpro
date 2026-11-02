/**
 * AppointPro Frontend Booking Wizard
 * 8-Step Interactive Experience with Real-Time Availability & Server-Side Price Verification
 */
(function () {
    const { createElement: h, useState, useEffect, useCallback } = window.wp.element;
    const { __ } = window.wp.i18n;

    function BookingWizard() {
        const [currentStep, setCurrentStep] = useState(1);
        const [servicesData, setServicesData] = useState([]);
        const [staffData, setStaffData] = useState([]);
        const [locationsData, setLocationsData] = useState([]);
        const [gatewaysData, setGatewaysData] = useState([]);

        // User Selection State
        const [selectedService, setSelectedService] = useState(null);
        const [selectedLocation, setSelectedLocation] = useState(null);
        const [selectedStaff, setSelectedStaff] = useState(null); // null means Auto-Assign
        const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
        const [availableSlots, setAvailableSlots] = useState([]);
        const [selectedSlot, setSelectedSlot] = useState(null);
        const [extras, setExtras] = useState({});
        const [numberOfPersons, setNumberOfPersons] = useState(1);
        const [customer, setCustomer] = useState({ first_name: '', last_name: '', email: '', phone: '', notes: '' });
        const [couponCode, setCouponCode] = useState('');
        const [pricing, setPricing] = useState(null);
        const [selectedGateway, setSelectedGateway] = useState('offline');
        const [sessionToken] = useState(() => 'tok_' + Math.random().toString(36).substring(2, 15));
        const [bookingResult, setBookingResult] = useState(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');

        const apiFetch = useCallback(async (endpoint, options = {}) => {
            const config = window.appointProFrontendConfig || {};
            const url = (config.apiUrl || '/wp-json/appointpro/v1/') + endpoint.replace(/^\//, '');
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
                });
                return await response.json();
            } catch (err) {
                console.error(err);
                return { success: false, message: err.message };
            }
        }, []);

        // Initial Data Fetch
        useEffect(() => {
            async function init() {
                setLoading(true);
                const [svcRes, staffRes, locRes, gwRes] = await Promise.all([
                    apiFetch('services'),
                    apiFetch('staff'),
                    apiFetch('locations'),
                    apiFetch('payments/gateways')
                ]);
                if (svcRes.success) setServicesData(svcRes.data);
                if (staffRes.success) setStaffData(staffRes.data);
                if (locRes.success) setLocationsData(locRes.data);
                if (gwRes.success) {
                    setGatewaysData(gwRes.data);
                    if (gwRes.data.length > 0) setSelectedGateway(gwRes.data[0].id);
                }
                setLoading(false);
            }
            init();
        }, [apiFetch]);

        // Fetch available slots when service, staff, or date changes
        useEffect(() => {
            if (selectedService && selectedDate) {
                async function fetchSlots() {
                    const staffParam = selectedStaff ? selectedStaff.id : 0;
                    const res = await apiFetch(`availability?service_id=${selectedService.id}&staff_id=${staffParam}&date=${selectedDate}`);
                    if (res.success) {
                        setAvailableSlots(res.data);
                        setSelectedSlot(null);
                    }
                }
                fetchSlots();
            }
        }, [selectedService, selectedStaff, selectedDate, apiFetch]);

        // Calculate Pricing on step 7
        const calculateServerPricing = async () => {
            if (!selectedService) return;
            const res = await apiFetch('appointments/calculate', {
                method: 'POST',
                body: JSON.stringify({
                    service_id: selectedService.id,
                    staff_id: selectedStaff ? selectedStaff.id : 0,
                    extras: extras,
                    number_of_persons: numberOfPersons,
                    coupon_code: couponCode
                })
            });
            if (res.success) {
                setPricing(res.data);
            }
        };

        const handleSlotSelect = async (slot) => {
            setSelectedSlot(slot);
            setError('');
            // Lock slot temporarily
            await apiFetch('availability/lock', {
                method: 'POST',
                body: JSON.stringify({
                    staff_id: slot.staff_id,
                    start_time: slot.utc_start,
                    end_time: slot.utc_end,
                    session_token: sessionToken
                })
            });
        };

        const handleFinalSubmit = async () => {
            setLoading(true);
            setError('');

            const payload = {
                service_id: selectedService.id,
                staff_id: selectedStaff ? selectedStaff.id : (selectedSlot ? selectedSlot.staff_id : 0),
                location_id: selectedLocation ? selectedLocation.id : null,
                start_time: selectedSlot.utc_start,
                end_time: selectedSlot.utc_end,
                customer: customer,
                customer_notes: customer.notes,
                extras: extras,
                number_of_persons: numberOfPersons,
                coupon_code: couponCode,
                session_token: sessionToken
            };

            const res = await apiFetch('appointments', {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            if (res.success) {
                // If payment required and online gateway selected
                if (selectedGateway !== 'offline') {
                    await apiFetch('payments/charge', {
                        method: 'POST',
                        body: JSON.stringify({
                            appointment_id: res.appointment_id,
                            gateway: selectedGateway,
                            customer: customer
                        })
                    });
                }
                setBookingResult(res);
                setCurrentStep(8);
            } else {
                setError(res.message || __('An error occurred while creating your appointment.', 'appointpro'));
            }
            setLoading(false);
        };

        return h('div', { className: 'ap-wizard-wrapper' },
            // Step Progress Indicator
            h('div', { className: 'ap-steps-progress' },
                [
                    { num: 1, title: __('Service', 'appointpro') },
                    { num: 2, title: __('Staff', 'appointpro') },
                    { num: 3, title: __('Time', 'appointpro') },
                    { num: 4, title: __('Extras', 'appointpro') },
                    { num: 5, title: __('Details', 'appointpro') },
                    { num: 6, title: __('Payment', 'appointpro') },
                    { num: 7, title: __('Done', 'appointpro') },
                ].map(s =>
                    h('div', {
                        key: s.num,
                        className: `ap-step-item ${currentStep === s.num ? 'active' : ''} ${currentStep > s.num ? 'completed' : ''}`
                    },
                        h('div', { className: 'ap-step-circle' }, currentStep > s.num ? '✓' : s.num),
                        h('span', { className: 'ap-step-title' }, s.title)
                    )
                )
            ),

            error && h('div', { style: { padding: '12px', background: '#fef2f2', color: '#991b1b', borderRadius: '6px', marginBottom: '20px' } }, error),

            // STEP 1: Service Selection
            currentStep === 1 && h('div', null,
                h('h3', { style: { marginBottom: '16px' } }, __('Select a Service', 'appointpro')),
                servicesData.map(cat =>
                    h('div', { key: cat.id, style: { marginBottom: '20px' } },
                        h('h4', { style: { color: cat.color || '#4f46e5', margin: '0 0 10px 0' } }, cat.name),
                        h('div', { className: 'ap-selection-grid' },
                            (cat.services || []).map(svc =>
                                h('div', {
                                    key: svc.id,
                                    className: `ap-selection-card ${selectedService && selectedService.id === svc.id ? 'selected' : ''}`,
                                    onClick: () => setSelectedService(svc)
                                },
                                    h('div', { className: 'ap-card-top' },
                                        h('h4', { className: 'ap-card-title' }, svc.name),
                                        h('span', { className: 'ap-card-price' }, `$${Number(svc.price).toFixed(2)}`)
                                    ),
                                    h('div', { className: 'ap-card-meta' },
                                        h('span', null, `⏱ ${svc.duration} mins`)
                                    )
                                )
                            )
                        )
                    )
                ),
                h('div', { className: 'ap-wizard-footer' },
                    h('div', null),
                    h('button', {
                        className: 'ap-nav-btn ap-btn-next',
                        disabled: !selectedService,
                        onClick: () => setCurrentStep(2)
                    }, __('Continue →', 'appointpro'))
                )
            ),

            // STEP 2: Staff Selection
            currentStep === 2 && h('div', null,
                h('h3', { style: { marginBottom: '16px' } }, __('Choose Specialist', 'appointpro')),
                h('div', { className: 'ap-selection-grid' },
                    h('div', {
                        className: `ap-selection-card ${selectedStaff === null ? 'selected' : ''}`,
                        onClick: () => setSelectedStaff(null)
                    },
                        h('h4', { className: 'ap-card-title' }, __('Any Available Specialist', 'appointpro')),
                        h('div', { className: 'ap-card-meta' }, __('Automatic round-robin assignment', 'appointpro'))
                    ),
                    staffData.map(st =>
                        h('div', {
                            key: st.id,
                            className: `ap-selection-card ${selectedStaff && selectedStaff.id === st.id ? 'selected' : ''}`,
                            onClick: () => setSelectedStaff(st)
                        },
                            h('h4', { className: 'ap-card-title' }, st.full_name),
                            h('div', { className: 'ap-card-meta' }, st.email)
                        )
                    )
                ),
                h('div', { className: 'ap-wizard-footer' },
                    h('button', { className: 'ap-nav-btn ap-btn-back', onClick: () => setCurrentStep(1) }, __('← Back', 'appointpro')),
                    h('button', { className: 'ap-nav-btn ap-btn-next', onClick: () => setCurrentStep(3) }, __('Continue →', 'appointpro'))
                )
            ),

            // STEP 3: Date & Time Picker
            currentStep === 3 && h('div', null,
                h('h3', { style: { marginBottom: '16px' } }, __('Choose Date & Time', 'appointpro')),
                h('div', { className: 'ap-datetime-layout' },
                    h('div', null,
                        h('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, __('Select Date', 'appointpro')),
                        h('input', {
                            type: 'date',
                            value: selectedDate,
                            min: new Date().toISOString().split('T')[0],
                            onChange: (e) => setSelectedDate(e.target.value),
                            style: { width: '100%', padding: '10px', borderRadius: '8px', border: '1px solid #cbd5e1' }
                        })
                    ),
                    h('div', null,
                        h('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, __('Available Slots', 'appointpro')),
                        availableSlots.length > 0
                            ? h('div', { className: 'ap-slots-grid' },
                                availableSlots.map(slot =>
                                    h('button', {
                                        key: slot.time,
                                        className: `ap-slot-btn ${selectedSlot && selectedSlot.time === slot.time ? 'selected' : ''}`,
                                        onClick: () => handleSlotSelect(slot)
                                    }, slot.formatted_time || slot.time)
                                )
                            )
                            : h('p', { style: { color: '#64748b' } }, __('No slots available on this date.', 'appointpro'))
                    )
                ),
                h('div', { className: 'ap-wizard-footer' },
                    h('button', { className: 'ap-nav-btn ap-btn-back', onClick: () => setCurrentStep(2) }, __('← Back', 'appointpro')),
                    h('button', {
                        className: 'ap-nav-btn ap-btn-next',
                        disabled: !selectedSlot,
                        onClick: () => setCurrentStep(4)
                    }, __('Continue →', 'appointpro'))
                )
            ),

            // STEP 4: Extras & Capacity
            currentStep === 4 && h('div', null,
                h('h3', { style: { marginBottom: '16px' } }, __('Enhance Your Appointment', 'appointpro')),
                selectedService.extras && selectedService.extras.length > 0
                    ? h('div', { className: 'ap-extras-list' },
                        selectedService.extras.map(extra => {
                            const qty = extras[extra.id] || 0;
                            return h('div', { key: extra.id, className: 'ap-extra-item' },
                                h('div', null,
                                    h('strong', null, extra.name),
                                    h('div', { style: { fontSize: '12px', color: '#64748b' } }, `+$${Number(extra.price).toFixed(2)}`)
                                ),
                                h('div', { className: 'ap-qty-control' },
                                    h('button', {
                                        className: 'ap-qty-btn',
                                        onClick: () => setExtras({ ...extras, [extra.id]: Math.max(0, qty - 1) })
                                    }, '-'),
                                    h('span', null, qty),
                                    h('button', {
                                        className: 'ap-qty-btn',
                                        onClick: () => setExtras({ ...extras, [extra.id]: Math.min(extra.max_quantity || 5, qty + 1) })
                                    }, '+')
                                )
                            );
                        })
                    )
                    : h('p', { style: { color: '#64748b' } }, __('No extra add-ons available for this service.', 'appointpro')),
                h('div', { className: 'ap-wizard-footer' },
                    h('button', { className: 'ap-nav-btn ap-btn-back', onClick: () => setCurrentStep(3) }, __('← Back', 'appointpro')),
                    h('button', { className: 'ap-nav-btn ap-btn-next', onClick: () => setCurrentStep(5) }, __('Continue →', 'appointpro'))
                )
            ),

            // STEP 5: Customer Details
            currentStep === 5 && h('div', null,
                h('h3', { style: { marginBottom: '16px' } }, __('Contact Information', 'appointpro')),
                h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' } },
                    h('div', null,
                        h('label', { style: { display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '6px' } }, __('First Name *', 'appointpro')),
                        h('input', {
                            style: { width: '100%', padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: '6px' },
                            value: customer.first_name,
                            onChange: (e) => setCustomer({ ...customer, first_name: e.target.value }),
                            required: true
                        })
                    ),
                    h('div', null,
                        h('label', { style: { display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '6px' } }, __('Last Name *', 'appointpro')),
                        h('input', {
                            style: { width: '100%', padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: '6px' },
                            value: customer.last_name,
                            onChange: (e) => setCustomer({ ...customer, last_name: e.target.value }),
                            required: true
                        })
                    )
                ),
                h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' } },
                    h('div', null,
                        h('label', { style: { display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '6px' } }, __('Email Address *', 'appointpro')),
                        h('input', {
                            type: 'email',
                            style: { width: '100%', padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: '6px' },
                            value: customer.email,
                            onChange: (e) => setCustomer({ ...customer, email: e.target.value }),
                            required: true
                        })
                    ),
                    h('div', null,
                        h('label', { style: { display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '6px' } }, __('Phone Number', 'appointpro')),
                        h('input', {
                            type: 'tel',
                            style: { width: '100%', padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: '6px' },
                            value: customer.phone,
                            onChange: (e) => setCustomer({ ...customer, phone: e.target.value })
                        })
                    )
                ),
                h('div', { style: { marginBottom: '16px' } },
                    h('label', { style: { display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '6px' } }, __('Special Instructions / Notes', 'appointpro')),
                    h('textarea', {
                        style: { width: '100%', padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: '6px', minHeight: '70px' },
                        value: customer.notes,
                        onChange: (e) => setCustomer({ ...customer, notes: e.target.value })
                    })
                ),
                h('div', { className: 'ap-wizard-footer' },
                    h('button', { className: 'ap-nav-btn ap-btn-back', onClick: () => setCurrentStep(4) }, __('← Back', 'appointpro')),
                    h('button', {
                        className: 'ap-nav-btn ap-btn-next',
                        disabled: !customer.first_name || !customer.last_name || !customer.email,
                        onClick: async () => {
                            await calculateServerPricing();
                            setCurrentStep(6);
                        }
                    }, __('Continue to Payment →', 'appointpro'))
                )
            ),

            // STEP 6: Payment & Confirmation
            currentStep === 6 && h('div', null,
                h('h3', { style: { marginBottom: '16px' } }, __('Review & Payment', 'appointpro')),
                pricing && h('div', { style: { background: '#f8fafc', padding: '16px', borderRadius: '8px', marginBottom: '20px' } },
                    h('div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: '6px' } },
                        h('span', null, __('Subtotal:', 'appointpro')),
                        h('strong', null, `$${pricing.subtotal.toFixed(2)}`)
                    ),
                    pricing.discount > 0 && h('div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: '6px', color: '#16a34a' } },
                        h('span', null, __('Coupon Discount:', 'appointpro')),
                        h('strong', null, `-$${pricing.discount.toFixed(2)}`)
                    ),
                    h('div', { style: { display: 'flex', justifyContent: 'space-between', fontSize: '16px', fontWeight: '700', borderTop: '1px solid #e2e8f0', paddingTop: '8px', marginTop: '8px' } },
                        h('span', null, __('Total Due:', 'appointpro')),
                        h('strong', { style: { color: '#4f46e5' } }, `$${pricing.final_price.toFixed(2)}`)
                    )
                ),
                h('div', { style: { marginBottom: '20px' } },
                    h('label', { style: { display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '8px' } }, __('Select Payment Method', 'appointpro')),
                    gatewaysData.map(gw =>
                        h('label', { key: gw.id, style: { display: 'flex', alignItems: 'center', gap: '8px', padding: '10px', border: '1px solid #cbd5e1', borderRadius: '6px', marginBottom: '8px', cursor: 'pointer' } },
                            h('input', {
                                type: 'radio',
                                name: 'gateway',
                                value: gw.id,
                                checked: selectedGateway === gw.id,
                                onChange: () => setSelectedGateway(gw.id)
                            }),
                            h('span', { style: { fontWeight: '600' } }, gw.title)
                        )
                    )
                ),
                h('div', { className: 'ap-wizard-footer' },
                    h('button', { className: 'ap-nav-btn ap-btn-back', onClick: () => setCurrentStep(5) }, __('← Back', 'appointpro')),
                    h('button', {
                        className: 'ap-nav-btn ap-btn-next',
                        disabled: loading,
                        onClick: handleFinalSubmit
                    }, loading ? __('Processing...', 'appointpro') : __('Confirm Booking ✓', 'appointpro'))
                )
            ),

            // STEP 8: Confirmation Screen
            currentStep === 8 && bookingResult && h('div', { className: 'ap-success-card' },
                h('div', { className: 'ap-success-icon' }, '✓'),
                h('h3', null, __('Booking Confirmed!', 'appointpro')),
                h('p', { style: { color: '#64748b' } }, __('Thank you! Your appointment has been scheduled successfully.', 'appointpro')),
                h('div', { className: 'ap-ref-badge' }, bookingResult.booking_reference),
                h('div', { style: { marginTop: '24px' } },
                    h('p', null, `${__('Service:', 'appointpro')} ${selectedService.name}`),
                    h('p', null, `${__('Date & Time:', 'appointpro')} ${selectedSlot.formatted_time || selectedSlot.time}`),
                    h('button', {
                        className: 'ap-btn ap-btn-secondary',
                        style: { marginTop: '16px' },
                        onClick: () => window.print()
                    }, __('🖨 Print Confirmation', 'appointpro'))
                )
            )
        );
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('appointpro-booking-wizard-root');
        if (root && window.wp && window.wp.element) {
            window.wp.element.render(h(BookingWizard), root);
        }
    });
})();
