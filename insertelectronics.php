<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = '';
$messageClass = '';

// Fetch categories for dropdown
$categories = [];
$catResult = mysqli_query($conn, "SELECT id, name, icon FROM electronics_categories ORDER BY name");
while ($row = mysqli_fetch_assoc($catResult)) {
    $categories[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (int)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if (empty($name) || empty($description) || $price < 0 || $stock < 0) {
        $message = __('fill_all_fields_electronics');
        $messageClass = 'bg-rose-500 text-white';
    } else {
        // Image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/electronics/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileTmp = $_FILES['image']['tmp_name'];
            $fileName = basename($_FILES['image']['name']);
            $fileSize = $_FILES['image']['size'];
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
                $newFileName = 'elec_' . uniqid() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $destPath)) {
                    $imagePath = $destPath;
                } else {
                    $message = __('upload_failed');
                    $messageClass = 'bg-rose-500 text-white';
                }
            }
        }

        if (empty($message)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO electronics_table (name, description, price, stock, category_id, image) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiiis", $name, $description, $price, $stock, $category_id, $imagePath);
            if (mysqli_stmt_execute($stmt)) {
                $message = __('electronics_insert_success');
                $messageClass = 'bg-emerald-500 text-slate-950';
                // Clear form via redirect to avoid resubmission
                header("Location: insertelectronics.php?success=1");
                exit;
            } else {
                $message = __('insert_failed') . ': ' . mysqli_stmt_error($stmt);
                $messageClass = 'bg-rose-500 text-white';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Show success message if redirected
if (isset($_GET['success'])) {
    $message = __('electronics_insert_success');
    $messageClass = 'bg-emerald-500 text-slate-950';
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_electronics_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0f0f12; position: relative; overflow-x: hidden; }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .content-wrapper { position: relative; z-index: 1; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
        .btn-ripple { position: relative; overflow: hidden; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: rippleAnim 0.6s linear; pointer-events: none; }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.3); outline: none; }
        .image-preview { transition: all 0.2s; border: 2px dashed #3a3a44; }
        .image-preview:hover { border-color: #3b82f6; }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="content-wrapper">
    <!-- Header (consistent with other pages) -->
    <header class="sticky top-0 z-50 bg-[#17171c]/90 backdrop-blur-md border-b border-[#2a2a30] shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <a href="index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-md flex items-center justify-center text-black font-black text-lg">G</div>
                <span class="text-xl font-bold tracking-tight text-white">GameStore</span>
            </a>
            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="index.php" class="text-white hover:text-cyan-400 transition"><?= __('home') ?></a>
                <a href="games.php" class="text-white hover:text-cyan-400 transition"><?= __('games') ?></a>
                <a href="electronics.php" class="text-white hover:text-cyan-400 transition"><?= __('electronics') ?></a>
                <?php if ($_SESSION['role'] === 'publisher'): ?>
                    <a href="mygames.php" class="text-white hover:text-cyan-400 transition"><?= __('my_games') ?></a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin/users.php" class="text-yellow-300 hover:text-yellow-200 transition"><?= __('admin') ?></a>
                    <a href="insertelectronics.php" class="text-cyan-400 font-semibold"><?= __('add_electronics') ?></a>
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
        <div class="bg-[#17171c] rounded-2xl border border-[#2a2a30] overflow-hidden fade-up shadow-xl">
            <!-- Header with icon -->
            <div class="p-6 border-b border-[#2a2a30] bg-gradient-to-r from-[#1f1f28] to-[#17171c]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-600/20 rounded-xl flex items-center justify-center text-2xl">💻</div>
                    <div>
                        <h1 class="text-2xl font-bold text-white"><?= __('add_electronics_product') ?></h1>
                        <p class="text-gray-400 text-sm mt-1"><?= __('electronics_form_instruction') ?></p>
                    </div>
                </div>
            </div>

            <!-- Message display -->
            <?php if ($message): ?>
                <div class="mx-6 mt-6 rounded-xl px-4 py-3 text-sm font-medium <?= $messageClass ?> shadow-md transition-all">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6" id="productForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left column -->
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                <?= __('product_name') ?> <span class="text-rose-400">*</span>
                            </label>
                            <input type="text" name="name" required
                                   class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-xl px-4 py-2.5 text-white transition focus:border-blue-500"
                                   placeholder="e.g., Wireless Gaming Mouse">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                <?= __('price_usd') ?> <span class="text-rose-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" name="price" min="0" required
                                       class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-xl px-4 py-2.5 pl-8 text-white focus:border-blue-500"
                                       placeholder="0">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                <?= __('stock_quantity') ?> <span class="text-rose-400">*</span>
                            </label>
                            <input type="number" name="stock" min="0" required
                                   class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-xl px-4 py-2.5 text-white focus:border-blue-500"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                <?= __('category') ?>
                            </label>
                            <select name="category_id" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-xl px-4 py-2.5 text-white focus:border-blue-500">
                                <option value="">-- <?= __('select_category') ?> --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>">
                                        <?= $cat['icon'] ?? '📦' ?> <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Right column -->
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                <?= __('product_image_optional') ?>
                            </label>
                            <div class="image-preview bg-[#2a2a30] border border-[#3a3a44] rounded-xl p-3 flex flex-col items-center justify-center min-h-[140px] transition-all"
                                 id="imagePreviewArea">
                                <div class="text-5xl mb-2 opacity-50">🖼️</div>
                                <p class="text-xs text-gray-500 text-center"><?= __('image_format_hint') ?></p>
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 text-sm text-gray-300 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-500 transition" id="imageInput">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full width description -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        <?= __('description') ?> <span class="text-rose-400">*</span>
                    </label>
                    <textarea name="description" rows="5" required
                              class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-xl px-4 py-2.5 text-white focus:border-blue-500 resize-y"
                              placeholder="<?= __('describe_product') ?>"></textarea>
                </div>

                <!-- Action buttons -->
                <div class="flex flex-wrap gap-4 pt-4 border-t border-[#2a2a30]">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-8 py-2.5 rounded-xl transition transform hover:scale-105 btn-ripple shadow-lg">
                        ➕ <?= __('insert_product') ?>
                    </button>
                    <button type="reset" class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-2.5 rounded-xl transition btn-ripple">
                        🔄 <?= __('reset') ?>
                    </button>
                    <a href="electronics.php" class="bg-[#2a2a30] hover:bg-[#3a3a44] text-gray-300 px-6 py-2.5 rounded-xl transition inline-flex items-center gap-2">
                        ← <?= __('back_to_shop') ?>
                    </a>
                </div>
            </form>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('add_game_footer') ?> — <?= __('admin_panel') ?>
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
        for (let i = 0; i < 80; i++) {
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
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 100) {
                const angle = Math.atan2(dy, dx);
                const force = (100 - dist) / 500;
                p.x += Math.cos(angle) * force;
                p.y += Math.sin(angle) * force;
            }

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(139, 92, 246, ${p.alpha})`; // purple tint for electronics
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

// Image preview
const imageInput = document.getElementById('imageInput');
const previewArea = document.getElementById('imagePreviewArea');
if (imageInput) {
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                previewArea.innerHTML = `
                    <img src="${event.target.result}" class="max-h-32 rounded-lg object-contain mb-2">
                    <p class="text-xs text-green-400">✓ ${file.name}</p>
                    <button type="button" id="removeImagePreview" class="text-xs text-rose-400 mt-1 hover:underline">${__('change_image') || 'Change'}</button>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="hidden" id="imageInputReplacement">
                `;
                const removeBtn = document.getElementById('removeImagePreview');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        previewArea.innerHTML = `
                            <div class="text-5xl mb-2 opacity-50">🖼️</div>
                            <p class="text-xs text-gray-500 text-center">JPG, PNG, WEBP up to 2MB</p>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 text-sm text-gray-300 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-500 transition" id="imageInput">
                        `;
                        document.getElementById('imageInput')?.addEventListener('change', arguments.callee);
                    });
                }
            };
            reader.readAsDataURL(file);
        }
    });
}

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

// Simple translation for "Change" if needed
function __(key) {
    const translations = {
        'change_image': 'Change image'
    };
    return translations[key] || key;
}
</script>
</body>
</html>