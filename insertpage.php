<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// Allow only publisher or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['publisher', 'admin'])) {
    header("Location: index.php");
    exit;
}

$logged_user_id = $_SESSION['user_id'];
$logged_role = $_SESSION['role'];

$message = '';
$messageClass = '';
$selectedTagIds = [];
$tags = [];

// Fetch all tags
$tagResult = mysqli_query($conn, "SELECT tags_id, tags_name, tags_category FROM tags_table ORDER BY tags_category, tags_name");
if ($tagResult) {
    while ($row = mysqli_fetch_assoc($tagResult)) {
        $tags[] = $row;
    }
} else {
    $message = __('could_not_load_tags') . ': ' . mysqli_error($conn);
    $messageClass = 'bg-rose-500 text-white';
}

// Group tags by category
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_name = trim($_POST['game_name'] ?? '');
    $game_descriptions = trim($_POST['game_descriptions'] ?? '');
    $game_publisher = trim($_POST['game_publisher'] ?? '');
    $game_price = trim($_POST['game_price'] ?? '');
    $selectedTagIds = array_filter(array_map('intval', (array)($_POST['game_tags'] ?? [])));
    $selectedTagIds = array_values(array_unique($selectedTagIds));

    // Validate fields
    if ($game_name === '' || $game_descriptions === '' || $game_publisher === '' || $game_price === '') {
        $message = __('fill_all_fields');
        $messageClass = 'bg-rose-500 text-white';
    } elseif (!filter_var($game_price, FILTER_VALIDATE_INT) || intval($game_price) < 0 || intval($game_price) > 9999) {
        $message = __('price_range');
        $messageClass = 'bg-rose-500 text-white';
    } else {
        // Check for duplicate game name
        $checkStmt = mysqli_prepare($conn, "SELECT game_id FROM game_table WHERE game_name = ?");
        mysqli_stmt_bind_param($checkStmt, "s", $game_name);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $message = __('duplicate_game');
            $messageClass = 'bg-rose-500 text-white';
            mysqli_stmt_close($checkStmt);
        } else {
            mysqli_stmt_close($checkStmt);

            // --- Image upload handling ---
            $imagePath = null;
            if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/games/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileTmp = $_FILES['game_image']['tmp_name'];
                $fileName = basename($_FILES['game_image']['name']);
                $fileSize = $_FILES['game_image']['size'];
                $fileType = mime_content_type($fileTmp);
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                $maxSize = 2 * 1024 * 1024;

                if (!in_array($fileType, $allowedTypes)) {
                    $message = __('invalid_image_type');
                    $messageClass = 'bg-rose-500 text-white';
                } elseif ($fileSize > $maxSize) {
                    $message = __('image_too_large');
                    $messageClass = 'bg-rose-500 text-white';
                } else {
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $newFileName = 'game_' . uniqid() . '.' . $ext;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $imagePath = $destPath;
                    } else {
                        $message = __('upload_failed');
                        $messageClass = 'bg-rose-500 text-white';
                    }
                }
            }

            // Proceed only if no image error
            if ($message === '' || strpos($message, __('image_too_large')) === false && strpos($message, __('invalid_image_type')) === false) {
                $priceValue = intval($game_price);
                // Insert game: if image provided, include it
                if ($imagePath) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO game_table (game_name, game_descriptions, game_publisher, game_price, game_image) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'sssis', $game_name, $game_descriptions, $game_publisher, $priceValue, $imagePath);
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO game_table (game_name, game_descriptions, game_publisher, game_price) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'sssi', $game_name, $game_descriptions, $game_publisher, $priceValue);
                }

                if (mysqli_stmt_execute($stmt)) {
                    $gameId = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    // Assign publisher_id if current user is publisher
                    if ($logged_role === 'publisher') {
                        $pubStmt = mysqli_prepare($conn, "UPDATE game_table SET publisher_id = ? WHERE game_id = ?");
                        mysqli_stmt_bind_param($pubStmt, "ii", $logged_user_id, $gameId);
                        mysqli_stmt_execute($pubStmt);
                        mysqli_stmt_close($pubStmt);
                    } else {
                        // Admin can optionally assign a publisher
                        if (isset($_POST['publisher_id']) && is_numeric($_POST['publisher_id'])) {
                            $adminPubId = (int)$_POST['publisher_id'];
                            $pubStmt = mysqli_prepare($conn, "UPDATE game_table SET publisher_id = ? WHERE game_id = ?");
                            mysqli_stmt_bind_param($pubStmt, "ii", $adminPubId, $gameId);
                            mysqli_stmt_execute($pubStmt);
                            mysqli_stmt_close($pubStmt);
                        }
                    }

                    // Insert tags
                    if (!empty($selectedTagIds)) {
                        $linkStmt = mysqli_prepare($conn, "INSERT INTO game_tag_table (game_id, tag_id) VALUES (?, ?)");
                        if ($linkStmt) {
                            foreach ($selectedTagIds as $tagId) {
                                mysqli_stmt_bind_param($linkStmt, 'ii', $gameId, $tagId);
                                mysqli_stmt_execute($linkStmt);
                            }
                            mysqli_stmt_close($linkStmt);
                        }
                    }

                    $message = __('insert_success');
                    $messageClass = 'bg-emerald-500 text-slate-950';
                    // Clear form fields
                    $game_name = $game_descriptions = $game_publisher = $game_price = '';
                    $selectedTagIds = [];
                } else {
                    $message = __('insert_failed') . ': ' . mysqli_stmt_error($stmt);
                    $messageClass = 'bg-rose-500 text-white';
                    if ($stmt) mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Fetch all users (for admin to assign publisher)
$publishers = [];
if ($logged_role === 'admin') {
    $pubQuery = mysqli_query($conn, "SELECT account_id, username FROM account_table WHERE role = 'publisher' OR role = 'admin' ORDER BY username");
    while ($row = mysqli_fetch_assoc($pubQuery)) {
        $publishers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_game_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; position: relative; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
        @keyframes pulseGlow { 0% { box-shadow: 0 0 0 0 rgba(44,125,160,0.4); } 70% { box-shadow: 0 0 0 6px rgba(44,125,160,0); } 100% { box-shadow: 0 0 0 0 rgba(44,125,160,0); } }
        .btn-pulse:active { animation: pulseGlow 0.4s ease-out; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .btn-ripple { position: relative; overflow: hidden; }
        .form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.3); }
        .category-btn.active { background: rgba(59,130,246,0.2); border-color: #3b82f6; color: #3b82f6; }
        .tag-item:hover { background: rgba(59,130,246,0.2); border-color: #3b82f6; }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="content-wrapper">
    <!-- Header with white links and language switcher -->
    <header class="sticky top-0 z-50 bg-[#17171c] border-b border-[#2a2a30] shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-md flex items-center justify-center text-black font-black text-lg">G</div>
                <span class="text-xl font-bold tracking-tight text-white">GameStore</span>
            </div>
            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="index.php" class="text-white hover:text-cyan-400 transition"><?= __('store') ?></a>
                <a href="insertpage.php" class="text-cyan-400 font-semibold"><?= __('add_game') ?></a>
                <?php if ($_SESSION['role'] === 'publisher'): ?>
                    <a href="mygames.php" class="text-white hover:text-cyan-400 transition"><?= __('my_games') ?></a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin/users.php" class="text-yellow-300 hover:text-yellow-200 transition"><?= __('admin') ?></a>
                <?php endif; ?>
                <a href="cart.php" class="text-white hover:text-cyan-400 transition"><?= __('cart') ?></a>
                <a href="profile.php" class="text-white hover:text-cyan-400 transition"><?= __('profile') ?></a>
                <a href="logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
            </nav>
            <div class="language-switcher flex gap-2">
                <a href="?lang=en" class="text-xs <?= $current_lang == 'en' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">EN</a>
                <a href="?lang=ku" class="text-xs <?= $current_lang == 'ku' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">KU</a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden fade-up">
            <div class="p-6 border-b border-[#2a2a30]">
                <p class="text-sm text-blue-400 uppercase tracking-wide"><?= ucfirst($logged_role) ?> <?= __('panel') ?></p>
                <h1 class="text-2xl font-bold text-white mt-1"><?= __('add_new_game') ?></h1>
                <p class="text-gray-400 text-sm mt-1"><?= __('form_instruction') ?></p>
            </div>

            <?php if ($message): ?>
                <div class="mx-6 mt-6 rounded-lg px-4 py-3 text-sm font-medium <?= $messageClass ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('game_name') ?> *</label>
                        <input type="text" name="game_name" value="<?= htmlspecialchars($game_name ?? '') ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('publisher') ?> *</label>
                        <input type="text" name="game_publisher" value="<?= htmlspecialchars($game_publisher ?? '') ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
                    </div>

                    <?php if ($logged_role === 'admin' && !empty($publishers)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('assign_publisher') ?></label>
                            <select name="publisher_id" class="w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- <?= __('none_unchanged') ?> --</option>
                                <?php foreach ($publishers as $p): ?>
                                    <option value="<?= $p['account_id'] ?>"><?= htmlspecialchars($p['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('description') ?> *</label>
                        <textarea name="game_descriptions" rows="4" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500"><?= htmlspecialchars($game_descriptions ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('price_usd') ?> *</label>
                        <input type="number" min="0" max="9999" step="1" name="game_price" value="<?= htmlspecialchars($game_price ?? '') ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('game_image_optional') ?></label>
                        <input type="file" name="game_image" accept="image/jpeg,image/png,image/webp" class="w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-500">
                        <p class="text-xs text-gray-500 mt-1"><?= __('image_format_hint') ?></p>
                    </div>
                </div>

                <!-- Tags section with category filters -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2"><?= __('tags') ?></label>
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button type="button" class="category-btn active px-3 py-1 rounded-full text-xs font-medium border bg-blue-600/20 text-blue-400 border-blue-500 transition" data-category="none">🚫 <?= __('none') ?></button>
                        <?php foreach ($groupedTags as $category => $categoryTags):
                            $emoji = $categoryEmojis[$category] ?? '🏷️';
                            $slug = strtolower(str_replace(' ', '-', $category));
                        ?>
                            <button type="button" class="category-btn px-3 py-1 rounded-full text-xs font-medium border border-[#3a3a44] bg-[#2a2a30] text-gray-300 hover:border-blue-500 transition" data-category="<?= htmlspecialchars($slug) ?>"><?= $emoji ?> <?= htmlspecialchars($category) ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="category-btn px-3 py-1 rounded-full text-xs font-medium border border-[#3a3a44] bg-[#2a2a30] text-gray-300 hover:border-blue-500 transition" data-category="all">🎯 <?= __('all') ?></button>
                    </div>
                    <div id="tagsContainer" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach ($groupedTags as $category => $categoryTags):
                            $categoryClass = strtolower(str_replace(' ', '-', $category));
                            foreach ($categoryTags as $tag):
                                $checked = in_array((int)$tag['tags_id'], $selectedTagIds, true) ? 'checked' : '';
                        ?>
                            <label class="tag-item <?= $categoryClass ?> flex items-center gap-2 border border-[#3a3a44] bg-[#2a2a30] rounded-lg px-3 py-2 text-sm cursor-pointer transition hover:bg-blue-500/10">
                                <input type="checkbox" name="game_tags[]" value="<?= htmlspecialchars($tag['tags_id']) ?>" class="w-4 h-4 rounded border-gray-500 bg-[#1f1f28] text-blue-500 focus:ring-blue-500" <?= $checked ?>>
                                <span class="text-gray-200"><?= htmlspecialchars($tag['tags_name']) ?></span>
                            </label>
                        <?php endforeach; endforeach; ?>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-[#2a2a30]">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-2 rounded-lg transition transform hover:scale-105 btn-ripple"><?= __('insert_game') ?></button>
                    <button type="reset" class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition"><?= __('reset') ?></button>
                </div>
            </form>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('add_game_footer') ?>
    </footer>
</div>

<script>
// Particle background (same as other pages)
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

// Tag category filter
const categoryBtns = document.querySelectorAll('.category-btn');
const tagItems = document.querySelectorAll('.tag-item');
function applyCategory(selectedCategory, activeButton) {
    categoryBtns.forEach(btn => {
        btn.classList.remove('active', 'border-blue-500', 'bg-blue-600/20', 'text-blue-400');
        btn.classList.add('border-[#3a3a44]', 'bg-[#2a2a30]', 'text-gray-300');
    });
    if (activeButton) {
        activeButton.classList.add('active', 'border-blue-500', 'bg-blue-600/20', 'text-blue-400');
        activeButton.classList.remove('border-[#3a3a44]', 'bg-[#2a2a30]', 'text-gray-300');
    }
    tagItems.forEach(tag => {
        if (selectedCategory === 'all') tag.style.display = 'flex';
        else if (selectedCategory === 'none') tag.style.display = 'none';
        else tag.style.display = tag.classList.contains(selectedCategory) ? 'flex' : 'none';
    });
}
categoryBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        applyCategory(this.getAttribute('data-category'), this);
    });
});
const defaultBtn = document.querySelector('.category-btn.active');
if (defaultBtn) applyCategory(defaultBtn.getAttribute('data-category'), defaultBtn);
else applyCategory('none', null);
</script>
</body>
</html>