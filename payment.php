<?php
session_start();
require_once 'languages/loader.php';
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart_type = $_GET['type'] ?? '';
if (!in_array($cart_type, ['games', 'electronics'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_holder = trim($_POST['card_holder']);
    $card_number = preg_replace('/\s/', '', $_POST['card_number']);
    $card_expiry = trim($_POST['card_expiry']);
    $card_cvv = trim($_POST['card_cvv']);
    
    if (empty($card_holder) || empty($card_number) || empty($card_expiry) || empty($card_cvv)) {
        $error = __('fill_all_payment');
    } elseif (!preg_match('/^\d{13,19}$/', $card_number)) {
        $error = __('invalid_card_number');
    } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
        $error = __('invalid_expiry_format');
    } elseif (!preg_match('/^\d{3,4}$/', $card_cvv)) {
        $error = __('invalid_cvv');
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO payment_info (user_id, order_id, card_holder, card_number, card_expiry, card_cvv) VALUES (?, NULL, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $_SESSION['user_id'], $card_holder, $card_number, $card_expiry, $card_cvv);
        mysqli_stmt_execute($stmt);
        $payment_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        if ($cart_type === 'games') {
            header("Location: checkout.php?payment_id=" . $payment_id);
        } else {
            header("Location: checkout_electronics.php?payment_id=" . $payment_id);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $current_lang == 'ku' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payment_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0f0f12; font-family: 'Inter', sans-serif; }
        .payment-card { background: #17171c; border: 1px solid #2a2a30; border-radius: 1rem; }
    </style>
</head>
<body class="bg-[#0f0f12] text-white">
<div class="max-w-md mx-auto px-4 py-12">
    <div class="payment-card p-6">
        <h1 class="text-2xl font-bold text-center text-blue-400 mb-2">💳 <?= __('payment_details') ?></h1>
        <p class="text-gray-400 text-sm text-center mb-6"><?= __('payment_for') ?> <strong><?= $cart_type === 'games' ? __('games') : __('electronics') ?></strong></p>
        <?php if ($error): ?>
            <div class="bg-rose-500/20 text-rose-400 p-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm mb-1"><?= __('card_holder_name') ?></label>
                <input type="text" name="card_holder" required class="w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm mb-1"><?= __('card_number') ?></label>
                <input type="text" name="card_number" placeholder="1234 5678 9012 3456" required class="w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm mb-1"><?= __('expiry') ?> (MM/YY)</label>
                    <input type="text" name="card_expiry" placeholder="12/28" required class="w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm mb-1"><?= __('cvv') ?></label>
                    <input type="text" name="card_cvv" placeholder="123" required class="w-full bg-[#2a2a30] border border-[#3a3a44] rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500">
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 rounded-lg transition"><?= __('pay_now') ?></button>
        </form>
        <div class="text-center text-gray-500 text-xs mt-4"><?= __('secure_payment_note') ?></div>
    </div>
</div>
</body>
</html>