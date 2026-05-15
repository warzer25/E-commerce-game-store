<?php
session_start();
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../languages/loader.php';

// Only admin allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$report_data = [];
$total_quantity = 0;
$total_revenue = 0;

if ($selected_year && $selected_month) {
    // Calculate first and last day of selected month
    $start_date = "$selected_year-$selected_month-01 00:00:00";
    $end_date = date("Y-m-t 23:59:59", strtotime($start_date));
    
    // Query electronics sales for the selected month
    $stmt = mysqli_prepare($conn, "
        SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.price * oi.quantity) as total_amount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.product_type = 'electronics'
        AND o.order_date BETWEEN ? AND ?
        GROUP BY oi.product_id, oi.product_name
        ORDER BY total_qty DESC
    ");
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
        $total_quantity += $row['total_qty'];
        $total_revenue += $row['total_amount'];
    }
    mysqli_stmt_close($stmt);
}

// Get available years for dropdown (from orders table)
$years = [];
$yearQuery = "SELECT DISTINCT YEAR(order_date) as yr FROM orders ORDER BY yr DESC";
$yearResult = mysqli_query($conn, $yearQuery);
while ($row = mysqli_fetch_assoc($yearResult)) {
    $years[] = $row['yr'];
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('sales_report_title') ?> | GameStore Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        .report-card { background: #17171c; border: 1px solid #2a2a30; transition: all 0.2s; }
        .report-card:hover { border-color: #3b82f6; }
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
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="users.php" class="text-white hover:text-cyan-400 transition"><?= __('users') ?></a>
                    <a href="dashboard.php" class="text-white hover:text-cyan-400 transition"><?= __('dashboard') ?></a>
                    <a href="sales_report.php" class="text-cyan-400 font-semibold"><?= __('sales_report') ?></a>
                    <a href="../insertelectronics.php" class="text-white hover:text-cyan-400 transition"><?= __('add_electronics') ?></a>
                <?php endif; ?>
                <a href="../cart.php" class="text-white hover:text-cyan-400 transition"><?= __('cart') ?></a>
                <a href="../profile.php" class="text-white hover:text-cyan-400 transition"><?= __('profile') ?></a>
                <a href="../logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
            </nav>
            <div class="language-switcher flex gap-2">
                <a href="?lang=en" class="text-xs <?= $current_lang == 'en' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">EN</a>
                <a href="?lang=ku" class="text-xs <?= $current_lang == 'ku' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">KU</a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <div class="mb-6 fade-up">
            <h1 class="text-2xl font-bold text-blue-400 flex items-center gap-2">📊 <?= __('sales_report') ?></h1>
            <p class="text-gray-500 text-sm mt-1"><?= __('sales_report_desc') ?></p>
        </div>

        <!-- Filter Form -->
        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-5 mb-6 fade-up">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-1"><?= __('year') ?></label>
                    <select name="year" class="bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white">
                        <?php foreach ($years as $yr): ?>
                            <option value="<?= $yr ?>" <?= $yr == $selected_year ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($years)): ?>
                            <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1"><?= __('month') ?></label>
                    <select name="month" class="bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $selected_month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg transition btn-ripple">🔍 <?= __('generate_report') ?></button>
                </div>
            </form>
        </div>

        <!-- Report Results -->
        <?php if (!empty($report_data)): ?>
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden fade-up">
                <div class="p-4 border-b border-[#2a2a30] bg-[#1f1f28] flex justify-between items-center flex-wrap gap-3">
                    <h2 class="font-semibold text-white">📅 <?= __('sales_for') ?> <?= date('F Y', strtotime("$selected_year-$selected_month-01")) ?></h2>
                    <div class="flex gap-4 text-sm">
                        <span class="text-gray-300"><?= __('total_items_sold') ?>: <strong class="text-blue-400"><?= $total_quantity ?></strong></span>
                        <span class="text-gray-300"><?= __('total_revenue') ?>: <strong class="text-green-400">$<?= number_format($total_revenue, 2) ?></strong></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#1f1f28] border-b border-[#2a2a30]">
                            <tr>
                                <th class="p-3 text-white"><?= __('product') ?></th>
                                <th class="p-3 text-white"><?= __('quantity_sold') ?></th>
                                <th class="p-3 text-white"><?= __('revenue') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $item): ?>
                                <tr class="border-t border-[#2a2a30]">
                                    <td class="p-3 text-white"><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="p-3 text-white"><?= $item['total_qty'] ?></td>
                                    <td class="p-3 text-green-400 font-semibold">$<?= number_format($item['total_amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_year && $selected_month): ?>
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 text-center fade-up">
                <p class="text-gray-400"><?= __('no_sales_for_month') ?></p>
                <a href="dashboard.php" class="inline-block mt-3 text-blue-400 hover:underline">← <?= __('back_to_dashboard') ?></a>
            </div>
        <?php else: ?>
            <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 text-center fade-up">
                <p class="text-gray-400"><?= __('select_month_to_view') ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="dashboard.php" class="text-gray-400 hover:text-white transition">← <?= __('back_to_dashboard') ?></a>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('admin_footer') ?>
    </footer>
</div>

<script>
// Particle background (same as dashboard)
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