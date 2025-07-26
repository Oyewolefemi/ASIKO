<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_auth.php');
    exit();
}

// Get pending manual payment orders
$stmt = $pdo->query("
    SELECT o.id, u.email, o.order_date, o.total_amount, o.delivery_fee,
           (o.total_amount + o.delivery_fee) AS grand_total
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'pending' AND o.payment_method = 'manual'
    ORDER BY o.order_date DESC
");
$pendingManualOrders = $stmt->fetchAll();

// Get approved orders
$stmt = $pdo->query("
    SELECT o.id, u.email, o.order_date, o.total_amount, o.delivery_fee,
           (o.total_amount + o.delivery_fee) AS grand_total, o.approved_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'active'
    ORDER BY o.order_date DESC
");
$approvedOrders = $stmt->fetchAll();

// Get all orders with status
$stmt = $pdo->query("
    SELECT o.id, u.email, o.order_date, o.total_amount, o.delivery_fee,
           (o.total_amount + o.delivery_fee) AS grand_total, o.status, o.payment_method
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.order_date DESC
");
$allOrders = $stmt->fetchAll();

// Get order statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_orders,
        COALESCE(SUM(CASE WHEN status = 'active' THEN total_amount + delivery_fee END), 0) as total_revenue
    FROM orders
");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800">Admin Panel</h2>
        </div>
        <nav class="p-6 space-y-2">
            <a href="admin_dashboard.php" class="block py-2 px-3 rounded text-gray-600 hover:bg-blue-50 hover:text-blue-600">Dashboard</a>
            <a href="admin_products.php" class="block py-2 px-3 rounded text-gray-600 hover:bg-blue-50 hover:text-blue-600">Products</a>
            <a href="orders.php" class="block py-2 px-3 rounded bg-blue-100 text-blue-600 font-medium">Orders</a>
            <a href="admin_logout.php" class="block py-2 px-3 rounded text-red-600 hover:bg-red-50">Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Orders Management</h1>
            <p class="text-gray-600 mt-2">Manage customer orders and payment approvals</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Total Orders</h3>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_orders'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Pending</h3>
                <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending_orders'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Active</h3>
                <p class="text-3xl font-bold text-green-600"><?= $stats['active_orders'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Revenue</h3>
                <p class="text-3xl font-bold text-blue-600">₦<?= number_format($stats['total_revenue'], 2) ?></p>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tabs -->
        <div x-data="{ activeTab: 'pending' }" class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button @click="activeTab = 'pending'" 
                            :class="activeTab === 'pending' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700">
                        Pending Approvals (<?= count($pendingManualOrders) ?>)
                    </button>
                    <button @click="activeTab = 'approved'" 
                            :class="activeTab === 'approved' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700">
                        Approved Orders (<?= count($approvedOrders) ?>)
                    </button>
                    <button @click="activeTab = 'all'" 
                            :class="activeTab === 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700">
                        All Orders (<?= count($allOrders) ?>)
                    </button>
                </nav>
            </div>

            <!-- Pending Orders Tab -->
            <div x-show="activeTab === 'pending'" class="p-6">
                <?php if (empty($pendingManualOrders)): ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-6xl mb-4">📋</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No pending orders</h3>
                        <p class="text-gray-500">All manual payment orders have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pendingManualOrders as $order): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #<?= $order['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($order['order_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($order['email']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            ₦<?= number_format($order['grand_total'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <a href="approve_order.php?order_id=<?= $order['id'] ?>"
                                               onclick="return confirm('Approve this order?')"
                                               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium">
                                                Approve
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approved Orders Tab -->
            <div x-show="activeTab === 'approved'" class="p-6">
                <?php if (empty($approvedOrders)): ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-6xl mb-4">✅</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No approved orders</h3>
                        <p class="text-gray-500">Approved orders will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($approvedOrders as $order): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #<?= $order['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($order['order_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($order['email']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            ₦<?= number_format($order['grand_total'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $order['approved_at'] ? date('M j, Y g:i A', strtotime($order['approved_at'])) : 'N/A' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Orders Tab -->
            <div x-show="activeTab === 'all'" class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($allOrders as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?= $order['id'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($order['order_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($order['email']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        ₦<?= number_format($order['grand_total'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= ucfirst($order['payment_method']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php
                                        $statusClasses = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'active' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'completed' => 'bg-blue-100 text-blue-800'
                                        ];
                                        $class = $statusClasses[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $class ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>