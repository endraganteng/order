<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaiterTaskTemplate extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'title',
        'name',
        'description',
        'task_type',
        'priority',
        'assignment_type',
        'assignment_strategy',
        'assigned_waiter_id',
        'assigned_waiter_name',
        'assigned_waiter_role',
        'selected_waiter_ids',
        'simple_lowest_load_enabled',
        'rolling_enabled',
        'rolling_waiter_ids',
        'rolling_period',
        'rolling_anchor_date',
        'schedule_mode',
        'schedule_time',
        'time_limit_minutes',
        'deadline_mode',
        'deadline_before_end_minutes',
        'shift_offset_minutes',
        'target_shift_id',
        'recurrence_type',
        'weekly_day',
        'interval_days',
        'recurrence_anchor_date',
        'rack_id',
        'rack_name',
        'rack_location',
        'rack_barcode_value',
        'rack_type',
        'rack_target_scope',
        'racks',
        'is_active',
        'requires_barcode_scan',
        'requires_photo_before',
        'requires_photo_proof',
        'allow_note',
        'enable_empty_product_report',
        'skip_when_no_eligible_waiter',
        'daily_cap_mode',
        'full_shift_daily_cap',
        'partial_shift_daily_cap',
        'category_id',
        'category_name',
        'assigned_by',
    ];

    protected $casts = [
        'selected_waiter_ids' => 'array',
        'rolling_waiter_ids' => 'array',
        'racks' => 'array',
        'is_active' => 'boolean',
        'simple_lowest_load_enabled' => 'boolean',
        'rolling_enabled' => 'boolean',
        'requires_barcode_scan' => 'boolean',
        'requires_photo_before' => 'boolean',
        'requires_photo_proof' => 'boolean',
        'allow_note' => 'boolean',
        'enable_empty_product_report' => 'boolean',
        'skip_when_no_eligible_waiter' => 'boolean',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(RackCheckPlan::class, 'template_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WaiterTask::class, 'template_id');
    }

    /**
     * Normalize racks: support multi-rak (racks JSON) dan legacy single-rak fields.
     */
    public function normalizedRacks(): array
    {
        if (! empty($this->racks) && is_array($this->racks)) {
            return $this->racks;
        }

        if (! empty($this->rack_id)) {
            return [[
                'id' => $this->rack_id,
                'name' => $this->rack_name ?? '',
                'location' => $this->rack_location ?? '',
                'barcode_value' => $this->rack_barcode_value ?? '',
                'rack_type' => $this->rack_type ?? 'storage',
            ]];
        }

        return [];
    }
}
