<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\Order;

final class OrderController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $this->view('Admin/orders/index', [
            'title'   => 'Orders',
            'orders'  => (new Order())->recent(100),
            'success' => Session::flash('success'),
            'lastId'  => (new Order())->latestId(),
        ], 'Admin/layouts/main');
    }

    public function show(string $id): void
    {
        AuthMiddleware::requireAdmin();
        $order = (new Order())->find((int) $id);
        if (!$order) {
            $this->redirect('/admin/orders');
        }
        $items = (new Order())->items((int) $id);
        $this->view('Admin/orders/show', [
            'title' => 'Order ' . $order['order_number'],
            'order' => $order,
            'items' => $items,
        ], 'Admin/layouts/main');
    }

    public function receipt(string $id): void
    {
        AuthMiddleware::requireAdmin();
        $order = (new Order())->find((int) $id);
        if (!$order) {
            $this->redirect('/admin/orders');
        }
        $this->view('Admin/orders/receipt', [
            'order' => $order,
            'items' => (new Order())->items((int) $id),
        ]);
    }

    public function updateStatus(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        $status = $_POST['order_status'] ?? '';
        $allowed = ['received', 'baking', 'ready', 'out_for_delivery', 'completed', 'cancelled'];
        if (in_array($status, $allowed, true)) {
            (new Order())->updateStatus((int) $id, $status);
            Session::flash('success', 'Status updated.');
        }
        $this->redirect('/admin/orders/' . $id);
    }

    public function poll(): void
    {
        AuthMiddleware::requireAdmin();
        $after = (int) ($_GET['after'] ?? 0);
        $new = (new Order())->sinceId($after);
        $this->json([
            'orders' => $new,
            'latest' => (new Order())->latestId(),
            'pending' => (new Order())->pendingCount(),
        ]);
    }
}
