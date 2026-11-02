/**
 * AppointPro Admin Application Bundle
 * Built on WordPress wp.element (React 18)
 */
(function () {
    const { createElement: h, useState, useEffect, useCallback } = window.wp.element;
    const { __ } = window.wp.i18n;

    function App() {
        const rootEl = document.getElementById('appointpro-admin-root');
        const initialPage = (rootEl && rootEl.dataset.currentPage) ? rootEl.dataset.currentPage.replace('appointpro-', '') : 'dashboard';
        const [activeTab, setActiveTab] = useState(initialPage === 'appointpro' ? 'dashboard' : initialPage);
        const [dashboardData, setDashboardData] = useState(null);
        const [servicesData, setServicesData] = useState([]);
        const [staffData, setStaffData] = useState([]);
        const [appointmentsData, setAppointmentsData] = useState([]);
        const [customersData, setCustomersData] = useState([]);
        const [locationsData, setLocationsData] = useState([]);
        const [resourcesData, setResourcesData] = useState([]);
        const [couponsData, setCouponsData] = useState([]);
        const [paymentsData, setPaymentsData] = useState([]);
        const [settingsData, setSettingsData] = useState(null);
        const [loading, setLoading] = useState(false);
        const [notice, setNotice] = useState(null);

        // API Fetch Helper
        const apiFetch = useCallback(async (endpoint, options = {}) => {
            const config = window.appointProConfig || {};
            const url = (config.apiUrl || '/wp-json/appointpro/v1/') + endpoint.replace(/^\//, '');
            const defaultHeaders = {
                'X-WP-Nonce': config.nonce || '',
                'Content-Type': 'application/json',
            };
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: { ...defaultHeaders, ...(options.headers || {}) },
                });
                return await response.json();
            } catch (err) {
                console.error('AppointPro API Error:', err);
                return { success: false, message: err.message };
            }
        }, []);

        // Load active tab data
        const loadTabData = useCallback(async (tab) => {
            setLoading(true);
            if (tab === 'dashboard' || tab === 'reports') {
                const res = await apiFetch('dashboard');
                if (res && res.success) setDashboardData(res.data);
            } else if (tab === 'services' || tab === 'categories') {
                const res = await apiFetch('services');
                if (res && res.success) setServicesData(res.data);
            } else if (tab === 'staff') {
                const res = await apiFetch('staff');
                if (res && res.success) setStaffData(res.data);
            } else if (tab === 'appointments' || tab === 'calendar') {
                const res = await apiFetch('appointments');
                if (res && res.success) setAppointmentsData(res.data);
            } else if (tab === 'customers') {
                const res = await apiFetch('customers');
                if (res && res.success) setCustomersData(res.data.items || []);
            } else if (tab === 'locations') {
                const res = await apiFetch('locations');
                if (res && res.success) setLocationsData(res.data || []);
            } else if (tab === 'resources') {
                const res = await apiFetch('resources');
                if (res && res.success) setResourcesData(res.data || []);
            } else if (tab === 'coupons') {
                const res = await apiFetch('coupons');
                if (res && res.success) setCouponsData(res.data || []);
            } else if (tab === 'payments') {
                const res = await apiFetch('payments');
                if (res && res.success) setPaymentsData(res.data || []);
            } else if (tab === 'settings' || tab === 'ai') {
                const res = await apiFetch('settings');
                if (res && res.success) setSettingsData(res.data || null);
            }
            setLoading(false);
        }, [apiFetch]);

        useEffect(() => {
            loadTabData(activeTab);
        }, [activeTab, loadTabData]);

        const tabs = [
            { id: 'dashboard', label: __('Dashboard', 'appointpro') },
            { id: 'calendar', label: __('Calendar', 'appointpro') },
            { id: 'appointments', label: __('Appointments', 'appointpro') },
            { id: 'services', label: __('Services', 'appointpro') },
            { id: 'staff', label: __('Staff', 'appointpro') },
            { id: 'customers', label: __('Customers', 'appointpro') },
            { id: 'locations', label: __('Locations', 'appointpro') },
            { id: 'resources', label: __('Resources', 'appointpro') },
            { id: 'payments', label: __('Payments', 'appointpro') },
            { id: 'coupons', label: __('Coupons', 'appointpro') },
            { id: 'reports', label: __('Reports', 'appointpro') },
            { id: 'ai', label: __('AI Assistant', 'appointpro') },
            { id: 'settings', label: __('Settings', 'appointpro') },
        ];

        return h('div', { className: 'ap-admin-container' },
            // Header
            h('div', { className: 'ap-header' },
                h('div', { className: 'ap-header-brand' },
                    h('h1', null, 'AppointPro'),
                    h('span', { className: 'ap-header-badge' }, 'v' + (window.appointProConfig?.version || '1.0.0'))
                ),
                h('div', { className: 'ap-header-actions' },
                    h('button', {
                        className: 'ap-btn ap-btn-secondary ap-btn-sm',
                        onClick: () => loadTabData(activeTab)
                    }, __('Refresh Data', 'appointpro'))
                )
            ),

            // Navigation Tabs
            h('div', { className: 'ap-nav-tabs' },
                tabs.map(tab => h('button', {
                    key: tab.id,
                    className: 'ap-nav-tab ' + (activeTab === tab.id ? 'active' : ''),
                    onClick: () => setActiveTab(tab.id)
                }, tab.label))
            ),

            // Loading state
            loading && h('div', { style: { padding: '20px', textAlign: 'center', color: '#64748b' } },
                __('Loading...', 'appointpro')
            ),

            // Tab View: Dashboard
            !loading && activeTab === 'dashboard' && dashboardData && h('div', { className: 'ap-admin-view' },
                h('div', { className: 'ap-stats-grid' },
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Total Appointments', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, dashboardData.total_appointments),
                        h('span', { className: 'ap-stat-sub' }, `${dashboardData.today_appointments} ${__('today', 'appointpro')}`)
                    ),
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Upcoming Bookings', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, dashboardData.upcoming_appointments),
                        h('span', { className: 'ap-stat-sub' }, `${dashboardData.pending_appointments} ${__('pending review', 'appointpro')}`)
                    ),
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Total Revenue', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, `$${Number(dashboardData.total_revenue).toFixed(2)}`),
                        h('span', { className: 'ap-stat-sub' }, `$${Number(dashboardData.collected_payments).toFixed(2)} ${__('collected', 'appointpro')}`)
                    ),
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Total Customers', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, dashboardData.total_customers),
                        h('span', { className: 'ap-stat-sub' }, `${dashboardData.total_staff} ${__('active staff', 'appointpro')}`)
                    )
                ),

                h('div', { style: { marginTop: '24px' }, className: 'ap-card' },
                    h('div', { className: 'ap-card-header' },
                        h('h3', { className: 'ap-card-title' }, __('Upcoming Appointments', 'appointpro'))
                    ),
                    dashboardData.upcoming_list && dashboardData.upcoming_list.length > 0
                        ? h('div', { className: 'ap-table-wrap' },
                            h('table', { className: 'ap-table' },
                                h('thead', null,
                                    h('tr', null,
                                        h('th', null, __('Reference', 'appointpro')),
                                        h('th', null, __('Service', 'appointpro')),
                                        h('th', null, __('Staff', 'appointpro')),
                                        h('th', null, __('Date & Time', 'appointpro')),
                                        h('th', null, __('Status', 'appointpro')),
                                        h('th', null, __('Price', 'appointpro'))
                                    )
                                ),
                                h('tbody', null,
                                    dashboardData.upcoming_list.map(appt =>
                                        h('tr', { key: appt.id },
                                            h('td', null, h('strong', null, appt.booking_reference)),
                                            h('td', null, appt.service_name),
                                            h('td', null, appt.staff_name),
                                            h('td', null, appt.formatted_start),
                                            h('td', null, h('span', { className: 'ap-badge ap-badge-' + appt.status }, appt.status)),
                                            h('td', null, `$${Number(appt.final_price).toFixed(2)}`)
                                        )
                                    )
                                )
                            )
                        )
                        : h('p', { style: { color: '#64748b' } }, __('No upcoming appointments found.', 'appointpro'))
                )
            ),

            // Tab View: Appointments
            !loading && activeTab === 'appointments' && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('Appointments Management', 'appointpro'))
                ),
                appointmentsData.length > 0
                    ? h('div', { className: 'ap-table-wrap' },
                        h('table', { className: 'ap-table' },
                            h('thead', null,
                                h('tr', null,
                                    h('th', null, __('Ref', 'appointpro')),
                                    h('th', null, __('Client', 'appointpro')),
                                    h('th', null, __('Service', 'appointpro')),
                                    h('th', null, __('Staff', 'appointpro')),
                                    h('th', null, __('Time', 'appointpro')),
                                    h('th', null, __('Status', 'appointpro')),
                                    h('th', null, __('Payment', 'appointpro')),
                                    h('th', null, __('Actions', 'appointpro'))
                                )
                            ),
                            h('tbody', null,
                                appointmentsData.map(appt =>
                                    h('tr', { key: appt.id },
                                        h('td', null, h('strong', null, appt.booking_reference)),
                                        h('td', null, appt.customer_name || 'Guest'),
                                        h('td', null, appt.service_name || 'Service'),
                                        h('td', null, appt.staff_name || 'Staff'),
                                        h('td', null, appt.start),
                                        h('td', null, h('span', { className: 'ap-badge ap-badge-' + appt.status }, appt.status)),
                                        h('td', null, h('span', { className: 'ap-badge ap-badge-' + appt.payment_status }, appt.payment_status)),
                                        h('td', null,
                                            h('button', {
                                                className: 'ap-btn ap-btn-sm ap-btn-danger',
                                                onClick: async () => {
                                                    if (confirm(__('Cancel this appointment?', 'appointpro'))) {
                                                        await apiFetch(`appointments/${appt.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason: 'Admin cancelled' }) });
                                                        loadTabData('appointments');
                                                    }
                                                }
                                            }, __('Cancel', 'appointpro'))
                                        )
                                    )
                                )
                            )
                        )
                    )
                    : h('p', { style: { color: '#64748b' } }, __('No appointments scheduled yet.', 'appointpro'))
            ),

            // Tab View: Calendar
            !loading && activeTab === 'calendar' && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('Interactive Calendar', 'appointpro'))
                ),
                h('div', { className: 'ap-calendar-grid' },
                    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day =>
                        h('div', { key: day, className: 'ap-calendar-header-cell' }, day)
                    ),
                    Array.from({ length: 35 }).map((_, idx) => {
                        const dayNum = (idx % 31) + 1;
                        return h('div', { key: idx, className: 'ap-calendar-cell' },
                            h('span', { className: 'ap-calendar-date-number' }, dayNum)
                        );
                    })
                )
            ),

            // Tab View: Services
            !loading && activeTab === 'services' && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('Services & Pricing', 'appointpro'))
                ),
                servicesData.map(cat =>
                    h('div', { key: cat.id, style: { marginBottom: '24px' } },
                        h('h4', { style: { margin: '0 0 12px 0', color: cat.color || '#4f46e5' } }, cat.name),
                        h('div', { className: 'ap-table-wrap' },
                            h('table', { className: 'ap-table' },
                                h('thead', null,
                                    h('tr', null,
                                        h('th', null, __('Service Name', 'appointpro')),
                                        h('th', null, __('Duration', 'appointpro')),
                                        h('th', null, __('Price', 'appointpro')),
                                        h('th', null, __('Buffers (Pre/Post)', 'appointpro')),
                                        h('th', null, __('Status', 'appointpro'))
                                    )
                                ),
                                h('tbody', null,
                                    (cat.services || []).map(svc =>
                                        h('tr', { key: svc.id },
                                            h('td', null, h('strong', null, svc.name)),
                                            h('td', null, `${svc.duration} mins`),
                                            h('td', null, `$${Number(svc.price).toFixed(2)}`),
                                            h('td', null, `${svc.buffer_before}m / ${svc.buffer_after}m`),
                                            h('td', null, h('span', { className: 'ap-badge ap-badge-' + svc.status }, svc.status))
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            ),

            // Tab View: Staff
            !loading && activeTab === 'staff' && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('Staff Directory & Working Schedules', 'appointpro'))
                ),
                h('div', { className: 'ap-table-wrap' },
                    h('table', { className: 'ap-table' },
                        h('thead', null,
                            h('tr', null,
                                h('th', null, __('Staff Member', 'appointpro')),
                                h('th', null, __('Email', 'appointpro')),
                                h('th', null, __('Phone', 'appointpro')),
                                h('th', null, __('Auto Assign', 'appointpro')),
                                h('th', null, __('Status', 'appointpro'))
                            )
                        ),
                        h('tbody', null,
                            staffData.map(st =>
                                h('tr', { key: st.id },
                                    h('td', null, h('strong', null, st.full_name)),
                                    h('td', null, st.email),
                                    h('td', null, st.phone || '—'),
                                    h('td', null, st.auto_assign ? __('Yes', 'appointpro') : __('No', 'appointpro')),
                                    h('td', null, h('span', { className: 'ap-badge ap-badge-' + st.status }, st.status))
                                )
                            )
                        )
                    )
                )
            ),

            // Tab View: Customers
            !loading && activeTab === 'customers' && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('Customer CRM', 'appointpro'))
                ),
                h('div', { className: 'ap-table-wrap' },
                    h('table', { className: 'ap-table' },
                        h('thead', null,
                            h('tr', null,
                                h('th', null, __('Customer Name', 'appointpro')),
                                h('th', null, __('Email', 'appointpro')),
                                h('th', null, __('Phone', 'appointpro')),
                                h('th', null, __('Bookings', 'appointpro')),
                                h('th', null, __('Total Spent', 'appointpro'))
                            )
                        ),
                        h('tbody', null,
                            customersData.map(c =>
                                h('tr', { key: c.id },
                                    h('td', null, `${c.first_name} ${c.last_name}`),
                                    h('td', null, c.email),
                                    h('td', null, c.phone || '—'),
                                    h('td', null, c.total_bookings || 0),
                                    h('td', null, `$${Number(c.total_spent || 0).toFixed(2)}`)
                                )
                            )
                        )
                    )
                )
            ),

            // Tab View: Reports
            !loading && activeTab === 'reports' && dashboardData && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('Financial & Booking Analytics', 'appointpro')),
                    h('a', {
                        className: 'ap-btn ap-btn-primary ap-btn-sm',
                        href: (window.appointProConfig?.apiUrl || '/wp-json/appointpro/v1/') + 'reports/export?_wpnonce=' + (window.appointProConfig?.nonce || ''),
                        download: true
                    }, __('Export CSV', 'appointpro'))
                ),
                h('div', { className: 'ap-stats-grid' },
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Confirmed Bookings', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, dashboardData.confirmed_appointments)
                    ),
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Cancelled Bookings', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, dashboardData.cancelled_appointments)
                    ),
                    h('div', { className: 'ap-stat-card' },
                        h('span', { className: 'ap-stat-label' }, __('Outstanding Balance', 'appointpro')),
                        h('span', { className: 'ap-stat-value' }, `$${Number(dashboardData.outstanding_balance).toFixed(2)}`)
                    )
                )
            ),

            // Tab View: Settings
            !loading && activeTab === 'settings' && settingsData && h('div', { className: 'ap-card' },
                h('div', { className: 'ap-card-header' },
                    h('h3', { className: 'ap-card-title' }, __('General Settings', 'appointpro'))
                ),
                h('form', {
                    onSubmit: async (e) => {
                        e.preventDefault();
                        const formData = new FormData(e.target);
                        const general = {
                            business_name: formData.get('business_name'),
                            business_email: formData.get('business_email'),
                            currency: formData.get('currency'),
                            currency_symbol: formData.get('currency_symbol'),
                            timezone: formData.get('timezone'),
                            delete_data_on_uninstall: formData.get('delete_data_on_uninstall') ? 1 : 0
                        };
                        const res = await apiFetch('settings', {
                            method: 'POST',
                            body: JSON.stringify({ general })
                        });
                        if (res && res.success) {
                            alert(__('Settings saved successfully!', 'appointpro'));
                        }
                    }
                },
                    h('div', { className: 'ap-form-group' },
                        h('label', { className: 'ap-form-label' }, __('Business Name', 'appointpro')),
                        h('input', {
                            className: 'ap-form-input',
                            name: 'business_name',
                            defaultValue: settingsData.general?.business_name || ''
                        })
                    ),
                    h('div', { className: 'ap-form-group' },
                        h('label', { className: 'ap-form-label' }, __('Business Email', 'appointpro')),
                        h('input', {
                            className: 'ap-form-input',
                            type: 'email',
                            name: 'business_email',
                            defaultValue: settingsData.general?.business_email || ''
                        })
                    ),
                    h('div', { className: 'ap-form-row' },
                        h('div', { className: 'ap-form-group' },
                            h('label', { className: 'ap-form-label' }, __('Currency Code', 'appointpro')),
                            h('input', {
                                className: 'ap-form-input',
                                name: 'currency',
                                defaultValue: settingsData.general?.currency || 'USD'
                            })
                        ),
                        h('div', { className: 'ap-form-group' },
                            h('label', { className: 'ap-form-label' }, __('Currency Symbol', 'appointpro')),
                            h('input', {
                                className: 'ap-form-input',
                                name: 'currency_symbol',
                                defaultValue: settingsData.general?.currency_symbol || '$'
                            })
                        )
                    ),
                    h('div', { className: 'ap-form-group', style: { marginTop: '16px' } },
                        h('label', null,
                            h('input', {
                                type: 'checkbox',
                                name: 'delete_data_on_uninstall',
                                defaultChecked: !!settingsData.general?.delete_data_on_uninstall,
                                style: { marginRight: '8px' }
                            }),
                            __('Remove all AppointPro database tables and settings upon plugin deletion (Uninstall Cleanup)', 'appointpro')
                        )
                    ),
                    h('div', { style: { marginTop: '20px' } },
                        h('button', { type: 'submit', className: 'ap-btn ap-btn-primary' }, __('Save Settings', 'appointpro'))
                    )
                )
            )
        );
    }

    // Mount on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('appointpro-admin-root');
        if (root && window.wp && window.wp.element) {
            window.wp.element.render(h(App), root);
        }
    });
})();
