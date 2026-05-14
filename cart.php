<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ADD ITEM
if (isset($_POST['add_to_cart'])) {
    $game_id = (int)$_POST['game_id'];
    $stmt = mysqli_prepare($conn, "SELECT game_name, game_price FROM game_table WHERE game_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $game_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $game = mysqli_fetch_assoc($result);
    if ($game) {
        if (!isset($_SESSION['cart'][$game_id])) {
            $_SESSION['cart'][$game_id] = [
                'name' => $game['game_name'],
                'price' => (float)$game['game_price'],
                'qty' => 1
            ];
        } else {
            $_SESSION['cart'][$game_id]['qty']++;
        }
    }
    mysqli_stmt_close($stmt);
    header("Location: cart.php");
    exit;
}

// UPDATE QUANTITY
if (isset($_POST['update_qty'])) {
    $game_id = (int)$_POST['game_id'];
    $new_qty = max(1, (int)$_POST['qty']);
    if (isset($_SESSION['cart'][$game_id])) {
        $_SESSION['cart'][$game_id]['qty'] = $new_qty;
    }
    header("Location: cart.php");
    exit;
}

// REMOVE ITEM
if (isset($_GET['remove'])) {
    $game_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$game_id]);
    header("Location: cart.php");
    exit;
}

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['qty'];
}
$cartCount = count($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('cart') ?> | GameStore</title>
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
        .cart-item { transition: background 0.2s; }
        .cart-item:hover { background: rgba(44,125,160,0.1); }
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
                <a href="index.php" class="hover:text-cyan-400 transition text-white"><?= __('store') ?></a>
                <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['publisher','admin'])): ?>
                    <a href="insertpage.php" class="hover:text-cyan-400 transition text-white"><?= __('add_game') ?></a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'publisher'): ?>
                    <a href="mygames.php" class="hover:text-cyan-400 transition text-white"><?= __('my_games') ?></a>
                <?php endif; ?>
                <a href="cart.php" class="text-cyan-400 transition"><?= __('cart') ?></a>
                <a href="profile.php" class="hover:text-cyan-400 transition text-white"><?= __('profile') ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="admin/users.php" class="text-yellow-300 hover:text-yellow-200 transition"><?= __('admin') ?></a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-600/80 text-white px-3 py-1 rounded-md hover:bg-blue-500 transition"><?= __('login') ?></a>
                <?php endif; ?>
                <div class="language-switcher flex gap-2">
                    <a href="?lang=en" class="text-xs text-gray-400 hover:text-white">EN</a>
                    <a href="?lang=ku" class="text-xs text-gray-400 hover:text-white">KU</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden fade-up">
            <div class="p-6 border-b border-[#2a2a30]">
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">🛒 <?= __('cart') ?> <span class="text-sm bg-gray-700 text-white px-2 py-0.5 rounded-full"><?= $cartCount ?> <?= __('items') ?></span></h1>
            </div>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="p-12 text-center">
                    <p class="text-gray-400 text-lg mb-4"><?= __('empty_cart') ?></p>
                    <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-md transition transform hover:scale-105 btn-ripple"><?= __('continue_shopping') ?> →</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#1f1f28] border-b border-[#2a2a30]">
                            <tr>
                                <th class="p-4 text-white"><?= __('game') ?></th>
                                <th class="p-4 text-white"><?= __('price') ?></th>
                                <th class="p-4 text-white"><?= __('quantity') ?></th>
                                <th class="p-4 text-white"><?= __('subtotal') ?></th>
                                <th class="p-4 text-white"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                                <tr class="cart-item border-b border-[#2a2a30]">
                                    <td class="p-4 font-medium text-white"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="p-4 text-white">$<?= number_format($item['price'], 2) ?></td>
                                    <td class="p-4">
                                        <form method="POST" action="cart.php" class="flex items-center gap-2">
                                            <input type="hidden" name="game_id" value="<?= $id ?>">
                                            <input type="number" name="qty" value="<?= $item['qty'] ?>" min="1" class="w-20 bg-[#2a2a30] border border-[#3a3a44] rounded-md px-2 py-1 text-white">
                                            <button type="submit" name="update_qty" class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded-md transition btn-ripple"><?= __('update') ?></button>
                                        </form>
                                    </td>
                                    <td class="p-4 text-blue-400 font-semibold">$<?= number_format($item['price'] * $item['qty'], 2) ?></td>
                                    <td class="p-4">
                                        <a href="cart.php?remove=<?= $id ?>" onclick="return confirm('<?= __('remove') ?>?')" class="text-red-400 hover:text-red-300 transition">✖ <?= __('remove') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-6 bg-[#1a1a22] border-t border-[#2a2a30] flex flex-wrap justify-between items-center gap-4">
                    <div class="text-lg">
                        <span class="text-gray-400"><?= __('total') ?>:</span>
                        <span class="text-2xl font-bold text-blue-400">$<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="flex gap-3">
                        <a href="index.php" class="bg-gray-700 hover:bg-gray-600 text-white px-5 py-2 rounded-md transition transform hover:scale-105 btn-ripple">← <?= __('continue_shopping') ?></a>
                        <form method="POST" action="checkout.php">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-2 rounded-md transition transform hover:scale-105 btn-ripple"><?= __('proceed_checkout') ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        © 2026 GameStore – <?= __('secure_checkout') ?>
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