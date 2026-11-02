import React, { useState, useEffect } from 'react';

export default function App() {
    const [tab, setTab] = useState('dashboard');

    return (
        <div className="ap-admin-container">
            <header className="ap-header">
                <h1>AppointPro Dashboard</h1>
            </header>
            <nav className="ap-nav-tabs">
                <button className={`ap-nav-tab ${tab === 'dashboard' ? 'active' : ''}`} onClick={() => setTab('dashboard')}>Dashboard</button>
                <button className={`ap-nav-tab ${tab === 'calendar' ? 'active' : ''}`} onClick={() => setTab('calendar')}>Calendar</button>
                <button className={`ap-nav-tab ${tab === 'appointments' ? 'active' : ''}`} onClick={() => setTab('appointments')}>Appointments</button>
                <button className={`ap-nav-tab ${tab === 'services' ? 'active' : ''}`} onClick={() => setTab('services')}>Services</button>
                <button className={`ap-nav-tab ${tab === 'staff' ? 'active' : ''}`} onClick={() => setTab('staff')}>Staff</button>
                <button className={`ap-nav-tab ${tab === 'settings' ? 'active' : ''}`} onClick={() => setTab('settings')}>Settings</button>
            </nav>
        </div>
    );
}
