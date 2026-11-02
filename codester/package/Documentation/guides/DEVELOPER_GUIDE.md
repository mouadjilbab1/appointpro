# AppointPro – Developer Documentation

## Architecture & Code Standards
- **PHP Version**: 8.1+ strictly typed.
- **Root Namespace**: `AppointPro\`
- **Autoloading**: Composer PSR-4 mapped to `/includes/`.
- **Database Prefix**: Dynamic via `$wpdb->prefix . 'appointpro_'`.
- **IoC Container**: Access the central service container via `appointpro()->getContainer()`.

## Core Service Bindings
```php
// Retrieve services from container:
$servicesRepo = appointpro()->getContainer()->get('services.repository');
$staffRepo    = appointpro()->getContainer()->get('staff.repository');
$availEngine  = appointpro()->getContainer()->get('availability.engine');
$bookingEngine = appointpro()->getContainer()->get('booking.engine');
$paymentsMgr  = appointpro()->getContainer()->get('payments.manager');
```

## Custom Action Hooks & Filters

### Actions:
- `appointpro_appointment_created` ($appointmentId, $appointmentData)
- `appointpro_appointment_confirmed` ($appointmentId)
- `appointpro_appointment_cancelled` ($appointmentId, $reason)
- `appointpro_payment_completed` ($appointmentId, $amount, $gatewayId)

### Filters:
- `appointpro_calculated_price` ($price, $serviceId, $staffId, $numberOfPersons)
- `appointpro_available_slots` ($slots, $serviceId, $staffId, $date)
- `appointpro_notification_recipients` ($recipients, $event, $appointmentId)

## Custom Payment Gateways
Implement `AppointPro\Payments\GatewayInterface` and register your custom gateway with:
```php
add_action('init', function () {
    if (function_exists('appointpro')) {
        $payments = appointpro()->getContainer()->get('payments.manager');
        $payments->registerGateway(new MyCustomGateway());
    }
});
```
