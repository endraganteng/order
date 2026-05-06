<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private FirebaseService $firebase)
    {
    }

    public function index(Request $request)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $entity = $request->query('entity');
        $adminId = $request->query('admin_id');

        $logs = $this->firebase->getAuditLogs($date, $entity, $adminId);

        // Get unique entities and admins for filter dropdowns
        $entities = array_unique(array_column($logs, 'entity'));
        $admins = [];
        foreach ($logs as $log) {
            $admins[$log['admin_id'] ?? ''] = $log['admin_name'] ?? 'Unknown';
        }

        return view('admin.audit.index', compact('logs', 'date', 'entity', 'adminId', 'entities', 'admins'));
    }
}
