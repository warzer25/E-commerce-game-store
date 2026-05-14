<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only publisher or admin allowed
if ($role !== 'publisher' && $role !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = '';
$messageClass = '';

// ---------- DELETE GAME ----------
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $game_id = (int)$_GET['delete'];
    
    $checkStmt = mysqli_prepare($conn, "SELECT publisher_id FROM game_table WHERE game_id = ?");
    mysqli_stmt_bind_param($checkStmt, "i", $game_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $game = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);
    
    if ($role === 'admin' || ($game && $game['publisher_id'] == $user_id)) {
        $delTags = mysqli_prepare($conn, "DELETE FROM game_tag_table WHERE game_id = ?");
        mysqli_stmt_bind_param($delTags, "i", $game_id);
        mysqli_stmt_execute($delTags);
        mysqli_stmt_close($delTags);
        
        $delGame = mysqli_prepare($conn, "DELETE FROM game_table WHERE game_id = ?");
        mysqli_stmt_bind_param($delGame, "i", $game_id);
        if (mysqli_stmt_execute($delGame)) {
            $message = __('game_delete_success');
            $messageClass = "bg-emerald-500 text-slate-950";
        } else {
            $message = __('game_delete_failed') . ": " . mysqli_stmt_error($delGame);
            $messageClass = "bg-rose-500 text-white";
        }
        mysqli_stmt_close($delGame);
    } else {
        $message = __('no_delete_permission');
        $messageClass = "bg-rose-500 text-white";
    }
}

// ---------- UPDATE GAME ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_game'])) {
    $game_id = (int)$_POST['game_id'];
    $game_name = trim($_POST['game_name']);
    $game_descriptions = trim($_POST['game_descriptions']);
    $game_publisher = trim($_POST['game_publisher']);
    $game_price = (int)$_POST['game_price'];
    $selectedTagIds = array_map('intval', (array)($_POST['game_tags'] ?? []));
    
    $checkStmt = mysqli_prepare($conn, "SELECT publisher_id FROM game_table WHERE game_id = ?");
    mysqli_stmt_bind_param($checkStmt, "i", $game_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $game = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);
    
    if ($role !== 'admin' && ($game['publisher_id'] != $user_id)) {
        $message = __('no_edit_permission');
        $messageClass = "bg-rose-500 text-white";
    } else {
        $updateStmt = mysqli_prepare($conn, "UPDATE game_table SET game_name=?, game_descriptions=?, game_publisher=?, game_price=? WHERE game_id=?");
        mysqli_stmt_bind_param($updateStmt, "sssii", $game_name, $game_descriptions, $game_publisher, $game_price, $game_id);
        if (mysqli_stmt_execute($updateStmt)) {
            $delTags = mysqli_prepare($conn, "DELETE FROM game_tag_table WHERE game_id = ?");
            mysqli_stmt_bind_param($delTags, "i", $game_id);
            mysqli_stmt_execute($delTags);
            mysqli_stmt_close($delTags);
            
            if (!empty($selectedTagIds)) {
                $insTag = mysqli_prepare($conn, "INSERT INTO game_tag_table (game_id, tag_id) VALUES (?, ?)");
                foreach ($selectedTagIds as $tag_id) {
                    mysqli_stmt_bind_param($insTag, "ii", $game_id, $tag_id);
                    mysqli_stmt_execute($insTag);
                }
                mysqli_stmt_close($insTag);
            }
            $message = __('game_update_success');
            $messageClass = "bg-emerald-500 text-slate-950";
        } else {
            $message = __('game_update_failed') . ": " . mysqli_stmt_error($updateStmt);
            $messageClass = "bg-rose-500 text-white";
        }
        mysqli_stmt_close($updateStmt);
    }
}

// ---------- FETCH GAMES ----------
if ($role === 'admin') {
    $gamesQuery = "SELECT g.game_id, g.game_name, g.game_descriptions, g.game_publisher, g.game_price, g.game_image, a.username as publisher_name 
                   FROM game_table g 
                   LEFT JOIN account_table a ON g.publisher_id = a.account_id 
                   ORDER BY g.game_id DESC";
    $gamesResult = mysqli_query($conn, $gamesQuery);
} else {
    $gamesQuery = "SELECT g.game_id, g.game_name, g.game_descriptions, g.game_publisher, g.game_price, g.game_image 
                   FROM game_table g 
                   WHERE g.publisher_id = ? 
                   ORDER BY g.game_id DESC";
    $gamesStmt = mysqli_prepare($conn, $gamesQuery);
    mysqli_stmt_bind_param($gamesStmt, "i", $user_id);
    mysqli_stmt_execute($gamesStmt);
    $gamesResult = mysqli_stmt_get_result($gamesStmt);
}
$games = [];
while ($row = mysqli_fetch_assoc($gamesResult)) {
    $games[] = $row;
}
if ($role !== 'admin') mysqli_stmt_close($gamesStmt);

// ---------- FETCH ALL TAGS ----------
$allTags = [];
$tagQuery = mysqli_query($conn, "SELECT tags_id, tags_name, tags_category FROM tags_table ORDER BY tags_category, tags_name");
while ($tag = mysqli_fetch_assoc($tagQuery)) {
    $allTags[] = $tag;
}

// ---------- FETCH GAME BEING EDITED ----------
$editGame = null;
$editGameTags = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editPermit = false;
    if ($role === 'admin') {
        $editPermit = true;
    } else {
        $checkOwner = mysqli_prepare($conn, "SELECT game_id FROM game_table WHERE game_id = ? AND publisher_id = ?");
        mysqli_stmt_bind_param($checkOwner, "ii", $editId, $user_id);
        mysqli_stmt_execute($checkOwner);
        mysqli_stmt_store_result($checkOwner);
        if (mysqli_stmt_num_rows($checkOwner) > 0) $editPermit = true;
        mysqli_stmt_close($checkOwner);
    }
    if ($editPermit) {
        $gameStmt = mysqli_prepare($conn, "SELECT * FROM game_table WHERE game_id = ?");
        mysqli_stmt_bind_param($gameStmt, "i", $editId);
        mysqli_stmt_execute($gameStmt);
        $editGame = mysqli_fetch_assoc(mysqli_stmt_get_result($gameStmt));
        mysqli_stmt_close($gameStmt);
        
        $tagsStmt = mysqli_prepare($conn, "SELECT tag_id FROM game_tag_table WHERE game_id = ?");
        mysqli_stmt_bind_param($tagsStmt, "i", $editId);
        mysqli_stmt_execute($tagsStmt);
        $tagsRes = mysqli_stmt_get_result($tagsStmt);
        while ($t = mysqli_fetch_assoc($tagsRes)) {
            $editGameTags[] = $t['tag_id'];
        }
        mysqli_stmt_close($tagsStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('mygames_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; position: relative; overflow-x: hidden; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .btn-ripple { position: relative; overflow: hidden; }
        .form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.3); }
        .game-row:hover { background: rgba(59,130,246,0.05); }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="content-wrapper">
    <header class="sticky top-0 z-50 bg-[#17171c] border-b border-[#2a2a30] shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-md flex items-center justify-center text-black font-black text-lg">G</div>
                <span class="text-xl font-bold tracking-tight text-white">GameStore</span>
            </div>
            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="index.php" class="text-white hover:text-cyan-400 transition"><?= __('store') ?></a>
                <a href="insertpage.php" class="text-white hover:text-cyan-400 transition"><?= __('add_game') ?></a>
                <?php if ($role === 'publisher'): ?>
                    <a href="mygames.php" class="text-cyan-400 font-semibold"><?= __('my_games') ?></a>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
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

    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($message): ?>
            <div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium <?= $messageClass ?> fade-up">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h1 class="text-2xl font-bold text-blue-400 mb-6 fade-up">📋 <?= __('my_games') ?></h1>

        <!-- Edit Form (if editing) -->
        <?php if ($editGame): ?>
            <div class="bg-[#17171c] rounded-xl border border-blue-500/30 p-6 mb-8 fade-up">
                <h2 class="text-xl font-semibold text-blue-400 mb-4">✏️ <?= __('editing') ?>: <?= htmlspecialchars($editGame['game_name']) ?></h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="game_id" value="<?= $editGame['game_id'] ?>">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-300 mb-1"><?= __('game_name') ?> *</label>
                            <input type="text" name="game_name" value="<?= htmlspecialchars($editGame['game_name']) ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-300 mb-1"><?= __('publisher') ?> *</label>
                            <input type="text" name="game_publisher" value="<?= htmlspecialchars($editGame['game_publisher']) ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-300 mb-1"><?= __('description') ?></label>
                            <textarea name="game_descriptions" rows="3" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500"><?= htmlspecialchars($editGame['game_descriptions']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-300 mb-1"><?= __('price_usd') ?></label>
                            <input type="number" name="game_price" value="<?= $editGame['game_price'] ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-300 mb-1"><?= __('tags') ?></label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mt-1">
                                <?php foreach ($allTags as $tag): ?>
                                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer hover:text-blue-400">
                                        <input type="checkbox" name="game_tags[]" value="<?= $tag['tags_id'] ?>" <?= in_array($tag['tags_id'], $editGameTags) ? 'checked' : '' ?> class="rounded border-gray-600 bg-[#1f1f28] text-blue-500 focus:ring-blue-500">
                                        <?= htmlspecialchars($tag['tags_name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" name="update_game" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-5 py-2 rounded-lg transition transform hover:scale-105 btn-ripple"><?= __('save_changes') ?></button>
                        <a href="mygames.php" class="bg-gray-700 hover:bg-gray-600 text-white px-5 py-2 rounded-lg transition"><?= __('cancel') ?></a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Games List Table -->
        <?php if (empty($games)): ?>
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 text-center fade-up">
                <p class="text-gray-400"><?= __('no_games_added') ?></p>
                <a href="insertpage.php" class="inline-block mt-3 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg transition">➕ <?= __('add_first_game') ?></a>
            </div>
        <?php else: ?>
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden fade-up">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#1f1f28] border-b border-[#2a2a30]">
                            <tr>
                                <th class="p-3 text-sm font-semibold text-gray-300"><?= __('id') ?></th>
                                <th class="p-3 text-sm font-semibold text-gray-300"><?= __('image') ?></th>
                                <th class="p-3 text-sm font-semibold text-gray-300"><?= __('name') ?></th>
                                <th class="p-3 text-sm font-semibold text-gray-300"><?= __('publisher') ?></th>
                                <th class="p-3 text-sm font-semibold text-gray-300"><?= __('price') ?></th>
                                <?php if ($role === 'admin'): ?>
                                    <th class="p-3 text-sm font-semibold text-gray-300"><?= __('owner') ?></th>
                                <?php endif; ?>
                                <th class="p-3 text-sm font-semibold text-gray-300"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr class="border-t border-[#2a2a30] game-row transition">
                                    <td class="p-3 text-sm"><?= $game['game_id'] ?></td>
                                    <td class="p-3">
                                        <?php if (!empty($game['game_image']) && file_exists($game['game_image'])): ?>
                                            <img src="<?= htmlspecialchars($game['game_image']) ?>" class="w-12 h-12 object-cover rounded-md">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-gradient-to-br from-purple-700 to-indigo-700 rounded-md flex items-center justify-center text-xl">🎮</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 font-medium text-white"><?= htmlspecialchars($game['game_name']) ?></td>
                                    <td class="p-3 text-gray-300"><?= htmlspecialchars($game['game_publisher']) ?></td>
                                    <td class="p-3 text-blue-400 font-semibold">$<?= number_format($game['game_price'], 2) ?></td>
                                    <?php if ($role === 'admin'): ?>
                                        <td class="p-3 text-gray-400"><?= htmlspecialchars($game['publisher_name'] ?? 'Unknown') ?></td>
                                    <?php endif; ?>
                                    <td class="p-3 space-x-2">
                                        <a href="mygames.php?edit=<?= $game['game_id'] ?>" class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-md text-sm hover:bg-blue-500/30 transition inline-block"><?= __('edit') ?></a>
                                        <a href="mygames.php?delete=<?= $game['game_id'] ?>" onclick="return confirm('<?= __('confirm_delete') ?>')" class="bg-rose-500/20 text-rose-400 px-3 py-1 rounded-md text-sm hover:bg-rose-500/30 transition inline-block"><?= __('delete') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('mygames_footer') ?>
    </footer>
</div>

<script>
// Particle background
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
</script>
</body>
</html>