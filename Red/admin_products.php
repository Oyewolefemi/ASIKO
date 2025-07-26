<?php
// Enable error reporting (for development only)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'config.php';    // PDO connection
require_once 'functions.php'; // Helper functions

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_auth.php");
    exit();
}

// Generate or retrieve CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(50));
}
$csrf_token = $_SESSION['csrf_token'];

$error   = '';
$success = '';
$action  = isset($_GET['action']) ? $_GET['action'] : '';
$productToEdit = null;

// Helper function to format currency
function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

function deleteProductImage($imagePath) {
    if (!empty($imagePath)) {
        $parsedUrl = parse_url($imagePath);
        $relativePath = ltrim($parsedUrl['path'] ?? '', '/');
        if ($relativePath && file_exists($relativePath)) {
            unlink($relativePath);
        }
    }
}

// Handle actions: add, edit, delete.
switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
                $error = "Invalid CSRF token.";
                break;
            }

            $name        = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $category    = trim($_POST['category']);
            $sku         = trim($_POST['sku']);

            if (empty($name) || empty($description) || empty($category) || empty($sku)) {
                $error = "All fields are required.";
            } elseif ($price === false || $price <= 0) {
                $error = "Valid price is required";
            }

            $imagePath = null;

            if (!$error && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $allowed   = ['jpg','jpeg','png','gif'];
                $fileName  = basename($_FILES['product_image']['name']);
                $fileTmp   = $_FILES['product_image']['tmp_name'];
                $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    $error = "Invalid file type for product image. Only JPG, JPEG, PNG and GIF are allowed.";
                } else {
                    $targetDir = "uploads/";
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $newFileName = uniqid('prod_', true) . "." . $ext;
                    $targetFile  = $targetDir . $newFileName;

                    if (!move_uploaded_file($fileTmp, $targetFile)) {
                        $error = "Failed to upload image.";
                    } else {
                        $imagePath = '/' . ltrim($targetFile, '/');
                    }
                }
            }

            if (!$error) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, description, price, category, sku, image_path)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $price, $category, $sku, $imagePath]);
                    $success = "Product added successfully!";
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        break;

    case 'edit':
        if (isset($_GET['id'])) {
            $productId = intval($_GET['id']);
            try {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $productToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$productToEdit) {
                    $error = "Product not found.";
                }
            } catch (Exception $e) {
                $error = "Error fetching product: " . $e->getMessage();
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
                $error = "Invalid CSRF token.";
                break;
            }

            $productId   = intval($_POST['product_id']);
            $name        = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $category    = trim($_POST['category']);
            $sku         = trim($_POST['sku']);
            $uploadNewImage = false;

            if (empty($name) || empty($description) || empty($category) || empty($sku)) {
                $error = "All fields are required.";
            } elseif ($price === false || $price <= 0) {
                $error = "Valid price is required";
            }

            // Get current product data for image handling
            if (!$error) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$currentProduct) {
                        $error = "Product not found.";
                    } else {
                        $imagePath = $currentProduct['image_path'];
                    }
                } catch (Exception $e) {
                    $error = "Error fetching current product data: " . $e->getMessage();
                }
            }

            if (!$error && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $allowed   = ['jpg','jpeg','png','gif'];
                $fileName  = basename($_FILES['product_image']['name']);
                $fileTmp   = $_FILES['product_image']['tmp_name'];
                $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    $error = "Invalid file type for product image. Only JPG, JPEG, PNG and GIF are allowed.";
                } else {
                    $targetDir = "uploads/";
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $newFileName = uniqid('prod_', true) . "." . $ext;
                    $targetFile  = $targetDir . $newFileName;

                    if (!move_uploaded_file($fileTmp, $targetFile)) {
                        $error = "Failed to upload image.";
                    } else {
                        $imagePath = '/' . ltrim($targetFile, '/');
                        $uploadNewImage = true;

                        // Delete old image if exists
                        if (!empty($currentProduct['image_path']) && $currentProduct['image_path'] !== $imagePath) {
                            deleteProductImage($currentProduct['image_path']);
                        }
                    }
                }
            }

            if (!$error) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE products
                        SET name = ?, description = ?, price = ?, category = ?, sku = ?, image_path = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $price, $category, $sku, $imagePath, $productId]);
                    $success = "Product updated successfully!";
                    
                    // Refresh product data
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $productToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        break;

    case 'delete':
        if (isset($_GET['id'])) {
            $productId = intval($_GET['id']);
            try {
                $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);

                $delStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $delStmt->execute([$productId]);

                if ($prod && !empty($prod['image_path'])) {
                    deleteProductImage($prod['image_path']);
                }

                $success = "Product deleted successfully!";
            } catch (Exception $e) {
                $error = "Error deleting product: " . $e->getMessage();
            }
        }
        break;

    default:
        // No specific action; default to listing products.
        break;
}

try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalProducts = count($products);
} catch (Exception $e) {
    $error = "Error fetching products: " . $e->getMessage();
    $products = [];
    $totalProducts = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - MBC E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        .form-card {
            transition: all 0.3s ease;
        }
        .product-image {
            transition: transform 0.2s ease;
        }
        .product-image:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Improved Sidebar (matching dashboard) -->
        <div class="w-64 bg-white shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">MBC Admin</h2>
                <p class="text-sm text-gray-600">Product Management</p>
            </div>
            <nav class="p-6 space-y-2">
                <a href="admin_dashboard.php" class="block py-3 px-4 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    📊 Dashboard
                </a>
                <a href="admin_products.php" class="block py-3 px-4 text-blue-600 bg-blue-50 rounded-lg font-medium">
                    📦 Products
                </a>
                <a href="orders.php" class="block py-3 px-4 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    🛒 Orders
                </a>
                <a href="customers.php" class="block py-3 px-4 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    👥 Customers
                </a>
                <hr class="my-4">
                <a href="admin_logout.php" class="block py-3 px-4 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                    🚪 Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Product Management</h1>
                <p class="text-gray-600">Manage your store products and inventory</p>
            </div>

            <!-- Stats Card -->
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Products</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totalProducts) ?></p>
                    </div>
                    <div class="text-4xl">📦</div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'edit' && $productToEdit): ?>
                <!-- Edit Product Form -->
                <div class="form-card bg-white p-6 rounded-lg shadow-md mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Edit Product #<?= htmlspecialchars($productToEdit['id']) ?></h2>
                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                            ← Back to List
                        </a>
                    </div>
                    
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?action=edit&id=<?= $productToEdit['id'] ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="product_id" value="<?= $productToEdit['id'] ?>">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required value="<?= htmlspecialchars($productToEdit['name']) ?>">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                                    <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required><?= htmlspecialchars($productToEdit['description']) ?></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Price (₦) *</label>
                                        <input type="number" step="0.01" name="price" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required value="<?= htmlspecialchars($productToEdit['price']) ?>">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                                        <input type="text" name="category" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required value="<?= htmlspecialchars($productToEdit['category']) ?>">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SKU *</label>
                                    <input type="text" name="sku" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required value="<?= htmlspecialchars($productToEdit['sku']) ?>">
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Image</label>
                                    <input type="file" name="product_image" accept="image/*" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <p class="text-sm text-gray-500 mt-1">Leave empty to keep current image</p>
                                    
                                    <?php if (!empty($productToEdit['image_path']) && file_exists(ltrim($productToEdit['image_path'], '/'))): ?>
                                        <div class="mt-4">
                                            <p class="text-sm font-medium text-gray-700 mb-2">Current Image:</p>
                                            <img src="<?= htmlspecialchars($productToEdit['image_path']) ?>" alt="Product Image" class="product-image h-32 w-32 object-cover rounded-lg shadow-md">
                                        </div>
                                    <?php elseif (!empty($productToEdit['image_path'])): ?>
                                        <p class="mt-2 text-yellow-600 text-sm">⚠️ Current image file not found</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                                💾 Update Product
                            </button>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-3 rounded-lg font-medium transition-colors">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Add Product Form -->
                <div class="form-card bg-white p-6 rounded-lg shadow-md mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Add New Product</h2>
                    
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?action=add" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                                    <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="Enter product description..."></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Price (₦) *</label>
                                        <input type="number" step="0.01" name="price" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                                        <input type="text" name="category" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="e.g., Electronics">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SKU *</label>
                                    <input type="text" name="sku" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="e.g., PROD-001">
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Image *</label>
                                    <input type="file" name="product_image" accept="image/*" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                    <p class="text-sm text-gray-500 mt-1">Supported formats: JPG, JPEG, PNG, GIF</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                                ➕ Add Product
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Product Listing -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">All Products (<?= $totalProducts ?>)</h2>
                        <?php if ($action === 'edit'): ?>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="text-blue-600 hover:text-blue-800 font-medium">View All Products →</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <?php if (empty($products)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <div class="text-6xl mb-4">📦</div>
                            <h3 class="text-xl font-medium text-gray-800 mb-2">No Products Found</h3>
                            <p class="text-gray-600 mb-4">Start by adding your first product to the store.</p>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-block">
                                ➕ Add First Product
                            </a>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $prod): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prod['name']) ?></p>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars(substr($prod['description'], 0, 60)) ?><?= strlen($prod['description']) > 60 ? '...' : '' ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            <?= formatCurrency($prod['price']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($prod['category']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= htmlspecialchars($prod['sku']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($prod['image_path']) && file_exists(ltrim($prod['image_path'], '/'))): ?>
                                                <img src="<?= htmlspecialchars($prod['image_path']) ?>" alt="Product Image" class="product-image h-12 w-12 object-cover rounded-lg shadow-sm">
                                            <?php else: ?>
                                                <div class="h-12 w-12 bg-gray-200 rounded-lg flex items-center justify-content-center">
                                                    <span class="text-gray-400 text-xs">No image</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium space-x-2">
                                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?action=edit&id=<?= $prod['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900 transition-colors">✏️ Edit</a>
                                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?action=delete&id=<?= $prod['id'] ?>" 
                                               class="text-red-600 hover:text-red-900 transition-colors ml-3"
                                               onclick="return confirm('⚠️ Are you sure you want to delete this product? This action cannot be undone.');">🗑️ Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states for links
            const links = document.querySelectorAll('a[href*=".php"]');
            links.forEach(link => {
                link.addEventListener('click', function() {
                    if (!this.onclick || this.onclick()) {
                        this.classList.add('loading');
                    }
                });
            });

            // Auto-hide success/error messages after 5 seconds
            const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });

            // Form validation feedback
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '⏳ Processing...';
                        submitBtn.classList.add('loading');
                    }
                });
            });
        });
    </script>
</body>
</html>