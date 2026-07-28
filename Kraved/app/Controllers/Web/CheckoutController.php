<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;

final class CheckoutController extends Controller
{
    public function index(): void
    {
        if (Cart::count() === 0) {
            Session::flash('error', 'Your basket is empty.');
            $this->redirect('/');
        }

        $user = AuthMiddleware::user();
        $this->view('Storefront/checkout/index', [
            'title'       => 'Checkout',
            'cart'        => Cart::summary(),
            'user'        => $user,
            'fulfillment' => Cart::fulfillment(),
            'cartCount'   => Cart::count(),
            'error'       => Session::flash('error'),
        ], 'Storefront/layouts/main');
    }

    public function place(): void
    {
        Helpers::requireCsrf();

        if (Cart::count() === 0) {
            Session::flash('error', 'Your basket is empty.');
            $this->redirect('/');
        }

        $fulfillment = Cart::fulfillment();
        $type = $fulfillment['type'] ?? 'collection';
        $name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $address = trim($_POST['delivery_address'] ?? '');
        $postcode = strtoupper(trim($_POST['postcode'] ?? ($fulfillment['postcode'] ?? '')));
        $payment = ($_POST['payment_method'] ?? 'cod') === 'card' ? 'card' : 'cod';
        $notes = trim($_POST['notes'] ?? '');
        $timeSlot = trim($_POST['delivery_time_slot'] ?? ($fulfillment['time_slot'] ?? 'ASAP'));

        if ($name === '' || $email === '' || $phone === '') {
            Session::flash('error', 'Please fill in your contact details.');
            $this->redirect('/checkout');
        }

        $deliveryFee = 0.0;
        if ($type === 'delivery') {
            if ($address === '' || $postcode === '') {
                Session::flash('error', 'Delivery address and postcode are required.');
                $this->redirect('/checkout');
            }
            $zone = (new DeliveryZone())->findByPrefix(Helpers::postcodePrefix($postcode));
            if (!$zone) {
                Session::flash('error', 'We cannot deliver to that postcode.');
                $this->redirect('/checkout');
            }
            if (Cart::subtotal() < (float) $zone['min_order_amount']) {
                Session::flash('error', 'Minimum order for your area is ' . Helpers::money((float) $zone['min_order_amount']));
                $this->redirect('/checkout');
            }
            Cart::setFulfillment(array_merge($fulfillment, [
                'fee' => (float) $zone['delivery_fee'],
                'min_order' => (float) $zone['min_order_amount'],
                'postcode' => $postcode,
                'zone' => $zone,
            ]));
            $deliveryFee = Cart::deliveryFee();
        }

        $customerId = null;
        $sessionUser = AuthMiddleware::user();
        if ($sessionUser) {
            $customerId = (int) $sessionUser['id'];
        } elseif (!empty($_POST['create_account']) && !empty($_POST['password'])) {
            $userModel = new User();
            if (!$userModel->findByEmail($email)) {
                $customerId = $userModel->create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                    'role' => 'customer',
                    'address' => $address ?: null,
                    'postcode' => $postcode ?: null,
                ]);
                Session::set('user', [
                    'id' => $customerId,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'customer',
                ]);
            }
        }

        $orderModel = new Order();
        $orderId = $orderModel->create([
            'order_number'       => Helpers::orderNumber(),
            'customer_id'        => $customerId,
            'fulfillment_type'   => $type,
            'customer_name'      => $name,
            'customer_email'     => $email,
            'customer_phone'     => $phone,
            'delivery_address'   => $type === 'delivery' ? $address : null,
            'postcode'           => $postcode ?: null,
            'subtotal'           => Cart::subtotal(),
            'delivery_fee'       => $deliveryFee,
            'total_amount'       => Cart::subtotal() + $deliveryFee,
            'payment_status'     => $payment === 'cod' ? 'cod' : 'pending',
            'payment_method'     => $payment,
            'order_status'       => 'received',
            'delivery_time_slot' => $timeSlot,
            'notes'              => $notes ?: null,
        ]);

        $order = $orderModel->find($orderId);
        foreach (Cart::items() as $item) {
            $addonsPayload = [
                'addons' => $item['addons'] ?? [],
                'box_picks' => $item['box_picks'] ?? [],
            ];
            $orderModel->addItem($orderId, [
                'product_id'       => $item['product_id'],
                'product_name'     => $item['name'],
                'price'            => $item['unit_price'],
                'quantity'         => $item['quantity'],
                'addons_json'      => json_encode($addonsPayload),
                'total_item_price' => $item['line_total'],
            ]);
        }

        Cart::clear();
        $this->redirect('/order/' . $order['order_number']);
    }
}
