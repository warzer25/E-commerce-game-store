<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$logged_in_role = $_SESSION['role'];

// Determine which profile to show (if admin has ?id= parameter)
$profile_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $logged_in_user_id;

// If not admin and trying to view another profile, redirect to own profile
if ($logged_in_role !== 'admin' && $profile_user_id !== $logged_in_user_id) {
    header("Location: profile.php");
    exit;
}

// Fetch user data
$user = null;
$owned_games = [];
$owned_electronics = [];

$stmt = mysqli_prepare($conn, "SELECT account_id, username, email, role, profile_picture, bio, location, website, last_login, is_active, created_at, preferred_language FROM account_table WHERE account_id = ?");
mysqli_stmt_bind_param($stmt, "i", $profile_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo __('user_not_found');
    exit;
}

// Fetch owned GAMES
$gamesStmt = mysqli_prepare($conn, "
    SELECT g.game_id, g.game_name, g.game_publisher, g.game_price, g.game_image, ui.purchase_date 
    FROM user_inventory ui 
    JOIN game_table g ON ui.game_id = g.game_id 
    WHERE ui.user_id = ? 
    ORDER BY ui.purchase_date DESC
");
mysqli_stmt_bind_param($gamesStmt, "i", $profile_user_id);
mysqli_stmt_execute($gamesStmt);
$gamesResult = mysqli_stmt_get_result($gamesStmt);
while ($row = mysqli_fetch_assoc($gamesResult)) {
    $owned_games[] = $row;
}
mysqli_stmt_close($gamesStmt);

// Fetch owned ELECTRONICS
$elecStmt = mysqli_prepare($conn, "
    SELECT e.id, e.name, e.price, e.image, uei.purchase_date 
    FROM user_electronics_inventory uei 
    JOIN electronics_table e ON uei.product_id = e.id 
    WHERE uei.user_id = ? 
    ORDER BY uei.purchase_date DESC
");
mysqli_stmt_bind_param($elecStmt, "i", $profile_user_id);
mysqli_stmt_execute($elecStmt);
$elecResult = mysqli_stmt_get_result($elecStmt);
while ($row = mysqli_fetch_assoc($elecResult)) {
    $owned_electronics[] = $row;
}
mysqli_stmt_close($elecStmt);

// Handle profile update (edit form)
$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Only allow editing own profile or admin editing any
    if ($profile_user_id !== $logged_in_user_id && $logged_in_role !== 'admin') {
        $message = __('cannot_edit_profile');
        $messageClass = "bg-rose-500 text-white";
    } else {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        $new_bio = trim($_POST['bio']);
        $new_location = trim($_POST['location']);
        $new_website = trim($_POST['website']);
        $new_language = trim($_POST['preferred_language']);

        // Optional: role change only for admin
        $new_role = $user['role'];
        $new_active = $user['is_active'];
        if ($logged_in_role === 'admin' && isset($_POST['role']) && $profile_user_id !== $logged_in_user_id) {
            $allowed_roles = ['user', 'publisher', 'admin'];
            if (in_array($_POST['role'], $allowed_roles)) {
                $new_role = $_POST['role'];
            }
        }
        if ($logged_in_role === 'admin' && isset($_POST['is_active']) && $profile_user_id !== $logged_in_user_id) {
            $new_active = (int)$_POST['is_active'];
        }

        if (empty($new_username) || empty($new_email)) {
            $message = __('username_email_required');
            $messageClass = "bg-rose-500 text-white";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $message = __('invalid_email');
            $messageClass = "bg-rose-500 text-white";
        } else {
            $checkStmt = mysqli_prepare($conn, "SELECT account_id FROM account_table WHERE (username = ? OR email = ?) AND account_id != ?");
            mysqli_stmt_bind_param($checkStmt, "ssi", $new_username, $new_email, $profile_user_id);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $message = __('username_email_taken');
                $messageClass = "bg-rose-500 text-white";
            } else {
                $updateStmt = mysqli_prepare($conn, "UPDATE account_table SET username=?, email=?, bio=?, location=?, website=?, preferred_language=?, role=?, is_active=? WHERE account_id=?");
                mysqli_stmt_bind_param($updateStmt, "sssssssii", $new_username, $new_email, $new_bio, $new_location, $new_website, $new_language, $new_role, $new_active, $profile_user_id);
                if (mysqli_stmt_execute($updateStmt)) {
                    $message = __('profile_updated');
                    $messageClass = "bg-emerald-500 text-slate-950";
                    if ($profile_user_id === $logged_in_user_id) {
                        $_SESSION['username'] = $new_username;
                        $_SESSION['role'] = $new_role;
                    }
                    $user['username'] = $new_username;
                    $user['email'] = $new_email;
                    $user['bio'] = $new_bio;
                    $user['location'] = $new_location;
                    $user['website'] = $new_website;
                    $user['preferred_language'] = $new_language;
                    $user['role'] = $new_role;
                    $user['is_active'] = $new_active;
                } else {
                    $message = __('update_failed') . ": " . mysqli_stmt_error($updateStmt);
                    $messageClass = "bg-rose-500 text-white";
                }
                mysqli_stmt_close($updateStmt);
            }
            mysqli_stmt_close($checkStmt);
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if ($profile_user_id !== $logged_in_user_id && $logged_in_role !== 'admin') {
        $message = __('cannot_change_avatar');
        $messageClass = "bg-rose-500 text-white";
    } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileTmp = $_FILES['avatar']['tmp_name'];
        $fileName = basename($_FILES['avatar']['name']);
        $fileSize = $_FILES['avatar']['size'];
        $fileType = mime_content_type($fileTmp);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($fileType, $allowedTypes)) {
            $message = __('invalid_image_type');
            $messageClass = "bg-rose-500 text-white";
        } elseif ($fileSize > $maxSize) {
            $message = __('image_too_large');
            $messageClass = "bg-rose-500 text-white";
        } else {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = 'avatar_' . $profile_user_id . '_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmp, $destPath)) {
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
                $updateStmt = mysqli_prepare($conn, "UPDATE account_table SET profile_picture = ? WHERE account_id = ?");
                mysqli_stmt_bind_param($updateStmt, "si", $destPath, $profile_user_id);
                if (mysqli_stmt_execute($updateStmt)) {
                    $user['profile_picture'] = $destPath;
                    $message = __('avatar_updated');
                    $messageClass = "bg-emerald-500 text-slate-950";
                } else {
                    $message = __('avatar_save_failed');
                    $messageClass = "bg-rose-500 text-white";
                }
                mysqli_stmt_close($updateStmt);
            } else {
                $message = __('upload_failed');
                $messageClass = "bg-rose-500 text-white";
            }
        }
    } else {
        $message = __('select_image');
        $messageClass = "bg-rose-500 text-white";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if ($profile_user_id !== $logged_in_user_id && $logged_in_role !== 'admin') {
        $message = __('cannot_change_password');
        $messageClass = "bg-rose-500 text-white";
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $message = __('password_fields_required');
            $messageClass = "bg-rose-500 text-white";
        } elseif (strlen($new) < 6) {
            $message = __('password_min_length');
            $messageClass = "bg-rose-500 text-white";
        } elseif ($new !== $confirm) {
            $message = __('password_mismatch');
            $messageClass = "bg-rose-500 text-white";
        } else {
            $passStmt = mysqli_prepare($conn, "SELECT password_hash FROM account_table WHERE account_id = ?");
            mysqli_stmt_bind_param($passStmt, "i", $profile_user_id);
            mysqli_stmt_execute($passStmt);
            $passResult = mysqli_stmt_get_result($passStmt);
            $passRow = mysqli_fetch_assoc($passResult);
            mysqli_stmt_close($passStmt);
            if (password_verify($current, $passRow['password_hash'])) {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $updatePass = mysqli_prepare($conn, "UPDATE account_table SET password_hash = ? WHERE account_id = ?");
                mysqli_stmt_bind_param($updatePass, "si", $newHash, $profile_user_id);
                if (mysqli_stmt_execute($updatePass)) {
                    $message = __('password_changed');
                    $messageClass = "bg-emerald-500 text-slate-950";
                } else {
                    $message = __('password_change_failed');
                    $messageClass = "bg-rose-500 text-white";
                }
                mysqli_stmt_close($updatePass);
            } else {
                $message = __('current_password_incorrect');
                $messageClass = "bg-rose-500 text-white";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('profile_title') ?> - <?= htmlspecialchars($user['username']) ?> | GameStore</title>
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
                <a href="index.php" class="text-white hover:text-cyan-400 transition"><?= __('home') ?></a>
                <a href="games.php" class="text-white hover:text-cyan-400 transition"><?= __('games') ?></a>
                <a href="electronics.php" class="text-white hover:text-cyan-400 transition"><?= __('electronics') ?></a>
                <?php if ($_SESSION['role'] === 'publisher'): ?>
                    <a href="mygames.php" class="text-white hover:text-cyan-400 transition"><?= __('my_games') ?></a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin/users.php" class="text-yellow-300 hover:text-yellow-200 transition"><?= __('admin') ?></a>
                <?php endif; ?>
                <a href="cart.php" class="text-white hover:text-cyan-400 transition"><?= __('cart') ?></a>
                <a href="profile.php" class="text-cyan-400 font-semibold"><?= __('profile') ?></a>
                <a href="logout.php" class="bg-red-500/20 text-red-300 px-3 py-1 rounded-md hover:bg-red-500/30 transition"><?= __('logout') ?></a>
            </nav>
            <div class="language-switcher flex gap-2">
                <a href="?lang=en" class="text-xs <?= $current_lang == 'en' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">EN</a>
                <a href="?lang=ku" class="text-xs <?= $current_lang == 'ku' ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?>">KU</a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- LEFT COLUMN: Avatar + quick info -->
            <div class="space-y-6 fade-up">
                <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-6 text-center">
                    <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-blue-500">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full mx-auto bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-4xl font-bold text-white">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="mt-4 text-xl font-bold text-white"><?= htmlspecialchars($user['username']) ?></h2>
                    <p class="text-blue-400 text-sm"><?= ucfirst($user['role']) ?></p>
                    <p class="text-gray-500 text-xs mt-2"><?= __('member_since') ?>: <?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?></p>
                    <div class="mt-4">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="text-sm text-gray-300 w-full">
                            <button type="submit" name="upload_avatar" class="mt-2 bg-blue-500/20 border border-blue-500 text-blue-400 px-3 py-1 rounded-full text-sm hover:bg-blue-500/30 transition btn-ripple"><?= __('upload_avatar') ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MIDDLE COLUMN: Edit profile + password change -->
            <div class="lg:col-span-2 space-y-6">
                <?php if ($message): ?>
                    <div class="rounded-lg px-4 py-3 text-sm font-medium <?= $messageClass ?> fade-up">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Edit Profile Form -->
                <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-6 fade-up">
                    <h3 class="text-lg font-semibold text-blue-400 mb-4"><?= __('edit_profile') ?></h3>
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-300 mb-1"><?= __('username') ?> *</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-300 mb-1"><?= __('email') ?> *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-300 mb-1"><?= __('location') ?></label>
                                <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-300 mb-1"><?= __('website') ?></label>
                                <input type="url" name="website" value="<?= htmlspecialchars($user['website'] ?? '') ?>" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-300 mb-1"><?= __('bio') ?></label>
                                <textarea name="bio" rows="3" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-300 mb-1"><?= __('preferred_language') ?></label>
                                <select name="preferred_language" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                                    <option value="en" <?= ($user['preferred_language'] ?? 'en') == 'en' ? 'selected' : '' ?>><?= __('english') ?></option>
                                    <option value="es" <?= ($user['preferred_language'] ?? '') == 'es' ? 'selected' : '' ?>><?= __('spanish') ?></option>
                                    <option value="fr" <?= ($user['preferred_language'] ?? '') == 'fr' ? 'selected' : '' ?>><?= __('french') ?></option>
                                    <option value="de" <?= ($user['preferred_language'] ?? '') == 'de' ? 'selected' : '' ?>><?= __('german') ?></option>
                                    <option value="ja" <?= ($user['preferred_language'] ?? '') == 'ja' ? 'selected' : '' ?>><?= __('japanese') ?></option>
                                </select>
                            </div>
                            <?php if ($logged_in_role === 'admin' && $profile_user_id !== $logged_in_user_id): ?>
                                <div>
                                    <label class="block text-sm text-gray-300 mb-1"><?= __('role') ?></label>
                                    <select name="role" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white">
                                        <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>><?= __('user_role') ?></option>
                                        <option value="publisher" <?= $user['role'] == 'publisher' ? 'selected' : '' ?>><?= __('publisher_role') ?></option>
                                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>><?= __('admin_role') ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-300 mb-1"><?= __('account_status') ?></label>
                                    <select name="is_active" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white">
                                        <option value="1" <?= $user['is_active'] == 1 ? 'selected' : '' ?>><?= __('active') ?></option>
                                        <option value="0" <?= $user['is_active'] == 0 ? 'selected' : '' ?>><?= __('suspended') ?></option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_profile" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-2 rounded-lg transition transform hover:scale-105 btn-ripple"><?= __('save_changes') ?></button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="bg-[#17171c] rounded-xl border border-[#2a2a30] p-6 fade-up">
                    <h3 class="text-lg font-semibold text-blue-400 mb-4"><?= __('change_password') ?></h3>
                    <form method="POST">
                        <div class="space-y-3">
                            <input type="password" name="current_password" placeholder="<?= __('current_password') ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            <input type="password" name="new_password" placeholder="<?= __('new_password_min') ?>" required minlength="6" class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            <input type="password" name="confirm_password" placeholder="<?= __('confirm_password') ?>" required class="form-input w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            <button type="submit" name="change_password" class="bg-blue-500/20 border border-blue-500 text-blue-400 px-4 py-2 rounded-lg hover:bg-blue-500/30 transition btn-ripple"><?= __('update_password') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Owned Games Section -->
        <div class="mt-10 bg-[#17171c] rounded-xl border border-[#2a2a30] p-6 fade-up">
            <h3 class="text-xl font-bold text-blue-400 mb-4">🎮 <?= __('games_owned') ?></h3>
            <?php if (empty($owned_games)): ?>
                <p class="text-gray-400"><?= __('no_games_purchased') ?> <a href="games.php" class="text-blue-400 hover:underline"><?= __('browse_store') ?></a></p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($owned_games as $game): ?>
                        <div class="bg-[#2a2a30] rounded-lg p-3 flex gap-3 items-center border border-[#3a3a44] hover:border-blue-500/50 transition">
                            <?php if (!empty($game['game_image']) && file_exists($game['game_image'])): ?>
                                <img src="<?= htmlspecialchars($game['game_image']) ?>" class="w-16 h-16 object-cover rounded-lg">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-purple-700 to-indigo-700 rounded-lg flex items-center justify-center text-2xl">🎮</div>
                            <?php endif; ?>
                            <div>
                                <h4 class="font-semibold text-white"><?= htmlspecialchars($game['game_name']) ?></h4>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($game['game_publisher']) ?></p>
                                <p class="text-xs text-blue-400">$<?= number_format($game['game_price'], 2) ?></p>
                                <p class="text-xs text-gray-500"><?= __('purchased') ?>: <?= date('M j, Y', strtotime($game['purchase_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Owned Electronics Section -->
        <div class="mt-10 bg-[#17171c] rounded-xl border border-[#2a2a30] p-6 fade-up">
            <h3 class="text-xl font-bold text-blue-400 mb-4">💻 <?= __('electronics_owned') ?></h3>
            <?php if (empty($owned_electronics)): ?>
                <p class="text-gray-400"><?= __('no_electronics_purchased') ?> <a href="electronics.php" class="text-blue-400 hover:underline"><?= __('shop_electronics') ?></a></p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($owned_electronics as $item): ?>
                        <div class="bg-[#2a2a30] rounded-lg p-3 flex gap-3 items-center border border-[#3a3a44] hover:border-blue-500/50 transition">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-800 to-cyan-800 rounded-lg flex items-center justify-center text-2xl">
                                <?php
                                $icon = '🖥️';
                                if (stripos($item['name'], 'headset') !== false) $icon = '🎧';
                                elseif (stripos($item['name'], 'keyboard') !== false) $icon = '⌨️';
                                elseif (stripos($item['name'], 'mouse') !== false) $icon = '🖱️';
                                elseif (stripos($item['name'], 'ssd') !== false) $icon = '💾';
                                elseif (stripos($item['name'], 'camera') !== false) $icon = '📷';
                                elseif (stripos($item['name'], 'monitor') !== false) $icon = '🖥️';
                                echo $icon;
                                ?>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white"><?= htmlspecialchars($item['name']) ?></h4>
                                <p class="text-xs text-blue-400">$<?= number_format($item['price'], 2) ?></p>
                                <p class="text-xs text-gray-500"><?= __('purchased') ?>: <?= date('M j, Y', strtotime($item['purchase_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="border-t border-[#2a2a30] mt-12 py-6 text-center text-gray-500 text-sm">
        <?= __('profile_footer') ?>
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