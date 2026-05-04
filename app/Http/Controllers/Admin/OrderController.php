<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTestOrderRequest;
use App\Http\Requests\ProcessCleanupRequest;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function showTestOrder()
    {
        return view('admin.test_order');
    }

    public function createTestOrder(CreateTestOrderRequest $request)
    {
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

    public function currentOrdersIndex(Request $request)
    {
        $masterWaiters = $this->firebase->getAllowedEmails();

        $todayDate = date('Y-m-d');
        $filterDateInput = trim((string) $request->input('filter_date', $todayDate));
        $filterDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateInput) === 1 ? $filterDateInput : $todayDate;

        // Fetch only orders for the selected date (not ALL orders)
        $rawOrders = $this->firebase->getOrdersByDate($filterDate);
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
            $createdAtTs = $this->normalizeTimestamp($order['created_at'] ?? 0);
            $expiresAtTs = $this->normalizeTimestamp($order['expires_at'] ?? 0);
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

    public function showCleanup()
    {
        $stats = $this->firebase->getCleanupStats();

        return view('admin.cleanup', compact('stats'));
    }

    public function processCleanup(ProcessCleanupRequest $request)
    {
        $deletedCount = $this->firebase->cleanupOldOrders($request->days_old);

        return back()->with('success', "Berhasil menghapus {$deletedCount} order lama!");
    }

    protected function normalizeTimestamp($timestamp): int
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
}
