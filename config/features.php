<?php

return [
    // Phase 1 — waiter & cashier tasks ke MySQL
    'mysql_waiter_tasks' => env('FEATURE_MYSQL_WAITER_TASKS', false),
    'active_waiter_task_node' => env('FEATURE_ACTIVE_WAITER_TASK_NODE', false),
    'mysql_cashier_tasks' => env('FEATURE_MYSQL_CASHIER_TASKS', false),

    // Phase 2 — orders
    'mysql_orders' => env('FEATURE_MYSQL_ORDERS', false),
    'active_orders_node' => env('FEATURE_ACTIVE_ORDERS_NODE', false),

    // Phase 2/3 — report, attendance, bonus, master data
    'mysql_attendance' => env('FEATURE_MYSQL_ATTENDANCE', false),
    'mysql_bonus' => env('FEATURE_MYSQL_BONUS', false),
    'mysql_master_data' => env('FEATURE_MYSQL_MASTER_DATA', false),
    'mysql_audit_logs' => env('FEATURE_MYSQL_AUDIT_LOGS', false),
    'mysql_activity_reports' => env('FEATURE_MYSQL_ACTIVITY_REPORTS', false),
    'mysql_product_categories' => env('FEATURE_MYSQL_PRODUCT_CATEGORIES', false),
    'mysql_work_shifts' => env('FEATURE_MYSQL_WORK_SHIFTS', false),
    'mysql_bonus_summary' => env('FEATURE_MYSQL_BONUS_SUMMARY', false),
    'mysql_penalties' => env('FEATURE_MYSQL_PENALTIES', false),
];
