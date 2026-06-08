<?php

namespace App\Services;

use App\Models\RestockOrder;
use Illuminate\Support\Collection;

class RestockService
{
    /**
     * Get pending/in_progress restock orders.
     */
    public function pending(): Collection
    {
        return RestockOrder::whereIn('status', ['pending', 'in_progress'])
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get restock orders by rack.
     */
    public function forRack(string $rackId, ?string $status = null): Collection
    {
        $query = RestockOrder::where('rack_id', $rackId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get restock orders by date range.
     */
    public function forDateRange(string $from, string $to): Collection
    {
        return RestockOrder::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Find by ID.
     */
    public function find(int $id): ?RestockOrder
    {
        return RestockOrder::find($id);
    }

    /**
     * Create restock order (from rack check result).
     */
    public function create(array $data): RestockOrder
    {
        return RestockOrder::create($data);
    }

    /**
     * Bulk create from rack check task results.
     */
    public function createFromTaskResults(array $shortages, array $taskMeta): int
    {
        $created = 0;
        foreach ($shortages as $item) {
            RestockOrder::create([
                'source_task_id' => $taskMeta['task_id'] ?? null,
                'source_task_legacy_key' => $taskMeta['task_legacy_key'] ?? null,
                'rack_id' => $taskMeta['rack_id'] ?? '',
                'rack_name' => $taskMeta['rack_name'] ?? '',
                'product_id' => $item['product_id'] ?? '',
                'product_name' => $item['product_name'] ?? '',
                'unit' => $item['unit'] ?? 'pcs',
                'standard_qty' => (int) ($item['standard_qty'] ?? 0),
                'actual_qty' => (int) ($item['actual_qty'] ?? 0),
                'needed_qty' => (int) ($item['needed_qty'] ?? 0),
                'status' => 'pending',
                'priority' => $this->calculatePriority($item),
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Update restock order status.
     */
    public function updateStatus(int $id, string $status, ?array $extra = []): ?RestockOrder
    {
        $order = RestockOrder::find($id);
        if (! $order) {
            return null;
        }

        $data = ['status' => $status];

        if ($status === 'done') {
            $data['fulfilled_at'] = now();
            $data['fulfilled_qty'] = $extra['fulfilled_qty'] ?? $order->needed_qty;
            $data['fulfilled_by'] = $extra['fulfilled_by'] ?? null;
            $data['fulfilled_by_name'] = $extra['fulfilled_by_name'] ?? null;
        }

        if ($status === 'cancelled') {
            $data['cancelled_at'] = now();
            $data['cancel_reason'] = $extra['cancel_reason'] ?? null;
        }

        $order->update($data);

        return $order->fresh();
    }

    /**
     * Get summary stats.
     */
    public function stats(): array
    {
        return [
            'pending' => RestockOrder::where('status', 'pending')->count(),
            'in_progress' => RestockOrder::where('status', 'in_progress')->count(),
            'done_today' => RestockOrder::where('status', 'done')
                ->whereDate('fulfilled_at', today())
                ->count(),
        ];
    }

    /**
     * Calculate priority based on shortage severity.
     */
    protected function calculatePriority(array $item): string
    {
        $standardQty = (int) ($item['standard_qty'] ?? 0);
        $actualQty = (int) ($item['actual_qty'] ?? 0);

        if ($standardQty === 0) {
            return 'normal';
        }

        $ratio = $actualQty / $standardQty;

        if ($ratio <= 0.2) {
            return 'urgent';
        }
        if ($ratio <= 0.5) {
            return 'high';
        }

        return 'normal';
    }
}
