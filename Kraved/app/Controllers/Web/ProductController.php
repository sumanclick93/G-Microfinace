<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;

final class ProductController extends Controller
{
    public function category(string $slug): void
    {
        $category = (new Category())->findBySlug($slug);
        if (!$category) {
            http_response_code(404);
            echo 'Category not found';
            return;
        }

        $this->view('Storefront/products/category', [
            'title'      => $category['name'],
            'category'   => $category,
            'products'   => (new Product())->byCategory((int) $category['id']),
            'categories' => (new Category())->withSubCategories(),
            'cartCount'  => Cart::count(),
            'fulfillment'=> Cart::fulfillment(),
        ], 'Storefront/layouts/main');
    }

    public function subCategory(string $slug): void
    {
        $sub = (new SubCategory())->findBySlug($slug);
        if (!$sub) {
            http_response_code(404);
            echo 'Sub-category not found';
            return;
        }

        $products = (new Product())->bySubCategory((int) $sub['id']);

        $this->view('Storefront/products/category', [
            'title'      => $sub['name'],
            'category'   => $sub,
            'products'   => $products,
            'categories' => (new Category())->withSubCategories(),
            'cartCount'  => Cart::count(),
            'fulfillment'=> Cart::fulfillment(),
        ], 'Storefront/layouts/main');
    }

    /** AJAX: product customisation payload */
    public function customise(string $id): void
    {
        $product = (new Product())->find((int) $id);
        if (!$product || !(int) $product['status']) {
            $this->json(['error' => 'Product not found'], 404);
        }

        $productModel = new Product();
        $payload = [
            'product' => $product,
            'groups'  => $productModel->withAddonGroups((int) $id),
            'box_choices' => (int) $product['is_box_deal']
                ? $productModel->boxChoices((int) $id)
                : [],
        ];
        $this->json($payload);
    }
}
