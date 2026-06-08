<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $entity = $request->query('entity');
        $adminId = $request->query('admin_id');

        $query = AuditLog::forDate($date);

        if ($entity) {
            $query->forEntity($entity);
        }

        if ($adminId) {
            $query->where('admin_id', $adminId);
        }

        $logs = $query->orderByDesc('created_at')->get()->map(function ($log) {
            $arr = $log->toArray();
            $arr['timestamp'] = $log->created_at ? $log->created_at->timestamp : 0;
            return $arr;
        })->toArray();

        // Get unique entities and admins for filter dropdowns
        $entities = array_unique(array_column($logs, 'entity'));
        $admins = [];
        foreach ($logs as $log) {
            $admins[$log['admin_id'] ?? ''] = $log['admin_name'] ?? 'Unknown';
        }

        return view('admin.audit.index', compact('logs', 'date', 'entity', 'adminId', 'entities', 'admins'));
    }
}
