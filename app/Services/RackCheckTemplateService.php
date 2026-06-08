<?php

namespace App\Services;

use App\Models\WaiterTaskTemplate;
use Illuminate\Support\Collection;

class RackCheckTemplateService
{
    /**
     * Get all rack_check templates, sorted: active first, then by created_at DESC.
     */
    public function all(): Collection
    {
        return WaiterTaskTemplate::where('task_type', 'rack_check')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get all active rack_check templates.
     */
    public function allActive(): Collection
    {
        return WaiterTaskTemplate::where('task_type', 'rack_check')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Find template by ID or firebase_legacy_key.
     */
    public function find(string $id): ?WaiterTaskTemplate
    {
        return WaiterTaskTemplate::where('firebase_legacy_key', $id)
            ->orWhere('id', $id)
            ->first();
    }

    /**
     * Create a new rack_check template.
     */
    public function create(array $data): WaiterTaskTemplate
    {
        $data['task_type'] = 'rack_check';

        return WaiterTaskTemplate::create($data);
    }

    /**
     * Update a template.
     */
    public function update(string $id, array $data): WaiterTaskTemplate
    {
        $template = $this->find($id);
        if (! $template) {
            throw new \RuntimeException("Template {$id} tidak ditemukan.");
        }

        $template->update($data);

        return $template->fresh();
    }

    /**
     * Toggle active status.
     */
    public function toggle(string $id): WaiterTaskTemplate
    {
        $template = $this->find($id);
        if (! $template) {
            throw new \RuntimeException("Template {$id} tidak ditemukan.");
        }

        $template->update(['is_active' => ! $template->is_active]);

        return $template->fresh();
    }

    /**
     * Delete template.
     */
    public function delete(string $id): bool
    {
        $template = $this->find($id);
        if (! $template) {
            return false;
        }

        return (bool) $template->delete();
    }

    /**
     * Get locked rack map: rak yang sudah punya template aktif.
     * Optionally exclude a template ID (for edit form).
     */
    public function getLockedRackMap(?string $excludeTemplateId = null): array
    {
        $query = WaiterTaskTemplate::where('task_type', 'rack_check')
            ->where('is_active', true);

        if ($excludeTemplateId) {
            $query->where('id', '!=', $excludeTemplateId)
                ->where('firebase_legacy_key', '!=', $excludeTemplateId);
        }

        $templates = $query->get();
        $lockedRackMap = [];

        foreach ($templates as $tpl) {
            $racks = $tpl->normalizedRacks();
            foreach ($racks as $rack) {
                $rackId = (string) ($rack['id'] ?? '');
                if ($rackId === '') {
                    continue;
                }
                $lockedRackMap[$rackId] = [
                    'template_id' => (string) $tpl->id,
                    'strategy' => (string) $tpl->assignment_strategy,
                    'rack_name' => (string) ($rack['name'] ?? $rackId),
                ];
            }
        }

        return $lockedRackMap;
    }
}
