<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\Addon;
use App\Models\AddonGroup;

final class AddonController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $groups = (new AddonGroup())->adminAll();
        $addonModel = new Addon();
        foreach ($groups as &$g) {
            $g['addons'] = $addonModel->byGroup((int) $g['id']);
        }
        $this->view('Admin/addons/index', [
            'title'  => 'Add-ons',
            'groups' => $groups,
            'success'=> Session::flash('success'),
        ], 'Admin/layouts/main');
    }

    public function storeGroup(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new AddonGroup())->create([
            'title'         => trim($_POST['title'] ?? ''),
            'min_selection' => (int) ($_POST['min_selection'] ?? 0),
            'max_selection' => (int) ($_POST['max_selection'] ?? 1),
            'is_required'   => isset($_POST['is_required']) ? 1 : 0,
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'status'        => 1,
        ]);
        Session::flash('success', 'Addon group created.');
        $this->redirect('/admin/addons');
    }

    public function storeAddon(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new Addon())->create([
            'group_id'      => (int) ($_POST['group_id'] ?? 0),
            'name'          => trim($_POST['name'] ?? ''),
            'price'         => (float) ($_POST['price'] ?? 0),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'status'        => 1,
        ]);
        Session::flash('success', 'Addon created.');
        $this->redirect('/admin/addons');
    }

    public function destroyGroup(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new AddonGroup())->delete((int) $id);
        Session::flash('success', 'Group deleted.');
        $this->redirect('/admin/addons');
    }

    public function destroyAddon(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new Addon())->delete((int) $id);
        Session::flash('success', 'Addon deleted.');
        $this->redirect('/admin/addons');
    }
}
