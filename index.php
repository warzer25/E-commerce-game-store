<?php
session_start();
require_once 'connection.php';
require_once 'languages/loader.php';
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('site_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; position: relative; overflow-x: hidden; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .fade-up { animation: fadeUp 0.6s ease forwards; }
        .choice-card { transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1); transform: scale(1); }
        .choice-card:hover { transform: scale(1.02); box-shadow: 0 25px 35px -12px rgba(0,0,0,0.5), 0 0 0 1px rgba(59,130,246,0.5); }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.4); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="content-wrapper">
    <header class="sticky top-0 z-50 bg-[#17171c]/80 backdrop-blur-md border-b border-[#2a2a30]">
        
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-md flex items-center justify-center text-black font-black text-lg">G</div>
                <span class="text-xl font-bold tracking-tight text-white">GameStore</span>
            </div>
            <nav class="flex gap-4 text-sm">
                <div class="language-switcher flex gap-2 mr-4">
                    <a href="?lang=en" class="text-gray-300 hover:text-white <?= $current_lang == 'en' ? 'font-bold text-blue-400' : '' ?>">EN</a>
                    <a href="?lang=ku" class="text-gray-300 hover:text-white <?= $current_lang == 'ku' ? 'font-bold text-blue-400' : '' ?>">کوردی</a>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="text-gray-300 hover:text-white transition"><?= __('profile') ?></a>
                    <a href="logout.php" class="text-gray-300 hover:text-white transition"><?= __('logout') ?></a>
                <?php else: ?>
                    <a href="login.php" class="text-gray-300 hover:text-white transition"><?= __('login') ?></a>
                    <a href="register.php" class="text-gray-300 hover:text-white transition"><?= __('register') ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-16 text-center">
        <div class="fade-up">
            <h1 class="text-5xl md:text-6xl font-extrabold bg-gradient-to-r from-blue-400 to-cyan-300 bg-clip-text text-transparent mb-4">
                <?= __('welcome') ?>
            </h1>
            <p class="text-gray-400 text-lg max-w-2xl mx-auto">
                <?= __('choose_destination') ?>
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-8 mt-12">
            <a href="games.php" class="choice-card block bg-[#17171c] rounded-2xl border border-[#2a2a30] p-8 text-center transition-all duration-300 group">
                <div class="text-7xl mb-4">🎮</div>
                <h2 class="text-2xl font-bold text-white mb-2"><?= __('video games') ?></h2>
                <p class="text-gray-400 text-sm"><?= __('buy_and_play_our_video_games') ?></p>
                <div class="mt-6 inline-flex items-center gap-2 text-blue-400 group-hover:gap-3 transition-all"><?= __('browse_store') ?> <span>→</span></div>
            </a>
            <a href="electronics.php" class="choice-card block bg-[#17171c] rounded-2xl border border-[#2a2a30] p-8 text-center transition-all duration-300 group">
                <div class="text-7xl mb-4">💻</div>
                <h2 class="text-2xl font-bold text-white mb-2"><?= __('electronics') ?></h2>
                <p class="text-gray-400 text-sm"><?= __('buy_and_use_our_electronics') ?></p>
                <div class="mt-6 inline-flex items-center gap-2 text-blue-400 group-hover:gap-3 transition-all"><?= __('shop_now') ?> <span>→</span></div>
            </a>
        </div>

        <div class="mt-16 text-gray-500 text-sm">✨ <?= __('tagline') ?> ✨</div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('copyright') ?>
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
        for (let i = 0; i < 80; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                radius: Math.random() * 3 + 1,
                speedX: (Math.random() - 0.5) * 0.4,
                speedY: (Math.random() - 0.5) * 0.2,
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

// Ripple effect for cards
document.querySelectorAll('.choice-card').forEach(card => {
    card.addEventListener('click', function(e) {
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