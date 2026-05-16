<?php
session_start();
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../languages/loader.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Stats
$userCount = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM account_table"))['total'];
$gameCount = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM game_table"))['total'];
$electronicsSold = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as total FROM order_items WHERE product_type = 'electronics'"))['total'] ?? 0;
$totalRevenue = (float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as revenue FROM orders WHERE status = 'completed'"))['revenue'] ?? 0;

// Low stock items
$lowStock = [];
$res = mysqli_query($conn, "SELECT id, name, stock FROM electronics_table WHERE stock < 5 ORDER BY stock ASC");
while ($row = mysqli_fetch_assoc($res)) $lowStock[] = $row;

// Recent orders
$recentOrders = [];
$res = mysqli_query($conn, "SELECT o.order_id, o.total_amount, o.order_date, u.username FROM orders o JOIN account_table u ON o.user_id = u.account_id ORDER BY o.order_date DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($res)) $recentOrders[] = $row;
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_dashboard_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        .stat-card { background: #17171c; border: 1px solid #2a2a30; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-3px); border-color: #3b82f6; }
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
    <header class="sticky top-0 z-50 bg-[#17171c]/90 backdrop-blur-md border-b border-[#2a2a30] shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-md flex items-center justify-center text-black font-black text-lg">G</div>
                <span class="text-xl font-bold tracking-tight text-white">GameStore</span>
            </div>
            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="../index.php" class="text-white hover:text-cyan-400 transition"><?= __('home') ?></a>
                <a href="../games.php" class="text-white hover:text-cyan-400 transition"><?= __('games') ?></a>
                <a href="../electronics.php" class="text-white hover:text-cyan-400 transition"><?= __('electronics') ?></a>
                <a href="users.php" class="text-white hover:text-cyan-400 transition"><?= __('users') ?></a>
                <a href="dashboard.php" class="text-cyan-400 font-semibold"><?= __('dashboard') ?></a>
                <a href="sales_report.php" class="text-white hover:text-cyan-400 transition"><?= __('sales_report') ?></a>
                <a href="../insertelectronics.php" class="text-white hover:text-cyan-400 transition"><?= __('add_electronics') ?></a>
                <a href="../logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
            </nav>
            <div class="language-switcher flex gap-2">
                <a href="?lang=en" class="text-xs <?= $current_lang == 'en' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">EN</a>
                <a href="?lang=ku" class="text-xs <?= $current_lang == 'ku' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">KU</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="mb-6 fade-up">
            <h1 class="text-2xl font-bold text-blue-400 flex items-center gap-2">📊 <?= __('admin_dashboard') ?></h1>
            <p class="text-gray-500 text-sm mt-1"><?= __('dashboard_desc') ?></p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8 fade-up">
            <div class="stat-card rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm"><?= __('total_users') ?></p>
                        <p class="text-3xl font-bold text-white"><?= number_format($userCount) ?></p>
                    </div>
                    <div class="text-4xl opacity-50">👥</div>
                </div>
            </div>
            <div class="stat-card rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm"><?= __('total_games') ?></p>
                        <p class="text-3xl font-bold text-white"><?= number_format($gameCount) ?></p>
                    </div>
                    <div class="text-4xl opacity-50">🎮</div>
                </div>
            </div>
            <div class="stat-card rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm"><?= __('electronics_sold') ?></p>
                        <p class="text-3xl font-bold text-white"><?= number_format($electronicsSold) ?></p>
                    </div>
                    <div class="text-4xl opacity-50">💻</div>
                </div>
            </div>
            <div class="stat-card rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm"><?= __('total_revenue') ?></p>
                        <p class="text-3xl font-bold text-green-400">$<?= number_format($totalRevenue, 2) ?></p>
                    </div>
                    <div class="text-4xl opacity-50">💰</div>
                </div>
            </div>
        </div>

        <!-- Two columns: Low Stock & Recent Orders -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 fade-up">
            <!-- Low Stock Section -->
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden">
                <div class="p-4 border-b border-[#2a2a30] bg-[#1f1f28]">
                    <h2 class="font-semibold text-yellow-400 flex items-center gap-2">⚠️ <?= __('low_stock_alert') ?></h2>
                </div>
                <div class="p-4">
                    <?php if (empty($lowStock)): ?>
                        <p class="text-gray-400 text-center py-4">✅ <?= __('no_low_stock') ?></p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($lowStock as $item): ?>
                                <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                                    <span class="text-white"><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="text-sm <?= $item['stock'] == 0 ? 'text-red-400' : 'text-yellow-400' ?>">
                                        <?= __('stock') ?>: <?= $item['stock'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden">
                <div class="p-4 border-b border-[#2a2a30] bg-[#1f1f28]">
                    <h2 class="font-semibold text-blue-400 flex items-center gap-2">🕒 <?= __('recent_orders') ?></h2>
                </div>
                <div class="p-4">
                    <?php if (empty($recentOrders)): ?>
                        <p class="text-gray-400 text-center py-4"><?= __('no_orders_yet') ?></p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                                    <div>
                                        <p class="text-white font-medium"><?= htmlspecialchars($order['username']) ?></p>
                                        <p class="text-xs text-gray-400"><?= date('M j, Y g:i A', strtotime($order['order_date'])) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-green-400 font-bold">$<?= number_format($order['total_amount'], 2) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Payments Section -->
        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-4 mt-6 fade-up">
            <h2 class="font-semibold text-blue-400 mb-3">💳 <?= __('recent_payments') ?></h2>
            <?php
            $paymentsQuery = "SELECT p.payment_id, p.card_holder, p.card_number, p.card_expiry, p.payment_date, u.username, o.order_id 
                              FROM payment_info p 
                              JOIN account_table u ON p.user_id = u.account_id 
                              LEFT JOIN orders o ON p.order_id = o.order_id
                              ORDER BY p.payment_date DESC LIMIT 10";
            $paymentsResult = mysqli_query($conn, $paymentsQuery);
            if ($paymentsResult && mysqli_num_rows($paymentsResult) > 0):
            ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-white">
                        <thead class="border-b border-gray-700">
                            <tr class="text-left">
                                <th class="py-2 text-white"><?= __('user') ?></th>
                                <th class="text-white"><?= __('card_holder') ?></th>
                                <th class="text-white"><?= __('card_number') ?></th>
                                <th class="text-white"><?= __('expiry') ?></th>
                                <th class="text-white"><?= __('order_id') ?></th>
                                <th class="text-white"><?= __('date') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pay = mysqli_fetch_assoc($paymentsResult)): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="py-2 text-white"><?= htmlspecialchars($pay['username']) ?></td>
                                    <td class="text-white"><?= htmlspecialchars($pay['card_holder']) ?></td>
                                    <td class="text-white"><?= htmlspecialchars($pay['card_number']) ?></td>
                                    <td class="text-white"><?= htmlspecialchars($pay['card_expiry']) ?></td>
                                    <td class="text-white"><?= $pay['order_id'] ?? '<span class="text-yellow-400">pending</span>' ?></td>
                                    <td class="text-white"><?= date('Y-m-d H:i', strtotime($pay['payment_date'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-400"><?= __('no_payments_yet') ?></p>
            <?php endif; ?>
        </div>

        <!-- Link to full users list -->
        <div class="mt-6 text-center fade-up">
            <a href="users.php" class="inline-block bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg transition btn-ripple">👥 <?= __('manage_all_users') ?></a>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('admin_dashboard_footer') ?>
    </footer>
</div>

<script>
// Particle background (same as before)
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
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY + window.scrollY;
    });
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