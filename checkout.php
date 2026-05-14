<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageClass = '';
$purchasedGames = [];
$skippedGames = [];
$error = false;
$success = false;

if (!empty($_SESSION['cart'])) {
    mysqli_begin_transaction($conn);
    
    $totalAmount = 0;
    $itemsToInsert = [];
    
    foreach ($_SESSION['cart'] as $game_id => $item) {
        $game_id = (int)$game_id;
        $checkGame = mysqli_prepare($conn, "SELECT game_id, game_name, game_price FROM game_table WHERE game_id = ?");
        mysqli_stmt_bind_param($checkGame, "i", $game_id);
        mysqli_stmt_execute($checkGame);
        $gameResult = mysqli_stmt_get_result($checkGame);
        $game = mysqli_fetch_assoc($gameResult);
        mysqli_stmt_close($checkGame);
        
        if (!$game) {
            $error = true;
            $message = __('game_not_exists') . " ID $game_id";
            $messageClass = "bg-rose-500 text-white";
            break;
        }
        
        // Check if already owned
        $checkOwn = mysqli_prepare($conn, "SELECT id FROM user_inventory WHERE user_id = ? AND game_id = ?");
        mysqli_stmt_bind_param($checkOwn, "ii", $user_id, $game_id);
        mysqli_stmt_execute($checkOwn);
        $ownRes = mysqli_stmt_get_result($checkOwn);
        if (mysqli_num_rows($ownRes) > 0) {
            $skippedGames[] = $game['game_name'];
            mysqli_stmt_close($checkOwn);
            continue;
        }
        mysqli_stmt_close($checkOwn);
        
        $totalAmount += $game['game_price'] * $item['qty'];
        $itemsToInsert[] = [
            'type' => 'game',
            'id' => $game_id,
            'name' => $game['game_name'],
            'qty' => $item['qty'],
            'price' => $game['game_price']
        ];
    }
    
    if (!$error && !empty($itemsToInsert)) {
        // Insert order
        $orderStmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'completed')");
        mysqli_stmt_bind_param($orderStmt, "id", $user_id, $totalAmount);
        if (mysqli_stmt_execute($orderStmt)) {
            $order_id = mysqli_insert_id($conn);
            mysqli_stmt_close($orderStmt);
            
            foreach ($itemsToInsert as $item) {
                // Order item
                $itemStmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_type, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($itemStmt, "isissd", $order_id, $item['type'], $item['id'], $item['name'], $item['qty'], $item['price']);
                mysqli_stmt_execute($itemStmt);
                mysqli_stmt_close($itemStmt);
                
                // User inventory
                $invStmt = mysqli_prepare($conn, "INSERT INTO user_inventory (user_id, game_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($invStmt, "ii", $user_id, $item['id']);
                mysqli_stmt_execute($invStmt);
                mysqli_stmt_close($invStmt);
                
                $purchasedGames[] = $item['name'];
            }
        } else {
            $error = true;
            $message = __('order_insert_failed');
            $messageClass = "bg-rose-500 text-white";
        }
    }
    
    if ($error) {
        mysqli_rollback($conn);
    } else {
        mysqli_commit($conn);
        $_SESSION['cart'] = [];
        $success = true;
        
        if (!empty($purchasedGames) && !empty($skippedGames)) {
            $message = __('purchase_success_with_skipped') . " " . implode(', ', $skippedGames);
            $messageClass = "bg-yellow-500 text-slate-950";
        } elseif (!empty($purchasedGames)) {
            $message = __('purchase_success');
            $messageClass = "bg-emerald-500 text-slate-950";
        } elseif (!empty($skippedGames)) {
            $message = __('all_games_already_owned') . " " . implode(', ', $skippedGames);
            $messageClass = "bg-amber-500 text-slate-950";
        } else {
            $message = __('cart_empty');
            $messageClass = "bg-amber-500 text-slate-950";
        }
    }
} else {
    $message = __('cart_empty');
    $messageClass = "bg-amber-500 text-slate-950";
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('checkout_title') ?> | GameStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
        .btn-ripple { position: relative; overflow: hidden; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="content-wrapper">
    <header class="sticky top-0 z-50 bg-[#17171c] border-b border-[#2a2a30] shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="index.php" class="text-xl font-bold text-white">GameStore</a>
            <nav class="flex gap-5 text-sm">
                <a href="index.php" class="hover:text-cyan-400 transition"><?= __('store') ?></a>
                <a href="cart.php" class="hover:text-cyan-400 transition"><?= __('cart') ?></a>
                <a href="profile.php" class="hover:text-cyan-400 transition"><?= __('profile') ?></a>
                <a href="logout.php" class="hover:text-cyan-400 transition"><?= __('logout') ?></a>
                <div class="language-switcher flex gap-2">
                    <a href="?lang=en" class="text-xs text-gray-400 hover:text-white">EN</a>
                    <a href="?lang=ku" class="text-xs text-gray-400 hover:text-white">KU</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-12">
        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 text-center fade-up">
            <div class="mb-6">
                <?php if ($success): ?>
                    <div class="text-6xl mb-4"><?= !empty($skippedGames) ? '⚠️' : '🎉' ?></div>
                    <h2 class="text-2xl font-bold <?= !empty($skippedGames) ? 'text-yellow-400' : 'text-emerald-400' ?>">
                        <?= !empty($skippedGames) ? __('partial_success') : __('thank_you') ?>
                    </h2>
                <?php else: ?>
                    <div class="text-6xl mb-4">❌</div>
                    <h2 class="text-2xl font-bold text-rose-400"><?= __('checkout_failed') ?></h2>
                <?php endif; ?>
                <div class="mt-4 p-3 rounded-lg <?= $messageClass ?> inline-block px-6">
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>

            <?php if (!empty($purchasedGames)): ?>
                <div class="mt-6 text-left bg-[#1f1f28] rounded-lg p-4">
                    <h3 class="font-semibold text-lg mb-3"><?= __('purchased_games') ?>:</h3>
                    <ul class="space-y-2">
                        <?php foreach ($purchasedGames as $name): ?>
                            <li class="border-b border-gray-700 pb-2">✅ <?= htmlspecialchars($name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($skippedGames)): ?>
                <div class="mt-4 text-left bg-yellow-900/30 border border-yellow-600 rounded-lg p-4">
                    <h3 class="font-semibold text-yellow-300 mb-2">⚠️ <?= __('already_owned_warning') ?></h3>
                    <p class="text-yellow-200 text-sm"><?= __('these_games_skipped') ?>:</p>
                    <ul class="list-disc pl-5 mt-1 text-yellow-100">
                        <?php foreach ($skippedGames as $name): ?>
                            <li><?= htmlspecialchars($name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="mt-8 flex justify-center gap-4">
                <a href="index.php" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-md transition transform hover:scale-105 btn-ripple"><?= __('continue_shopping') ?></a>
                <a href="cart.php" class="bg-gray-700 hover:bg-gray-600 text-white px-5 py-2 rounded-md transition"><?= __('back_to_cart') ?></a>
            </div>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('copyright_checkout') ?>
    </footer>
</div>

<script>
(function() {
    const canvas = document.getElementById('particleCanvas');
    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];
    function initParticles() {
        for (let i = 0; i < 60; i++) {
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
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(44, 125, 160, ${p.alpha})`;
            ctx.fill();
        }
        requestAnimationFrame(drawParticles);
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    drawParticles();
})();

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