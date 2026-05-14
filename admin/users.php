<?php
session_start();

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../languages/loader.php';

// Only admin allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageClass = '';

// ----- DELETE USER -----
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    if ($user_id == $_SESSION['user_id']) {
        $message = __('cannot_delete_self');
        $messageClass = "bg-rose-500 text-white";
    } else {
        $delStmt = mysqli_prepare($conn, "DELETE FROM account_table WHERE account_id = ?");
        mysqli_stmt_bind_param($delStmt, "i", $user_id);
        if (mysqli_stmt_execute($delStmt)) {
            $message = __('user_deleted_success');
            $messageClass = "bg-emerald-500 text-slate-950";
        } else {
            $message = __('delete_failed') . ": " . mysqli_stmt_error($delStmt);
            $messageClass = "bg-rose-500 text-white";
        }
        mysqli_stmt_close($delStmt);
    }
}

// ----- TOGGLE ACTIVE STATUS -----
if (isset($_GET['toggle_active']) && is_numeric($_GET['toggle_active'])) {
    $user_id = (int)$_GET['toggle_active'];
    if ($user_id == $_SESSION['user_id']) {
        $message = __('cannot_suspend_self');
        $messageClass = "bg-rose-500 text-white";
    } else {
        $statusStmt = mysqli_prepare($conn, "SELECT is_active FROM account_table WHERE account_id = ?");
        mysqli_stmt_bind_param($statusStmt, "i", $user_id);
        mysqli_stmt_execute($statusStmt);
        $statusRes = mysqli_stmt_get_result($statusStmt);
        $userStatus = mysqli_fetch_assoc($statusRes);
        mysqli_stmt_close($statusStmt);
        if ($userStatus) {
            $newStatus = $userStatus['is_active'] ? 0 : 1;
            $updateStmt = mysqli_prepare($conn, "UPDATE account_table SET is_active = ? WHERE account_id = ?");
            mysqli_stmt_bind_param($updateStmt, "ii", $newStatus, $user_id);
            if (mysqli_stmt_execute($updateStmt)) {
                $message = $newStatus ? __('user_activated') : __('user_suspended');
                $messageClass = "bg-emerald-500 text-slate-950";
            } else {
                $message = __('action_failed');
                $messageClass = "bg-rose-500 text-white";
            }
            mysqli_stmt_close($updateStmt);
        }
    }
}

// ----- UPDATE ROLE -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    $allowed_roles = ['user', 'publisher', 'admin'];
    if (in_array($new_role, $allowed_roles)) {
        if ($user_id == $_SESSION['user_id'] && $new_role !== 'admin') {
            $message = __('cannot_demote_self');
            $messageClass = "bg-rose-500 text-white";
        } else {
            $roleStmt = mysqli_prepare($conn, "UPDATE account_table SET role = ? WHERE account_id = ?");
            mysqli_stmt_bind_param($roleStmt, "si", $new_role, $user_id);
            if (mysqli_stmt_execute($roleStmt)) {
                $message = __('role_updated_success');
                $messageClass = "bg-emerald-500 text-slate-950";
            } else {
                $message = __('role_update_failed');
                $messageClass = "bg-rose-500 text-white";
            }
            mysqli_stmt_close($roleStmt);
        }
    }
}

// ----- SEARCH & PAGINATION -----
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$whereClause = "";
$params = [];
$types = "";
if ($search !== '') {
    $whereClause = "WHERE username LIKE ? OR email LIKE ?";
    $like = "%$search%";
    $params = [$like, $like];
    $types = "ss";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM account_table $whereClause";
$countStmt = mysqli_prepare($conn, $countSql);
if ($whereClause) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$countRes = mysqli_stmt_get_result($countStmt);
$totalUsers = mysqli_fetch_assoc($countRes)['total'];
$totalPages = ceil($totalUsers / $limit);
mysqli_stmt_close($countStmt);

// Fetch users
$sql = "SELECT account_id, username, email, role, is_active, last_login, created_at FROM account_table $whereClause ORDER BY account_id ASC LIMIT ? OFFSET ?";
$fetchStmt = mysqli_prepare($conn, $sql);
if ($whereClause) {
    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes = $types . "ii";
    mysqli_stmt_bind_param($fetchStmt, $allTypes, ...$allParams);
} else {
    mysqli_stmt_bind_param($fetchStmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($fetchStmt);
$usersResult = mysqli_stmt_get_result($fetchStmt);
$users = [];
while ($row = mysqli_fetch_assoc($usersResult)) {
    $users[] = $row;
}
mysqli_stmt_close($fetchStmt);
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_users_title') ?></title>
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
        .user-row:hover { background: rgba(59,130,246,0.05); }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .status-active { background: rgba(16,185,129,0.2); color: #34d399; }
        .status-suspended { background: rgba(244,63,94,0.2); color: #f87171; }
        .role-badge { padding: 0.25rem 0.5rem; border-radius: 0.5rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }
        .role-admin { background: #f59e0b20; color: #fbbf24; }
        .role-publisher { background: #3b82f620; color: #60a5fa; }
        .role-user { background: #6b728020; color: #9ca3af; }
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
                <?php if ($_SESSION['role'] === 'publisher'): ?>
                    <a href="../mygames.php" class="text-white hover:text-cyan-400 transition"><?= __('my_games') ?></a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="users.php" class="text-cyan-400 font-semibold"><?= __('admin_panel') ?></a>
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

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-wrap justify-between items-center gap-4 mb-6 fade-up">
            <div>
                <h1 class="text-2xl font-bold text-blue-400 flex items-center gap-2">👥 <?= __('user_management') ?></h1>
                <p class="text-gray-500 text-sm mt-1"><?= __('manage_users_desc') ?></p>
            </div>
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_users_placeholder') ?>" class="form-input bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 text-white w-64 focus:outline-none focus:border-blue-500">
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg font-semibold transition btn-ripple">🔍 <?= __('search') ?></button>
                <?php if ($search): ?>
                    <a href="users.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">🗑️ <?= __('clear') ?></a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium <?= $messageClass ?> fade-up shadow-md">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] overflow-hidden fade-up shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-[#1f1f28] border-b border-[#2a2a30]">
                        <tr>
                            <th class="p-3 text-sm font-semibold text-gray-300">ID</th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('username') ?></th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('email') ?></th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('role') ?></th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('status') ?></th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('last_login') ?></th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('joined') ?></th>
                            <th class="p-3 text-sm font-semibold text-gray-300"><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">
                                    <?= $search ? __('no_users_found_search') : __('no_users_found') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="border-t border-[#2a2a30] user-row transition">
                                    <td class="p-3 text-sm text-gray-300"><?= $user['account_id'] ?></td>
                                    <td class="p-3 font-medium text-white">
                                        <a href="../profile.php?id=<?= $user['account_id'] ?>" class="hover:text-blue-400 transition flex items-center gap-1">
                                            <?= htmlspecialchars($user['username']) ?>
                                            <span class="text-xs text-gray-500">↗</span>
                                        </a>
                                    </td>
                                    <td class="p-3 text-gray-300"><?= htmlspecialchars($user['email']) ?></td>
                                    <td class="p-3">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="user_id" value="<?= $user['account_id'] ?>">
                                            <select name="role" class="bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-2 py-1 text-sm text-white focus:outline-none focus:border-blue-500">
                                                <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>><?= __('user_role') ?></option>
                                                <option value="publisher" <?= $user['role'] == 'publisher' ? 'selected' : '' ?>><?= __('publisher_role') ?></option>
                                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>><?= __('admin_role') ?></option>
                                            </select>
                                            <button type="submit" name="update_role" class="bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 px-2 py-1 rounded-lg text-xs transition btn-ripple">💾 <?= __('save') ?></button>
                                        </form>
                                    </td>
                                    <td class="p-3">
                                        <?php if ($user['is_active']): ?>
                                            <span class="status-badge status-active">✅ <?= __('active') ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-suspended">⛔ <?= __('suspended') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-sm text-gray-400">
                                        <?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : __('never') ?>
                                    </td>
                                    <td class="p-3 text-sm text-gray-400">
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td class="p-3 space-x-2">
                                        <a href="../profile.php?id=<?= $user['account_id'] ?>" class="inline-block bg-gray-700 hover:bg-gray-600 text-white px-2 py-1 rounded-md text-xs transition" title="<?= __('view_profile') ?>">👁️</a>
                                        <?php if ($user['account_id'] != $_SESSION['user_id']): ?>
                                            <a href="?toggle_active=<?= $user['account_id'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" class="inline-block <?= $user['is_active'] ? 'bg-yellow-600/20 hover:bg-yellow-600/30 text-yellow-400' : 'bg-green-600/20 hover:bg-green-600/30 text-green-400' ?> px-2 py-1 rounded-md text-xs transition" onclick="return confirm('<?= __('toggle_active_confirm') ?>')">
                                                <?= $user['is_active'] ? '🔒' : '🔓' ?>
                                            </a>
                                            <a href="?delete=<?= $user['account_id'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" class="inline-block bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 px-2 py-1 rounded-md text-xs transition" onclick="return confirm('<?= __('delete_user_confirm') ?>')">
                                                🗑️
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-600 text-xs"><?= __('current_user') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center gap-2 mt-6 fade-up">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition">← <?= __('previous') ?></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-md transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600 text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition"><?= __('next') ?> →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('admin_footer') ?>
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