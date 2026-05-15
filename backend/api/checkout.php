<?php
/**
 * Unified Checkout API
 *
 * Handles both online (Razorpay) and COD orders.
 * Requires phone verification token (from /api/verify_otp.php).
 *
 * Workflow:
 *   1. Frontend collects items + address + verification_token
 *   2. POST /api/checkout.php
 *   3. Server verifies OTP token, creates user (if new), creates address,
 *      creates order, optionally creates Razorpay order
 *   4. Returns either razorpay order details (online) or success (COD)
 */
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Razorpay.php';
handleCors();
requirePost();

$input = jsonInput();
$items = $input['items'] ?? [];
$paymentMethod = $input['payment_method'] ?? 'online';
$addressData = $input['address'] ?? [];
$verificationToken = $input['verification_token'] ?? '';
$customerInfo = $input['customer'] ?? [];
$createPassword = $input['password'] ?? '';

if (empty($items)) {
    jsonResponse(['success' => false, 'message' => 'Cart is empty']);
}
if (!in_array($paymentMethod, ['online', 'cod'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid payment method']);
}

// COD checks
$codEnabled = getSetting('cod_enabled', '1') === '1';
$codCharge = floatval(getSetting('cod_charge', '40'));
$codMaxAmount = floatval(getSetting('cod_max_amount', '5000'));
if ($paymentMethod === 'cod' && !$codEnabled) {
    jsonResponse(['success' => false, 'message' => 'Cash on Delivery is currently disabled']);
}

// Phone verification check
$requireVerification = getSetting('require_phone_verification', '1') === '1';
$verifiedPhone = null;

if ($requireVerification) {
    if (!$verificationToken) {
        jsonResponse(['success' => false, 'message' => 'Phone verification required'], 401);
    }
    $payload = JWT::decode($verificationToken);
    if (!$payload || empty($payload['phone'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid verification token. Please verify your phone again.']);
    }
    // Token shouldn't be older than 30 minutes
    if (isset($payload['exp_short']) && $payload['exp_short'] < time()) {
        jsonResponse(['success' => false, 'message' => 'Verification expired. Please verify your phone again.']);
    }
    $verifiedPhone = $payload['phone']; // E.164 format
}

// Validate address
foreach (['name', 'phone', 'address_line1', 'city', 'state', 'pincode'] as $f) {
    if (empty($addressData[$f])) {
        jsonResponse(['success' => false, 'message' => "Address field '{$f}' is required"]);
    }
}

$addrPhone = preg_replace('/\D/', '', $addressData['phone']);
if (strlen($addrPhone) !== 10) {
    jsonResponse(['success' => false, 'message' => 'Address phone must be 10 digits']);
}

// If verification is required, address phone should match verified phone
if ($requireVerification && $verifiedPhone) {
    $verifiedTen = preg_replace('/\D/', '', substr($verifiedPhone, -10));
    if ($verifiedTen !== $addrPhone) {
        jsonResponse(['success' => false, 'message' => 'Address phone must match verified phone number']);
    }
}

try {
    // Verify all items exist & calculate total server-side
    $verifiedTotal = 0;
    $verifiedItems = [];
    foreach ($items as $item) {
        $pid = intval($item['id'] ?? 0);
        $qty = max(1, intval($item['qty'] ?? 1));
        $product = Database::row("SELECT id, title, price, inventory FROM products WHERE id = ? AND is_active = 1", [$pid]);
        if (!$product) continue;
        if ($product['inventory'] < $qty) {
            jsonResponse(['success' => false, 'message' => "Insufficient stock for: " . $product['title']]);
        }
        $verifiedTotal += floatval($product['price']) * $qty;
        $verifiedItems[] = [
            'id' => $product['id'],
            'name' => $product['title'],
            'price' => floatval($product['price']),
            'qty' => $qty
        ];
    }

    if ($verifiedTotal < 1) {
        jsonResponse(['success' => false, 'message' => 'Invalid items in cart']);
    }

    // COD amount check
    if ($paymentMethod === 'cod' && $verifiedTotal > $codMaxAmount) {
        jsonResponse([
            'success' => false,
            'message' => "COD not available for orders above ₹{$codMaxAmount}. Please use online payment."
        ]);
    }

    // Calculate shipping & COD charges
    $freeShippingAbove = floatval(getSetting('shipping_free_above', '999'));
    $shippingFlat = floatval(getSetting('shipping_flat', '50'));
    $shippingCost = $verifiedTotal >= $freeShippingAbove ? 0 : $shippingFlat;
    $codChargeFinal = $paymentMethod === 'cod' ? $codCharge : 0;
    $finalAmount = $verifiedTotal + $shippingCost + $codChargeFinal;

    // ===== Find or create user =====
    $user = getAuthUser();
    $userId = $user['id'] ?? null;
    $email = strtolower(trim($customerInfo['email'] ?? $addressData['email'] ?? ''));

    if (!$userId && $email) {
        $existingUser = Database::row("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existingUser) {
            $userId = $existingUser['id'];
        } else {
            // Create user account automatically
            $userId = Database::insert('users', [
                'name' => clean($addressData['name']),
                'email' => $email,
                'phone' => $addrPhone,
                'city' => clean($addressData['city']),
                'state' => clean($addressData['state']),
                'pincode' => clean($addressData['pincode']),
                'password_hash' => $createPassword ? hashPassword($createPassword) : null,
                'phone_verified' => $requireVerification ? 1 : 0,
                'role' => 'user'
            ]);
        }
    } elseif (!$userId && !$email) {
        // Guest with phone only - create user with phone
        $existingUser = Database::row("SELECT id FROM users WHERE phone = ?", [$addrPhone]);
        if ($existingUser) {
            $userId = $existingUser['id'];
        } else {
            $userId = Database::insert('users', [
                'name' => clean($addressData['name']),
                'email' => 'guest_' . $addrPhone . '@vastukundali.local',
                'phone' => $addrPhone,
                'city' => clean($addressData['city']),
                'phone_verified' => 1,
                'role' => 'user'
            ]);
        }
    }

    // ===== Save address =====
    $addressId = null;
    if ($userId) {
        // Try to reuse identical address
        $existingAddr = Database::row(
            "SELECT id FROM addresses WHERE user_id = ? AND address_line1 = ? AND pincode = ? LIMIT 1",
            [$userId, clean($addressData['address_line1']), clean($addressData['pincode'])]
        );
        if ($existingAddr) {
            $addressId = $existingAddr['id'];
        } else {
            $addressId = Database::insert('addresses', [
                'user_id' => $userId,
                'label' => clean($addressData['label'] ?? 'Home'),
                'name' => clean($addressData['name']),
                'phone' => $addrPhone,
                'address_line1' => clean($addressData['address_line1']),
                'address_line2' => clean($addressData['address_line2'] ?? ''),
                'city' => clean($addressData['city']),
                'state' => clean($addressData['state']),
                'pincode' => clean($addressData['pincode']),
                'country' => clean($addressData['country'] ?? 'India'),
                'is_default' => 1
            ]);
        }
    }

    // ===== Create order =====
    $orderStatus = $paymentMethod === 'cod' ? 'paid' : 'pending';  // COD = treated as confirmed
    if ($paymentMethod === 'cod') $orderStatus = 'paid';  // Will be updated to 'shipped' / 'delivered' later

    $orderId = Database::insert('orders', [
        'user_id' => $userId,
        'customer_name' => clean($addressData['name']),
        'customer_email' => $email,
        'customer_phone' => $addrPhone,
        'items_json' => json_encode($verifiedItems),
        'items_count' => count($verifiedItems),
        'subtotal' => $verifiedTotal,
        'shipping_cost' => $shippingCost,
        'cod_charge' => $codChargeFinal,
        'amount' => $finalAmount,
        'payment_method' => $paymentMethod,
        'address_id' => $addressId,
        'phone_verified' => $requireVerification ? 1 : 0,
        'shipping_name' => clean($addressData['name']),
        'shipping_phone' => $addrPhone,
        'shipping_address' => clean($addressData['address_line1']) . (!empty($addressData['address_line2']) ? ', ' . clean($addressData['address_line2']) : ''),
        'shipping_city' => clean($addressData['city']),
        'shipping_state' => clean($addressData['state']),
        'shipping_pincode' => clean($addressData['pincode']),
        'shipping_country' => clean($addressData['country'] ?? 'India'),
        'status' => $paymentMethod === 'cod' ? 'processing' : 'pending'
    ]);

    // ===== COD: order is done =====
    if ($paymentMethod === 'cod') {
        // Decrement inventory
        foreach ($verifiedItems as $item) {
            Database::exec("UPDATE products SET inventory = GREATEST(0, inventory - ?) WHERE id = ?", [$item['qty'], $item['id']]);
        }

        // Capture as lead
        @Database::insert('leads', [
            'name' => clean($addressData['name']),
            'email' => $email,
            'phone' => $addrPhone,
            'source' => 'cod_order',
            'status' => 'converted'
        ]);

        // Send confirmation email
        if ($email) {
            $itemList = implode(', ', array_column($verifiedItems, 'name'));
            @sendEmail($email, 'Order Confirmed - VastuKundali', "
                <h2>Thank you for your order!</h2>
                <p>Hi " . htmlspecialchars($addressData['name']) . ",</p>
                <p>Your COD order #{$orderId} has been confirmed.</p>
                <p><strong>Items:</strong> {$itemList}</p>
                <p><strong>Total Payable on Delivery:</strong> ₹" . number_format($finalAmount, 0) . "</p>
                <p>You'll pay ₹" . number_format($finalAmount, 0) . " in cash when our delivery partner reaches you.</p>
                <p>Track your order: " . SITE_URL . "/frontend/pages/dashboard.html</p>
            ");
        }

        jsonResponse([
            'success' => true,
            'order_id' => $orderId,
            'payment_method' => 'cod',
            'amount' => $finalAmount,
            'message' => 'Order placed successfully! Pay ₹' . number_format($finalAmount, 0) . ' on delivery.',
            'redirect' => 'order_success.html?id=' . $orderId
        ]);
    }

    // ===== Online: create Razorpay order =====
    $rzpKeyId = getSetting('razorpay_key_id', RAZORPAY_KEY_ID);
    $rzpSecret = getSetting('razorpay_key_secret', RAZORPAY_KEY_SECRET);
    $rzp = new Razorpay($rzpKeyId, $rzpSecret);

    $rzpOrder = $rzp->createOrder($finalAmount, 'ord_' . $orderId, [
        'order_id' => $orderId,
        'type' => 'product_order'
    ]);

    if (isset($rzpOrder['error'])) {
        jsonResponse(['success' => false, 'message' => 'Payment gateway error: ' . ($rzpOrder['error']['description'] ?? 'unknown')]);
    }

    Database::exec("UPDATE orders SET razorpay_order_id = ? WHERE id = ?", [$rzpOrder['id'], $orderId]);

    Database::insert('payments', [
        'razorpay_order_id' => $rzpOrder['id'],
        'amount' => $finalAmount,
        'status' => 'created',
        'type' => 'order',
        'reference_id' => $orderId,
        'user_id' => $userId,
        'customer_email' => $email,
        'customer_phone' => $addrPhone
    ]);

    jsonResponse([
        'success' => true,
        'order_id' => $rzpOrder['id'],
        'internal_order_id' => $orderId,
        'amount' => $rzpOrder['amount'],
        'currency' => 'INR',
        'razorpay_key' => $rzp->getKeyId(),
        'payment_method' => 'online',
        'breakdown' => [
            'subtotal' => $verifiedTotal,
            'shipping' => $shippingCost,
            'total' => $finalAmount
        ]
    ]);

} catch (Exception $e) {
    logDebug('Checkout error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()]);
}
