<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// Initialize cart
if (!isset($_SESSION['electronics_cart'])) {
    $_SESSION['electronics_cart'] = [];
}

// Add to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $stmt = mysqli_prepare($conn, "SELECT name, price FROM electronics_table WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($product) {
        if (!isset($_SESSION['electronics_cart'][$product_id])) {
            $_SESSION['electronics_cart'][$product_id] = ['name' => $product['name'], 'price' => (float)$product['price'], 'qty' => 1];
        } else {
            $_SESSION['electronics_cart'][$product_id]['qty']++;
        }
    }
    header("Location: electronics.php");
    exit;
}
if (isset($_POST['update_qty'])) {
    $product_id = (int)$_POST['product_id'];
    $new_qty = max(1, (int)$_POST['qty']);
    if (isset($_SESSION['electronics_cart'][$product_id])) {
        $_SESSION['electronics_cart'][$product_id]['qty'] = $new_qty;
    }
    header("Location: electronics.php");
    exit;
}
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    unset($_SESSION['electronics_cart'][$product_id]);
    header("Location: electronics.php");
    exit;
}

// Pagination & category logic
$limit = 4;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$current_category = isset($_GET['cat']) ? (int)$_GET['cat'] : null;

// If a specific category is selected (for pagination), show only that category
if ($current_category !== null) {
    // Get category info
    $catStmt = mysqli_prepare($conn, "SELECT id, name, icon FROM electronics_categories WHERE id = ?");
    mysqli_stmt_bind_param($catStmt, "i", $current_category);
    mysqli_stmt_execute($catStmt);
    $catResult = mysqli_stmt_get_result($catStmt);
    $selectedCategory = mysqli_fetch_assoc($catResult);
    mysqli_stmt_close($catStmt);
    
    if ($selectedCategory) {
        // Count total products in this category
        $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM electronics_table WHERE category_id = ?");
        mysqli_stmt_bind_param($countStmt, "i", $current_category);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $totalItems = mysqli_fetch_assoc($countRes)['total'];
        mysqli_stmt_close($countStmt);
        
        $totalPages = ceil($totalItems / $limit);
        $offset = ($current_page - 1) * $limit;
        
        $prodStmt = mysqli_prepare($conn, "SELECT id, name, description, price, image, stock FROM electronics_table WHERE category_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($prodStmt, "iii", $current_category, $limit, $offset);
        mysqli_stmt_execute($prodStmt);
        $prodResult = mysqli_stmt_get_result($prodStmt);
        $categoryProducts = [];
        while ($row = mysqli_fetch_assoc($prodResult)) {
            $categoryProducts[] = $row;
        }
        mysqli_stmt_close($prodStmt);
    } else {
        $selectedCategory = null;
        $categoryProducts = [];
        $totalPages = 0;
    }
} else {
    // Fetch all categories without pagination (for the main grouped view)
    $categories = [];
    $catQuery = "SELECT id, name, icon FROM electronics_categories ORDER BY name";
    $catResult = mysqli_query($conn, $catQuery);
    if ($catResult) {
        while ($cat = mysqli_fetch_assoc($catResult)) {
            $cat['products'] = [];
            // Use prepared statement for category products
            $prodStmt = mysqli_prepare($conn, "SELECT id, name, description, price, image, stock FROM electronics_table WHERE category_id = ? ORDER BY id DESC LIMIT 4");
            mysqli_stmt_bind_param($prodStmt, "i", $cat['id']);
            mysqli_stmt_execute($prodStmt);
            $prodResult = mysqli_stmt_get_result($prodStmt);
            while ($prod = mysqli_fetch_assoc($prodResult)) {
                $cat['products'][] = $prod;
            }
            mysqli_stmt_close($prodStmt);
            $categories[] = $cat;
        }
        mysqli_free_result($catResult);
    }
    
    // Uncategorized products (first 4) - using prepared statement
    $uncategorized = [];
    $uncatStmt = mysqli_prepare($conn, "SELECT id, name, description, price, image, stock FROM electronics_table WHERE category_id IS NULL ORDER BY id DESC LIMIT 4");
    mysqli_stmt_execute($uncatStmt);
    $uncatResult = mysqli_stmt_get_result($uncatStmt);
    while ($prod = mysqli_fetch_assoc($uncatResult)) {
        $uncategorized[] = $prod;
    }
    mysqli_stmt_close($uncatStmt);
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('electronics_page_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0a0a0f; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes floatUp { 0% { opacity: 0; transform: translateY(30px); } 100% { opacity: 1; transform: translateY(0); } }
        .animate-float { animation: floatUp 0.6s ease forwards; }
        .product-card {
            transition: all 0.2s ease;
            background: #1a1a2e;
            border: 1px solid #2a2a3e;
        }
        .product-card:hover {
            transform: scale(1.02);
            border-color: #f97316;
            box-shadow: 0 10px 20px -5px rgba(249,115,22,0.3);
        }
        .btn-ripple { position: relative; overflow: hidden; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="content-wrapper">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-[#0a0a0f]/90 backdrop-blur-lg border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <a href="index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-orange-500 rounded-xl flex items-center justify-center text-black font-black text-lg">E</div>
                <span class="text-xl font-bold text-white">GameStore<br><span class="text-xs text-gray-400"><?= __('electronics') ?></span></span>
            </a>
            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="index.php" class="text-white/70 hover:text-white transition"><?= __('home') ?></a>
                <a href="games.php" class="text-white/70 hover:text-white transition"><?= __('games') ?></a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="insertelectronics.php" class="text-white hover:text-purple-300 transition"><?= __('add_electronics') ?></a>
                    <a href="admin/dashboard.php" class="text-cyan-400 font-semibold"><?= __('dashboard') ?></a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'publisher'): ?>
                    <a href="mygames.php" class="text-white/70 hover:text-white transition"><?= __('my_games') ?></a>
                <?php endif; ?>
                <a href="cart_electronics.php" class="text-purple-400 font-semibold relative">
                    <?= __('electronics_cart') ?>
                    <?php if (!empty($_SESSION['electronics_cart'])): ?>
                        <span class="absolute -top-1 -right-3 bg-purple-500 text-white text-xs rounded-full px-1.5 py-0.5">
                            <?= count($_SESSION['electronics_cart']) ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" class="text-white/70 hover:text-white transition"><?= __('profile') ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
                <?php else: ?>
                    <a href="login.php" class="bg-purple-600/80 text-white px-3 py-1 rounded-md hover:bg-purple-500 transition"><?= __('login') ?></a>
                <?php endif; ?>
                <div class="language-switcher flex gap-2">
                    <a href="?lang=en" class="text-xs text-gray-400 hover:text-white">EN</a>
                    <a href="?lang=ku" class="text-xs text-gray-400 hover:text-white">KU</a>
                </div>
            </nav>
            <form method="GET" action="electronics.php" class="flex items-center bg-black/40 rounded-full px-3 py-1 border border-white/10">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="<?= __('search_electronics') ?>" class="bg-transparent outline-none text-sm w-40 md:w-56 text-white">
                <button type="submit" class="text-purple-400 hover:text-purple-300">🔍</button>
            </form>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <!-- Hero section -->
        <div class="text-center mb-12 animate-float">
            <h1 class="text-5xl md:text-6xl font-extrabold bg-gradient-to-r from-purple-400 to-orange-400 bg-clip-text text-transparent"><?= __('electronics_hero_title') ?></h1>
            <p class="text-gray-300 text-lg mt-4 max-w-2xl mx-auto"><?= __('electronics_hero_desc') ?></p>
        </div>

        <?php if (isset($_GET['search']) && trim($_GET['search']) !== ''): ?>
            <?php
            $searchTerm = '%' . $_GET['search'] . '%';
            $searchStmt = mysqli_prepare($conn, "SELECT id, name, description, price, image, stock FROM electronics_table WHERE name LIKE ? OR description LIKE ?");
            mysqli_stmt_bind_param($searchStmt, "ss", $searchTerm, $searchTerm);
            mysqli_stmt_execute($searchStmt);
            $searchResult = mysqli_stmt_get_result($searchStmt);
            $searchProducts = [];
            while ($row = mysqli_fetch_assoc($searchResult)) {
                $searchProducts[] = $row;
            }
            mysqli_stmt_close($searchStmt);
            ?>
            <div class="mt-4">
                <div class="flex justify-between items-center mb-5">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        🔍 <?= __('search_results') ?> (<?= count($searchProducts) ?>)
                    </h2>
                    <a href="electronics.php" class="text-purple-400 hover:text-purple-300 text-sm">← <?= __('back_to_categories') ?></a>
                </div>
                <?php if (empty($searchProducts)): ?>
                    <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 text-center">
                        <p class="text-gray-400"><?= __('no_electronics_found') ?></p>
                        <a href="electronics.php" class="inline-block mt-3 text-purple-400 hover:underline"><?= __('browse_all_categories') ?></a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <?php foreach ($searchProducts as $product): ?>
                            <div class="product-card rounded-xl overflow-hidden group">
                                <div class="h-44 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-5xl relative">
                                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                        <img src="<?= htmlspecialchars($product['image']) ?>" class="w-full h-full object-cover transition duration-300 group-hover:scale-105">
                                    <?php else: ?>
                                        🖥️
                                    <?php endif; ?>
                                    <?php if ($product['stock'] < 1): ?>
                                        <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                            <span class="text-white text-xs font-bold bg-red-500 px-2 py-1 rounded"><?= __('out_of_stock') ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h3 class="font-semibold text-white truncate"><?= htmlspecialchars($product['name']) ?></h3>
                                    <p class="text-xs text-gray-400 mt-1 line-clamp-2"><?= htmlspecialchars(substr($product['description'], 0, 60)) ?>…</p>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-orange-400 font-bold text-lg">$<?= number_format($product['price'], 2) ?></span>
                                        <?php if ($product['stock'] > 0): ?>
                                            <form method="POST" action="electronics.php">
                                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                <button type="submit" name="add_to_cart" class="bg-purple-600/80 hover:bg-purple-500 text-white text-xs px-3 py-1.5 rounded-full transition btn-ripple"><?= __('add') ?></button>
                                            </form>
                                        <?php else: ?>
                                            <button disabled class="bg-gray-600 text-gray-300 text-xs px-3 py-1.5 rounded-full cursor-not-allowed"><?= __('out') ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- If a category is selected (pagination view) -->
            <?php if ($current_category !== null && $selectedCategory): ?>
                <div>
                    <div class="flex justify-between items-center mb-5">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl"><?= htmlspecialchars($selectedCategory['icon'] ?? '📦') ?></span>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($selectedCategory['name']) ?></h2>
                            <span class="text-sm bg-purple-500/20 text-purple-300 px-2 py-0.5 rounded-full"><?= $totalItems ?> <?= __('items') ?></span>
                        </div>
                        <a href="electronics.php" class="text-purple-400 hover:text-purple-300 text-sm">← <?= __('back_to_categories') ?></a>
                    </div>
                    <?php if (empty($categoryProducts)): ?>
                        <p class="text-gray-400"><?= __('no_products_category') ?></p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                            <?php foreach ($categoryProducts as $product): ?>
                                <div class="product-card rounded-xl overflow-hidden group">
                                    <div class="h-44 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-5xl relative">
                                        <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                            <img src="<?= htmlspecialchars($product['image']) ?>" class="w-full h-full object-cover transition duration-300 group-hover:scale-105">
                                        <?php else: ?>
                                            <?php
                                            $icon = '🖥️';
                                            if (stripos($product['name'], 'headset') !== false) $icon = '🎧';
                                            elseif (stripos($product['name'], 'keyboard') !== false) $icon = '⌨️';
                                            elseif (stripos($product['name'], 'mouse') !== false) $icon = '🖱️';
                                            elseif (stripos($product['name'], 'ssd') !== false) $icon = '💾';
                                            echo $icon;
                                            ?>
                                        <?php endif; ?>
                                        <?php if ($product['stock'] < 1): ?>
                                            <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                                <span class="text-white text-xs font-bold bg-red-500 px-2 py-1 rounded"><?= __('out_of_stock') ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-3">
                                        <h3 class="font-semibold text-white truncate"><?= htmlspecialchars($product['name']) ?></h3>
                                        <p class="text-xs text-gray-400 mt-1 line-clamp-2"><?= htmlspecialchars(substr($product['description'], 0, 60)) ?>…</p>
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-orange-400 font-bold text-lg">$<?= number_format($product['price'], 2) ?></span>
                                            <?php if ($product['stock'] > 0): ?>
                                                <form method="POST" action="electronics.php">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="add_to_cart" class="bg-purple-600/80 hover:bg-purple-500 text-white text-xs px-3 py-1.5 rounded-full transition btn-ripple"><?= __('add') ?></button>
                                                </form>
                                            <?php else: ?>
                                                <button disabled class="bg-gray-600 text-gray-300 text-xs px-3 py-1.5 rounded-full cursor-not-allowed"><?= __('out') ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Pagination buttons -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex justify-center gap-3 mt-8">
                                <?php if ($current_page > 1): ?>
                                    <a href="?cat=<?= $current_category ?>&page=<?= $current_page-1 ?>" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-full transition">← <?= __('previous') ?></a>
                                <?php endif; ?>
                                <span class="bg-purple-600/30 text-white px-4 py-2 rounded-full"><?= __('page') ?> <?= $current_page ?> <?= __('of') ?> <?= $totalPages ?></span>
                                <?php if ($current_page < $totalPages): ?>
                                    <a href="?cat=<?= $current_category ?>&page=<?= $current_page+1 ?>" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-full transition"><?= __('next') ?> →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            <!-- Else: show all categories with first 4 products and "View All" link -->
            <?php else: ?>
                <div class="space-y-12">
                    <?php foreach ($categories as $cat): ?>
                        <?php if (count($cat['products']) > 0): ?>
                            <div>
                                <div class="flex justify-between items-center mb-5">
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl"><?= htmlspecialchars($cat['icon'] ?? '📦') ?></span>
                                        <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($cat['name']) ?></h2>
                                        <span class="text-sm bg-purple-500/20 text-purple-300 px-2 py-0.5 rounded-full"><?= count($cat['products']) ?> <?= __('shown') ?></span>
                                    </div>
                                    <?php
                                    // Count total products in this category
                                    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM electronics_table WHERE category_id = ?");
                                    mysqli_stmt_bind_param($countStmt, "i", $cat['id']);
                                    mysqli_stmt_execute($countStmt);
                                    $countRes = mysqli_stmt_get_result($countStmt);
                                    $totalInCat = mysqli_fetch_assoc($countRes)['total'];
                                    mysqli_stmt_close($countStmt);
                                    if ($totalInCat > 4):
                                    ?>
                                        <a href="?cat=<?= $cat['id'] ?>&page=1" class="text-purple-400 hover:text-purple-300 text-sm"><?= __('view_all') ?> <?= $totalInCat ?> <?= __('items') ?> →</a>
                                    <?php endif; ?>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                                    <?php foreach ($cat['products'] as $product): ?>
                                        <div class="product-card rounded-xl overflow-hidden group">
                                            <div class="h-44 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-5xl relative">
                                                <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                                    <img src="<?= htmlspecialchars($product['image']) ?>" class="w-full h-full object-cover transition duration-300 group-hover:scale-105">
                                                <?php else: ?>
                                                    <?php
                                                    $icon = '🖥️';
                                                    if (stripos($product['name'], 'headset') !== false) $icon = '🎧';
                                                    elseif (stripos($product['name'], 'keyboard') !== false) $icon = '⌨️';
                                                    elseif (stripos($product['name'], 'mouse') !== false) $icon = '🖱️';
                                                    elseif (stripos($product['name'], 'ssd') !== false) $icon = '💾';
                                                    echo $icon;
                                                    ?>
                                                <?php endif; ?>
                                                <?php if ($product['stock'] < 1): ?>
                                                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                                        <span class="text-white text-xs font-bold bg-red-500 px-2 py-1 rounded"><?= __('out_of_stock') ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="p-3">
                                                <h3 class="font-semibold text-white truncate"><?= htmlspecialchars($product['name']) ?></h3>
                                                <p class="text-xs text-gray-400 mt-1 line-clamp-2"><?= htmlspecialchars(substr($product['description'], 0, 60)) ?>…</p>
                                                <div class="flex justify-between items-center mt-2">
                                                    <span class="text-orange-400 font-bold text-lg">$<?= number_format($product['price'], 2) ?></span>
                                                    <?php if ($product['stock'] > 0): ?>
                                                        <form method="POST" action="electronics.php">
                                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                            <button type="submit" name="add_to_cart" class="bg-purple-600/80 hover:bg-purple-500 text-white text-xs px-3 py-1.5 rounded-full transition btn-ripple"><?= __('add') ?></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button disabled class="bg-gray-600 text-gray-300 text-xs px-3 py-1.5 rounded-full cursor-not-allowed"><?= __('out') ?></button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Uncategorized section (first 4) -->
                    <?php if (!empty($uncategorized)): ?>
                        <div>
                            <div class="flex justify-between items-center mb-5">
                                <div class="flex items-center gap-3">
                                    <span class="text-3xl">🔹</span>
                                    <h2 class="text-2xl font-bold text-white"><?= __('other_electronics') ?></h2>
                                    <span class="text-sm bg-gray-500/20 text-gray-300 px-2 py-0.5 rounded-full"><?= count($uncategorized) ?> <?= __('shown') ?></span>
                                </div>
                                <?php
                                // Count total uncategorized products
                                $uncatCountStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM electronics_table WHERE category_id IS NULL");
                                mysqli_stmt_execute($uncatCountStmt);
                                $uncatCountRes = mysqli_stmt_get_result($uncatCountStmt);
                                $totalUncat = mysqli_fetch_assoc($uncatCountRes)['total'];
                                mysqli_stmt_close($uncatCountStmt);
                                if ($totalUncat > 4):
                                ?>
                                    <a href="?cat=0&page=1" class="text-purple-400 hover:text-purple-300 text-sm"><?= __('view_all') ?> <?= $totalUncat ?> <?= __('items') ?> →</a>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                                <?php foreach ($uncategorized as $product): ?>
                                    <div class="product-card rounded-xl overflow-hidden">
                                        <div class="h-44 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-5xl">
                                            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                                <img src="<?= htmlspecialchars($product['image']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                🎧
                                            <?php endif; ?>
                                            <?php if ($product['stock'] < 1): ?>
                                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                                    <span class="text-white text-xs font-bold bg-red-500 px-2 py-1 rounded"><?= __('out_of_stock') ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-3">
                                            <h3 class="font-semibold text-white truncate"><?= htmlspecialchars($product['name']) ?></h3>
                                            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>…</p>
                                            <div class="flex justify-between items-center mt-2">
                                                <span class="text-orange-400 font-bold text-lg">$<?= number_format($product['price'], 2) ?></span>
                                                <form method="POST" action="electronics.php">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="add_to_cart" class="bg-purple-600/80 hover:bg-purple-500 text-white text-xs px-3 py-1.5 rounded-full"><?= __('add') ?></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="border-t border-white/10 mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('electronics_footer') ?>
    </footer>
</div>

<script>
// Particle background (unchanged)
(function() {
    const canvas = document.getElementById('particleCanvas');
    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];
    let mouseX = -1000, mouseY = -1000;
    function initParticles() {
        for (let i = 0; i < 70; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                radius: Math.random() * 3 + 1,
                speedX: (Math.random() - 0.5) * 0.5,
                speedY: (Math.random() - 0.5) * 0.3,
                alpha: Math.random() * 0.3 + 0.1
            });
        }
    }
    function resizeCanvas() {
        width = window.innerWidth;
        height = document.body.scrollHeight;
        canvas.width = width;
        canvas.height = height;
        ctx.clearRect(0, 0, width, height);
        particles = [];
        initParticles();
    }
    function drawParticles() {
        ctx.clearRect(0, 0, width, height);
        for (let p of particles) {
            p.x += p.speedX;
            p.y += p.speedY;
            if (p.x < 0) p.x = width;
            if (p.x > width) p.x = 0;
            if (p.y < 0) p.y = height;
            if (p.y > height) p.y = 0;
            const dx = mouseX - p.x;
            const dy = mouseY - p.y;
            const dist = Math.sqrt(dx*dx + dy*dy);
            if (dist < 100) {
                const angle = Math.atan2(dy, dx);
                const force = (100 - dist) / 500;
                p.x += Math.cos(angle) * force;
                p.y += Math.sin(angle) * force;
            }
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(139, 92, 246, ${p.alpha})`;
            ctx.fill();
        }
        requestAnimationFrame(drawParticles);
    }
    window.addEventListener('resize', resizeCanvas);
    document.addEventListener('mousemove', (e) => { mouseX = e.clientX; mouseY = e.clientY + window.scrollY; });
    resizeCanvas();
    drawParticles();
})();

// Ripple effect
document.querySelectorAll('.btn-ripple').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        ripple.style.left = `${x}px`;
        ripple.style.top = `${y}px`;
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    });
});
</script>
</body>
</html>