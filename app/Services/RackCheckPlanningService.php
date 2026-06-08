<?php

namespace App\Services;

use App\Models\RackCheckPlan;
use Illuminate\Support\Collection;

class RackCheckPlanningService
{
    /**
     * Get plans for a specific date.
     */
    public function forDate(string $date): Collection
    {
        return RackCheckPlan::where('plan_date', $date)
            ->orderBy('rack_name')
            ->get();
    }

    /**
     * Get plans for a waiter on a specific date.
     */
    public function forWaiterOnDate(string $waiterId, string $date): Collection
    {
        return RackCheckPlan::where('waiter_id', $waiterId)
            ->where('plan_date', $date)
            ->orderBy('rack_name')
            ->get();
    }

    /**
     * Get plans by template.
     */
    public function forTemplate(int $templateId, ?string $date = null): Collection
    {
        $query = RackCheckPlan::where('template_id', $templateId);

        if ($date) {
            $query->where('plan_date', $date);
        }

        return $query->orderByDesc('plan_date')->get();
    }

    /**
     * Create a plan entry.
     */
    public function create(array $data): RackCheckPlan
    {
        return RackCheckPlan::create($data);
    }

    /**
     * Bulk create plans (for batch assign).
     */
    public function bulkCreate(array $plans): int
    {
        $created = 0;
        foreach ($plans as $plan) {
            RackCheckPlan::create($plan);
            $created++;
        }

        return $created;
    }

    /**
     * Update a plan.
     */
    public function update(int $id, array $data): ?RackCheckPlan
    {
        $plan = RackCheckPlan::find($id);
        if (! $plan) {
            return null;
        }

        $plan->update($data);

        return $plan->fresh();
    }

    /**
     * Delete a plan.
     */
    public function delete(int $id): bool
    {
        $plan = RackCheckPlan::find($id);
        if (! $plan) {
            return false;
        }

        return (bool) $plan->delete();
    }

    /**
     * Cancel all plans for a date.
     */
    public function cancelForDate(string $date, ?string $reason = null): int
    {
        return RackCheckPlan::where('plan_date', $date)
            ->where('status', 'planned')
            ->update([
                'status' => 'cancelled',
                'skip_reason' => $reason ?? 'Dibatalkan admin',
            ]);
    }

    /**
     * Get assignment summary: waiter => [racks assigned count] for a date.
     */
    public function assignmentSummary(string $date): array
    {
        return RackCheckPlan::where('plan_date', $date)
            ->where('status', 'planned')
            ->selectRaw('waiter_id, waiter_name, COUNT(*) as rack_count')
            ->groupBy('waiter_id', 'waiter_name')
            ->get()
            ->keyBy('waiter_id')
            ->toArray();
    }
}
