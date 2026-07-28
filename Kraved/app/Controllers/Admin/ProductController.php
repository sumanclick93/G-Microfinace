<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;

final class ProductController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $this->view('Admin/products/index', [
            'title'    => 'Products',
            'products' => (new Product())->adminAll(),
            'success'  => Session::flash('success'),
        ], 'Admin/layouts/main');
    }

    public function create(): void
    {
        AuthMiddleware::requireAdmin();
        $this->formView(null);
    }

    public function edit(string $id): void
    {
        AuthMiddleware::requireAdmin();
        $product = (new Product())->find((int) $id);
        if (!$product) {
            $this->redirect('/admin/products');
        }
        $this->formView($product);
    }

    private function formView(?array $product): void
    {
        $productModel = new Product();
        $this->view('Admin/products/form', [
            'title'        => $product ? 'Edit Product' : 'Add Product',
            'product'      => $product,
            'categories'   => (new Category())->all('display_order ASC'),
            'addonGroups'  => (new AddonGroup())->adminAll(),
            'allProducts'  => $productModel->adminAll(),
            'selectedGroups' => $product ? $productModel->addonGroupIds((int) $product['id']) : [],
            'selectedChoices' => $product ? $productModel->boxChoiceIds((int) $product['id']) : [],
            'subs'         => $product && $product['category_id']
                ? (new SubCategory())->byCategory((int) $product['category_id'])
                : [],
        ], 'Admin/layouts/main');
    }

    public function store(): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        $data = $this->payloadFromPost();
        $data['image'] = $this->handleUpload(null);
        $id = (new Product())->create($data);
        $this->syncRelations($id);
        Session::flash('success', 'Product created.');
        $this->redirect('/admin/products');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        $id = (int) $id;
        $existing = (new Product())->find($id);
        if (!$existing) {
            $this->redirect('/admin/products');
        }
        $data = $this->payloadFromPost();
        $data['image'] = $this->handleUpload($existing['image']);
        (new Product())->update($id, $data);
        $this->syncRelations($id);
        Session::flash('success', 'Product updated.');
        $this->redirect('/admin/products');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();
        Helpers::requireCsrf();
        (new Product())->delete((int) $id);
        Session::flash('success', 'Product deleted.');
        $this->redirect('/admin/products');
    }

    private function payloadFromPost(): array
    {
        $title = trim($_POST['title'] ?? '');
        return [
            'category_id'       => (int) ($_POST['category_id'] ?? 0),
            'sub_category_id'   => (int) ($_POST['sub_category_id'] ?? 0) ?: null,
            'title'             => $title,
            'slug'              => Helpers::slugify($_POST['slug'] ?? $title),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'full_description'  => trim($_POST['full_description'] ?? ''),
            'base_price'        => (float) ($_POST['base_price'] ?? 0),
            'stock_qty'         => (int) ($_POST['stock_qty'] ?? 0),
            'weight_label'      => trim($_POST['weight_label'] ?? ''),
            'is_featured'       => isset($_POST['is_featured']) ? 1 : 0,
            'is_box_deal'       => isset($_POST['is_box_deal']) ? 1 : 0,
            'box_max_items'     => isset($_POST['is_box_deal']) ? (int) ($_POST['box_max_items'] ?? 4) : null,
            'status'            => isset($_POST['status']) ? 1 : 0,
            'display_order'     => (int) ($_POST['display_order'] ?? 0),
        ];
    }

    private function syncRelations(int $id): void
    {
        $groups = array_map('intval', $_POST['addon_groups'] ?? []);
        (new Product())->syncAddonGroups($id, $groups);

        if (isset($_POST['is_box_deal'])) {
            $choices = array_map('intval', $_POST['box_choices'] ?? []);
            (new Product())->syncBoxChoices($id, $choices);
        }
    }

    private function handleUpload(?string $current): ?string
    {
        if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            return $current;
        }

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $current;
        }

        $dir = dirname(__DIR__, 3) . '/public/uploads/products/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'p_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename)) {
            return 'products/' . $filename;
        }
        return $current;
    }
}
