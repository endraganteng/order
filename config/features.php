<?php

return [
    // Phase 1 — waiter & cashier tasks ke MySQL
    'mysql_waiter_tasks' => env('FEATURE_MYSQL_WAITER_TASKS', true),
    'active_waiter_task_node' => env('FEATURE_ACTIVE_WAITER_TASK_NODE', false),
    'mysql_cashier_tasks' => env('FEATURE_MYSQL_CASHIER_TASKS', true),

    // Phase 2 — orders
    'mysql_orders' => env('FEATURE_MYSQL_ORDERS', false),
    'active_orders_node' => env('FEATURE_ACTIVE_ORDERS_NODE', false),

    // Phase 2/3 — report, attendance, bonus, master data
    'mysql_attendance' => env('FEATURE_MYSQL_ATTENDANCE', true),
    'mysql_bonus' => env('FEATURE_MYSQL_BONUS', false),
    'mysql_master_data' => env('FEATURE_MYSQL_MASTER_DATA', false),
    'mysql_audit_logs' => env('FEATURE_MYSQL_AUDIT_LOGS', true),
    'mysql_activity_reports' => env('FEATURE_MYSQL_ACTIVITY_REPORTS', true),
    'mysql_product_categories' => env('FEATURE_MYSQL_PRODUCT_CATEGORIES', true),
    'mysql_work_shifts' => env('FEATURE_MYSQL_WORK_SHIFTS', true),
    'mysql_bonus_summary' => env('FEATURE_MYSQL_BONUS_SUMMARY', true),
    'mysql_penalties' => env('FEATURE_MYSQL_PENALTIES', true),
    'mysql_manual_bonuses' => env('FEATURE_MYSQL_MANUAL_BONUSES', true),

    // Phase 4 — kill-switch tulis ke node Firebase legacy.
    // Default true: dual-write tetap mirror RTDB (jaring pengaman transisi).
    // Set false PER NODE setelah cleanup tuntas + monitor stabil -> tulis
    // hanya ke MySQL, RTDB legacy tak di-regenerate. Tujuan akhir plan:
    // RTDB cuma untuk fitur realtime; sisanya MySQL only.
    'legacy_write_waiter_tasks' => env('LEGACY_WRITE_WAITER_TASKS', true),
    'legacy_write_cashier_tasks' => env('LEGACY_WRITE_CASHIER_TASKS', true),
    'legacy_write_audit_logs' => env('LEGACY_WRITE_AUDIT_LOGS', false),
    'legacy_write_activity_reports' => env('LEGACY_WRITE_ACTIVITY_REPORTS', false),
    'legacy_write_product_categories' => env('LEGACY_WRITE_PRODUCT_CATEGORIES', false),
    'legacy_write_work_shifts' => env('LEGACY_WRITE_WORK_SHIFTS', false),
    'legacy_write_bonus_summary' => env('LEGACY_WRITE_BONUS_SUMMARY', true),
    'legacy_write_penalties' => env('LEGACY_WRITE_PENALTIES', true),
    'legacy_write_manual_bonuses' => env('LEGACY_WRITE_MANUAL_BONUSES', false),
    'legacy_write_attendance' => env('LEGACY_WRITE_ATTENDANCE', true),
];
