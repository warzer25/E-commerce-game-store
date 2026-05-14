<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// Handle filter and search (same as before)
$selectedTagIds = array_filter(array_map('intval', (array)($_GET['tags'] ?? [])));
$selectedTagIds = array_values(array_unique($selectedTagIds));
$search = trim($_GET['search'] ?? '');
$searchSql = '';
if ($search !== '') {
    $escapedSearch = mysqli_real_escape_string($conn, $search);
    $searchSql = " AND (g.game_name LIKE '%$escapedSearch%' OR g.game_descriptions LIKE '%$escapedSearch%' OR g.game_publisher LIKE '%$escapedSearch%')";
}

$games = [];
$sql = "SELECT g.game_id, g.game_name, g.game_descriptions, g.game_publisher, g.game_price, g.game_image FROM game_table g";
if (!empty($selectedTagIds)) {
    $tagIds = implode(',', $selectedTagIds);
    $tagCount = count($selectedTagIds);
    $sql .= " JOIN game_tag_table gt ON g.game_id = gt.game_id WHERE gt.tag_id IN ($tagIds) $searchSql GROUP BY g.game_id HAVING COUNT(DISTINCT gt.tag_id) = $tagCount ORDER BY g.game_id DESC";
} else {
    $sql .= " WHERE 1=1 $searchSql ORDER BY g.game_id DESC";
}
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $games[] = $row;
    }
    mysqli_free_result($result);
}

$tags = [];
$tagResult = mysqli_query($conn, "SELECT tags_id, tags_name, tags_category FROM tags_table ORDER BY tags_category, tags_name");
if ($tagResult) {
    while ($row = mysqli_fetch_assoc($tagResult)) {
        $tags[] = $row;
    }
    mysqli_free_result($tagResult);
}

$groupedTags = [];
foreach ($tags as $tag) {
    $category = $tag['tags_category'];
    if (!isset($groupedTags[$category])) {
        $groupedTags[$category] = [];
    }
    $groupedTags[$category][] = $tag;
}
$categoryEmojis = [
    'Genres' => '🎮',
    'Game Modes' => '👥',
    'Features & Styles' => '✨',
    'Themes' => '🎭',
    'Other' => '⚙️'
];

$carouselGames = array_slice($games, 0, 5);
$topSellerGames = array_slice($games, 5, 3);
$newTrendingGames = array_slice($games, 8, 3);
$allGamesForPagination = array_slice($games, 11); // All remaining games

// --- PAGINATION for All Games section ---
$gamesPerPage = 9; // 3x3
$currentPage = isset($_GET['games_page']) ? max(1, (int)$_GET['games_page']) : 1;
$totalAllGames = count($allGamesForPagination);
$totalPages = ceil($totalAllGames / $gamesPerPage);
$offset = ($currentPage - 1) * $gamesPerPage;
$remainingGames = array_slice($allGamesForPagination, $offset, $gamesPerPage);

$cartItems = $_SESSION['cart'] ?? [];
$cartTotal = 0;
$cartCount = 0;
foreach ($cartItems as $item) {
    $cartTotal += $item['price'] * $item['qty'];
    $cartCount += $item['qty'];
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <!-- same head as before -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('games_page_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        /* same styles as original */
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; overflow-x: hidden; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
        .game-card { transition: all 0.3s cubic-bezier(0.2,0.9,0.4,1.1); background: #17171c; border: 1px solid #2a2a30; }
        .game-card:hover { transform: translateY(-6px); border-color: #3b82f6; box-shadow: 0 20px 30px -12px rgba(0,0,0,0.5); }
        .carousel-container { position: relative; overflow: hidden; border-radius: 1rem; }
        .carousel-track { display: flex; transition: transform 0.6s cubic-bezier(0.25,0.46,0.45,0.94); }
        .carousel-slide { flex: 0 0 100%; }
        .carousel-btn { cursor: pointer; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); transition: all 0.2s; }
        .carousel-btn:hover { background: rgba(0,0,0,0.8); transform: scale(1.1); }
        .carousel-dot { cursor: pointer; transition: all 0.2s ease; background: rgba(255,255,255,0.4); }
        .carousel-dot.active { background: #3b82f6; width: 1.5rem; }
        .filter-section { border-bottom: 1px solid rgba(255,255,255,0.08); }
        .filter-header { cursor: pointer; user-select: none; }
        .filter-header:hover { color: #3b82f6; }
        .filter-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .filter-content.expanded { max-height: 300px; overflow-y: auto; }
        .toggle-icon { transition: transform 0.2s; }
        .filter-header.expanded .toggle-icon { transform: rotate(90deg); }
        .btn-ripple { position: relative; overflow: hidden; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .section-title { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .section-title span { width: 4px; height: 24px; background: #3b82f6; border-radius: 2px; }
        .custom-grid {
            display: grid;
            grid-template-columns: 1fr;
        }
        @media (min-width: 1024px) {
            .custom-grid {
                grid-template-columns: 20% 60% 20%;
            }
        }
    </style>
</head>
<body>
<canvas id="particleCanvas"></canvas>
<div class="content-wrapper">
    <!-- Header (same as original) -->
    <header class="sticky top-0 z-50 bg-[#17171c] border-b border-[#2a2a30] shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <a href="index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-md flex items-center justify-center text-black font-black text-lg">G</div>
                <span class="text-xl font-bold tracking-tight text-white">GameStore</span>
            </a>
            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="index.php" class="text-white hover:text-cyan-400 transition"><?= __('home') ?></a>
                <a href="games.php" class="text-cyan-400 font-semibold"><?= __('games') ?></a>
                <a href="electronics.php" class="text-white hover:text-cyan-400 transition"><?= __('electronics') ?></a>
                <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['publisher','admin'])): ?>
                    <a href="insertpage.php" class="text-white hover:text-cyan-400 transition"><?= __('add_game') ?></a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'publisher'): ?>
                    <a href="mygames.php" class="text-white hover:text-cyan-400 transition"><?= __('my_games') ?></a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="admin/users.php" class="text-yellow-300 hover:text-yellow-200 transition"><?= __('admin') ?></a>
                    <a href="admin/dashboard.php" class="text-cyan-400 font-semibold"><?= __('dashboard') ?></a>
                <?php endif; ?>
                <a href="cart.php" class="text-white hover:text-cyan-400 transition relative"><?= __('cart') ?><?php if ($cartCount>0): ?> <span class="text-cyan-400">●<?= $cartCount ?></span><?php endif; ?></a>
                <a href="profile.php" class="text-white hover:text-cyan-400 transition"><?= __('profile') ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-600/80 text-white px-3 py-1 rounded-md hover:bg-blue-500 transition"><?= __('login') ?></a>
                <?php endif; ?>
            </nav>
            <div class="flex items-center gap-3">
                <form method="GET" action="games.php" class="flex items-center bg-[#2a2a30] rounded-md px-3 py-1">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_games') ?>" class="bg-transparent outline-none text-sm w-40 md:w-56 text-white">
                    <button type="submit" class="text-gray-400 hover:text-white">🔍</button>
                </form>
                <div class="language-switcher flex gap-2">
                    <a href="?lang=en" class="text-xs <?= $current_lang == 'en' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">EN</a>
                    <a href="?lang=ku" class="text-xs <?= $current_lang == 'ku' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">KU</a>
                </div>
            </div>
        </div>
    </header>

    <main class="w-full">
        <div class="custom-grid">
            <!-- LEFT SIDEBAR (Filters) – same as original -->
            <aside class="bg-[#17171c] p-6 border-r border-[#2a2a30] h-fit sticky top-20">
                <h2 class="font-semibold text-white mb-3 flex items-center gap-2">🎯 <?= __('filters') ?></h2>
                <form method="GET" action="games.php" id="filterForm">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php foreach ($groupedTags as $category => $catTags): ?>
                        <div class="filter-section py-2">
                            <div class="filter-header flex justify-between items-center cursor-pointer py-1" data-category="<?= htmlspecialchars($category) ?>">
                                <div class="text-sm font-semibold text-gray-300 flex items-center gap-1">
                                    <?= $categoryEmojis[$category] ?? '🏷️' ?> <?= htmlspecialchars($category) ?>
                                </div>
                                <span class="toggle-icon text-gray-400 transition-transform duration-200">▶</span>
                            </div>
                            <div class="filter-content" data-category-content="<?= htmlspecialchars($category) ?>">
                                <div class="space-y-1 pl-1 pt-1 pb-1">
                                    <?php foreach ($catTags as $tag):
                                        $checked = in_array($tag['tags_id'], $selectedTagIds, true) ? 'checked' : '';
                                    ?>
                                        <label class="flex items-center gap-2 text-sm text-gray-400 hover:text-white cursor-pointer transition duration-150">
                                            <input type="checkbox" name="tags[]" value="<?= $tag['tags_id'] ?>" <?= $checked ?> class="rounded border-gray-600 bg-gray-800 text-blue-500 focus:ring-blue-500">
                                            <span><?= htmlspecialchars($tag['tags_name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="flex gap-2 mt-4 pt-3 border-t border-gray-700">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white text-sm py-1.5 rounded-md transition btn-ripple"><?= __('apply') ?></button>
                        <a href="games.php" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white text-sm py-1.5 rounded-md text-center transition"><?= __('reset') ?></a>
                    </div>
                </form>
            </aside>

            <!-- MAIN CONTENT – 60% -->
            <div class="px-4 md:px-6 py-6 space-y-10">
                <!-- HERO CAROUSEL (unchanged) -->
                <?php if (!empty($carouselGames)): ?>
                    <div class="relative carousel-container rounded-xl overflow-hidden shadow-2xl">
                        <div id="carouselTrack" class="carousel-track">
                            <?php foreach ($carouselGames as $game): ?>
                                <div class="carousel-slide relative h-72 md:h-80 flex items-end justify-start p-6 bg-cover bg-center" style="background-image: url('<?= !empty($game['game_image']) && file_exists($game['game_image']) ? htmlspecialchars($game['game_image']) : 'https://placehold.co/1920x600/1e2a3a/475569?text=Game+Art' ?>');">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent"></div>
                                    <div class="relative z-10 max-w-lg">
                                        <div class="text-xs text-blue-300 mb-1"><?= __('featured') ?></div>
                                        <h2 class="text-3xl font-bold text-white"><?= htmlspecialchars($game['game_name']) ?></h2>
                                        <p class="text-sm text-gray-300 line-clamp-2"><?= htmlspecialchars(substr($game['game_descriptions'], 0, 100)) ?>…</p>
                                        <div class="flex items-center gap-3 mt-3">
                                            <span class="text-xl font-bold text-blue-400">$<?= number_format($game['game_price'], 2) ?></span>
                                            <form method="POST" action="cart.php">
                                                <input type="hidden" name="game_id" value="<?= $game['game_id'] ?>">
                                                <button type="submit" name="add_to_cart" class="bg-white text-black px-4 py-1.5 rounded-md text-sm font-semibold hover:bg-gray-200 transition btn-ripple"><?= __('add_to_cart') ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button id="prevBtn" class="carousel-btn absolute left-3 top-1/2 -translate-y-1/2 text-white rounded-full p-1 w-8 h-8 flex items-center justify-center text-xl">‹</button>
                        <button id="nextBtn" class="carousel-btn absolute right-3 top-1/2 -translate-y-1/2 text-white rounded-full p-1 w-8 h-8 flex items-center justify-center text-xl">›</button>
                        <div id="carouselIndicators" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5"></div>
                    </div>
                <?php endif; ?>

                <!-- TOP SELLERS (unchanged) -->
                <?php if (!empty($topSellerGames)): ?>
                    <div>
                        <div class="section-title">
                            <span></span>
                            <h2 class="text-xl font-bold text-white">🔥 <?= __('top_sellers') ?></h2>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                            <?php foreach ($topSellerGames as $game): ?>
                                <div class="bg-[#1e1e24] rounded-xl overflow-hidden game-card">
                                    <div class="h-44 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-5xl">
                                        <?php if (!empty($game['game_image']) && file_exists($game['game_image'])): ?>
                                            <img src="<?= htmlspecialchars($game['game_image']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            🎮
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-white text-lg truncate"><?= htmlspecialchars($game['game_name']) ?></h3>
                                        <p class="text-sm text-gray-400 truncate"><?= htmlspecialchars($game['game_publisher']) ?></p>
                                        <div class="flex justify-between items-center mt-3">
                                            <span class="text-xl font-bold text-blue-400">$<?= number_format($game['game_price'], 2) ?></span>
                                            <form method="POST" action="cart.php">
                                                <input type="hidden" name="game_id" value="<?= $game['game_id'] ?>">
                                                <button type="submit" name="add_to_cart" class="bg-blue-600 hover:bg-blue-500 text-white text-sm px-3 py-1.5 rounded-lg transition btn-ripple"><?= __('add') ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- NEW & TRENDING (unchanged) -->
                <?php if (!empty($newTrendingGames)): ?>
                    <div>
                        <div class="section-title">
                            <span></span>
                            <h2 class="text-xl font-bold text-white">✨ <?= __('new_trending') ?></h2>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                            <?php foreach ($newTrendingGames as $game): ?>
                                <div class="bg-[#1e1e24] rounded-xl overflow-hidden game-card">
                                    <div class="h-44 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-5xl">
                                        <?php if (!empty($game['game_image']) && file_exists($game['game_image'])): ?>
                                            <img src="<?= htmlspecialchars($game['game_image']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            🎮
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-white text-lg truncate"><?= htmlspecialchars($game['game_name']) ?></h3>
                                        <p class="text-sm text-gray-400 truncate"><?= htmlspecialchars($game['game_publisher']) ?></p>
                                        <div class="flex justify-between items-center mt-3">
                                            <span class="text-xl font-bold text-blue-400">$<?= number_format($game['game_price'], 2) ?></span>
                                            <form method="POST" action="cart.php">
                                                <input type="hidden" name="game_id" value="<?= $game['game_id'] ?>">
                                                <button type="submit" name="add_to_cart" class="bg-blue-600 hover:bg-blue-500 text-white text-sm px-3 py-1.5 rounded-lg transition btn-ripple"><?= __('add') ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ALL GAMES with Pagination -->
                <?php if (!empty($remainingGames)): ?>
                    <div>
                        <div class="section-title">
                            <span></span>
                            <h2 class="text-xl font-bold text-white">🎮 <?= __('all_games') ?> (<?= $totalAllGames ?> total)</h2>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach ($remainingGames as $game): ?>
                                <div class="bg-[#1e1e24] rounded-lg overflow-hidden game-card">
                                    <div class="h-32 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-3xl">
                                        <?php if (!empty($game['game_image']) && file_exists($game['game_image'])): ?>
                                            <img src="<?= htmlspecialchars($game['game_image']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            🎮
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-2">
                                        <h3 class="font-semibold text-sm truncate text-white"><?= htmlspecialchars($game['game_name']) ?></h3>
                                        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($game['game_publisher']) ?></p>
                                        <div class="flex justify-between items-center mt-1">
                                            <span class="text-blue-400 font-bold text-sm">$<?= number_format($game['game_price'], 2) ?></span>
                                            <form method="POST" action="cart.php">
                                                <input type="hidden" name="game_id" value="<?= $game['game_id'] ?>">
                                                <button type="submit" name="add_to_cart" class="bg-blue-600/80 hover:bg-blue-500 text-white text-xs px-2 py-1 rounded transition btn-ripple"><?= __('cart_short') ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination controls -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex justify-center items-center gap-2 mt-6">
                                <?php if ($currentPage > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['games_page' => $currentPage - 1])) ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition">← <?= __('previous') ?></a>
                                <?php endif; ?>
                                <span class="text-gray-400 text-sm"><?= __('page') ?> <?= $currentPage ?> <?= __('of') ?> <?= $totalPages ?></span>
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['games_page' => $currentPage + 1])) ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition"><?= __('next') ?> →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- SEARCH / FILTER RESULTS (if any) - same as original -->
                <?php if ($search !== '' || !empty($selectedTagIds)): ?>
                    <div>
                        <div class="section-title">
                            <span></span>
                            <h2 class="text-xl font-bold text-white"><?= __('search_results') ?> (<?= count($games) ?>)</h2>
                        </div>
                        <?php if (empty($games)): ?>
                            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 text-center">
                                <p class="text-gray-400"><?= __('no_games_match') ?></p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                <?php foreach ($games as $game): ?>
                                    <div class="bg-[#1e1e24] rounded-lg overflow-hidden game-card">
                                        <div class="h-32 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-3xl">
                                            <?php if (!empty($game['game_image']) && file_exists($game['game_image'])): ?>
                                                <img src="<?= htmlspecialchars($game['game_image']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                🎮
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-2">
                                            <h3 class="font-semibold text-sm truncate text-white"><?= htmlspecialchars($game['game_name']) ?></h3>
                                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($game['game_publisher']) ?></p>
                                            <div class="flex justify-between items-center mt-1">
                                                <span class="text-blue-400 font-bold text-sm">$<?= number_format($game['game_price'], 2) ?></span>
                                                <form method="POST" action="cart.php">
                                                    <input type="hidden" name="game_id" value="<?= $game['game_id'] ?>">
                                                    <button type="submit" name="add_to_cart" class="bg-blue-600/80 hover:bg-blue-500 text-white text-xs px-2 py-1 rounded transition btn-ripple"><?= __('cart_short') ?></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT CART SIDEBAR – 20% (unchanged) -->
            <aside class="bg-[#17171c] p-6 border-l border-[#2a2a30] h-fit sticky top-20">
                <div class="flex justify-between items-center border-b border-gray-700 pb-2 mb-3">
                    <h2 class="font-semibold text-white">🛒 <?= __('your_cart') ?></h2>
                    <span class="bg-gray-700 text-white text-xs px-2 py-0.5 rounded-full"><?= $cartCount ?></span>
                </div>
                <?php if (empty($cartItems)): ?>
                    <p class="text-gray-400 text-sm text-center py-6"><?= __('cart_empty_short') ?></p>
                <?php else: ?>
                    <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                        <?php foreach ($cartItems as $id => $item): ?>
                            <div class="text-sm border-b border-gray-700/50 pb-2 transition hover:bg-gray-800/30 p-1 rounded">
                                <div class="font-medium truncate text-white"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="flex justify-between text-xs mt-1">
                                    <span class="text-gray-300"><?= __('qty') ?>: <?= $item['qty'] ?></span>
                                    <span class="text-blue-400">$<?= number_format($item['price'] * $item['qty'], 2) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-700">
                        <div class="flex justify-between text-sm font-medium">
                            <span class="text-gray-300"><?= __('total') ?>:</span>
                            <span class="text-blue-400 font-bold">$<?= number_format($cartTotal, 2) ?></span>
                        </div>
                        <a href="cart.php" class="mt-3 block text-center bg-gray-700 hover:bg-gray-600 text-white py-1.5 rounded-md text-sm transition"><?= __('view_cart') ?> →</a>
                        <?php if (!empty($cartItems)): ?>
                            <form method="POST" action="checkout.php" class="mt-2">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-1.5 rounded-md transition btn-ripple"><?= __('checkout') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('games_footer') ?>
    </footer>
</div>

<script>
// Particle background (same)
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
            ctx.fillStyle = `rgba(44, 125, 160, ${p.alpha})`;
            ctx.fill();
        }
        requestAnimationFrame(drawParticles);
    }
    window.addEventListener('resize', resizeCanvas);
    document.addEventListener('mousemove', (e) => { mouseX = e.clientX; mouseY = e.clientY + window.scrollY; });
    resizeCanvas();
    drawParticles();
})();

// Carousel logic (unchanged)
(function() {
    const track = document.getElementById('carouselTrack');
    if (track && track.children.length > 0) {
        let slides = Array.from(track.children);
        let current = 0;
        let interval;
        const prev = document.getElementById('prevBtn');
        const next = document.getElementById('nextBtn');
        const indicatorsDiv = document.getElementById('carouselIndicators');
        function createDots() {
            indicatorsDiv.innerHTML = '';
            slides.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.className = 'carousel-dot h-1.5 rounded-full transition-all duration-200 bg-gray-400';
                dot.style.width = i === current ? '1.5rem' : '0.5rem';
                dot.addEventListener('click', () => goTo(i));
                indicatorsDiv.appendChild(dot);
            });
        }
        function updateDots() {
            const dots = indicatorsDiv.children;
            for (let i = 0; i < dots.length; i++) {
                if (i === current) {
                    dots[i].style.width = '1.5rem';
                    dots[i].classList.add('active', 'bg-blue-500');
                    dots[i].classList.remove('bg-gray-400');
                } else {
                    dots[i].style.width = '0.5rem';
                    dots[i].classList.remove('active', 'bg-blue-500');
                    dots[i].classList.add('bg-gray-400');
                }
            }
        }
        function goTo(idx) {
            if (idx < 0) idx = slides.length - 1;
            if (idx >= slides.length) idx = 0;
            current = idx;
            track.style.transform = `translateX(-${current * 100}%)`;
            updateDots();
            resetAuto();
        }
        function nextSlide() { goTo(current+1); }
        function prevSlide() { goTo(current-1); }
        function startAuto() { interval = setInterval(nextSlide, 6000); }
        function resetAuto() { clearInterval(interval); startAuto(); }
        prev.addEventListener('click', prevSlide);
        next.addEventListener('click', nextSlide);
        createDots();
        startAuto();
        const container = document.querySelector('.carousel-container');
        if (container) {
            container.addEventListener('mouseenter', () => clearInterval(interval));
            container.addEventListener('mouseleave', startAuto);
        }
    }
})();

// Accordion filters (unchanged)
document.querySelectorAll('.filter-header').forEach(header => {
    const content = document.querySelector(`.filter-content[data-category-content="${header.getAttribute('data-category')}"]`);
    if (content) {
        content.classList.add('expanded');
        header.classList.add('expanded');
        const icon = header.querySelector('.toggle-icon');
        if (icon) icon.style.transform = 'rotate(90deg)';
        header.addEventListener('click', () => {
            const isExpanded = content.classList.contains('expanded');
            if (isExpanded) {
                content.classList.remove('expanded');
                header.classList.remove('expanded');
                if (icon) icon.style.transform = 'rotate(0deg)';
            } else {
                content.classList.add('expanded');
                header.classList.add('expanded');
                if (icon) icon.style.transform = 'rotate(90deg)';
            }
        });
    }
});

// Ripple effect (unchanged)
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