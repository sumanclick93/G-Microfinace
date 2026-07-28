<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;

final class HomeController extends Controller
{
    public function index(): void
    {
        $categories = (new Category())->withSubCategories();
        $productModel = new Product();
        $menu = [];
        foreach ($categories as $cat) {
            $menu[] = [
                'category' => $cat,
                'products' => $productModel->byCategory((int) $cat['id']),
            ];
        }

        $this->view('Storefront/home/index', [
            'title'       => 'Order Fresh Cookies',
            'categories'  => $categories,
            'menu'        => $menu,
            'featured'    => $productModel->featured(6),
            'fulfillment' => Cart::fulfillment(),
            'cartCount'   => Cart::count(),
        ], 'Storefront/layouts/main');
    }
}
