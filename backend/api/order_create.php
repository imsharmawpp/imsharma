<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Razorpay.php';
handleCors();
requirePost();

$input = jsonInput();
$items = $input['items'] ?? [];
$amount = floatval($input['amount'] ?? 0);
$shippingAddress = $input['shipping'] ?? [];

if (empty($items) || $amount < 1) {
    jsonResponse(['success' => false, 'message' => 'Cart is empty']);
}

// Recalculate amount server-side from product prices
try {
    $verifiedTotal = 0;
    $verifiedItems = [];
    foreach ($items as $item) {
        $pid = intval($item['id'] ?? 0);
        $qty = max(1, intval($item['qty'] ?? 1));
        $product = Database::row("SELECT id, title, price, inventory FROM products WHERE id = ? AND is_active = 1", [$pid]);
        if ($product) {
            $verifiedTotal += floatval($product['price']) * $qty;
            $verifiedItems[] = [
                'id' => $product['id'],
                'name' => $product['title'],
                'price' => floatval($product['price']),
                'qty' => $qty
            ];
        }
    }

    if ($verifiedTotal < 1) {
        jsonResponse(['success' => false, 'message' => 'Invalid items in cart']);
    }

    // Calculate shipping
    $freeShippingAbove = floatval(getSetting('shipping_free_above', '999'));
    $shippingFlat = floatval(getSetting('shipping_flat', '50'));
    $shippingCost = $verifiedTotal >= $freeShippingAbove ? 0 : $shippingFlat;
    $finalAmount = $verifiedTotal + $shippingCost;

    $user = getAuthUser();

    $orderId = Database::insert('orders', [
        'user_id' => $user['id'] ?? null,
        'customer_name' => $shippingAddress['name'] ?? ($user['name'] ?? 'Guest'),
        'customer_email' => $shippingAddress['email'] ?? ($user['email'] ?? ''),
        'customer_phone' => $shippingAddress['phone'] ?? ($user['phone'] ?? ''),
        'items_json' => json_encode($verifiedItems),
        'items_count' => count($verifiedItems),
        'subtotal' => $verifiedTotal,
        'shipping_cost' => $shippingCost,
        'amount' => $finalAmount,
        'shipping_name' => $shippingAddress['name'] ?? '',
        'shipping_phone' => $shippingAddress['phone'] ?? '',
        'shipping_address' => $shippingAddress['address'] ?? '',
        'shipping_city' => $shippingAddress['city'] ?? '',
        'shipping_state' => $shippingAddress['state'] ?? '',
        'shipping_pincode' => $shippingAddress['pincode'] ?? '',
        'status' => 'pending'
    ]);

    // Create Razorpay order
    $rzpKeyId = getSetting('razorpay_key_id', RAZORPAY_KEY_ID);
    $rzpSecret = getSetting('razorpay_key_secret', RAZORPAY_KEY_SECRET);
    $rzp = new Razorpay($rzpKeyId, $rzpSecret);

    $rzpOrder = $rzp->createOrder($finalAmount, 'ord_' . $orderId, [
        'order_id' => $orderId,
        'type' => 'product_order'
    ]);

    if (isset($rzpOrder['error'])) {
        jsonResponse(['success' => false, 'message' => 'Payment gateway error']);
    }

    // Update order with razorpay order id
    Database::exec("UPDATE orders SET razorpay_order_id = ? WHERE id = ?", [$rzpOrder['id'], $orderId]);

    // Create payment record
    Database::insert('payments', [
        'razorpay_order_id' => $rzpOrder['id'],
        'amount' => $finalAmount,
        'status' => 'created',
        'type' => 'order',
        'reference_id' => $orderId,
        'user_id' => $user['id'] ?? null
    ]);

    jsonResponse([
        'success' => true,
        'order_id' => $rzpOrder['id'],
        'internal_order_id' => $orderId,
        'amount' => $rzpOrder['amount'],
        'currency' => 'INR',
        'razorpay_key' => $rzp->getKeyId()
    ]);

} catch (Exception $e) {
    logDebug('Order create error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to create order']);
}
