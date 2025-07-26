<?php
/**
 * Header Component - Modern Luxury Navigation
 * Handmade by Niffy
 */
session_start();

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handmade by Niffy</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        /* ===== CSS VARIABLES ===== */
        :root {
            --primary-cyan: rgb(219, 203, 54);
            --primary-cyan-hover: rgb(217, 208, 77);
            --luxury-black: #1a1a1a;
            --luxury-gray: #666666;
            --luxury-light-gray: #f8f8f8;
            --luxury-border: #e8e8e8;
        }

        /* ===== GLOBAL STYLES ===== */
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            padding-top: 80px;
            background: #fefefe;
        }

        /* ===== HEADER STYLES ===== */
        .luxury-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--luxury-border);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .luxury-header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        /* ===== LOGO STYLES ===== */
        .logo {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: -0.02em;
            color: var(--luxury-black);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logo:hover {
            color: var(--primary-cyan);
        }

        /* ===== NAVIGATION STYLES ===== */
        .nav-link {
            font-size: 14px;
            font-weight: 400;
            color: var(--luxury-gray);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
            letter-spacing: 0.02em;
        }

        .nav-link:hover {
            color: var(--luxury-black);
            background: var(--luxury-light-gray);
        }

        .nav-link.active {
            color: var(--primary-cyan);
            background: rgba(92, 225, 230, 0.1);
        }

        .nav-account {
            background: var(--primary-cyan);
            color: white;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 6px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .nav-account:hover {
            background: var(--primary-cyan-hover);
            transform: translateY(-1px);
        }

        /* ===== CART BADGE ===== */
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary-cyan);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== MOBILE MENU STYLES ===== */
        .mobile-menu-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn:hover {
            background: var(--luxury-light-gray);
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 320px;
            height: 100vh;
            background: white;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 1001;
            padding: 80px 30px 30px;
        }

        .mobile-menu.open {
            right: 0;
        }

        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .mobile-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav-link {
            display: block;
            padding: 15px 0;
            font-size: 16px;
            color: var(--luxury-black);
            text-decoration: none;
            border-bottom: 1px solid var(--luxury-border);
            transition: all 0.3s ease;
        }

        .mobile-nav-link:hover {
            color: var(--primary-cyan);
            padding-left: 10px;
        }

        .close-menu {
            position: absolute;
            top: 25px;
            right: 25px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--luxury-gray);
            transition: all 0.3s ease;
        }

        .close-menu:hover {
            color: var(--luxury-black);
            transform: rotate(90deg);
        }

        /* ===== RESPONSIVE STYLES ===== */
        @media (max-width: 768px) {
            .luxury-header {
                padding: 15px 20px;
            }
            
            body {
                padding-top: 70px;
            }
        }
    </style>
</head>

<body>
    <!-- ===== MAIN HEADER ===== -->
    <header class="luxury-header" id="header">
        <div class="max-w-7xl mx-auto px-6 flex items-center justify-between h-20">
            
            <!-- Logo Section -->
            <div class="flex items-center">
                <a href="index.php" class="logo flex items-center">
                    <img src="niffy.png" alt="Handmade by Niffy" class="h-12 w-auto mr-3 object-contain">
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-2">
                <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    Home
                </a>
                
                <a href="products.php" class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">
                    Products
                </a>
                
                <a href="cart.php" class="nav-link relative <?= $current_page == 'cart.php' ? 'active' : '' ?>">
                    Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- User Authentication Links -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my-account.php" class="nav-link <?= $current_page == 'my-account.php' ? 'active' : '' ?>">
                        Account
                    </a>
                    <a href="logout.php" class="nav-account">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="auth.php" class="nav-account">
                        Sign In
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn md:hidden" id="mobileMenuBtn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </header>

    <!-- ===== MOBILE OVERLAY ===== -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- ===== MOBILE MENU ===== -->
    <nav class="mobile-menu" id="mobileMenu">
        <button class="close-menu" id="closeMenu">&times;</button>
        
        <div class="space-y-1">
            <a href="index.php" class="mobile-nav-link">Home</a>
            <a href="products.php" class="mobile-nav-link">Products</a>
            
            <a href="cart.php" class="mobile-nav-link">
                Cart
                <?php if ($cart_count > 0): ?>
                    <span style="float: right; background: var(--primary-cyan); color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px;">
                        <?= $cart_count ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <!-- Mobile User Authentication -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="my-account.php" class="mobile-nav-link">My Account</a>
                <a href="logout.php" class="mobile-nav-link">Logout</a>
            <?php else: ?>
                <a href="auth.php" class="mobile-nav-link">Sign In / Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ===== JAVASCRIPT ===== -->
    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const closeMenu = document.getElementById('closeMenu');

        function openMobileMenu() {
            mobileMenu.classList.add('open');
            mobileOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu.classList.remove('open');
            mobileOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        // Event listeners
        mobileMenuBtn.addEventListener('click', openMobileMenu);
        closeMenu.addEventListener('click', closeMobileMenu);
        mobileOverlay.addEventListener('click', closeMobileMenu);

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>