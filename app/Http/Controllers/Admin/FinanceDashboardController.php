<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class FinanceDashboardController extends Controller
{
    public function __invoke()
    {
        return redirect()->route('admin.finance.dashboard');
    }
}
