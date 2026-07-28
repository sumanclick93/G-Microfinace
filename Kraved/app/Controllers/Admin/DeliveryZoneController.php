<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\DeliveryZone;

final class DeliveryZoneController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $this->view('Admin/delivery_zones/index', [
            'title'  => 'Delivery Zones',
            'zones'  => (new DeliveryZone())->all('postcode_prefix ASC'),
            'success'=> Session::flash('success'),
        ], 'Admin/layouts/main');
    }

    public function store(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new DeliveryZone())->create([
            'postcode_prefix'  => trim($_POST['postcode_prefix'] ?? ''),
            'delivery_fee'     => (float) ($_POST['delivery_fee'] ?? 0),
            'min_order_amount' => (float) ($_POST['min_order_amount'] ?? 0),
            'estimated_mins'   => (int) ($_POST['estimated_mins'] ?? 45),
            'status'           => 1,
        ]);
        Session::flash('success', 'Zone added.');
        $this->redirect('/admin/delivery-zones');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new DeliveryZone())->update((int) $id, [
            'postcode_prefix'  => trim($_POST['postcode_prefix'] ?? ''),
            'delivery_fee'     => (float) ($_POST['delivery_fee'] ?? 0),
            'min_order_amount' => (float) ($_POST['min_order_amount'] ?? 0),
            'estimated_mins'   => (int) ($_POST['estimated_mins'] ?? 45),
            'status'           => isset($_POST['status']) ? 1 : 0,
        ]);
        Session::flash('success', 'Zone updated.');
        $this->redirect('/admin/delivery-zones');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new DeliveryZone())->delete((int) $id);
        Session::flash('success', 'Zone deleted.');
        $this->redirect('/admin/delivery-zones');
    }
}
