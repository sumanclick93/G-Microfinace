<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\Category;
use App\Models\SubCategory;

final class CategoryController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $categories = (new Category())->all('display_order ASC');
        $subModel = new SubCategory();
        foreach ($categories as &$c) {
            $c['subs'] = $subModel->byCategory((int) $c['id']);
        }

        $this->view('Admin/categories/index', [
            'title'      => 'Categories',
            'categories' => $categories,
            'success'    => Session::flash('success'),
        ], 'Admin/layouts/main');
    }

    public function store(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();

        $name = trim($_POST['name'] ?? '');
        $slug = Helpers::slugify($_POST['slug'] ?? $name);
        (new Category())->create([
            'name'          => $name,
            'slug'          => $slug,
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'status'        => isset($_POST['status']) ? 1 : 0,
            'image'         => null,
        ]);
        Session::flash('success', 'Category created.');
        $this->redirect('/admin/categories');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        $id = (int) $id;
        $cat = (new Category())->find($id);
        if (!$cat) {
            $this->redirect('/admin/categories');
        }

        (new Category())->update($id, [
            'name'          => trim($_POST['name'] ?? $cat['name']),
            'slug'          => Helpers::slugify($_POST['slug'] ?? $cat['slug']),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'status'        => isset($_POST['status']) ? 1 : 0,
            'image'         => $cat['image'],
        ]);
        Session::flash('success', 'Category updated.');
        $this->redirect('/admin/categories');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new Category())->delete((int) $id);
        Session::flash('success', 'Category deleted.');
        $this->redirect('/admin/categories');
    }

    public function storeSub(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        $name = trim($_POST['name'] ?? '');
        (new SubCategory())->create([
            'category_id'   => (int) ($_POST['category_id'] ?? 0),
            'name'          => $name,
            'slug'          => Helpers::slugify($_POST['slug'] ?? $name),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'status'        => 1,
        ]);
        Session::flash('success', 'Sub-category created.');
        $this->redirect('/admin/categories');
    }

    public function destroySub(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new SubCategory())->delete((int) $id);
        Session::flash('success', 'Sub-category deleted.');
        $this->redirect('/admin/categories');
    }

    public function reorder(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            (new Category())->updateOrder($ids);
        }
        $this->json(['ok' => true]);
    }

    public function subsJson(string $categoryId): void
    {
        AuthMiddleware::requireAdmin();
        $subs = (new SubCategory())->byCategory((int) $categoryId);
        $this->json($subs);
    }
}
