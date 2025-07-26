<?php

include 'header.php';
include 'config.php';
include 'functions.php';

// Session check
$user_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;

// Pagination and filters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$category_filter = $_GET['category'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$sort_by = $_GET['sort'] ?? 'name';
$sort_order = strtoupper($_GET['order'] ?? 'ASC');

// Whitelist and map sort fields to avoid SQL injection
$sort_fields = ['name' => 'name', 'price' => 'price', 'created_at' => 'created_at'];
$sort_orders = ['ASC', 'DESC'];

if (!array_key_exists($sort_by, $sort_fields)) {
    $sort_by = 'name';
}
if (!in_array($sort_order, $sort_orders)) {
    $sort_order = 'ASC';
}

// Build WHERE and params
$where_conditions = [];
$params = [];
$param_count = 0;

if ($category_filter !== '') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $param_count++;
}

if ($search_query !== '') {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $param_count += 2;
}

$where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get total products count
    $count_sql = "SELECT COUNT(*) FROM products $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    
    // Bind parameters for count query
    if (!empty($params)) {
        $count_stmt->execute($params);
    } else {
        $count_stmt->execute();
    }
    
    $total_products = (int)$count_stmt->fetchColumn();
    $total_pages = (int)ceil($total_products / $per_page);

    // Fetch products for current page
    $sql = "SELECT id, name, description, price, image_path, category, sku, created_at
            FROM products
            $where_clause
            ORDER BY {$sort_fields[$sort_by]} $sort_order
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);

    // Bind params for filters first
    $bind_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($bind_index, $param);
        $bind_index++;
    }
    
    // Bind limit and offset as integers
    $stmt->bindValue($bind_index, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($bind_index + 1, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch categories for filter dropdown
    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Products page error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $total_products = 0;
    $total_pages = 0;
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

// Helper function to check if image exists
function getProductImage($imagePath) {
    if (!empty($imagePath)) {
        $relativePath = __DIR__ . '/Red/uploads' . ltrim($imagePath, '/Red/uploads');
        if (file_exists($relativePath)) {
            return $imagePath;
        }
    }
    return null;
}

// Helper function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'vegetables' => '🥕',
        'fruits' => '🍎',
        'grains' => '🌾',
        'dairy' => '🥛',
        'meat' => '🥩',
        'herbs' => '🌿',
        'seeds' => '🌱',
        'organic' => '🌿',
        'fresh' => '🥬',
        'seasonal' => '🍂'
    ];
    return $icons[strtolower($category)] ?? '🌱';
}

// Helper function to get freshness badge
function getFreshnessBadge($created_at) {
    $days_old = (time() - strtotime($created_at)) / (24 * 60 * 60);
    if ($days_old <= 1) {
        return '<span class="freshness-badge fresh">Fresh Today</span>';
    } elseif ($days_old <= 3) {
        return '<span class="freshness-badge recent">Farm Fresh</span>';
    } elseif ($days_old <= 7) {
        return '<span class="freshness-badge good">This Week</span>';
    }
    return '';
}

// Debug function (remove in production)
function debugFilters() {
    global $search_query, $category_filter, $sort_by, $sort_order, $where_clause, $params;
    if (isset($_GET['debug'])) {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Debug Info:</strong><br>";
        echo "Search Query: '" . htmlspecialchars($search_query) . "'<br>";
        echo "Category Filter: '" . htmlspecialchars($category_filter) . "'<br>";
        echo "Sort By: " . htmlspecialchars($sort_by) . "<br>";
        echo "Sort Order: " . htmlspecialchars($sort_order) . "<br>";
        echo "WHERE Clause: " . htmlspecialchars($where_clause) . "<br>";
        echo "Parameters: " . json_encode($params) . "<br>";
        echo "</div>";
    }
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

:root {
    --primary-green: #2d5016;
    --accent-green: #4a7c59;
    --light-green: #8fbc8f;
    --cream: #f8f6f0;
    --warm-white: #fefcf7;
    --earth-brown: #8b4513;
    --harvest-orange: #ff8c00;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --border-light: #e8e8e8;
    --shadow-soft: rgba(0, 0, 0, 0.08);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, var(--warm-white) 0%, var(--cream) 100%);
    min-height: 100vh;
}

.products-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    font-family: 'Poppins', sans-serif;
}

.hero-section {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-green) 100%);
    color: white;
    padding: 80px 20px;
    text-align: center;
    margin-bottom: 60px;
    border-radius: 0 0 30px 30px;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y="50" font-size="20" opacity="0.1">🌾🌱🥕🍎🌿</text></svg>') repeat;
    opacity: 0.3;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.page-title {
    font-size: 48px;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-bottom: 15px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.page-subtitle {
    font-size: 20px;
    font-weight: 300;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.filters-section {
    background: white;
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px var(--shadow-soft);
    border: 1px solid var(--border-light);
}

.filters-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.filters-icon {
    font-size: 24px;
}

.filters-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--primary-green);
    margin: 0;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 25px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-input, .filter-select {
    padding: 15px 20px;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
    font-family: 'Poppins', sans-serif;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: var(--accent-green);
    box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
    transform: translateY(-2px);
}

.filter-btn {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-green) 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    height: fit-content;
    box-shadow: 0 4px 15px rgba(45, 80, 22, 0.3);
}

.filter-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(45, 80, 22, 0.4);
}

.clear-filters {
    background: transparent;
    color: var(--text-light);
    border: 2px solid var(--border-light);
    padding: 15px 30px;
    border-radius: 12px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-weight: 500;
}

.clear-filters:hover {
    background: var(--cream);
    color: var(--text-dark);
    transform: translateY(-2px);
}

.results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px var(--shadow-soft);
    font-size: 14px;
    color: var(--text-light);
}

.results-count {
    font-weight: 600;
    color: var(--primary-green);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.product-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s ease;
    cursor: pointer;
    position: relative;
    box-shadow: 0 4px 20px var(--shadow-soft);
    border: 1px solid var(--border-light);
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.product-image-container {
    position: relative;
    width: 100%;
    height: 250px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--cream) 0%, var(--warm-white) 100%);
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.product-card:hover .product-image {
    transform: scale(1.1);
}

.product-image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--cream) 0%, var(--warm-white) 100%);
    color: var(--text-light);
    font-size: 16px;
    flex-direction: column;
    gap: 10px;
}

.product-placeholder-icon {
    font-size: 48px;
    opacity: 0.5;
}

.product-badges {
    position: absolute;
    top: 15px;
    left: 15px;
    right: 15px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.product-sku {
    background: rgba(255, 255, 255, 0.95);
    color: var(--text-dark);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    backdrop-filter: blur(10px);
}

.freshness-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.freshness-badge.fresh {
    background: linear-gradient(135deg, #4caf50, #8bc34a);
    color: white;
}

.freshness-badge.recent {
    background: linear-gradient(135deg, #ff9800, #ffc107);
    color: white;
}

.freshness-badge.good {
    background: linear-gradient(135deg, #2196f3, #03a9f4);
    color: white;
}

.product-info {
    padding: 30px;
}

.product-category {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--accent-green);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    font-weight: 600;
}

.product-name {
    font-size: 22px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 12px;
    line-height: 1.3;
}

.product-description {
    font-size: 14px;
    color: var(--text-light);
    line-height: 1.6;
    margin-bottom: 20px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-price {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-green);
    margin-bottom: 25px;
}

.product-actions {
    display: flex;
    gap: 12px;
}

.btn-add-cart {
    flex: 1;
    background: linear-gradient(135deg, var(--harvest-orange) 0%, #ff6b35 100%);
    color: white;
    border: none;
    padding: 15px 25px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.btn-add-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
}

.btn-add-cart:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-view {
    background: transparent;
    color: var(--accent-green);
    border: 2px solid var(--accent-green);
    padding: 15px 20px;
    border-radius: 12px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}

.btn-view:hover {
    background: var(--accent-green);
    color: white;
    transform: translateY(-2px);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 50px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 12px 18px;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
    min-width: 45px;
    text-align: center;
    background: white;
}

.pagination a:hover {
    background: var(--cream);
    color: var(--text-dark);
    transform: translateY(-2px);
}

.pagination .current {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-green) 100%);
    color: white;
    border-color: var(--primary-green);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--text-light);
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px var(--shadow-soft);
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--text-dark);
}

.empty-state p {
    font-size: 16px;
    margin-bottom: 30px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.loading-state {
    text-align: center;
    padding: 40px;
    color: var(--text-light);
}

.error-message {
    background: linear-gradient(135deg, #fee 0%, #fdd 100%);
    border: 2px solid #fcc;
    color: #c33;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .products-container {
        padding: 0 15px;
    }
    
    .hero-section {
        padding: 60px 15px;
        margin-bottom: 40px;
    }
    
    .page-title {
        font-size: 36px;
    }
    
    .page-subtitle {
        font-size: 18px;
    }
    
    .filters-section {
        padding: 25px;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .results-info {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .product-info {
        padding: 20px;
    }
    
    .pagination {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .pagination a, .pagination span {
        padding: 10px 14px;
        font-size: 12px;
    }
}
</style>

<main class="products-container">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <h1 class="page-title">Fresh From Our Farm</h1>
            <p class="page-subtitle">
                Discover the finest selection of farm-fresh produce, grown with love and harvested with care. 
                From our fields to your table, taste the difference quality makes.
            </p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="filters-header">
            <span class="filters-icon">🌾</span>
            <h2 class="filters-title">Find Your Perfect Produce</h2>
        </div>
        <form method="GET" action="products.php">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">
                        <span>🔍</span>
                        Search Products
                    </label>
                    <input type="text" name="search" class="filter-input" 
                           placeholder="Search fruits, vegetables, herbs..." 
                           value="<?= htmlspecialchars($search_query) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">
                        <span>🏷️</span>
                        Category
                    </label>
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                    <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                <?= getCategoryIcon($cat['category']) ?> <?= htmlspecialchars(ucfirst($cat['category'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">
                        <span>📊</span>
                        Sort By
                    </label>
                    <select name="sort" class="filter-select">
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Product Name</option>
                        <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Price</option>
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Freshest First</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">
                        <span>🔄</span>
                        Order
                    </label>
                    <select name="order" class="filter-select">
                        <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>A to Z / Low to High</option>
                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Z to A / High to Low</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    🌱 Apply Filters
                </button>
                
                <?php if (!empty($search_query) || !empty($category_filter)): ?>
                    <a href="products.php" class="clear-filters">Clear All Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <?php if ($total_products > 0): ?>
        <div class="results-info">
            <span class="results-count">
                🌿 Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_products) ?> 
                of <?= number_format($total_products) ?> fresh products
            </span>
            <span>Page <?= $page ?> of <?= number_format($total_pages) ?></span>
        </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🥕</div>
            <h3>No Fresh Produce Found</h3>
            <p>We couldn't find any products matching your search criteria. Our harvest might be coming in soon!</p>
            <?php if (!empty($search_query) || !empty($category_filter)): ?>
                <a href="products.php" class="filter-btn">🌾 View All Products</a>
            <?php else: ?>
                <p>Please check back later or contact us about upcoming harvests.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): 
                $product_image = getProductImage($product['image_path']);
            ?>
                <article class="product-card" onclick="window.location.href='product-detail.php?id=<?= $product['id'] ?>'">
                    <div class="product-image-container">
                        <?php if ($product_image): ?>
                            <img src="<?= htmlspecialchars($product_image) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-image"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="product-image-placeholder">
                                <div class="product-placeholder-icon"><?= getCategoryIcon($product['category']) ?></div>
                                <span>Fresh Product Image</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-badges">
                            <?php if (!empty($product['sku'])): ?>
                                <div class="product-sku">SKU: <?= htmlspecialchars($product['sku']) ?></div>
                            <?php endif; ?>
                            
                            <?= getFreshnessBadge($product['created_at']) ?>
                        </div>
                    </div>
                    
                    <div class="product-info">
                        <?php if (!empty($product['category'])): ?>
                            <div class="product-category">
                                <span><?= getCategoryIcon($product['category']) ?></span>
                                <span><?= htmlspecialchars(ucfirst($product['category'])) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        
                        <?php if (!empty($product['description'])): ?>
                            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="product-price"><?= formatCurrency($product['price']) ?></div>
                        
                        <div class="product-actions" onclick="event.stopPropagation()">
                            <?php if ($user_logged_in): ?>
                                <button class="btn-add-cart" onclick="addToCart(<?= $product['id'] ?>)">
                                    🛒 Add to Cart
                                </button>
                            <?php else: ?>
                                <a href="auth.php" class="btn-add-cart">
                                    🌱 Sign In to Order
                                </a>
                            <?php endif; ?>

                            <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn-view">
                                👁️ View Details
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
<!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= buildPaginationUrl($page - 1) ?>" class="pagination-link">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <?php
            // Calculate pagination range
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            // Show first page if not in range
            if ($start_page > 1): ?>
                <a href="<?= buildPaginationUrl(1) ?>" class="pagination-link">1</a>
                <?php if ($start_page > 2): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="pagination-link current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= buildPaginationUrl($i) ?>" class="pagination-link"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php
            // Show last page if not in range
            if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>
                <a href="<?= buildPaginationUrl($total_pages) ?>" class="pagination-link"><?= $total_pages ?></a>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="<?= buildPaginationUrl($page + 1) ?>" class="pagination-link">
                    Next →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Debug info (remove in production) -->
<?php debugFilters(); ?>

</main>

<script>
// Add to cart functionality
function addToCart(productId) {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Adding...';
    button.disabled = true;
    
    // Create form data
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    formData.append('action', 'add_to_cart');
    
    // Send AJAX request
    fetch('cart-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success feedback
            button.innerHTML = '✅ Added!';
            button.style.background = 'linear-gradient(135deg, #4caf50, #8bc34a)';
            
            // Update cart count if element exists
            const cartCount = document.querySelector('.cart-count');
            if (cartCount && data.cart_count) {
                cartCount.textContent = data.cart_count;
                cartCount.style.animation = 'bounce 0.5s ease';
            }
            
            // Show success message
            showNotification('Product added to cart! 🌱', 'success');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                button.style.background = '';
            }, 2000);
        } else {
            // Error feedback
            button.innerHTML = '❌ Error';
            button.style.background = 'linear-gradient(135deg, #f44336, #e57373)';
            
            showNotification(data.message || 'Failed to add product to cart', 'error');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                button.style.background = '';
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = '❌ Error';
        button.style.background = 'linear-gradient(135deg, #f44336, #e57373)';
        
        showNotification('Network error. Please try again.', 'error');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            button.style.background = '';
        }, 2000);
    });
}

// Show notification function
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelectorAll('.notification');
    existing.forEach(n => n.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        min-width: 300px;
        max-width: 400px;
        animation: slideIn 0.3s ease;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        font-weight: 500;
    `;
    
    // Set colors based on type
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #4caf50, #8bc34a)';
        notification.style.color = 'white';
    } else if (type === 'error') {
        notification.style.background = 'linear-gradient(135deg, #f44336, #e57373)';
        notification.style.color = 'white';
    } else {
        notification.style.background = 'linear-gradient(135deg, #2196f3, #03a9f4)';
        notification.style.color = 'white';
    }
    
    // Add to document
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(-10px); }
        60% { transform: translateY(-5px); }
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.8;
        transition: opacity 0.2s ease;
    }
    
    .notification-close:hover {
        opacity: 1;
    }
    
    .pagination-dots {
        padding: 12px 8px;
        color: var(--text-light);
        font-size: 14px;
    }
`;
document.head.appendChild(style);

// Smooth scrolling for pagination links
document.querySelectorAll('.pagination a').forEach(link => {
    link.addEventListener('click', function(e) {
        // Add a small delay to show loading state
        this.style.opacity = '0.7';
        setTimeout(() => {
            this.style.opacity = '1';
        }, 200);
    });
});

// Product card click handling (already handled in PHP, but add smooth transition)
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-10px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Search input improvements
const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Could add auto-search functionality here
            console.log('Search query:', this.value);
        }, 500);
    });
}

// Form submission improvements
const filterForm = document.querySelector('form');
if (filterForm) {
    filterForm.addEventListener('submit', function() {
        const submitButton = this.querySelector('.filter-btn');
        if (submitButton) {
            submitButton.innerHTML = '🔄 Searching...';
            submitButton.disabled = true;
        }
    });
}

// Lazy loading for images (additional improvement)
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}

// Add loading states to view buttons
document.querySelectorAll('.btn-view').forEach(button => {
    button.addEventListener('click', function() {
        this.innerHTML = '⏳ Loading...';
        this.style.opacity = '0.7';
    });
});

console.log('🌱 Products page loaded successfully!');
</script>

<?php
// Helper function to build pagination URLs
function buildPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return 'products.php?' . http_build_query($params);
}

// Include footer
include 'footer.php';
?>