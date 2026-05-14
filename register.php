<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// If already logged in, redirect to homepage
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = __('all_fields_required');
        $messageClass = "bg-rose-500 text-white";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = __('invalid_email_format');
        $messageClass = "bg-rose-500 text-white";
    } elseif (strlen($password) < 6) {
        $message = __('password_min_length_register');
        $messageClass = "bg-rose-500 text-white";
    } elseif ($password !== $confirm_password) {
        $message = __('passwords_not_match');
        $messageClass = "bg-rose-500 text-white";
    } else {
        // Check if username or email already exists
        $checkStmt = mysqli_prepare($conn, "SELECT account_id FROM account_table WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($checkStmt, "ss", $username, $email);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $message = __('username_email_taken_register');
            $messageClass = "bg-rose-500 text-white";
        } else {
            // Hash password and insert new user with default values
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = mysqli_prepare($conn, "INSERT INTO account_table (username, email, password_hash, role, is_active, email_verified, preferred_language) VALUES (?, ?, ?, 'user', 1, 0, 'en')");
            mysqli_stmt_bind_param($insertStmt, "sss", $username, $email, $password_hash);

            if (mysqli_stmt_execute($insertStmt)) {
                $newUserId = mysqli_insert_id($conn);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                header("Location: index.php");
                exit;
            } else {
                $message = __('registration_failed') . ": " . mysqli_stmt_error($insertStmt);
                $messageClass = "bg-rose-500 text-white";
            }
            mysqli_stmt_close($insertStmt);
        }
        mysqli_stmt_close($checkStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('register_title') ?></title>
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
                <a href="cart.php" class="text-white hover:text-cyan-400 transition"><?= __('cart') ?></a>
                <a href="login.php" class="text-white hover:text-cyan-400 transition"><?= __('login') ?></a>
            </nav>
            <div class="language-switcher flex gap-2">
                <a href="?lang=en" class="text-xs <?= $current_lang == 'en' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">EN</a>
                <a href="?lang=ku" class="text-xs <?= $current_lang == 'ku' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">KU</a>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-12">
        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-8 fade-up">
            <h2 class="text-2xl font-bold text-white mb-2"><?= __('create_account') ?></h2>
            <p class="text-gray-400 text-sm mb-6"><?= __('register_subtitle') ?></p>

            <?php if ($message): ?>
                <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium <?= $messageClass ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('username') ?></label>
                    <input type="text" name="username" required
                           class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('email') ?></label>
                    <input type="email" name="email" required
                           class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('password_min_chars') ?></label>
                    <input type="password" name="password" required minlength="6"
                           class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('confirm_password') ?></label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 rounded-lg transition transform hover:scale-[1.02] btn-ripple">
                    <?= __('register_btn') ?>
                </button>
            </form>

            <p class="text-center text-gray-400 text-sm mt-6">
                <?= __('already_have_account') ?>
                <a href="login.php" class="text-blue-400 hover:underline"><?= __('login_here') ?></a>
            </p>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('register_footer') ?>
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