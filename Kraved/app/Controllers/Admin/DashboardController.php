<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Models\Order;

final class DashboardController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $orderModel = new Order();
        $stats = $orderModel->todayStats();
        $orders = $orderModel->recent(15);

        $this->view('Admin/dashboard/index', [
            'title'  => 'Dashboard',
            'stats'  => $stats,
            'orders' => $orders,
        ], 'Admin/layouts/main');
    }
}
