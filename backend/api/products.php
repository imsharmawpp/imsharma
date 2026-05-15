<?php
require_once __DIR__ . '/../config/config.php';
handleCors();

try {
    $where = "WHERE is_active = 1";
    $params = [];

    if (!empty($_GET['category'])) {
        $where .= " AND category = ?";
        $params[] = clean($_GET['category']);
    }

    if (!empty($_GET['featured'])) {
        $where .= " AND is_featured = 1";
    }

    if (!empty($_GET['search'])) {
        $where .= " AND (title LIKE ? OR description LIKE ?)";
        $term = '%' . clean($_GET['search']) . '%';
        $params[] = $term;
        $params[] = $term;
    }

    $limit = min(100, intval($_GET['limit'] ?? 50));
    $orderBy = "ORDER BY is_featured DESC, id DESC";
    $sortBy = $_GET['sort'] ?? '';
    if ($sortBy === 'price_low') $orderBy = "ORDER BY price ASC";
    elseif ($sortBy === 'price_high') $orderBy = "ORDER BY price DESC";
    elseif ($sortBy === 'rating') $orderBy = "ORDER BY rating DESC";
    elseif ($sortBy === 'newest') $orderBy = "ORDER BY id DESC";

    $sql = "SELECT id, title as name, slug, short_description as description,
                   category, price, original_price, icon, image, rating, reviews, badge,
                   is_featured, inventory
            FROM products {$where} {$orderBy} LIMIT {$limit}";

    $products = Database::all($sql, $params);

    jsonResponse(['success' => true, 'products' => $products, 'count' => count($products)]);

} catch (Exception $e) {
    logDebug('Products error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to load products', 'products' => []]);
}
