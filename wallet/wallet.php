<?php
require_once '../includes/config.php';
require_once '../config/security.php';

// Only authenticated users can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$walletBalance = 0.00;
$transactions = [];
$paymentMethods = [];
$error = '';
$success = '';

// Paystack Configuration - Use test keys for development

$paystackPublicKey = 'pk_test_fdeb97ce15dc119e28cc589fcb24fac669b14f81'; // Your Paystack public key
$paystackSecretKey = 'sk_test_b91e0557f6dca556b24425e6f6683cba1e86c25b';
// Initialize security
$security = new URLSecurity();

// Get user details and wallet
try {
    // Get user information
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COALESCE(w.balance, 0) as wallet_balance,
               w.id as wallet_id
        FROM users u 
        LEFT JOIN wallet w ON u.id = w.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    $user_email = $user['email'];
    $user_name = $user['first_name'] . ' ' . $user['last_name'];
    $walletBalance = $user['wallet_balance'];
    $wallet_id = $user['wallet_id'];

    // Create wallet if it doesn't exist
    if (!$wallet_id) {
        $stmt = $pdo->prepare("
            INSERT INTO wallet (user_id, balance, created_at, updated_at) 
            VALUES (?, 0.00, NOW(), NOW())
        ");
        $stmt->execute([$user_id]);
        $wallet_id = $pdo->lastInsertId();
        $walletBalance = 0.00;
    }

    // Get recent transactions
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_transactions 
        WHERE wallet_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$wallet_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment methods
    $stmt = $pdo->prepare("
        SELECT * FROM payment_methods 
        WHERE user_id = ? AND is_verified = TRUE
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error loading wallet: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Handle Paystack callback for deposits
if (isset($_GET['reference']) && isset($_GET['type']) && $_GET['type'] === 'deposit') {
    $reference = $_GET['reference'];
    
    try {
        // Verify transaction with Paystack
        $verification = verifyPaystackTransaction($reference, $paystackSecretKey);
        
        if ($verification['status'] === true && $verification['data']['status'] === 'success') {
            $amount = $verification['data']['amount'] / 100; // Convert from kobo to naira
            
            // Check if we've already processed this transaction
            $stmt = $pdo->prepare("SELECT id FROM wallet_transactions WHERE paystack_reference = ?");
            $stmt->execute([$reference]);
            
            if ($stmt->rowCount() === 0) {
                // Start transaction
                $pdo->beginTransaction();
                
                // Add to wallet balance
                $stmt = $pdo->prepare("
                    UPDATE wallet 
                    SET balance = balance + ?, updated_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$amount, $user_id]);
                
                // Record transaction
                $stmt = $pdo->prepare("
                    INSERT INTO wallet_transactions 
                    (wallet_id, amount, type, description, status, payment_method, paystack_reference, created_at)
                    VALUES (?, ?, 'deposit', 'Wallet deposit via Paystack', 'completed', 'paystack', ?, NOW())
                ");
                $stmt->execute([$wallet_id, $amount, $reference]);
                
                $pdo->commit();
                
                $success = "₦" . number_format($amount, 2) . " successfully added to your wallet!";
                header("Location: wallet.php?success=" . urlencode($success));
                exit;
            } else {
                $success = "Payment already processed.";
            }
        } else {
            $error = "Payment verification failed. Status: " . ($verification['data']['status'] ?? 'unknown');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Payment verification error: " . $e->getMessage();
    }
}

// Handle form submissions with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !CSRFProtection::validateToken($_POST['csrf_token'])) {
        $error = "Invalid security token";
    } else {
        // Handle withdrawal request
        if (isset($_POST['withdraw_funds'])) {
            $amount = floatval($_POST['amount']);
            $bank_account_id = $_POST['bank_account_id'];
            
            if ($amount < 100) {
                $error = "Minimum withdrawal amount is ₦100";
            } elseif ($amount > $walletBalance) {
                $error = "Insufficient balance for withdrawal";
            } elseif (empty($paymentMethods)) {
                $error = "Please add a bank account first";
            } else {
                try {
                    // Get selected bank account
                    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ? AND user_id = ?");
                    $stmt->execute([$bank_account_id, $user_id]);
                    $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$bankAccount) {
                        throw new Exception("Selected bank account not found");
                    }
                    
                    // Generate transaction reference
                    $transaction_ref = 'WDL_' . time() . '_' . rand(1000, 9999);
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Deduct from wallet immediately
                    $stmt = $pdo->prepare("
                        UPDATE wallet 
                        SET balance = balance - ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$amount, $wallet_id]);
                    
                    // Create withdrawal transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO wallet_transactions 
                        (wallet_id, amount, type, description, status, bank_name, account_number, account_name, transaction_reference, created_at)
                        VALUES (?, ?, 'withdrawal', 'Withdrawal to bank', 'processing', ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $wallet_id,
                        $amount,
                        $bankAccount['bank_name'],
                        $bankAccount['account_number'],
                        $bankAccount['account_name'],
                        $transaction_ref
                    ]);
                    
                    $transaction_id = $pdo->lastInsertId();
                    
                    // For demo purposes, we'll simulate successful transfer
                    // In production, uncomment the Paystack transfer code
                    /*
                    $transfer_result = processPaystackTransfer(
                        $bankAccount['account_number'],
                        getBankCode($bankAccount['bank_name']),
                        $amount,
                        "Withdrawal from OnlinePlaza wallet - " . $transaction_ref,
                        $bankAccount['account_name'],
                        $paystackSecretKey
                    );
                    */
                    
                    // Simulate successful transfer for demo
                    $transfer_result = ['success' => true, 'data' => ['reference' => 'DEMO_' . $transaction_ref]];
                    
                    if ($transfer_result['success']) {
                        // Update transaction status to completed
                        $stmt = $pdo->prepare("
                            UPDATE wallet_transactions 
                            SET status = 'completed', paystack_reference = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$transfer_result['data']['reference'] ?? $transfer_result['data']['transfer_code'], $transaction_id]);
                        
                        $pdo->commit();
                        
                        // Update local balance
                        $walletBalance -= $amount;
                        
                        $success = "Withdrawal successful! ₦" . number_format($amount, 2) . " has been sent to your bank account.";
                    } else {
                        // Refund wallet balance if transfer fails
                        $stmt = $pdo->prepare("
                            UPDATE wallet 
                            SET balance = balance + ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$amount, $wallet_id]);
                        
                        // Update transaction status to failed
                        $stmt = $pdo->prepare("
                            UPDATE wallet_transactions 
                            SET status = 'failed', description = CONCAT(description, ' - ', ?), updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$transfer_result['message'], $transaction_id]);
                        
                        $pdo->commit();
                        
                        $error = "Withdrawal failed: " . $transfer_result['message'];
                    }
                    
                    header("Location: wallet.php?" . ($success ? "success=" . urlencode($success) : "error=" . urlencode($error)));
                    exit;
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Error processing withdrawal: " . $e->getMessage();
                }
            }
        }
        
        // Handle send money
        if (isset($_POST['send_money'])) {
            $recipient_identifier = trim($_POST['recipient']);
            $amount = floatval($_POST['amount']);
            $description = trim($_POST['description']);
            
            if ($amount < 1) {
                $error = "Minimum transfer amount is ₦1";
            } elseif ($amount > $walletBalance) {
                $error = "Insufficient balance for transfer";
            } else {
                try {
                    // Find recipient
                    $stmt = $pdo->prepare("
                        SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, w.id as wallet_id 
                        FROM users u 
                        LEFT JOIN wallet w ON u.id = w.user_id 
                        WHERE u.email = ? OR u.phone = ?
                    ");
                    $stmt->execute([$recipient_identifier, $recipient_identifier]);
                    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$recipient) {
                        $error = "Recipient not found. Please check the email or phone number.";
                    } elseif ($recipient['id'] == $user_id) {
                        $error = "You cannot send money to yourself.";
                    } elseif (!$recipient['wallet_id']) {
                        $error = "Recipient does not have a wallet set up.";
                    } else {
                        $transaction_ref = 'TRF_' . time() . '_' . rand(1000, 9999);
                        
                        // Start transaction
                        $pdo->beginTransaction();
                        
                        // Deduct from sender
                        $stmt = $pdo->prepare("
                            UPDATE wallet 
                            SET balance = balance - ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$amount, $wallet_id]);
                        
                        // Add to recipient
                        $stmt = $pdo->prepare("
                            UPDATE wallet 
                            SET balance = balance + ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$amount, $recipient['wallet_id']]);
                        
                        // Record sender transaction
                        $stmt = $pdo->prepare("
                            INSERT INTO wallet_transactions 
                            (wallet_id, amount, type, description, status, transaction_reference, recipient_id, created_at)
                            VALUES (?, ?, 'transfer_sent', ?, 'completed', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $wallet_id,
                            $amount,
                            "Transfer to " . $recipient['name'] . ($description ? ": " . $description : ""),
                            $transaction_ref,
                            $recipient['id']
                        ]);
                        
                        // Record recipient transaction
                        $stmt = $pdo->prepare("
                            INSERT INTO wallet_transactions 
                            (wallet_id, amount, type, description, status, transaction_reference, sender_id, created_at)
                            VALUES (?, ?, 'transfer_received', ?, 'completed', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $recipient['wallet_id'],
                            $amount,
                            "Transfer from " . $user_name . ($description ? ": " . $description : ""),
                            $transaction_ref,
                            $user_id
                        ]);
                        
                        $pdo->commit();
                        
                        // Update local balance
                        $walletBalance -= $amount;
                        
                        $success = "₦" . number_format($amount, 2) . " successfully sent to " . $recipient['name'] . "!";
                        header("Location: wallet.php?success=" . urlencode($success));
                        exit;
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Error processing transfer: " . $e->getMessage();
                }
            }
        }
        
        // Handle add bank account
        if (isset($_POST['add_bank_account'])) {
            $bank_name = $_POST['bank_name'];
            $account_number = $_POST['account_number'];
            $account_name = $_POST['account_name'];
            
            try {
                // Verify account details (in real app, use bank verification API)
                $is_verified = true; // Simulated verification
                
                $stmt = $pdo->prepare("
                    INSERT INTO payment_methods 
                    (user_id, type, bank_name, account_number, account_name, is_verified, created_at)
                    VALUES (?, 'bank_account', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $bank_name, $account_number, $account_name, $is_verified]);
                
                $success = "Bank account added successfully!";
                header("Location: wallet.php?success=" . urlencode($success));
                exit;
            } catch (PDOException $e) {
                $error = "Error adding bank account: " . $e->getMessage();
            }
        }
    }
}

// Handle success messages from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Paystack helper functions
function verifyPaystackTransaction($reference, $secretKey) {
    $url = "https://api.paystack.co/transaction/verify/" . $reference;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secretKey
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true);
}

function processPaystackTransfer($account_number, $bank_code, $amount, $reason, $recipient_name, $secretKey) {
    // First create transfer recipient
    $recipient_data = [
        'type' => 'nuban',
        'name' => $recipient_name,
        'account_number' => $account_number,
        'bank_code' => $bank_code,
        'currency' => 'NGN'
    ];
    
    $url = "https://api.paystack.co/transferrecipient";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($recipient_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secretKey,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code !== 200 && $http_code !== 201) {
        return ['success' => false, 'message' => 'Recipient creation failed: ' . ($result['message'] ?? 'Unknown error')];
    }
    
    $recipient_code = $result['data']['recipient_code'];
    
    // Now initiate the transfer
    $transfer_data = [
        'source' => 'balance',
        'amount' => $amount * 100, // Convert to kobo
        'recipient' => $recipient_code,
        'reason' => $reason
    ];
    
    $url = "https://api.paystack.co/transfer";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transfer_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secretKey,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 || $http_code === 201) {
        return ['success' => true, 'data' => $result['data']];
    } else {
        return ['success' => false, 'message' => 'Transfer failed: ' . ($result['message'] ?? 'Unknown error')];
    }
}

function getBankCode($bankName) {
    $bankCodes = [
        'Access Bank' => '044',
        'Citibank' => '023',
        'Diamond Bank' => '063',
        'Ecobank Nigeria' => '050',
        'Fidelity Bank' => '070',
        'First Bank of Nigeria' => '011',
        'First City Monument Bank' => '214',
        'Guaranty Trust Bank' => '058',
        'Heritage Bank' => '030',
        'Keystone Bank' => '082',
        'Polaris Bank' => '076',
        'Providus Bank' => '101',
        'Stanbic IBTC Bank' => '221',
        'Standard Chartered Bank' => '068',
        'Sterling Bank' => '232',
        'Suntrust Bank' => '100',
        'Union Bank of Nigeria' => '032',
        'United Bank for Africa' => '033',
        'Unity Bank' => '215',
        'Wema Bank' => '035',
        'Zenith Bank' => '057'
    ];
    
    return $bankCodes[$bankName] ?? '';
}

$pageTitle = "My Wallet - OnlinePlaza";
include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Wallet</h1>
            <p class="text-gray-600 mt-2">Manage your funds and transactions</p>
        </div>

        <!-- Error and Success Messages -->
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Wallet Balance Card -->
        <div class="bg-gradient-to-r from-green-500 to-blue-500 rounded-2xl p-6 text-white mb-8 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-green-100 mb-2">Current Balance</p>
                    <h2 class="text-4xl font-bold mb-4">₦<?php echo number_format($walletBalance, 2); ?></h2>
                    <p class="text-green-100 text-sm">Available for spending and withdrawal</p>
                </div>
                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                    <i class="fas fa-wallet text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <button onclick="openModal('addFundsModal')" 
                    class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:shadow-lg transition-shadow duration-300">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-plus text-green-600 text-xl"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Add Funds</h3>
                <p class="text-gray-600 text-sm">Deposit money to your wallet</p>
            </button>

            <button onclick="openModal('withdrawModal')" 
                    class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:shadow-lg transition-shadow duration-300">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-paper-plane text-blue-600 text-xl"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Withdraw</h3>
                <p class="text-gray-600 text-sm">Send money to your bank</p>
            </button>

            <button onclick="openModal('sendMoneyModal')" 
                    class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:shadow-lg transition-shadow duration-300">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-share-alt text-purple-600 text-xl"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Send Money</h3>
                <p class="text-gray-600 text-sm">Transfer to other users</p>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Transactions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Transactions</h3>
                    <a href="transactions.php" class="text-green-600 hover:text-green-700 text-sm font-medium">
                        View All
                    </a>
                </div>

                <div class="space-y-4">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-receipt text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500">No transactions yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center 
                                        <?php echo $transaction['type'] === 'deposit' || $transaction['type'] === 'transfer_received' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                        <i class="fas <?php echo $transaction['type'] === 'deposit' || $transaction['type'] === 'transfer_received' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="font-medium text-gray-900 text-sm">
                                            <?php echo htmlspecialchars($transaction['description']); ?>
                                        </p>
                                        <p class="text-gray-500 text-xs">
                                            <?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold <?php echo $transaction['type'] === 'deposit' || $transaction['type'] === 'transfer_received' ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo ($transaction['type'] === 'deposit' || $transaction['type'] === 'transfer_received' ? '+' : '-'); ?>
                                        ₦<?php echo number_format($transaction['amount'], 2); ?>
                                    </p>
                                    <span class="inline-block px-2 py-1 text-xs rounded-full 
                                        <?php echo $transaction['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                               ($transaction['status'] === 'processing' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bank Accounts -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Bank Accounts</h3>
                    <button onclick="openModal('addBankModal')" 
                            class="text-green-600 hover:text-green-700 text-sm font-medium flex items-center">
                        <i class="fas fa-plus mr-1"></i>
                        Add Account
                    </button>
                </div>

                <div class="space-y-4">
                    <?php if (empty($paymentMethods)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-university text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500 mb-4">No bank accounts added</p>
                            <button onclick="openModal('addBankModal')" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                Add Bank Account
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($paymentMethods as $bank): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-university text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($bank['bank_name']); ?></p>
                                        <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($bank['account_name']); ?></p>
                                        <p class="text-gray-500 text-xs">••••<?php echo substr($bank['account_number'], -4); ?></p>
                                    </div>
                                </div>
                                <?php if ($bank['is_default']): ?>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Default</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Funds Modal -->
<div id="addFundsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Add Funds</h3>
            <button onclick="closeModal('addFundsModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 mb-2" for="deposit_amount">Amount (₦)</label>
            <input type="number" id="deposit_amount" min="100" step="100" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" 
                   placeholder="Enter amount">
            <p class="text-sm text-gray-500 mt-1">Minimum: ₦100</p>
        </div>

        <button onclick="payWithPaystack()" 
                class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 rounded-lg font-medium hover:from-green-600 hover:to-blue-600 transition flex items-center justify-center">
            <i class="fas fa-credit-card mr-2"></i>
            Pay with Paystack
        </button>
        
        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-blue-500 mr-2"></i>
                <p class="text-sm text-blue-700">Secure payment powered by Paystack</p>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Funds Modal -->
<div id="withdrawModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Withdraw Funds</h3>
            <button onclick="closeModal('withdrawModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="withdraw_amount">Amount (₦)</label>
                <input type="number" id="withdraw_amount" name="amount" min="100" max="<?php echo $walletBalance; ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" required>
                <p class="text-sm text-gray-500 mt-1">Available: ₦<?php echo number_format($walletBalance, 2); ?></p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="bank_account_id">Bank Account</label>
                <select id="bank_account_id" name="bank_account_id" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" required>
                    <?php foreach ($paymentMethods as $bank): ?>
                        <option value="<?php echo $bank['id']; ?>" <?php echo $bank['is_default'] ? 'selected' : ''; ?>>
                            <?php echo $bank['bank_name'] . ' - ' . $bank['account_name'] . ' (••••' . substr($bank['account_number'], -4) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="withdraw_funds" 
                    class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 rounded-lg font-medium hover:from-green-600 hover:to-blue-600 transition flex items-center justify-center">
                <i class="fas fa-paper-plane mr-2"></i>
                Process Withdrawal
            </button>
            
            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-clock text-blue-500 mr-2"></i>
                    <p class="text-sm text-blue-700">Withdrawals are processed instantly to your bank account</p>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Send Money Modal -->
<div id="sendMoneyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Send Money</h3>
            <button onclick="closeModal('sendMoneyModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="recipient">Recipient Email or Phone</label>
                <input type="text" id="recipient" name="recipient" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" 
                       placeholder="Enter email or phone number" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="send_amount">Amount (₦)</label>
                <input type="number" id="send_amount" name="amount" min="1" max="<?php echo $walletBalance; ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" required>
                <p class="text-sm text-gray-500 mt-1">Available: ₦<?php echo number_format($walletBalance, 2); ?></p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="description">Description (Optional)</label>
                <input type="text" id="description" name="description" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" 
                       placeholder="Add a note">
            </div>

            <button type="submit" name="send_money" 
                    class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 rounded-lg font-medium hover:from-green-600 hover:to-blue-600 transition flex items-center justify-center">
                <i class="fas fa-paper-plane mr-2"></i>
                Send Money
            </button>
        </form>
    </div>
</div>

<!-- Add Bank Account Modal -->
<div id="addBankModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Add Bank Account</h3>
            <button onclick="closeModal('addBankModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="bank_name">Bank Name</label>
                <select id="bank_name" name="bank_name" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" required>
                    <option value="">Select Bank</option>
                    <option value="Access Bank">Access Bank</option>
                    <option value="First Bank of Nigeria">First Bank of Nigeria</option>
                    <option value="Guaranty Trust Bank">Guaranty Trust Bank</option>
                    <option value="United Bank for Africa">United Bank for Africa</option>
                    <option value="Zenith Bank">Zenith Bank</option>
                    <option value="Fidelity Bank">Fidelity Bank</option>
                    <option value="Stanbic IBTC Bank">Stanbic IBTC Bank</option>
                    <option value="Sterling Bank">Sterling Bank</option>
                    <option value="Union Bank of Nigeria">Union Bank of Nigeria</option>
                    <option value="Wema Bank">Wema Bank</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="account_number">Account Number</label>
                <input type="text" id="account_number" name="account_number" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" 
                       placeholder="Enter account number" required maxlength="10">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="account_name">Account Name</label>
                <input type="text" id="account_name" name="account_name" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500" 
                       placeholder="Enter account name" required>
            </div>

            <button type="submit" name="add_bank_account" 
                    class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 rounded-lg font-medium hover:from-green-600 hover:to-blue-600 transition flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>
                Add Bank Account
            </button>
        </form>
    </div>
</div>

<!-- Load Paystack script -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
    // Paystack integration for deposits
    function payWithPaystack() {
        const amount = document.getElementById('deposit_amount').value;
        const email = '<?php echo $user_email; ?>';
        const userName = '<?php echo $user_name; ?>';
        
        if (!amount || amount < 100) {
            alert('Please enter a valid amount (minimum ₦100)');
            return;
        }
        
        const handler = PaystackPop.setup({
            key: '<?php echo $paystackPublicKey; ?>',
            email: email,
            amount: amount * 100, // Convert to kobo
            currency: "NGN",
            ref: 'WLT_' + Math.floor((Math.random() * 1000000000) + 1),
            metadata: {
                custom_fields: [{
                    display_name: "User Name",
                    variable_name: "user_name",
                    value: userName
                }]
            },
            callback: function(response) {
                // Payment was successful
                const reference = response.reference;
                window.location.href = 'wallet.php?reference=' + reference + '&type=deposit';
            },
            onClose: function() {
                alert('Payment window closed. If you already made payment, your wallet will be updated shortly.');
            }
        });
        handler.openIframe();
    }

    // Modal functions
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Close modals when clicking outside
    document.querySelectorAll('.fixed').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Auto-focus amount field when add funds modal opens
    document.getElementById('addFundsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            document.getElementById('deposit_amount').focus();
        }
    });

    // Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.fixed').forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    closeModal(modal.id);
                }
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
