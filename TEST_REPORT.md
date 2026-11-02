# TEST REPORT – AppointPro

## 1. Test Execution Summary
- **Execution Date**: 2026-11-02 17:45 UTC
- **Test Framework**: PHPUnit 10.5.66 on PHP 8.2.12
- **Test Suites**: Unit & Integration
- **Total Tests**: 21
- **Total Assertions**: 82
- **Failures / Errors**: 0
- **Status**: 100% PASSED

---

## 2. Test Suite Matrix

| Module / Component | Category | Test Method | Assertions | Status |
|---|---|---|---|---|
| Pricing Calculation | Unit | `testBasePriceAndDepositCalculation` | 7 | PASS |
| Pricing (Capacity) | Unit | `testGroupBookingCapacityMultiplier` | 4 | PASS |
| Duration Formatting | Unit | `testFormattingMinutesOnly` | 1 | PASS |
| Duration Formatting | Unit | `testFormattingExactHours` | 1 | PASS |
| Duration Formatting | Unit | `testFormattingHoursAndMinutes` | 2 | PASS |
| Timezone | Unit | `testToUtcConversion` | 1 | PASS |
| Timezone | Unit | `testToLocalConversion` | 1 | PASS |
| Timezone | Unit | `testIntervalsOverlapDetection` | 2 | PASS |
| Recurrence | Unit | `testWeeklyRecurrenceGeneration` | 5 | PASS |
| Recurrence | Unit | `testDailyRecurrenceGeneration` | 4 | PASS |
| Security | Unit | `testNonceValidation` | 4 | PASS |
| Security | Unit | `testInputSanitization` | 3 | PASS |
| Security | Unit | `testSecureTokenGeneration` | 3 | PASS |
| Security | Unit | `testRateLimiting` | 3 | PASS |
| Coupons | Unit | `testPercentageDiscountCalculation` | 2 | PASS |
| Coupons | Unit | `testMinimumOrderAmountEnforcement` | 2 | PASS |
| Availability | Unit | `testAvailabilityWithWorkingScheduleAndBreaks` | 1 | PASS |
| Appointment Lifecycle | Integration | `testAppointmentCreationAndTransitions` | 4 | PASS |
| Concurrency Lock | Integration | `testSlotReservationAndRelease` | 1 | PASS |
| Database Migrations | Integration | `testSchemaContainsAllRequiredTables` | 32 | PASS |
| Database Migrations | Integration | `testMigrationRunsIdempotently` | 2 | PASS |

---

## 3. Defect & Resolution Log
1. **Defect**: Syntax typing in `tests/bootstrap.php` for `$action` parameter default `-1` with `string` type hint.  
   **Fix**: Updated parameter type to `string|int $action = -1` matching WordPress core signature.
2. **Defect**: `ARRAY_A` and `OBJECT` constant scoping in namespaced classes.  
   **Fix**: Ensured global definition in bootstrap and main plugin bootstrap.
3. **Defect**: Schema array key typo `appointpro_coupons\`` with trailing backtick.  
   **Fix**: Removed stray backtick from schema definition key.
4. **Defect**: Mock `$wpdb->get_row()` returning falsy on unpersisted mock rows.  
   **Fix**: Improved `MockWPDB` lookup mechanics to accurately match inserted test records by ID.
