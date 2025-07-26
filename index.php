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

    $bind_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($bind_index, $param);
        $bind_index++;
    }

    $stmt->bindValue($bind_index, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($bind_index + 1, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Products page error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $total_products = 0;
    $total_pages = 0;
}

function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

function getProductImage($imagePath) {
    if (empty($imagePath)) return null;
    
    $filename = basename($imagePath);
    
    // Use the correct web path with /kiosk/
    $webPath = "/niffyhm/Red/uploads/" . $filename;
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;
    
    if (file_exists($serverPath)) {
        return $webPath;
    }
    
    return null;
}
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
.products-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 60px 20px;
    font-family: 'Inter', sans-serif;
}

.page-title {
    font-size: 36px;
    font-weight: 200;
    letter-spacing: -0.03em;
    color: #1a1a1a;
    margin-bottom: 15px;
    text-align: center;
}

.page-subtitle {
    font-size: 16px;
    color: #666;
    text-align: center;
    margin-bottom: 50px;
    font-weight: 300;
}

.filters-section {
    background: white;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 40px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #888;
    margin-bottom: 8px;
}

.filter-input, .filter-select {
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: #5ce1e6;
    box-shadow: 0 0 0 3px rgba(92, 225, 230, 0.1);
}

.filter-btn {
    background: #5ce1e6;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    height: fit-content;
}

.filter-btn:hover {
    background: #4dd4d9;
    transform: translateY(-1px);
}

.clear-filters {
    background: transparent;
    color: #666;
    border: 1px solid #ddd;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.clear-filters:hover {
    background: #f8f8f8;
    color: #333;
}

.results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    font-size: 14px;
    color: #666;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.product-card {
    background: white;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border-color: #e0e0e0;
}

.product-image-container {
    position: relative;
    width: 100%;
    height: 250px;
    overflow: hidden;
    background: #f8f8f8;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image {
    transform: scale(1.05);
}

.product-image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f8f8f8 0%, #f0f0f0 100%);
    color: #aaa;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
    flex-direction: column;
    gap: 10px;
}

.product-sku {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.9);
    color: #666;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-info {
    padding: 25px;
}

.product-category {
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.product-name {
    font-size: 18px;
    font-weight: 400;
    color: #1a1a1a;
    margin-bottom: 10px;
    line-height: 1.3;
}

.product-description {
    font-size: 14px;
    color: #666;
    line-height: 1.5;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-price {
    font-size: 20px;
    font-weight: 500;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.product-actions {
    display: flex;
    gap: 10px;
}

.btn-add-cart {
    flex: 1;
    background: #5ce1e6;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-add-cart:hover {
    background: #4dd4d9;
    transform: translateY(-1px);
}

.btn-add-cart:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.btn-view {
    background: transparent;
    color: #666;
    border: 1px solid #ddd;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-view:hover {
    background: #f8f8f8;
    color: #333;
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
    padding: 10px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    color: #666;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 14px;
    min-width: 40px;
    text-align: center;
}

.pagination a:hover {
    background: #f8f8f8;
    color: #333;
}

.pagination .current {
    background: #5ce1e6;
    color: white;
    border-color: #5ce1e6;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #888;
}

.empty-state h3 {
    font-size: 24px;
    font-weight: 300;
    margin-bottom: 15px;
    color: #666;
}

.empty-state p {
    font-size: 16px;
    margin-bottom: 30px;
}

.loading-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.error-message {
    background: #fee;
    border: 1px solid #fcc;
    color: #c33;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .products-container {
        padding: 40px 15px;
    }
    
    .page-title {
        font-size: 28px;
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
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .pagination {
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .pagination a, .pagination span {
        padding: 8px 12px;
        font-size: 12px;
    }
}
</style>

<main class="products-container">
 <div
 class="text-center mb-12"
style="
background-image: url('dedew.svg');
background-size: cover;
background-position: center;
background-repeat: no-repeat;
padding: 60px 20px;
filter: brightness(0.7) contrast(1.1);
color: white;
text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
"
>
<h1 class="page-title">Our Collection</h1>
<p class="page-subtitle">
 Discover our carefully curated selection of premium handmade wears
</p>
</div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Search Products</label>
                    <input type="text" name="search" class="filter-input" 
                           placeholder="Search by name or description..." 
                           value="<?= htmlspecialchars($search_query) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                    <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($cat['category'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-select">
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Price</option>
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Newest</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Order</label>
                    <select name="order" class="filter-select">
                        <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">Apply Filters</button>
                
                <?php if (!empty($search_query) || !empty($category_filter)): ?>
                    <a href="products.php" class="clear-filters">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <?php if ($total_products > 0): ?>
        <div class="results-info">
            <span>
                Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_products) ?> 
                of <?= number_format($total_products) ?> products
            </span>
            <span>Page <?= $page ?> of <?= number_format($total_pages) ?></span>
        </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <h3>No Products Found</h3>
            <p>We couldn't find any products matching your criteria.</p>
            <?php if (!empty($search_query) || !empty($category_filter)): ?>
                <a href="products.php" class="filter-btn">Clear Filters & View All</a>
            <?php else: ?>
                <p>Please check back later or contact us for more information.</p>
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
                                <span>📦</span>
                                <span>No Image</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($product['sku'])): ?>
                            <div class="product-sku"><?= htmlspecialchars($product['sku']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <?php if (!empty($product['category'])): ?>
                            <div class="product-category"><?= htmlspecialchars($product['category']) ?></div>
                        <?php endif; ?>
                        
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        
                        <?php if (!empty($product['description'])): ?>
                            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="product-price"><?= formatCurrency($product['price']) ?></div>
                        
                        <div class="product-actions" onclick="event.stopPropagation()">
                            <?php if ($user_logged_in): ?>
                                <button class="btn-add-cart" onclick="addToCart(<?= $product['id'] ?>)">
                                    Add to Cart
                                </button>
                            <?php else: ?>
                                <a href="auth.php" class="btn-add-cart">
                                    Sign In to Order
                                </a>
                            <?php endif; ?>

                        
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" title="First Page">‹‹</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" title="Previous Page">‹</a>
                <?php endif; ?>

                <?php
                // Show pagination numbers with ellipsis for large page counts
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                    if ($start_page > 2) {
                        echo '<span>...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span>...</span>';
                    }
                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" title="Next Page">›</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" title="Last Page">››</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
function addToCart(productId) {
    // Show loading state
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Adding...';
    button.disabled = true;
    
    fetch('add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = 'Added!';
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 2000);
            
            // Optional: Update cart counter if you have one
            if (typeof updateCartCounter === 'function') {
                updateCartCounter();
            }
        } else {
            alert(data.message || "Failed to add to cart.");
            button.textContent = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error("Error adding to cart:", error);
        alert("Something went wrong. Please try again.");
        button.textContent = originalText;
        button.disabled = false;
    });
}

// Optional: Auto-apply filters when typing (with debounce)
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length > 2 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});
</script>

<?php include 'footer.php'; ?>