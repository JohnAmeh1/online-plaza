<?php
require_once '../includes/config.php';

if (isLoggedIn()) {
    header('Location: /online-plaza/index.php');
    exit;
}

// Initialize variables at the top
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $referral_code = trim($_POST['referral_code']);

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            // Check referral code if provided
            $referred_by = null;
            $referrer_id = null;
            if (!empty($referral_code)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->execute([$referral_code]);
                $referrer = $stmt->fetch();

                if ($referrer) {
                    $referred_by = $referrer['id'];
                    $referrer_id = $referrer['id'];
                } else {
                    $error = 'Invalid referral code.';
                }
            }

            if (!$error) {
                // Generate unique referral code
                $user_referral_code = substr(md5(uniqid($username, true)), 0, 10);

                try {
                    $pdo->beginTransaction();

                    // Insert user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

                    if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $user_referral_code, $referred_by])) {
                        $new_user_id = $pdo->lastInsertId();

                        // Create wallet for new user with zero balance
                        $wallet_stmt = $pdo->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0)");
                        $wallet_stmt->execute([$new_user_id]);

                        // If referral code was used, add ₦400 to referrer's wallet
                        if ($referrer_id) {
                            // Add ₦400 to referrer's wallet
                            $update_wallet = $pdo->prepare("UPDATE wallet SET balance = balance + 400 WHERE user_id = ?");
                            $update_wallet->execute([$referrer_id]);

                            // Record the referral transaction
                            $transaction_stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status) VALUES (?, 400, 'credit', 'Referral bonus for new user signup', 'completed')");
                            $transaction_stmt->execute([$referrer_id]);

                            // Update referred_count for the referrer (optional - if you have this field)
                            $update_referred_count = $pdo->prepare("UPDATE users SET referred_count = referred_count + 1 WHERE id = ?");
                            $update_referred_count->execute([$referrer_id]);
                        }

                        $pdo->commit();

                        $success = 'Account created successfully! You can now login.';
                        if ($referrer_id) {
                            $success .= ' The referrer has been credited with ₦400.';
                        }
                        // Clear form
                        $_POST = array();
                    } else {
                        throw new Exception('Failed to create user account.');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'An error occurred during registration. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Martly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/martly.svg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0c4a6e 0%, #0d9488 50%, #10b981 100%);
        }

        .card-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            border-color: #0d9488;
        }

        .btn-primary {
            background: linear-gradient(to right, #0d9488, #10b981);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, #0f766e, #059669);
            transform: translateY(-1px);
            box-shadow: 0 10px 25px -5px rgba(13, 148, 136, 0.4);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .referral-badge {
            background: linear-gradient(to right, #0d9488, #10b981);
        }

        .brand-gradient {
            background: linear-gradient(to right, #0d9488, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>

<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full">
        <!-- Logo and Brand -->
        <div class="text-center mb-10">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 rounded-full glass-effect flex items-center justify-center">
                    <i class="fas fa-store text-2xl text-white"></i>
                </div>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">Martly</h1>
            <p class="text-teal-100">Premium Shopping Experience</p>
        </div>

        <!-- Register Card -->
        <div class="bg-white rounded-xl card-shadow p-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800">Create Account</h2>
                <p class="mt-2 text-sm text-gray-600">Join our premium shopping community</p>
            </div>

            <!-- Referral Info Banner -->
            <?php if (isset($_GET['ref']) && !empty($_GET['ref'])): ?>
                <div class="referral-badge text-white rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-gift text-xl mr-3"></i>
                        <div>
                            <p class="font-semibold text-sm">Referral Code Applied</p>
                            <p class="text-xs opacity-90 mt-1">The referrer will earn ₦400 when you complete registration.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form class="space-y-6" method="POST">
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo $error; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-md" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo $success; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input id="first_name" name="first_name" type="text"
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                                placeholder="First Name"
                                value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input id="last_name" name="last_name" type="text"
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                                placeholder="Last Name"
                                value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-at text-gray-400"></i>
                        </div>
                        <input id="username" name="username" type="text" required
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                            placeholder="Choose a username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input id="email" name="email" type="email" required
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                            placeholder="Enter your email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input id="password" name="password" type="password" required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                                placeholder="Min. 6 characters">
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input id="confirm_password" name="confirm_password" type="password" required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                                placeholder="Confirm password">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="referral_code" class="block text-sm font-medium text-gray-700 mb-2">
                        Referral Code <span class="text-xs text-teal-600 font-normal">(Optional)</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-handshake text-gray-400"></i>
                        </div>
                        <input id="referral_code" name="referral_code" type="text"
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:ring-teal-500 focus:border-teal-500 transition duration-150"
                            placeholder="Enter referral code"
                            value="<?php echo isset($_POST['referral_code']) ? htmlspecialchars($_POST['referral_code']) : (isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : ''); ?>">
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Referrer earns ₦400 when you complete registration</p>
                </div>

                <!-- Referral Program Info -->
                <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-teal-500 mt-0.5 mr-3"></i>
                        <div>
                            <p class="text-teal-800 font-semibold text-sm">Referral Program</p>
                            <p class="text-teal-600 text-xs mt-1">Share your referral code and earn ₦400 for every friend who joins!</p>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg text-sm font-medium text-white btn-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                        <i class="fas fa-user-plus mr-2"></i>
                        Create Account
                    </button>
                </div>

                <div class="text-center pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-600">
                        Already have an account?
                        <a href="/online-plaza/auth/login.php" class="font-medium text-teal-600 hover:text-teal-500 transition duration-150 ml-1">
                            Sign In
                        </a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-sm text-teal-100">
                &copy; 2023 Martly. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // Auto-fill referral code from URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const refCode = urlParams.get('ref');
            if (refCode && !document.getElementById('referral_code').value) {
                document.getElementById('referral_code').value = refCode;
            }
        });
    </script>
</body>

</html>