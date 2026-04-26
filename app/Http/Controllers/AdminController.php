<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Show login form
     */
    public function showLogin()
    {
        if (session()->has('admin_authenticated')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Process login
     */
    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        if ($request->password === env('ADMIN_PASSWORD')) {
            session()->put('admin_authenticated', true);

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['password' => 'Password salah']);
    }

    /**
     * Logout
     */
    public function logout()
    {
        session()->forget('admin_authenticated');

        return redirect()->route('admin.login');
    }

    /**
     * Show dashboard
     */
    public function dashboard(Request $request)
    {
        $waiters = $this->firebase->getAllowedEmails();
        $settings = $this->firebase->getSettings();
        [$periodStartTs, $periodEndTs, $orderPeriodLabel, $startDate, $endDate, $dateRangeInput] = $this->resolveDashboardDateRangeFromRequest($request);
        $waiterIdentityDirectory = $this->buildWaiterIdentityDirectory($waiters);

        $userStats = $this->buildWaiterOrderStatsByPeriod(
            $this->firebase->getOrders(),
            $periodStartTs,
            $periodEndTs,
            $waiterIdentityDirectory
        );

        $waiterTaskRanking = $this->buildWaiterTaskCompletionRankingByPeriod(
            $this->firebase->getWaiterTasks(),
            $periodStartTs,
            $periodEndTs,
            $waiterIdentityDirectory
        );

        $orderStatsSummary = [
            'total_orders' => array_sum(array_map(function ($stat) {
                return (int) ($stat['order_count'] ?? 0);
            }, $userStats)),
            'waiter_with_orders' => count($userStats),
        ];

        return view('admin.dashboard', compact(
            'waiters',
            'settings',
            'userStats',
            'waiterTaskRanking',
            'orderPeriodLabel',
            'periodStartTs',
            'periodEndTs',
            'orderStatsSummary',
            'startDate',
            'endDate',
            'dateRangeInput'
        ));
    }

    /**
     * List waiters
     */
    public function waitersIndex()
    {
        $waiters = $this->firebase->getAllowedEmails();

        return view('admin.waiters.index', compact('waiters'));
    }

    /**
     * Show create waiter form
     */
    public function waitersCreate()
    {
        return view('admin.waiters.create');
    }

    /**
     * Store new waiter
     */
    public function waitersStore(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:6|max:100',
        ]);

        $email = strtolower(trim((string) $request->email));
        if ($this->isWaiterEmailAlreadyUsed($email)) {
            return back()
                ->withErrors(['email' => 'Email waiter sudah terdaftar. Gunakan email lain.'])
                ->withInput();
        }

        $passwordHash = $request->filled('password')
            ? Hash::make($request->password)
            : null;

        $this->firebase->addAllowedEmailWithPassword($email, $request->name, $passwordHash);

        return redirect()->route('admin.waiters.index')
            ->with('success', 'Waiter berhasil ditambahkan');
    }

    /**
     * Show edit waiter form
     */
    public function waitersEdit($id)
    {
        $waiters = $this->firebase->getAllowedEmails();
        $waiter = collect($waiters)->firstWhere('id', $id);

        if (! $waiter) {
            abort(404);
        }

        return view('admin.waiters.edit', compact('waiter'));
    }

    /**
     * Update waiter
     */
    public function waitersUpdate(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
            'password' => 'nullable|string|min:6|max:100',
        ]);

        $email = strtolower(trim((string) $request->email));
        if ($this->isWaiterEmailAlreadyUsed($email, (string) $id)) {
            return back()
                ->withErrors(['email' => 'Email waiter sudah digunakan akun lain.'])
                ->withInput();
        }

        $payload = [
            'email' => $email,
            'name' => $request->name,
            'is_active' => (bool) $request->is_active,
        ];

        if ($request->filled('password')) {
            $payload['password_hash'] = Hash::make($request->password);
        }

        $this->firebase->updateAllowedEmail($id, $payload);

        return redirect()->route('admin.waiters.index')
            ->with('success', 'Waiter berhasil diupdate');
    }

    /**
     * Delete waiter
     */
    public function waitersDestroy($id)
    {
        $this->firebase->deleteAllowedEmail($id);

        return redirect()->route('admin.waiters.index')
            ->with('success', 'Waiter berhasil dihapus');
    }

    /**
     * List rack master data.
     */
    public function racksIndex()
    {
        $racks = $this->firebase->getRacks();

        return view('admin.racks.index', compact('racks'));
    }

    /**
     * Show create rack form.
     */
    public function racksCreate()
    {
        return view('admin.racks.create');
    }

    /**
     * Store new rack.
     */
    public function racksStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'location' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $this->firebase->createRack([
            'name' => $request->name,
            'location' => $request->location,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.racks.index')
            ->with('success', 'Rak berhasil ditambahkan dan barcode otomatis digenerate.');
    }

    /**
     * Print rack barcode labels (single/selected/all).
     */
    public function racksPrintLabels(Request $request)
    {
        $selectedRacks = $this->resolveSelectedRacksFromRequest($request);

        if (count($selectedRacks) === 0) {
            return redirect()->route('admin.racks.index')
                ->with('error', 'Pilih minimal satu rak untuk print label barcode.');
        }

        $labelScope = $request->boolean('all') ? 'Semua Rak Aktif' : 'Rak Terpilih';

        return view('admin.racks.print_labels', [
            'racks' => $selectedRacks,
            'labelScope' => $labelScope,
            'printedAt' => time(),
        ]);
    }

    /**
     * Export selected/all rack barcodes to CSV.
     */
    public function racksExportBarcodes(Request $request)
    {
        $selectedRacks = $this->resolveSelectedRacksFromRequest($request);

        if (count($selectedRacks) === 0) {
            return redirect()->route('admin.racks.index')
                ->with('error', 'Pilih minimal satu rak untuk export barcode.');
        }

        $fileName = 'rack-barcodes-'.date('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($selectedRacks) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($output, ['Rack ID', 'Nama Rak', 'Lokasi', 'Barcode Value', 'Status']);

            foreach ($selectedRacks as $rack) {
                $status = (($rack['is_active'] ?? true) === true) ? 'Aktif' : 'Nonaktif';

                fputcsv($output, [
                    $this->sanitizeCsvCell((string) ($rack['id'] ?? '')),
                    $this->sanitizeCsvCell((string) ($rack['name'] ?? '')),
                    $this->sanitizeCsvCell((string) ($rack['location'] ?? '')),
                    $this->sanitizeCsvCell((string) ($rack['barcode_value'] ?? '')),
                    $status,
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Show edit rack form.
     */
    public function racksEdit($id)
    {
        $rack = $this->firebase->getRackById($id);
        if (! $rack) {
            abort(404);
        }

        return view('admin.racks.edit', compact('rack'));
    }

    /**
     * Update rack metadata.
     */
    public function racksUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'location' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'required|boolean',
        ]);

        $rack = $this->firebase->getRackById($id);
        if (! $rack) {
            abort(404);
        }

        $this->firebase->updateRack($id, [
            'name' => $request->name,
            'location' => $request->location,
            'description' => $request->description,
            'is_active' => (bool) $request->is_active,
        ]);

        return redirect()->route('admin.racks.index')
            ->with('success', 'Data rak berhasil diupdate.');
    }

    /**
     * Regenerate rack barcode value.
     */
    public function racksRegenerateBarcode($id)
    {
        $barcode = $this->firebase->regenerateRackBarcode($id);
        if (! $barcode) {
            abort(404);
        }

        return redirect()->route('admin.racks.index')
            ->with('success', 'Barcode rak berhasil digenerate ulang.');
    }

    /**
     * Delete rack.
     */
    public function racksDestroy($id)
    {
        $this->firebase->deleteRack($id);

        return redirect()->route('admin.racks.index')
            ->with('success', 'Rak berhasil dihapus.');
    }

    /**
     * Show settings form
     */
    public function showSettings()
    {
        $settings = $this->firebase->getSettings();

        return view('admin.settings', compact('settings'));
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'order_timeout_minutes' => 'required|integer|min:1',
        ]);

        $this->firebase->updateSettings([
            'order_timeout_minutes' => (int) $request->order_timeout_minutes,
        ]);

        return back()->with('success', 'Settings berhasil diupdate');
    }

    /**
     * Show test order form
     */
    public function showTestOrder()
    {
        return view('admin.test_order');
    }

    /**
     * Create test order
     */
    public function createTestOrder(Request $request)
    {
        $request->validate([
            'waiter_name' => 'required|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string',
            'products.*.price' => 'required|numeric|min:0',
        ]);

        $settings = $this->firebase->getSettings();
        $timeoutMinutes = $settings['order_timeout_minutes'] ?? 3;

        $orderData = [
            'waiter_id' => 'admin-test',
            'waiter_name' => $request->waiter_name,
            'waiter_email' => 'admin@test.com',
            'products' => array_map(function ($product) {
                return [
                    'name' => $product['name'],
                    'price' => (int) $product['price'],
                ];
            }, $request->products),
            'created_at' => time(),
            'expires_at' => time() + ($timeoutMinutes * 60),
        ];

        $this->firebase->createOrder($orderData);

        return back()->with('success', 'Test order berhasil dibuat!');
    }

    /**
     * Show latest current orders with filters.
     */
    public function currentOrdersIndex(Request $request)
    {
        $rawOrders = $this->firebase->getOrders();
        $masterWaiters = $this->firebase->getAllowedEmails();

        $todayDate = date('Y-m-d');
        $filterDateInput = trim((string) $request->input('filter_date', $todayDate));
        $filterDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateInput) === 1 ? $filterDateInput : $todayDate;
        $filterHourInput = trim((string) $request->input('filter_hour', ''));
        $filterTimeInput = trim((string) $request->input('filter_time', ''));
        $filterWaiter = trim((string) $request->input('filter_waiter', $request->input('filter_waiter_id', '')));
        $filterSearch = trim((string) $request->input('filter_search', ''));

        $filterHour = '';
        if ($filterHourInput !== '' && ctype_digit($filterHourInput)) {
            $hourInt = (int) $filterHourInput;
            if ($hourInt >= 0 && $hourInt <= 23) {
                $filterHour = str_pad((string) $hourInt, 2, '0', STR_PAD_LEFT);
            }
        }

        $filterTime = '';
        if ($filterTimeInput !== '' && preg_match('/^\d{2}:\d{2}$/', $filterTimeInput) === 1) {
            $filterTime = $filterTimeInput;
        }

        $orders = array_map(function ($order) {
            $createdAtTs = $this->normalizeOrderTimestamp($order['created_at'] ?? 0);
            $expiresAtTs = $this->normalizeOrderTimestamp($order['expires_at'] ?? 0);
            $queueNumber = (int) ($order['queue_number'] ?? 0);
            $waiterId = trim((string) ($order['waiter_id'] ?? ''));
            $waiterName = trim((string) ($order['waiter_name'] ?? ''));
            $waiterEmail = strtolower(trim((string) ($order['waiter_email'] ?? '')));
            $products = is_array($order['products'] ?? null) ? $order['products'] : [];
            $waiterFilterKey = '';
            if ($waiterEmail !== '') {
                $waiterFilterKey = 'email:'.$waiterEmail;
            } elseif ($waiterId !== '') {
                $waiterFilterKey = 'id:'.$waiterId;
            } elseif ($waiterName !== '') {
                $waiterFilterKey = 'name:'.strtolower($waiterName);
            }

            $normalizedProducts = [];
            $totalPrice = 0;
            foreach ($products as $product) {
                $name = trim((string) ($product['name'] ?? ''));
                $price = (int) ($product['price'] ?? 0);
                if ($name === '') {
                    continue;
                }

                $normalizedProducts[] = [
                    'name' => $name,
                    'price' => $price,
                ];
                $totalPrice += $price;
            }

            return array_merge($order, [
                'created_at_ts' => $createdAtTs,
                'expires_at_ts' => $expiresAtTs,
                'queue_number' => $queueNumber,
                'waiter_id' => $waiterId,
                'waiter_name' => $waiterName !== '' ? $waiterName : '-',
                'waiter_email' => $waiterEmail,
                'products' => $normalizedProducts,
                'product_count' => count($normalizedProducts),
                'total_price' => $totalPrice,
                'created_date' => $createdAtTs > 0 ? date('Y-m-d', $createdAtTs) : '',
                'created_hour' => $createdAtTs > 0 ? date('H', $createdAtTs) : '',
                'created_time' => $createdAtTs > 0 ? date('H:i', $createdAtTs) : '',
                'waiter_filter_key' => $waiterFilterKey,
            ]);
        }, $rawOrders);

        usort($orders, function ($a, $b) {
            $createdCompare = ((int) ($b['created_at_ts'] ?? 0)) <=> ((int) ($a['created_at_ts'] ?? 0));
            if ($createdCompare !== 0) {
                return $createdCompare;
            }

            return ((int) ($b['queue_number'] ?? 0)) <=> ((int) ($a['queue_number'] ?? 0));
        });

        $searchNeedle = strtolower($filterSearch);

        $filteredOrders = array_values(array_filter($orders, function ($order) use ($filterDate, $filterHour, $filterTime, $filterWaiter, $searchNeedle) {
            if ($filterDate !== '' && (string) ($order['created_date'] ?? '') !== $filterDate) {
                return false;
            }

            if ($filterHour !== '' && (string) ($order['created_hour'] ?? '') !== $filterHour) {
                return false;
            }

            if ($filterTime !== '' && (string) ($order['created_time'] ?? '') !== $filterTime) {
                return false;
            }

            if ($filterWaiter !== '' && (string) ($order['waiter_filter_key'] ?? '') !== $filterWaiter) {
                return false;
            }

            if ($searchNeedle !== '') {
                $productTokens = array_map(function ($product) {
                    $name = trim((string) ($product['name'] ?? ''));
                    $price = (string) ((int) ($product['price'] ?? 0));

                    return trim($name.' '.$price);
                }, is_array($order['products'] ?? null) ? $order['products'] : []);

                $haystack = strtolower(trim(implode(' ', array_filter([
                    (string) ($order['id'] ?? ''),
                    (string) ($order['queue_number'] ?? ''),
                    (string) ($order['waiter_id'] ?? ''),
                    (string) ($order['waiter_name'] ?? ''),
                    (string) ($order['waiter_email'] ?? ''),
                    (string) ($order['created_date'] ?? ''),
                    (string) ($order['created_time'] ?? ''),
                    implode(' ', $productTokens),
                ]))));

                if ($haystack === '' || strpos($haystack, $searchNeedle) === false) {
                    return false;
                }
            }

            return true;
        }));

        $waiterOptionMap = [];
        $upsertWaiterOption = function (array $waiterCandidate) use (&$waiterOptionMap) {
            $id = trim((string) ($waiterCandidate['id'] ?? $waiterCandidate['waiter_id'] ?? ''));
            $name = trim((string) ($waiterCandidate['name'] ?? $waiterCandidate['waiter_name'] ?? ''));
            $email = strtolower(trim((string) ($waiterCandidate['email'] ?? $waiterCandidate['waiter_email'] ?? '')));

            $key = '';
            if ($email !== '') {
                $key = 'email:'.$email;
            } elseif ($id !== '') {
                $key = 'id:'.$id;
            } elseif ($name !== '') {
                $key = 'name:'.strtolower($name);
            }

            if ($key === '') {
                return;
            }

            if (! isset($waiterOptionMap[$key])) {
                $waiterOptionMap[$key] = [
                    'key' => $key,
                    'id' => $id,
                    'name' => $name,
                    'email' => $email,
                ];

                return;
            }

            if ($waiterOptionMap[$key]['name'] === '' && $name !== '') {
                $waiterOptionMap[$key]['name'] = $name;
            }

            if ($waiterOptionMap[$key]['email'] === '' && $email !== '') {
                $waiterOptionMap[$key]['email'] = $email;
            }

            if ($waiterOptionMap[$key]['id'] === '' && $id !== '') {
                $waiterOptionMap[$key]['id'] = $id;
            }
        };

        foreach ($masterWaiters as $waiter) {
            $upsertWaiterOption($waiter);
        }

        foreach ($orders as $order) {
            $upsertWaiterOption($order);
        }

        $waiterOptions = array_values($waiterOptionMap);
        usort($waiterOptions, function ($a, $b) {
            $aLabel = strtolower(trim((string) (($a['name'] ?? '') !== '' ? $a['name'] : $a['email'])));
            $bLabel = strtolower(trim((string) (($b['name'] ?? '') !== '' ? $b['name'] : $b['email'])));

            return strcmp($aLabel, $bLabel);
        });

        $nowTs = time();
        $perPage = 20;
        $currentPage = max((int) $request->input('page', 1), 1);
        $filteredOrderCount = count($filteredOrders);
        $pagedOrders = array_slice($filteredOrders, ($currentPage - 1) * $perPage, $perPage);
        $ordersPaginator = new LengthAwarePaginator(
            $pagedOrders,
            $filteredOrderCount,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]
        );
        $hasCustomFilters = $filterDate !== $todayDate || $filterHour !== '' || $filterTime !== '' || $filterWaiter !== '' || $filterSearch !== '';

        return view('admin.current_order', [
            'orders' => $ordersPaginator,
            'totalOrderCount' => count($orders),
            'filteredOrderCount' => $filteredOrderCount,
            'waiterOptions' => $waiterOptions,
            'filterDate' => $filterDate,
            'filterHour' => $filterHour,
            'filterTime' => $filterTime,
            'filterWaiter' => $filterWaiter,
            'filterSearch' => $filterSearch,
            'hasCustomFilters' => $hasCustomFilters,
            'todayDate' => $todayDate,
            'nowTs' => $nowTs,
        ]);
    }

    /**
     * Show cleanup page
     */
    public function showCleanup()
    {
        $stats = $this->firebase->getCleanupStats();

        return view('admin.cleanup', compact('stats'));
    }

    /**
     * Process cleanup
     */
    public function processCleanup(Request $request)
    {
        $request->validate([
            'days_old' => 'required|integer|min:1|max:365',
        ]);

        $deletedCount = $this->firebase->cleanupOldOrders($request->days_old);

        return back()->with('success', "Berhasil menghapus {$deletedCount} order lama!");
    }

    // ========================================
    // Task Management (Supervisor Tasks)
    // ========================================

    /**
     * Show tasks list
     */
    public function tasksIndex(Request $request)
    {
        return $this->tasksIndexByScope($request, 'general');
    }

    /**
     * Show dedicated rack-check management list.
     */
    public function rackTasksIndex(Request $request)
    {
        return $this->tasksIndexByScope($request, 'rack_check');
    }

    /**
     * Reset all rack-check waiter data (tasks + recurring templates).
     */
    public function rackTasksReset()
    {
        try {
            $result = $this->firebase->resetRackCheckWaiterData();
            $deletedTasks = (int) ($result['deleted_tasks'] ?? 0);
            $deletedTemplates = (int) ($result['deleted_templates'] ?? 0);

            if ($deletedTasks === 0 && $deletedTemplates === 0) {
                return redirect()->route('admin.tasks.rack.index')
                    ->with('success', 'Data cek rak waiter sudah kosong. Tidak ada data yang direset.');
            }

            return redirect()->route('admin.tasks.rack.index')
                ->with('success', "Reset data cek rak berhasil. {$deletedTasks} task dan {$deletedTemplates} template berulang telah dihapus.");
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.tasks.rack.index')
                ->with('error', 'Reset data cek rak gagal diproses. Silakan coba lagi.');
        }
    }

    /**
     * Render tasks index by task scope.
     */
    protected function tasksIndexByScope(Request $request, string $taskScope)
    {
        $taskScope = $taskScope === 'rack_check' ? 'rack_check' : 'general';

        $this->firebase->generateDueRecurringWaiterTasks();
        $this->firebase->markOverdueWaiterTasks();
        $tasks = $this->firebase->getWaiterTasks();
        $recurringTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $waiters = $this->firebase->getActiveWaiters();
        $racks = $this->firebase->getRacks();

        $tasks = array_map(function ($task) {
            $task['tracking_date'] = $this->resolveTrackingDate($task);

            return $task;
        }, $tasks);

        $tasks = array_values(array_filter($tasks, function ($task) use ($taskScope) {
            return $this->matchesTaskScope($task, $taskScope);
        }));

        $recurringTemplates = array_values(array_filter($recurringTemplates, function ($template) use ($taskScope) {
            return $this->matchesTaskScope($template, $taskScope);
        }));

        $taskHistory = $tasks;

        $selectedDate = $request->input('track_date', date('Y-m-d'));
        $dateTasks = array_values(array_filter($tasks, function ($task) use ($selectedDate) {
            return ($task['tracking_date'] ?? '') === $selectedDate;
        }));
        $waiterActivityReports = $this->firebase->getWaiterActivityReportsByDate($selectedDate);
        $waiterActivityBoard = $this->buildWaiterActivityBoard($waiterActivityReports);

        $dateDoneTasks = array_values(array_filter($dateTasks, function ($task) {
            return ($task['status'] ?? '') === 'done';
        }));

        $dateNotDoneTasks = array_values(array_filter($dateTasks, function ($task) {
            return ($task['status'] ?? '') !== 'done';
        }));

        $dateWaiterTrackingBoard = $this->buildWaiterTrackingBoard($dateDoneTasks, $dateNotDoneTasks);

        $rackExecutionBoard = $this->buildRackExecutionBoard($dateTasks);
        $collectedStockBoard = $this->buildCollectedStockBoard($dateTasks);

        $waiterPerformance = $this->buildWaiterPerformance($tasks);

        $taskScopeRouteName = $taskScope === 'rack_check'
            ? 'admin.tasks.rack.index'
            : 'admin.tasks.index';

        $otherTaskScopeRouteName = $taskScope === 'rack_check'
            ? 'admin.tasks.index'
            : 'admin.tasks.rack.index';

        $taskScopeLabel = $taskScope === 'rack_check'
            ? 'Cek Rak'
            : 'Tugas Umum';

        $otherTaskScopeLabel = $taskScope === 'rack_check'
            ? 'Tugas Umum'
            : 'Cek Rak';

        return view('admin.tasks.index', compact(
            'tasks',
            'recurringTemplates',
            'waiters',
            'taskHistory',
            'selectedDate',
            'dateTasks',
            'dateDoneTasks',
            'dateNotDoneTasks',
            'dateWaiterTrackingBoard',
            'waiterActivityReports',
            'waiterActivityBoard',
            'racks',
            'rackExecutionBoard',
            'collectedStockBoard',
            'waiterPerformance',
            'taskScope',
            'taskScopeRouteName',
            'otherTaskScopeRouteName',
            'taskScopeLabel',
            'otherTaskScopeLabel'
        ));
    }

    /**
     * Add cashier worker master data
     */
    public function tasksCashierStore(Request $request)
    {
        $request->validate([
            'cashier_name' => 'required|string|max:100',
        ]);

        $this->firebase->addCashierWorker($request->cashier_name);

        return redirect()->route('admin.tasks.index')
            ->with('success', 'Nama kasir berhasil ditambahkan');
    }

    /**
     * Delete cashier worker master data
     */
    public function tasksCashierDestroy($id)
    {
        $this->firebase->deleteCashierWorker($id);

        return redirect()->route('admin.tasks.index')
            ->with('success', 'Nama kasir berhasil dihapus');
    }

    /**
     * Show create task form
     */
    public function tasksCreate(Request $request)
    {
        $waiters = $this->firebase->getActiveWaiters();
        $racks = $this->firebase->getActiveRacks();
        $requestedScope = (string) $request->input('task_scope', 'general');
        $taskScope = $requestedScope === 'rack_check' ? 'rack_check' : 'general';
        $requestedTaskType = $taskScope === 'rack_check' ? 'rack_check' : 'general';
        $backRouteName = $taskScope === 'rack_check'
            ? 'admin.tasks.rack.index'
            : 'admin.tasks.index';

        return view('admin.tasks.create', compact('waiters', 'racks', 'taskScope', 'backRouteName', 'requestedTaskType'));
    }

    /**
     * Store new task
     */
    public function tasksStore(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:urgent,normal,low',
            'task_scope' => 'required|in:general,rack_check',
            'task_type' => 'required|in:general,rack_check',
            'requires_photo_proof' => 'nullable|boolean',
            'rack_target_scope' => 'nullable|in:single,all',
            'rack_id' => 'nullable|string',
            'assignment_type' => 'required|in:single,all',
            'assigned_waiter_id' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'schedule_time' => 'nullable|date_format:H:i',
            'time_limit_minutes' => 'nullable|integer|min:1|max:1440',
            'recurrence_type' => 'nullable|in:daily,weekly,every_n_days',
            'weekly_day' => 'nullable|integer|min:1|max:7',
            'interval_days' => 'nullable|integer|min:1|max:365',
        ]);

        $isRecurring = (bool) $request->boolean('is_recurring');
        $assignmentType = $request->input('assignment_type', 'all');
        $assignedWaiterId = $request->input('assigned_waiter_id');
        $rackTargetScope = (string) $request->input('rack_target_scope', 'single');
        $requiresPhotoProof = (bool) $request->boolean('requires_photo_proof');
        $requestedScope = (string) $request->input('task_scope', 'general');
        $taskType = $requestedScope === 'rack_check' ? 'rack_check' : 'general';
        $taskTitle = trim((string) $request->input('title', ''));
        $taskDescription = trim((string) $request->input('description', ''));
        $taskPriority = (string) $request->input('priority', 'normal');

        if ($taskType !== 'rack_check' && $taskTitle === '') {
            return back()
                ->withErrors(['title' => 'Judul tugas wajib diisi untuk tugas umum.'])
                ->withInput();
        }

        if (! in_array($taskPriority, ['urgent', 'normal', 'low'], true)) {
            $taskPriority = 'normal';
        }

        if ($taskType === 'rack_check') {
            $isRecurring = true;
            $taskDescription = '';
            $taskPriority = 'normal';
            $request->merge([
                'is_recurring' => 1,
            ]);
        }

        $redirectRouteName = $this->resolveTaskScopeRouteNameByTaskType($taskType, $requestedScope);

        $taskRackPayloads = [[
            'task_type' => 'general',
            'requires_barcode_scan' => false,
            'requires_photo_proof' => $requiresPhotoProof,
            'rack_target_scope' => null,
            'rack_id' => null,
            'rack_name' => null,
            'rack_location' => null,
            'rack_barcode_value' => null,
        ]];

        if ($taskType === 'rack_check') {
            if (! in_array($rackTargetScope, ['single', 'all'], true)) {
                $rackTargetScope = 'single';
            }

            if ($rackTargetScope === 'all') {
                $activeRacks = $this->firebase->getActiveRacks();
                if (count($activeRacks) === 0) {
                    return back()
                        ->withErrors(['rack_target_scope' => 'Tidak ada rak aktif. Tambahkan/aktifkan rak dulu sebelum pilih semua rak.'])
                        ->withInput();
                }

                $taskRackPayloads = array_map(function ($rack) use ($requiresPhotoProof) {
                    return [
                        'task_type' => 'rack_check',
                        'requires_barcode_scan' => true,
                        'requires_photo_proof' => $requiresPhotoProof,
                        'rack_target_scope' => 'all',
                        'rack_id' => $rack['id'] ?? null,
                        'rack_name' => $rack['name'] ?? null,
                        'rack_location' => $rack['location'] ?? null,
                        'rack_barcode_value' => $rack['barcode_value'] ?? null,
                    ];
                }, $activeRacks);
            } else {
                $rackId = (string) $request->input('rack_id', '');
                if ($rackId === '') {
                    return back()
                        ->withErrors(['rack_id' => 'Untuk task cek rak, pilih rak tujuan terlebih dahulu.'])
                        ->withInput();
                }

                $rack = $this->firebase->getRackById($rackId);
                if (! $rack || (($rack['is_active'] ?? true) === false)) {
                    return back()
                        ->withErrors(['rack_id' => 'Rak tidak valid atau sedang nonaktif.'])
                        ->withInput();
                }

                $taskRackPayloads = [[
                    'task_type' => 'rack_check',
                    'requires_barcode_scan' => true,
                    'requires_photo_proof' => $requiresPhotoProof,
                    'rack_target_scope' => 'single',
                    'rack_id' => $rack['id'] ?? $rackId,
                    'rack_name' => $rack['name'] ?? null,
                    'rack_location' => $rack['location'] ?? null,
                    'rack_barcode_value' => $rack['barcode_value'] ?? null,
                ]];
            }
        }

        if ($assignmentType === 'single' && ! $assignedWaiterId) {
            return back()
                ->withErrors(['assigned_waiter_id' => 'Pilih waiter tujuan untuk delegasi spesifik.'])
                ->withInput();
        }

        if ($assignmentType === 'single') {
            $targetWaiter = $this->firebase->getWaiterById($assignedWaiterId);
            if (! $targetWaiter || (($targetWaiter['is_active'] ?? true) === false)) {
                return back()
                    ->withErrors(['assigned_waiter_id' => 'Waiter tujuan tidak valid atau nonaktif.'])
                    ->withInput();
            }
        }

        if ($isRecurring && ! $request->filled('schedule_time')) {
            return back()
                ->withErrors(['schedule_time' => 'Jam jadwal wajib diisi untuk task berulang'])
                ->withInput();
        }

        if ($isRecurring && ! $request->filled('time_limit_minutes')) {
            return back()
                ->withErrors(['time_limit_minutes' => 'Batas waktu (menit) wajib diisi untuk task berulang'])
                ->withInput();
        }

        $recurrenceType = $request->input('recurrence_type', 'daily');

        if ($isRecurring && $recurrenceType === 'weekly' && ! $request->filled('weekly_day')) {
            return back()
                ->withErrors(['weekly_day' => 'Hari mingguan wajib dipilih untuk mode mingguan'])
                ->withInput();
        }

        if ($isRecurring && $recurrenceType === 'every_n_days' && ! $request->filled('interval_days')) {
            return back()
                ->withErrors(['interval_days' => 'Jumlah hari wajib diisi untuk mode setiap N hari'])
                ->withInput();
        }

        if ($isRecurring) {
            $templateCount = 0;
            foreach ($taskRackPayloads as $taskRackPayload) {
                $resolvedTaskTitle = $taskType === 'rack_check'
                    ? $this->buildRackCheckTaskTitle($taskRackPayload)
                    : $taskTitle;

                $this->firebase->createRecurringWaiterTaskTemplate([
                    'title' => $resolvedTaskTitle,
                    'description' => $taskDescription,
                    'priority' => $taskPriority,
                    'assigned_by' => 'Supervisor',
                    'task_type' => $taskRackPayload['task_type'],
                    'requires_barcode_scan' => $taskRackPayload['requires_barcode_scan'],
                    'requires_photo_proof' => $taskRackPayload['requires_photo_proof'],
                    'rack_target_scope' => $taskRackPayload['rack_target_scope'],
                    'rack_id' => $taskRackPayload['rack_id'],
                    'rack_name' => $taskRackPayload['rack_name'],
                    'rack_location' => $taskRackPayload['rack_location'],
                    'rack_barcode_value' => $taskRackPayload['rack_barcode_value'],
                    'assignment_type' => $assignmentType,
                    'assigned_waiter_id' => $assignmentType === 'single' ? $assignedWaiterId : null,
                    'schedule_time' => $request->schedule_time,
                    'time_limit_minutes' => (int) $request->time_limit_minutes,
                    'recurrence_type' => $recurrenceType,
                    'weekly_day' => $recurrenceType === 'weekly' ? (int) $request->weekly_day : null,
                    'interval_days' => $recurrenceType === 'every_n_days' ? (int) $request->interval_days : null,
                ]);
                $templateCount++;
            }

            if ($taskType === 'rack_check' && $rackTargetScope === 'all') {
                return redirect()->route($redirectRouteName)
                    ->with('success', "Template task cek rak berulang berhasil dibuat untuk semua rak aktif ({$templateCount} rak). Waiter akan wajib scan barcode tiap rak saat eksekusi.");
            }

            return redirect()->route($redirectRouteName)
                ->with('success', 'Task berulang waiter berhasil dibuat dengan pola jadwal yang dipilih.');
        }

        $createdCount = 0;
        foreach ($taskRackPayloads as $taskRackPayload) {
            $resolvedTaskTitle = $taskType === 'rack_check'
                ? $this->buildRackCheckTaskTitle($taskRackPayload)
                : $taskTitle;

            $createdCount += $this->firebase->createWaiterTasksFromAssignment([
                'title' => $resolvedTaskTitle,
                'description' => $taskDescription,
                'priority' => $taskPriority,
                'assigned_by' => 'Supervisor',
                'task_type' => $taskRackPayload['task_type'],
                'requires_barcode_scan' => $taskRackPayload['requires_barcode_scan'],
                'requires_photo_proof' => $taskRackPayload['requires_photo_proof'],
                'rack_target_scope' => $taskRackPayload['rack_target_scope'],
                'rack_id' => $taskRackPayload['rack_id'],
                'rack_name' => $taskRackPayload['rack_name'],
                'rack_location' => $taskRackPayload['rack_location'],
                'rack_barcode_value' => $taskRackPayload['rack_barcode_value'],
                'assignment_type' => $assignmentType,
                'assigned_waiter_id' => $assignmentType === 'single' ? $assignedWaiterId : null,
            ]);
        }

        if ($createdCount <= 0) {
            return redirect()->route($redirectRouteName)
                ->with('error', 'Tidak ada waiter aktif yang bisa menerima tugas ini.');
        }

        if ($taskType === 'rack_check' && $rackTargetScope === 'all') {
            $rackCount = count($taskRackPayloads);

            return redirect()->route($redirectRouteName)
                ->with('success', "Tugas cek rak berhasil dibuat untuk semua rak aktif ({$rackCount} rak) dengan total {$createdCount} delegasi waiter. Waiter harus scan barcode setiap rak melalui task masing-masing.");
        }

        return redirect()->route($redirectRouteName)
            ->with('success', "Tugas berhasil dibuat dan didelegasikan ke {$createdCount} waiter.");
    }

    /**
     * Delete task
     */
    public function tasksDestroy($id)
    {
        $task = collect($this->firebase->getWaiterTasks())->first(function ($candidate) use ($id) {
            return (string) ($candidate['id'] ?? '') === (string) $id;
        });

        $redirectRouteName = $this->resolveTaskScopeRouteNameByTaskType((string) ($task['task_type'] ?? 'general'));

        $this->firebase->deleteWaiterTask($id);

        return redirect()->route($redirectRouteName)
            ->with('success', 'Tugas berhasil dihapus');
    }

    /**
     * Delete recurring task template
     */
    public function tasksRecurringDestroy($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        $redirectRouteName = $this->resolveTaskScopeRouteNameByTaskType((string) ($template['task_type'] ?? 'general'));

        $this->firebase->deleteRecurringWaiterTaskTemplate($id);

        return redirect()->route($redirectRouteName)
            ->with('success', 'Template task berulang berhasil dihapus');
    }

    /**
     * Show edit recurring task template form
     */
    public function tasksRecurringEdit($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);

        if (! $template) {
            abort(404);
        }

        return view('admin.tasks.edit_recurring', compact('template'));
    }

    /**
     * Update recurring task template
     */
    public function tasksRecurringUpdate($id, Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority' => 'required|in:urgent,normal,low',
            'schedule_time' => 'required|date_format:H:i',
            'time_limit_minutes' => 'required|integer|min:1|max:1440',
            'is_active' => 'nullable|boolean',
            'recurrence_type' => 'required|in:daily,weekly,every_n_days',
            'weekly_day' => 'nullable|integer|min:1|max:7',
            'interval_days' => 'nullable|integer|min:1|max:365',
            'reset_anchor_date' => 'nullable|boolean',
        ]);

        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            abort(404);
        }

        $recurrenceType = (string) $request->input('recurrence_type', 'daily');
        $scheduleTime = (string) $request->input('schedule_time', '');
        $timeLimitMinutes = (int) $request->input('time_limit_minutes', 0);

        if ($recurrenceType === 'weekly' && ! $request->filled('weekly_day')) {
            return back()
                ->withErrors(['weekly_day' => 'Hari mingguan wajib dipilih untuk mode mingguan'])
                ->withInput();
        }

        if ($recurrenceType === 'every_n_days' && ! $request->filled('interval_days')) {
            return back()
                ->withErrors(['interval_days' => 'Jumlah hari wajib diisi untuk mode setiap N hari'])
                ->withInput();
        }

        $this->firebase->updateRecurringWaiterTaskTemplate($id, [
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => $request->priority,
            'schedule_time' => $scheduleTime,
            'time_limit_minutes' => $timeLimitMinutes,
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) $request->weekly_day : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) $request->interval_days : null,
            'reset_anchor_date' => $request->has('reset_anchor_date'),
            'is_active' => $request->has('is_active'),
        ]);

        $redirectRouteName = $this->resolveTaskScopeRouteNameByTaskType((string) ($template['task_type'] ?? 'general'));

        return redirect()->route($redirectRouteName)
            ->with('success', 'Template task berulang berhasil diupdate');
    }

    /**
     * Check duplicate waiter email in Firebase master.
     */
    protected function normalizeOrderTimestamp($timestamp): int
    {
        $value = (int) $timestamp;
        if ($value <= 0) {
            return 0;
        }

        if ($value > 9999999999) {
            $value = (int) floor($value / 1000);
        }

        return $value;
    }

    /**
     * Resolve dashboard period range for order/task stats.
     */
    protected function resolveDashboardPeriodRange(string $period): array
    {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));

        if ($period === 'weekly') {
            $weekStart = strtotime('monday this week', $todayStart);
            if ($weekStart === false) {
                $weekStart = $todayStart;
            }

            return [(int) $weekStart, (int) ($weekStart + (7 * 24 * 60 * 60) - 1), 'Minggu Ini'];
        }

        if ($period === 'monthly') {
            $monthStart = strtotime(date('Y-m-01 00:00:00'));
            $monthEnd = strtotime(date('Y-m-t 23:59:59'));

            return [(int) $monthStart, (int) $monthEnd, 'Bulan Ini'];
        }

        return [$todayStart, (int) ($todayStart + (24 * 60 * 60) - 1), 'Hari Ini'];
    }

    /**
     * Resolve dashboard date range from daterangepicker inputs.
     */
    protected function resolveDashboardDateRangeFromRequest(Request $request): array
    {
        $startDateInput = trim((string) $request->input('start_date', ''));
        $endDateInput = trim((string) $request->input('end_date', ''));
        $dateRangeInput = trim((string) $request->input('date_range', ''));

        if (($startDateInput === '' || $endDateInput === '') && $dateRangeInput !== '') {
            $parts = preg_split('/\s*-\s*/', $dateRangeInput) ?: [];
            if (count($parts) >= 2) {
                $startDateInput = $startDateInput !== '' ? $startDateInput : trim((string) ($parts[0] ?? ''));
                $endDateInput = $endDateInput !== '' ? $endDateInput : trim((string) ($parts[1] ?? ''));
            }
        }

        $startDate = $this->normalizeDashboardDateString($startDateInput);
        $endDate = $this->normalizeDashboardDateString($endDateInput);

        if ($startDate === '' && $endDate === '') {
            $legacyPeriodInput = strtolower(trim((string) $request->input('order_period', 'daily')));
            $legacyPeriod = in_array($legacyPeriodInput, ['daily', 'weekly', 'monthly'], true) ? $legacyPeriodInput : 'daily';
            [$legacyStartTs, $legacyEndTs] = $this->resolveDashboardPeriodRange($legacyPeriod);

            $startDate = date('Y-m-d', $legacyStartTs);
            $endDate = date('Y-m-d', $legacyEndTs);
        } elseif ($startDate === '' && $endDate !== '') {
            $startDate = $endDate;
        } elseif ($endDate === '' && $startDate !== '') {
            $endDate = $startDate;
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $periodStartTs = (int) strtotime($startDate.' 00:00:00');
        $periodEndTs = (int) strtotime($endDate.' 23:59:59');
        $orderPeriodLabel = $this->resolveDashboardRangeLabel($startDate, $endDate);

        return [
            $periodStartTs,
            $periodEndTs,
            $orderPeriodLabel,
            $startDate,
            $endDate,
            date('d M Y', $periodStartTs).' - '.date('d M Y', $periodEndTs),
        ];
    }

    /**
     * Normalize dashboard date string to Y-m-d.
     */
    protected function normalizeDashboardDateString(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $parsed = strtotime($date.' 00:00:00');
            if ($parsed === false) {
                return '';
            }

            return date('Y-m-d', $parsed);
        }

        $parsed = strtotime($date);
        if ($parsed === false) {
            return '';
        }

        return date('Y-m-d', $parsed);
    }

    /**
     * Build dashboard period label based on selected range.
     */
    protected function resolveDashboardRangeLabel(string $startDate, string $endDate): string
    {
        $today = date('Y-m-d');
        if ($startDate === $today && $endDate === $today) {
            return 'Today';
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($startDate === $yesterday && $endDate === $yesterday) {
            return 'Yesterday';
        }

        $last7Start = date('Y-m-d', strtotime('-6 day'));
        if ($startDate === $last7Start && $endDate === $today) {
            return 'Last 7 Days';
        }

        $last30Start = date('Y-m-d', strtotime('-29 day'));
        if ($startDate === $last30Start && $endDate === $today) {
            return 'Last 30 Days';
        }

        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        if ($startDate === $weekStart && $endDate === $weekEnd) {
            return 'Minggu Ini';
        }

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        if ($startDate === $monthStart && $endDate === $monthEnd) {
            return 'This Month';
        }

        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
        if ($startDate === $lastMonthStart && $endDate === $lastMonthEnd) {
            return 'Last Month';
        }

        return 'Rentang Kustom';
    }

    /**
     * Build canonical waiter identity key.
     */
    protected function buildWaiterIdentityKey(string $waiterId, string $waiterName, string $waiterEmail): string
    {
        $waiterId = trim($waiterId);
        $waiterName = trim($waiterName);
        $waiterEmail = strtolower(trim($waiterEmail));

        if ($waiterEmail !== '') {
            return 'email:'.$waiterEmail;
        }

        if ($waiterId !== '') {
            return 'id:'.$waiterId;
        }

        if ($waiterName !== '') {
            return 'name:'.strtolower($waiterName);
        }

        return 'unknown';
    }

    /**
     * Build canonical waiter lookup maps from master waiter data.
     */
    protected function buildWaiterIdentityDirectory(array $waiters): array
    {
        $byId = [];
        $byEmail = [];

        foreach ($waiters as $waiter) {
            $id = trim((string) ($waiter['id'] ?? ''));
            $name = trim((string) ($waiter['name'] ?? ''));
            $email = strtolower(trim((string) ($waiter['email'] ?? '')));

            $profile = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
            ];

            if ($id !== '') {
                $byId[$id] = $profile;
            }

            if ($email !== '') {
                $byEmail[$email] = $profile;
            }
        }

        return [
            'by_id' => $byId,
            'by_email' => $byEmail,
        ];
    }

    /**
     * Resolve waiter profile using master waiter directory to avoid duplicate identities.
     */
    protected function resolveCanonicalWaiterProfile(string $waiterId, string $waiterName, string $waiterEmail, array $directory): array
    {
        $waiterId = trim($waiterId);
        $waiterName = trim($waiterName);
        $waiterEmail = strtolower(trim($waiterEmail));

        $byId = is_array($directory['by_id'] ?? null) ? $directory['by_id'] : [];
        $byEmail = is_array($directory['by_email'] ?? null) ? $directory['by_email'] : [];

        $masterProfile = null;
        if ($waiterEmail !== '' && isset($byEmail[$waiterEmail])) {
            $masterProfile = $byEmail[$waiterEmail];
        } elseif ($waiterId !== '' && isset($byId[$waiterId])) {
            $masterProfile = $byId[$waiterId];
        }

        $canonicalId = $waiterId;
        $canonicalName = $waiterName;
        $canonicalEmail = $waiterEmail;

        if (is_array($masterProfile)) {
            if (($masterProfile['id'] ?? '') !== '') {
                $canonicalId = (string) $masterProfile['id'];
            }

            if (($masterProfile['name'] ?? '') !== '') {
                $canonicalName = (string) $masterProfile['name'];
            }

            if (($masterProfile['email'] ?? '') !== '') {
                $canonicalEmail = (string) $masterProfile['email'];
            }
        }

        $identityKey = $this->buildWaiterIdentityKey($canonicalId, $canonicalName, $canonicalEmail);

        return [
            'identity_key' => $identityKey,
            'waiter_id' => $canonicalId,
            'waiter_name' => $canonicalName,
            'waiter_email' => $canonicalEmail,
        ];
    }

    /**
     * Build waiter order stats within selected period.
     */
    protected function buildWaiterOrderStatsByPeriod(array $orders, int $startTs, int $endTs, array $waiterDirectory = []): array
    {
        $stats = [];

        foreach ($orders as $order) {
            $createdAt = $this->normalizeOrderTimestamp($order['created_at'] ?? 0);
            if ($createdAt < $startTs || $createdAt > $endTs) {
                continue;
            }

            $resolvedWaiter = $this->resolveCanonicalWaiterProfile(
                (string) ($order['waiter_id'] ?? ''),
                (string) ($order['waiter_name'] ?? ''),
                (string) ($order['waiter_email'] ?? ''),
                $waiterDirectory
            );

            $waiterId = (string) ($resolvedWaiter['waiter_id'] ?? '');
            $waiterName = trim((string) ($resolvedWaiter['waiter_name'] ?? ''));
            $waiterEmail = strtolower(trim((string) ($resolvedWaiter['waiter_email'] ?? '')));
            $identityKey = (string) ($resolvedWaiter['identity_key'] ?? 'unknown');

            if (! isset($stats[$identityKey])) {
                $stats[$identityKey] = [
                    'waiter_id' => $waiterId,
                    'waiter_name' => $waiterName !== '' ? $waiterName : 'Waiter Tidak Diketahui',
                    'waiter_email' => $waiterEmail,
                    'order_count' => 0,
                    'last_order_at' => 0,
                ];
            }

            $stats[$identityKey]['order_count']++;
            if ($createdAt > $stats[$identityKey]['last_order_at']) {
                $stats[$identityKey]['last_order_at'] = $createdAt;
            }

            if ($stats[$identityKey]['waiter_name'] === 'Waiter Tidak Diketahui' && $waiterName !== '') {
                $stats[$identityKey]['waiter_name'] = $waiterName;
            }

            if ($stats[$identityKey]['waiter_email'] === '' && $waiterEmail !== '') {
                $stats[$identityKey]['waiter_email'] = $waiterEmail;
            }

            if ($stats[$identityKey]['waiter_id'] === '' && $waiterId !== '') {
                $stats[$identityKey]['waiter_id'] = $waiterId;
            }
        }

        $result = array_values($stats);
        usort($result, function ($a, $b) {
            if (($b['order_count'] ?? 0) === ($a['order_count'] ?? 0)) {
                return ((int) ($b['last_order_at'] ?? 0)) <=> ((int) ($a['last_order_at'] ?? 0));
            }

            return ((int) ($b['order_count'] ?? 0)) <=> ((int) ($a['order_count'] ?? 0));
        });

        return $result;
    }

    /**
     * Build waiter ranking for completed tasks (including rack-check) in selected period.
     */
    protected function buildWaiterTaskCompletionRankingByPeriod(array $tasks, int $startTs, int $endTs, array $waiterDirectory = []): array
    {
        $stats = [];

        foreach ($tasks as $task) {
            if ((string) ($task['status'] ?? '') !== 'done') {
                continue;
            }

            $completedAt = $this->normalizeOrderTimestamp($task['completed_at'] ?? 0);
            if ($completedAt < $startTs || $completedAt > $endTs) {
                continue;
            }

            $resolvedWaiter = $this->resolveCanonicalWaiterProfile(
                (string) ($task['completed_by_waiter_id'] ?? $task['assigned_waiter_id'] ?? ''),
                (string) ($task['completed_by_waiter_name'] ?? $task['assigned_waiter_name'] ?? ''),
                (string) ($task['completed_by_waiter_email'] ?? ''),
                $waiterDirectory
            );

            $waiterId = (string) ($resolvedWaiter['waiter_id'] ?? '');
            $waiterName = trim((string) ($resolvedWaiter['waiter_name'] ?? ''));
            $waiterEmail = strtolower(trim((string) ($resolvedWaiter['waiter_email'] ?? '')));
            $identityKey = (string) ($resolvedWaiter['identity_key'] ?? 'unknown');
            $taskType = (string) ($task['task_type'] ?? 'general');

            if (! isset($stats[$identityKey])) {
                $stats[$identityKey] = [
                    'waiter_id' => $waiterId,
                    'waiter_name' => $waiterName !== '' ? $waiterName : 'Waiter Tidak Diketahui',
                    'waiter_email' => $waiterEmail,
                    'completed_count' => 0,
                    'rack_done_count' => 0,
                    'general_done_count' => 0,
                    'last_completed_at' => 0,
                ];
            }

            $stats[$identityKey]['completed_count']++;
            if ($taskType === 'rack_check') {
                $stats[$identityKey]['rack_done_count']++;
            } else {
                $stats[$identityKey]['general_done_count']++;
            }

            if ($completedAt > $stats[$identityKey]['last_completed_at']) {
                $stats[$identityKey]['last_completed_at'] = $completedAt;
            }

            if ($stats[$identityKey]['waiter_name'] === 'Waiter Tidak Diketahui' && $waiterName !== '') {
                $stats[$identityKey]['waiter_name'] = $waiterName;
            }

            if ($stats[$identityKey]['waiter_email'] === '' && $waiterEmail !== '') {
                $stats[$identityKey]['waiter_email'] = $waiterEmail;
            }

            if ($stats[$identityKey]['waiter_id'] === '' && $waiterId !== '') {
                $stats[$identityKey]['waiter_id'] = $waiterId;
            }
        }

        $result = array_values($stats);
        usort($result, function ($a, $b) {
            $totalCompare = ((int) ($b['completed_count'] ?? 0)) <=> ((int) ($a['completed_count'] ?? 0));
            if ($totalCompare !== 0) {
                return $totalCompare;
            }

            $rackCompare = ((int) ($b['rack_done_count'] ?? 0)) <=> ((int) ($a['rack_done_count'] ?? 0));
            if ($rackCompare !== 0) {
                return $rackCompare;
            }

            return ((int) ($b['last_completed_at'] ?? 0)) <=> ((int) ($a['last_completed_at'] ?? 0));
        });

        return $result;
    }

    /**
     * Check whether task/template belongs to selected management scope.
     */
    protected function matchesTaskScope(array $item, string $taskScope): bool
    {
        $type = (string) ($item['task_type'] ?? 'general');
        if ($taskScope === 'rack_check') {
            return $type === 'rack_check';
        }

        return $type !== 'rack_check';
    }

    /**
     * Resolve destination route based on task scope.
     */
    protected function resolveTaskScopeRouteNameByTaskType(string $taskType, string $fallbackScope = ''): string
    {
        if ($taskType === 'rack_check') {
            return 'admin.tasks.rack.index';
        }

        if ($fallbackScope === 'rack_check') {
            return 'admin.tasks.rack.index';
        }

        return 'admin.tasks.index';
    }

    /**
     * Rack-check tasks always use rack name as title.
     */
    protected function buildRackCheckTaskTitle(array $taskRackPayload): string
    {
        $rackName = trim((string) ($taskRackPayload['rack_name'] ?? ''));
        if ($rackName !== '') {
            return $rackName;
        }

        $rackLocation = trim((string) ($taskRackPayload['rack_location'] ?? ''));
        if ($rackLocation !== '') {
            return 'Rak '.$rackLocation;
        }

        return 'Rak Tanpa Nama';
    }

    /**
     * Resolve selected racks from request scope.
     */
    protected function resolveSelectedRacksFromRequest(Request $request): array
    {
        $allRacks = $this->firebase->getRacks();

        if ($request->boolean('all')) {
            return $allRacks;
        }

        $rawRackIds = $request->input('rack_ids', []);
        if (! is_array($rawRackIds)) {
            $rawRackIds = explode(',', (string) $rawRackIds);
        }

        $rackIds = array_values(array_unique(array_filter(array_map(function ($id) {
            return trim((string) $id);
        }, $rawRackIds), function ($id) {
            return $id !== '';
        })));

        if (count($rackIds) === 0) {
            return [];
        }

        return array_values(array_filter($allRacks, function ($rack) use ($rackIds) {
            return in_array((string) ($rack['id'] ?? ''), $rackIds, true);
        }));
    }

    /**
     * Prevent CSV formula injection on exported cells.
     */
    protected function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Check duplicate waiter email in Firebase master.
     */
    protected function isWaiterEmailAlreadyUsed(string $email, ?string $excludeId = null): bool
    {
        $email = strtolower(trim($email));

        foreach ($this->firebase->getAllowedEmails() as $waiter) {
            $currentId = (string) ($waiter['id'] ?? '');
            if ($excludeId !== null && $currentId === $excludeId) {
                continue;
            }

            $currentEmail = strtolower(trim((string) ($waiter['email'] ?? '')));
            if ($currentEmail !== '' && $currentEmail === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve task tracking date (for reporting)
     */
    protected function resolveTrackingDate($task)
    {
        if (! empty($task['scheduled_for_date'])) {
            return $task['scheduled_for_date'];
        }

        if (! empty($task['created_at'])) {
            return date('Y-m-d', (int) $task['created_at']);
        }

        return date('Y-m-d');
    }

    /**
     * Build waiter performance ranking from completed tasks
     */
    protected function buildWaiterPerformance(array $tasks)
    {
        $stats = [];

        foreach ($tasks as $task) {
            if (($task['status'] ?? '') !== 'done') {
                continue;
            }

            $waiterName = trim((string) ($task['completed_by_waiter_name'] ?? ''));
            if ($waiterName === '') {
                continue;
            }

            if (! isset($stats[$waiterName])) {
                $stats[$waiterName] = [
                    'name' => $waiterName,
                    'done_count' => 0,
                    'last_done_at' => 0,
                ];
            }

            $stats[$waiterName]['done_count']++;

            $completedAt = (int) ($task['completed_at'] ?? 0);
            if ($completedAt > $stats[$waiterName]['last_done_at']) {
                $stats[$waiterName]['last_done_at'] = $completedAt;
            }
        }

        $result = array_values($stats);
        usort($result, function ($a, $b) {
            if ($b['done_count'] === $a['done_count']) {
                return $b['last_done_at'] <=> $a['last_done_at'];
            }

            return $b['done_count'] <=> $a['done_count'];
        });

        return $result;
    }

    /**
     * Build waiter-centric tracking board for selected date.
     */
    protected function buildWaiterTrackingBoard(array $doneTasks, array $notDoneTasks): array
    {
        $board = [];

        $upsertWaiter = function (string $waiterName, string $waiterId) use (&$board): string {
            $normalizedName = trim($waiterName);
            $normalizedId = trim($waiterId);

            $key = '';
            if ($normalizedId !== '') {
                $key = 'id:'.$normalizedId;
            } elseif ($normalizedName !== '') {
                $key = 'name:'.strtolower($normalizedName);
            } else {
                $key = 'unknown';
            }

            if (! isset($board[$key])) {
                $board[$key] = [
                    'waiter_key' => $key,
                    'waiter_id' => $normalizedId,
                    'waiter_name' => $normalizedName !== '' ? $normalizedName : 'Waiter Tidak Diketahui',
                    'done_tasks' => [],
                    'not_done_tasks' => [],
                    'done_count' => 0,
                    'not_done_count' => 0,
                    'total_count' => 0,
                ];
            }

            if ($board[$key]['waiter_name'] === 'Waiter Tidak Diketahui' && $normalizedName !== '') {
                $board[$key]['waiter_name'] = $normalizedName;
            }

            if ($board[$key]['waiter_id'] === '' && $normalizedId !== '') {
                $board[$key]['waiter_id'] = $normalizedId;
            }

            return $key;
        };

        foreach ($doneTasks as $task) {
            $key = $upsertWaiter(
                (string) ($task['completed_by_waiter_name'] ?? ''),
                (string) ($task['completed_by_waiter_id'] ?? '')
            );

            $board[$key]['done_tasks'][] = $task;
            $board[$key]['done_count']++;
            $board[$key]['total_count']++;
        }

        foreach ($notDoneTasks as $task) {
            $key = $upsertWaiter(
                (string) ($task['assigned_waiter_name'] ?? ''),
                (string) ($task['assigned_waiter_id'] ?? '')
            );

            $board[$key]['not_done_tasks'][] = $task;
            $board[$key]['not_done_count']++;
            $board[$key]['total_count']++;
        }

        $result = array_values($board);
        usort($result, function ($a, $b) {
            $totalCompare = (int) ($b['total_count'] ?? 0) <=> (int) ($a['total_count'] ?? 0);
            if ($totalCompare !== 0) {
                return $totalCompare;
            }

            return strcmp(
                strtolower((string) ($a['waiter_name'] ?? '')),
                strtolower((string) ($b['waiter_name'] ?? ''))
            );
        });

        return $result;
    }

    /**
     * Build supervisor board for waiter daily activity reports.
     */
    protected function buildWaiterActivityBoard(array $reports): array
    {
        $grouped = [];

        foreach ($reports as $report) {
            $waiterId = (string) ($report['waiter_id'] ?? '');
            $key = $waiterId !== '' ? $waiterId : 'unknown::'.md5((string) ($report['waiter_name'] ?? ''));

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'waiter_id' => $waiterId,
                    'waiter_name' => (string) ($report['waiter_name'] ?? 'Waiter'),
                    'waiter_email' => (string) ($report['waiter_email'] ?? ''),
                    'report_count' => 0,
                    'latest_created_at' => 0,
                    'reports' => [],
                ];
            }

            $createdAt = (int) ($report['created_at'] ?? 0);
            $grouped[$key]['report_count']++;
            if ($createdAt > $grouped[$key]['latest_created_at']) {
                $grouped[$key]['latest_created_at'] = $createdAt;
            }

            $grouped[$key]['reports'][] = [
                'id' => (string) ($report['id'] ?? ''),
                'activity_text' => (string) ($report['activity_text'] ?? ''),
                'activity_items' => is_array($report['activity_items'] ?? null) ? $report['activity_items'] : [],
                'created_at' => $createdAt,
                'report_date' => (string) ($report['report_date'] ?? ''),
            ];
        }

        $waiters = array_values($grouped);
        foreach ($waiters as &$waiter) {
            usort($waiter['reports'], function ($a, $b) {
                return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
            });
        }
        unset($waiter);

        usort($waiters, function ($a, $b) {
            if (($b['report_count'] ?? 0) === ($a['report_count'] ?? 0)) {
                return ((int) ($b['latest_created_at'] ?? 0)) <=> ((int) ($a['latest_created_at'] ?? 0));
            }

            return ($b['report_count'] ?? 0) <=> ($a['report_count'] ?? 0);
        });

        return [
            'total_reports' => count($reports),
            'waiter_count' => count($waiters),
            'waiters' => $waiters,
        ];
    }

    /**
     * Build dedicated board for collected depleted-stock reports.
     */
    protected function buildCollectedStockBoard(array $dateTasks): array
    {
        $rackGroups = [];
        $globalItems = [];
        $totalReports = 0;
        $totalItemMentions = 0;

        foreach ($dateTasks as $task) {
            if ((string) ($task['task_type'] ?? 'general') !== 'rack_check') {
                continue;
            }

            if ((string) ($task['status'] ?? '') !== 'done') {
                continue;
            }

            if ((bool) ($task['completed_no_out_of_stock'] ?? false) === true) {
                continue;
            }

            $items = $this->normalizeStockReportItems($task);
            if (count($items) === 0) {
                continue;
            }

            $totalReports++;
            $totalItemMentions += count($items);

            $rackId = (string) ($task['rack_id'] ?? '');
            $rackCode = (string) ($task['rack_barcode_value'] ?? '');
            $rackKey = $rackId !== '' ? $rackId : 'barcode::'.$rackCode;

            if (! isset($rackGroups[$rackKey])) {
                $rackGroups[$rackKey] = [
                    'rack_id' => $rackId,
                    'rack_name' => (string) ($task['rack_name'] ?? 'Rak Tidak Diketahui'),
                    'rack_location' => (string) ($task['rack_location'] ?? '-'),
                    'rack_barcode_value' => $rackCode,
                    'reports_count' => 0,
                    'item_mentions_count' => 0,
                    'latest_reported_at' => 0,
                    'items' => [],
                    'reports' => [],
                ];
            }

            $reportedAt = (int) ($task['stock_reported_at'] ?? $task['completed_at'] ?? 0);
            $waiterName = trim((string) ($task['completed_by_waiter_name'] ?? $task['assigned_waiter_name'] ?? '-'));
            if ($waiterName === '') {
                $waiterName = '-';
            }

            $rackGroups[$rackKey]['reports_count']++;
            $rackGroups[$rackKey]['item_mentions_count'] += count($items);
            $rackGroups[$rackKey]['latest_reported_at'] = max($rackGroups[$rackKey]['latest_reported_at'], $reportedAt);
            $rackGroups[$rackKey]['reports'][] = [
                'task_id' => (string) ($task['id'] ?? ''),
                'task_title' => (string) ($task['title'] ?? '-'),
                'waiter_name' => $waiterName,
                'items' => $items,
                'reported_at' => $reportedAt,
                'raw_report' => (string) ($task['completed_stock_report'] ?? ''),
            ];

            foreach ($items as $item) {
                $itemKey = strtolower($item);

                if (! isset($rackGroups[$rackKey]['items'][$itemKey])) {
                    $rackGroups[$rackKey]['items'][$itemKey] = [
                        'item' => $item,
                        'count' => 0,
                    ];
                }
                $rackGroups[$rackKey]['items'][$itemKey]['count']++;

                if (! isset($globalItems[$itemKey])) {
                    $globalItems[$itemKey] = [
                        'item' => $item,
                        'count' => 0,
                        'racks' => [],
                    ];
                }
                $globalItems[$itemKey]['count']++;
                $globalItems[$itemKey]['racks'][$rackKey] = $rackGroups[$rackKey]['rack_name'];
            }
        }

        $racks = array_values(array_map(function ($rack) {
            $rack['items'] = array_values($rack['items']);

            usort($rack['items'], function ($a, $b) {
                if (($b['count'] ?? 0) === ($a['count'] ?? 0)) {
                    return strcmp((string) ($a['item'] ?? ''), (string) ($b['item'] ?? ''));
                }

                return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            });

            usort($rack['reports'], function ($a, $b) {
                return ((int) ($b['reported_at'] ?? 0)) <=> ((int) ($a['reported_at'] ?? 0));
            });

            return $rack;
        }, $rackGroups));

        usort($racks, function ($a, $b) {
            if (($b['item_mentions_count'] ?? 0) === ($a['item_mentions_count'] ?? 0)) {
                return ($b['reports_count'] ?? 0) <=> ($a['reports_count'] ?? 0);
            }

            return ($b['item_mentions_count'] ?? 0) <=> ($a['item_mentions_count'] ?? 0);
        });

        $topItems = array_values(array_map(function ($item) {
            return [
                'item' => (string) ($item['item'] ?? '-'),
                'count' => (int) ($item['count'] ?? 0),
                'racks' => array_values($item['racks'] ?? []),
                'rack_count' => count($item['racks'] ?? []),
            ];
        }, $globalItems));

        usort($topItems, function ($a, $b) {
            if (($b['count'] ?? 0) === ($a['count'] ?? 0)) {
                return strcmp((string) ($a['item'] ?? ''), (string) ($b['item'] ?? ''));
            }

            return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        });

        return [
            'total_reports' => $totalReports,
            'total_item_mentions' => $totalItemMentions,
            'racks' => $racks,
            'top_items' => $topItems,
        ];
    }

    /**
     * Normalize item list from task report payload.
     */
    protected function normalizeStockReportItems(array $task): array
    {
        $source = $task['completed_stock_report_items'] ?? [];

        if (! is_array($source) || count($source) === 0) {
            $raw = (string) ($task['completed_stock_report'] ?? '');
            $source = preg_split('/[\r\n,;]+/', $raw) ?: [];
        }

        $items = [];
        $seen = [];
        foreach ($source as $rawItem) {
            $item = trim(preg_replace('/\s+/', ' ', (string) $rawItem) ?? '');
            if ($item === '') {
                continue;
            }

            $key = strtolower($item);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Build rack tracking board (who did and who did not).
     */
    protected function buildRackExecutionBoard(array $dateTasks): array
    {
        $grouped = [];

        foreach ($dateTasks as $task) {
            if ((string) ($task['task_type'] ?? 'general') !== 'rack_check') {
                continue;
            }

            $rackId = (string) ($task['rack_id'] ?? '');
            $rackCode = (string) ($task['rack_barcode_value'] ?? '');
            $key = $rackId !== '' ? $rackId : 'barcode::'.$rackCode;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'rack_id' => $rackId,
                    'rack_name' => (string) ($task['rack_name'] ?? 'Rak Tidak Diketahui'),
                    'rack_location' => (string) ($task['rack_location'] ?? '-'),
                    'rack_barcode_value' => $rackCode,
                    'done_waiters' => [],
                    'not_done_waiters' => [],
                    'done_count' => 0,
                    'not_done_count' => 0,
                ];
            }

            $waiterLabel = trim((string) ($task['assigned_waiter_name'] ?? '-'));
            if ($waiterLabel === '') {
                $waiterLabel = '-';
            }

            if (($task['status'] ?? '') === 'done') {
                $grouped[$key]['done_count']++;
                $grouped[$key]['done_waiters'][] = [
                    'name' => $waiterLabel,
                    'completed_scanned_barcode' => (string) ($task['completed_scanned_barcode'] ?? ''),
                    'completed_stock_report' => (string) ($task['completed_stock_report'] ?? ''),
                    'completed_stock_report_items' => $this->normalizeStockReportItems($task),
                    'completed_no_out_of_stock' => (bool) ($task['completed_no_out_of_stock'] ?? false),
                ];
            } else {
                $grouped[$key]['not_done_count']++;
                $grouped[$key]['not_done_waiters'][] = [
                    'name' => $waiterLabel,
                    'status' => (string) ($task['status'] ?? 'pending'),
                ];
            }
        }

        $result = array_values($grouped);
        usort($result, function ($a, $b) {
            if ($b['not_done_count'] === $a['not_done_count']) {
                return $b['done_count'] <=> $a['done_count'];
            }

            return $b['not_done_count'] <=> $a['not_done_count'];
        });

        return $result;
    }
}
