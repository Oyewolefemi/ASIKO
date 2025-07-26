<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
require_once 'functions.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_auth.php");
    exit();
}

$error = '';
$success = '';

// Configuration - adjust these based on your actual database schema
$orderTotalColumn = 'total_amount'; // Change to 'total' if that's your column name

// Initialize variables
$stats = [
    'totalOrders' => 0,
    'approvedOrders' => 0,
    'pendingOrders' => 0,
    'totalRevenue' => 0,
    'monthlyRevenue' => 0,
    'totalProductsSold' => 0
];

$recentProducts = [];
$recentOrders = [];

try {
    // Basic statistics - optimized single queries
    $statsQuery = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as approved_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'active' THEN $orderTotalColumn ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status = 'active' AND MONTH(order_date) = MONTH(CURRENT_DATE()) AND YEAR(order_date) = YEAR(CURRENT_DATE()) THEN $orderTotalColumn ELSE 0 END) as monthly_revenue
        FROM orders
    ");
    $statsData = $statsQuery->fetch();
    
    $stats['totalOrders'] = (int)$statsData['total_orders'];
    $stats['approvedOrders'] = (int)$statsData['approved_orders'];
    $stats['pendingOrders'] = (int)$statsData['pending_orders'];
    $stats['totalRevenue'] = (float)$statsData['total_revenue'];
    $stats['monthlyRevenue'] = (float)$statsData['monthly_revenue'];

    // Total products sold
    $productsSoldQuery = $pdo->query("
        SELECT IFNULL(SUM(od.quantity), 0) as total_sold
        FROM order_details od
        JOIN orders o ON o.id = od.order_id
        WHERE o.status = 'active'
    ");
    $stats['totalProductsSold'] = (int)$productsSoldQuery->fetchColumn();

    // Recent products (simplified - removed stock_quantity)
    $recentProducts = $pdo->query("
        SELECT id, name, price, category, sku, created_at 
        FROM products 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Recent orders (simplified)
    $recentOrders = $pdo->query("
        SELECT
            o.id,
            o.order_date,
            COALESCE(u.email, 'Guest') as email,
            o.$orderTotalColumn AS amount,
            o.status
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.order_date DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MBC E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom styles for better performance */
        .stat-card {
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Simplified Sidebar -->
        <div class="w-64 bg-white shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">MBC Admin</h2>
                <p class="text-sm text-gray-600">Dashboard</p>
            </div>
            <nav class="p-6 space-y-2">
                <a href="admin_dashboard.php" class="block py-3 px-4 text-blue-600 bg-blue-50 rounded-lg font-medium">
                    📊 Dashboard
                </a>
                <a href="admin_products.php" class="block py-3 px-4 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
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
                <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
                <p class="text-gray-600">Welcome back! Here's what's happening with your store.</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Key Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Orders</p>
                            <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['totalOrders']) ?></p>
                        </div>
                        <div class="text-4xl">🛒</div>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                            <p class="text-3xl font-bold text-gray-800"><?= formatCurrency($stats['totalRevenue']) ?></p>
                        </div>
                        <div class="text-4xl">💰</div>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending Orders</p>
                            <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['pendingOrders']) ?></p>
                        </div>
                        <div class="text-4xl">⏳</div>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Products Sold</p>
                            <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['totalProductsSold']) ?></p>
                        </div>
                        <div class="text-4xl">📦</div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">This Month</h3>
                    <p class="text-2xl font-bold text-green-600"><?= formatCurrency($stats['monthlyRevenue']) ?></p>
                    <p class="text-sm text-gray-600">Revenue generated this month</p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Order Status</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">✅ Approved:</span>
                            <span class="text-sm font-medium text-green-600"><?= $stats['approvedOrders'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">⏳ Pending:</span>
                            <span class="text-sm font-medium text-yellow-600"><?= $stats['pendingOrders'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <a href="admin_products.php?action=add" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-lg text-center transition-colors">
                        ➕ Add Product
                    </a>
                    <a href="orders.php?status=pending" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-3 rounded-lg text-center transition-colors">
                        📋 Review Orders
                    </a>
                    <a href="admin_products.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-lg text-center transition-colors">
                        📦 Manage Products
                    </a>
                    <a href="reports.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-3 rounded-lg text-center transition-colors">
                        📊 View Reports
                    </a>
                </div>
            </div>

            <!-- Recent Products -->
            <div class="bg-white rounded-lg shadow-md mb-8">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Recent Products</h2>
                        <a href="admin_products.php" class="text-blue-600 hover:text-blue-800 font-medium">View All →</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <?php if (empty($recentProducts)): ?>
                        <div class="p-6 text-center text-gray-500">
                            <div class="text-4xl mb-4">📦</div>
                            <p>No products found. <a href="admin_products.php?action=add" class="text-blue-600 hover:underline">Add your first product</a></p>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentProducts as $product): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
                                                <p class="text-sm text-gray-500">SKU: <?= htmlspecialchars($product['sku']) ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?= formatCurrency($product['price']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?= htmlspecialchars($product['category']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($product['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Recent Orders</h2>
                        <a href="orders.php" class="text-blue-600 hover:text-blue-800 font-medium">View All →</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <?php if (empty($recentOrders)): ?>
                        <div class="p-6 text-center text-gray-500">
                            <div class="text-4xl mb-4">🛒</div>
                            <p>No orders yet. Your first order will appear here!</p>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            #<?= htmlspecialchars($order['id']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?= htmlspecialchars($order['email']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?= formatCurrency($order['amount']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getStatusBadgeClass($order['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= date('M j, g:i A', strtotime($order['order_date'])) ?>
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
        // Simple performance monitoring
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states for links
            const links = document.querySelectorAll('a[href*=".php"]');
            links.forEach(link => {
                link.addEventListener('click', function() {
                    this.classList.add('loading');
                });
            });

            // Log page load time
            window.addEventListener('load', function() {
                const loadTime = performance.now();
                console.log('Dashboard loaded in:', Math.round(loadTime), 'ms');
            });
        });
    </script>
</body>
</html>