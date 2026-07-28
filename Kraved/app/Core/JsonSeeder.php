<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Seeds JSON storage on first run when tables are empty.
 */
final class JsonSeeder
{
    public static function ensure(): void
    {
        $flag = dirname(__DIR__, 2) . '/storage/data/.seeded';
        if (is_file($flag)) {
            return;
        }

        $dir = dirname($flag);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Only seed if users table is empty
        if (JsonStore::table('users')->all() !== []) {
            file_put_contents($flag, date('c'));
            return;
        }

        self::seed();
        file_put_contents($flag, date('c'));
    }

    public static function seed(): void
    {
        JsonStore::clearCache();

        JsonStore::table('users')->replaceAll([
            [
                'id' => 1,
                'name' => 'Kraved Admin',
                'email' => 'admin@kraved.local',
                'phone' => '07000000000',
                'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'role' => 'admin',
                'address' => '12 Baker Street, London',
                'postcode' => 'W1U 3BW',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ], 2);

        JsonStore::table('settings')->replaceAll([
            ['id' => 1, 'setting_key' => 'store_name', 'setting_value' => 'Kraved'],
            ['id' => 2, 'setting_key' => 'store_tagline', 'setting_value' => 'Freshly Baked Cookies & Homemade Treats'],
            ['id' => 3, 'setting_key' => 'currency_symbol', 'setting_value' => '£'],
            ['id' => 4, 'setting_key' => 'free_delivery_threshold', 'setting_value' => '25.00'],
            ['id' => 5, 'setting_key' => 'collection_address', 'setting_value' => '12 Baker Street, London W1U 3BW'],
            ['id' => 6, 'setting_key' => 'asap_prep_mins', 'setting_value' => '35'],
            ['id' => 7, 'setting_key' => 'order_prefix', 'setting_value' => 'KRV'],
        ], 8);

        JsonStore::table('categories')->replaceAll([
            ['id' => 1, 'name' => 'Freshly Baked Cookies', 'slug' => 'freshly-baked-cookies', 'image' => null, 'display_order' => 1, 'status' => 1],
            ['id' => 2, 'name' => 'Warm Cookie Dough', 'slug' => 'warm-cookie-dough', 'image' => null, 'display_order' => 2, 'status' => 1],
            ['id' => 3, 'name' => 'Cookie Pies', 'slug' => 'cookie-pies', 'image' => null, 'display_order' => 3, 'status' => 1],
            ['id' => 4, 'name' => 'Cookie Milkshakes', 'slug' => 'cookie-milkshakes', 'image' => null, 'display_order' => 4, 'status' => 1],
            ['id' => 5, 'name' => 'Bundles & Deals', 'slug' => 'bundles-deals', 'image' => null, 'display_order' => 5, 'status' => 1],
        ], 6);

        JsonStore::table('sub_categories')->replaceAll([
            ['id' => 1, 'category_id' => 1, 'name' => 'Stuffed', 'slug' => 'stuffed-cookies', 'display_order' => 1, 'status' => 1],
            ['id' => 2, 'category_id' => 1, 'name' => 'Loaded', 'slug' => 'loaded-cookies', 'display_order' => 2, 'status' => 1],
            ['id' => 3, 'category_id' => 1, 'name' => 'Classic', 'slug' => 'classic-cookies', 'display_order' => 3, 'status' => 1],
            ['id' => 4, 'category_id' => 1, 'name' => 'Vegan', 'slug' => 'vegan-cookies', 'display_order' => 4, 'status' => 1],
        ], 5);

        JsonStore::table('addon_groups')->replaceAll([
            ['id' => 1, 'title' => 'Choose Cookie Base', 'min_selection' => 1, 'max_selection' => 1, 'is_required' => 1, 'display_order' => 1, 'status' => 1],
            ['id' => 2, 'title' => 'Extra Drizzle Sauce', 'min_selection' => 0, 'max_selection' => 2, 'is_required' => 0, 'display_order' => 2, 'status' => 1],
            ['id' => 3, 'title' => 'Add Toppings', 'min_selection' => 0, 'max_selection' => 3, 'is_required' => 0, 'display_order' => 3, 'status' => 1],
            ['id' => 4, 'title' => 'Side Gelato Scoop', 'min_selection' => 0, 'max_selection' => 1, 'is_required' => 0, 'display_order' => 4, 'status' => 1],
        ], 5);

        JsonStore::table('addons')->replaceAll([
            ['id' => 1, 'group_id' => 1, 'name' => 'Chocolate Chip', 'price' => 0.00, 'display_order' => 1, 'status' => 1],
            ['id' => 2, 'group_id' => 1, 'name' => 'Biscoff', 'price' => 0.00, 'display_order' => 2, 'status' => 1],
            ['id' => 3, 'group_id' => 1, 'name' => 'Lotus Speculoos', 'price' => 0.50, 'display_order' => 3, 'status' => 1],
            ['id' => 4, 'group_id' => 1, 'name' => 'Double Chocolate', 'price' => 0.50, 'display_order' => 4, 'status' => 1],
            ['id' => 5, 'group_id' => 2, 'name' => 'Nutella', 'price' => 0.75, 'display_order' => 1, 'status' => 1],
            ['id' => 6, 'group_id' => 2, 'name' => 'Biscoff Sauce', 'price' => 0.75, 'display_order' => 2, 'status' => 1],
            ['id' => 7, 'group_id' => 2, 'name' => 'White Chocolate', 'price' => 0.75, 'display_order' => 3, 'status' => 1],
            ['id' => 8, 'group_id' => 2, 'name' => 'Salted Caramel', 'price' => 0.75, 'display_order' => 4, 'status' => 1],
            ['id' => 9, 'group_id' => 3, 'name' => 'Kinder Bueno', 'price' => 1.00, 'display_order' => 1, 'status' => 1],
            ['id' => 10, 'group_id' => 3, 'name' => 'Ferrero Rocher', 'price' => 1.25, 'display_order' => 2, 'status' => 1],
            ['id' => 11, 'group_id' => 3, 'name' => 'Oreo Crumb', 'price' => 0.80, 'display_order' => 3, 'status' => 1],
            ['id' => 12, 'group_id' => 3, 'name' => 'Lotus Biscoff Crumb', 'price' => 0.80, 'display_order' => 4, 'status' => 1],
            ['id' => 13, 'group_id' => 3, 'name' => 'Mini Marshmallows', 'price' => 0.60, 'display_order' => 5, 'status' => 1],
            ['id' => 14, 'group_id' => 4, 'name' => 'Vanilla Gelato', 'price' => 1.50, 'display_order' => 1, 'status' => 1],
            ['id' => 15, 'group_id' => 4, 'name' => 'Chocolate Gelato', 'price' => 1.50, 'display_order' => 2, 'status' => 1],
            ['id' => 16, 'group_id' => 4, 'name' => 'Salted Caramel Gelato', 'price' => 1.75, 'display_order' => 3, 'status' => 1],
        ], 17);

        $now = date('Y-m-d H:i:s');
        JsonStore::table('products')->replaceAll([
            ['id' => 1, 'category_id' => 1, 'sub_category_id' => 1, 'title' => 'Nutella Stuffed Cookie', 'slug' => 'nutella-stuffed-cookie', 'short_description' => 'Warm cookie oozing with molten Nutella.', 'full_description' => 'Our signature 90g cookie, baked to order and stuffed with premium Nutella.', 'base_price' => 4.50, 'stock_qty' => 100, 'weight_label' => '90g', 'is_featured' => 1, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 1, 'created_at' => $now],
            ['id' => 2, 'category_id' => 1, 'sub_category_id' => 1, 'title' => 'Biscoff Stuffed Cookie', 'slug' => 'biscoff-stuffed-cookie', 'short_description' => 'Lotus Biscoff centre, golden edges.', 'full_description' => 'Crispy-edged cookie with a molten Biscoff core.', 'base_price' => 4.50, 'stock_qty' => 100, 'weight_label' => '90g', 'is_featured' => 1, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 2, 'created_at' => $now],
            ['id' => 3, 'category_id' => 1, 'sub_category_id' => 2, 'title' => 'Kinder Bueno Loaded Cookie', 'slug' => 'kinder-bueno-loaded-cookie', 'short_description' => 'Loaded with crushed Kinder Bueno.', 'full_description' => 'Chocolate chip base topped with Kinder Bueno pieces.', 'base_price' => 5.00, 'stock_qty' => 80, 'weight_label' => '100g', 'is_featured' => 1, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 3, 'created_at' => $now],
            ['id' => 4, 'category_id' => 1, 'sub_category_id' => 3, 'title' => 'Classic Chocolate Chip', 'slug' => 'classic-chocolate-chip', 'short_description' => 'The timeless bake.', 'full_description' => 'Brown-butter chocolate chip cookie.', 'base_price' => 3.50, 'stock_qty' => 150, 'weight_label' => '80g', 'is_featured' => 0, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 4, 'created_at' => $now],
            ['id' => 5, 'category_id' => 1, 'sub_category_id' => 4, 'title' => 'Vegan Dark Choc Cookie', 'slug' => 'vegan-dark-choc-cookie', 'short_description' => 'Plant-based, no compromise.', 'full_description' => 'Rich dark chocolate vegan cookie.', 'base_price' => 4.00, 'stock_qty' => 60, 'weight_label' => '85g', 'is_featured' => 0, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 5, 'created_at' => $now],
            ['id' => 6, 'category_id' => 2, 'sub_category_id' => null, 'title' => 'Warm Cookie Dough Pot', 'slug' => 'warm-cookie-dough-pot', 'short_description' => 'Edible dough, served warm.', 'full_description' => 'Safe-to-eat cookie dough served warm in a pot.', 'base_price' => 5.50, 'stock_qty' => 50, 'weight_label' => '150g pot', 'is_featured' => 1, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 1, 'created_at' => $now],
            ['id' => 7, 'category_id' => 3, 'sub_category_id' => null, 'title' => 'Deep Dish Cookie Pie', 'slug' => 'deep-dish-cookie-pie', 'short_description' => 'Shareable deep-dish cookie.', 'full_description' => 'Oven-baked deep dish cookie pie.', 'base_price' => 12.00, 'stock_qty' => 30, 'weight_label' => 'Serves 2–3', 'is_featured' => 1, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 1, 'created_at' => $now],
            ['id' => 8, 'category_id' => 4, 'sub_category_id' => null, 'title' => 'Cookie Crumble Milkshake', 'slug' => 'cookie-crumb-milkshake', 'short_description' => 'Blended with real cookie pieces.', 'full_description' => 'Thick milkshake blended with house cookies.', 'base_price' => 6.50, 'stock_qty' => 40, 'weight_label' => '16oz', 'is_featured' => 0, 'is_box_deal' => 0, 'box_max_items' => null, 'image' => null, 'status' => 1, 'display_order' => 1, 'created_at' => $now],
            ['id' => 9, 'category_id' => 5, 'sub_category_id' => null, 'title' => 'Box of 4 Mix & Match', 'slug' => 'box-of-4-mix-match', 'short_description' => 'Pick any 4 cookies.', 'full_description' => 'Build your own box of 4.', 'base_price' => 15.00, 'stock_qty' => 200, 'weight_label' => 'Box of 4', 'is_featured' => 1, 'is_box_deal' => 1, 'box_max_items' => 4, 'image' => null, 'status' => 1, 'display_order' => 1, 'created_at' => $now],
            ['id' => 10, 'category_id' => 5, 'sub_category_id' => null, 'title' => 'Box of 6 Mix & Match', 'slug' => 'box-of-6-mix-match', 'short_description' => 'Pick any 6 cookies.', 'full_description' => 'Best-value bundle of 6.', 'base_price' => 21.00, 'stock_qty' => 200, 'weight_label' => 'Box of 6', 'is_featured' => 1, 'is_box_deal' => 1, 'box_max_items' => 6, 'image' => null, 'status' => 1, 'display_order' => 2, 'created_at' => $now],
        ], 11);

        JsonStore::table('product_addon_groups')->replaceAll([
            ['product_id' => 1, 'group_id' => 1], ['product_id' => 1, 'group_id' => 2], ['product_id' => 1, 'group_id' => 3], ['product_id' => 1, 'group_id' => 4],
            ['product_id' => 2, 'group_id' => 1], ['product_id' => 2, 'group_id' => 2], ['product_id' => 2, 'group_id' => 3], ['product_id' => 2, 'group_id' => 4],
            ['product_id' => 3, 'group_id' => 2], ['product_id' => 3, 'group_id' => 3], ['product_id' => 3, 'group_id' => 4],
            ['product_id' => 5, 'group_id' => 2], ['product_id' => 5, 'group_id' => 3],
            ['product_id' => 6, 'group_id' => 2], ['product_id' => 6, 'group_id' => 3], ['product_id' => 6, 'group_id' => 4],
            ['product_id' => 7, 'group_id' => 1], ['product_id' => 7, 'group_id' => 2], ['product_id' => 7, 'group_id' => 3], ['product_id' => 7, 'group_id' => 4],
        ], 1);

        JsonStore::table('box_deal_products')->replaceAll([
            ['box_product_id' => 9, 'choice_product_id' => 1], ['box_product_id' => 9, 'choice_product_id' => 2], ['box_product_id' => 9, 'choice_product_id' => 3], ['box_product_id' => 9, 'choice_product_id' => 4], ['box_product_id' => 9, 'choice_product_id' => 5],
            ['box_product_id' => 10, 'choice_product_id' => 1], ['box_product_id' => 10, 'choice_product_id' => 2], ['box_product_id' => 10, 'choice_product_id' => 3], ['box_product_id' => 10, 'choice_product_id' => 4], ['box_product_id' => 10, 'choice_product_id' => 5],
        ], 1);

        JsonStore::table('delivery_zones')->replaceAll([
            ['id' => 1, 'postcode_prefix' => 'E1', 'delivery_fee' => 2.99, 'min_order_amount' => 12.00, 'estimated_mins' => 40, 'status' => 1],
            ['id' => 2, 'postcode_prefix' => 'E2', 'delivery_fee' => 2.99, 'min_order_amount' => 12.00, 'estimated_mins' => 45, 'status' => 1],
            ['id' => 3, 'postcode_prefix' => 'EC1', 'delivery_fee' => 3.49, 'min_order_amount' => 15.00, 'estimated_mins' => 40, 'status' => 1],
            ['id' => 4, 'postcode_prefix' => 'EC2', 'delivery_fee' => 3.49, 'min_order_amount' => 15.00, 'estimated_mins' => 40, 'status' => 1],
            ['id' => 5, 'postcode_prefix' => 'N1', 'delivery_fee' => 3.99, 'min_order_amount' => 15.00, 'estimated_mins' => 50, 'status' => 1],
            ['id' => 6, 'postcode_prefix' => 'NW1', 'delivery_fee' => 3.99, 'min_order_amount' => 15.00, 'estimated_mins' => 50, 'status' => 1],
            ['id' => 7, 'postcode_prefix' => 'SW1', 'delivery_fee' => 4.49, 'min_order_amount' => 18.00, 'estimated_mins' => 55, 'status' => 1],
            ['id' => 8, 'postcode_prefix' => 'W1', 'delivery_fee' => 3.49, 'min_order_amount' => 15.00, 'estimated_mins' => 40, 'status' => 1],
            ['id' => 9, 'postcode_prefix' => 'SE1', 'delivery_fee' => 3.99, 'min_order_amount' => 15.00, 'estimated_mins' => 50, 'status' => 1],
            ['id' => 10, 'postcode_prefix' => 'M1', 'delivery_fee' => 2.49, 'min_order_amount' => 10.00, 'estimated_mins' => 35, 'status' => 1],
        ], 11);

        JsonStore::table('orders')->replaceAll([], 1);
        JsonStore::table('order_items')->replaceAll([], 1);
    }
}
