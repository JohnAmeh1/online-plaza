<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Redirect if user is not a vendor
if ($currentUser['user_type'] !== 'vendor') {
    header('Location: /online-plaza/profile/index.php');
    exit;
}

// Get company details
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$company = $stmt->fetch();

// Get current subscription
$subscriptionStmt = $pdo->prepare("SELECT * FROM vendor_subscriptions WHERE company_id = ? ORDER BY created_at DESC LIMIT 1");
$subscriptionStmt->execute([$company['id']]);
$subscription = $subscriptionStmt->fetch();

// Check if renewal is allowed (within 7 days of expiry)
$canRenew = false;
if ($subscription && $subscription['expiry_date']) {
    $expiryDate = new DateTime($subscription['expiry_date']);
    $today = new DateTime();
    $interval = $today->diff($expiryDate);
    $daysUntilExpiry = $interval->days;

    if ($subscription['status'] === 'active' && $daysUntilExpiry <= 7 && $daysUntilExpiry >= 0) {
        $canRenew = true;
    }
}

// Redirect if renewal is not allowed
if (!$canRenew && $subscription['status'] !== 'expired') {
    header('Location: /online-plaza/company/dashboard.php');
    exit;
}

$error = '';
$success = '';

// Paystack configuration
$paystackPublicKey = 'pk_test_fdeb97ce15dc119e28cc589fcb24fac669b14f81'; // Replace with your Paystack public key
$paystackSecretKey = 'sk_test_b91e0557f6dca556b24425e6f6683cba1e86c25b';

// Handle payment callback
if (isset($_GET['reference']) && isset($_GET['type']) && $_GET['type'] === 'renewal') {
    $reference = $_GET['reference'];

    // Verify payment with Paystack
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $reference,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . $paystackSecretKey,
            "Cache-Control: no-cache",
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $error = "cURL Error: " . $err;
    } else {
        $result = json_decode($response);

        if ($result->status && $result->data->status === 'success') {
            $amount = $result->data->amount / 100; // Convert from kobo to naira

            // Store renewal in database
            $stmt = $pdo->prepare("
                INSERT INTO vendor_subscriptions 
                (user_id, company_id, paystack_reference, amount, status, start_date, expiry_date, created_at) 
                VALUES (?, ?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
            ");
            $stmt->execute([
                $currentUser['id'],
                $company['id'],
                $reference,
                $amount
            ]);

            $success = 'Subscription renewed successfully! Your vendor features have been extended for 30 days.';
        } else {
            $error = 'Payment verification failed. Please contact support.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Subscription - Martly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php require_once '../../includes/header.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">Renew Subscription</h1>
            <p class="text-gray-600">Continue enjoying premium vendor features</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-green-800">Success!</h3>
                        <p class="text-green-700"><?php echo $success; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="/online-plaza/company/dashboard.php" class="bg-green-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-green-700 transition">
                        Go to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">Payment Error</h3>
                        <p class="text-red-700"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Subscription Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Renewal Details</h2>

                        <!-- Current Subscription Info -->
                        <div class="bg-gradient-to-r from-orange-50 to-red-50 border border-orange-200 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-orange-800 mb-4">Current Subscription</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-orange-700">Status</p>
                                    <p class="font-semibold text-orange-800">
                                        <?php echo ucfirst($subscription['status']); ?>
                                        <?php if ($subscription['status'] === 'active'): ?>
                                            <span class="text-sm font-normal">(Expires in <?php echo $daysUntilExpiry; ?> days)</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-orange-700">Expiry Date</p>
                                    <p class="font-semibold text-orange-800">
                                        <?php echo date('F j, Y', strtotime($subscription['expiry_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Renewal Benefits -->
                        <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-green-800 mb-4">Renewal Benefits</h3>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    <span class="text-green-700">30-day vendor subscription extension</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    <span class="text-green-700">Unlimited product listings</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    <span class="text-green-700">Premium company profile</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    <span class="text-green-700">Advanced analytics dashboard</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    <span class="text-green-700">Priority customer support</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Summary</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Subscription Renewal (30 days)</span>
                                    <span class="font-semibold">₦25,000.00</span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-200 pt-3">
                                    <span class="text-lg font-semibold text-gray-800">Total Amount</span>
                                    <span class="text-xl font-bold text-green-600">₦25,000.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <button onclick="payWithPaystack()"
                                class="flex-1 bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 px-6 rounded-lg font-semibold hover:from-green-600 hover:to-blue-600 transition shadow-lg">
                                <i class="fas fa-credit-card mr-2"></i>
                                Pay ₦25,000.00
                            </button>
                            <a href="/online-plaza/company/dashboard.php"
                                class="flex-1 border border-gray-300 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-50 transition text-center">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Company Information</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-600">Company Name</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($company['name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Contact Email</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($company['contact_email']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Phone</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($company['phone']); ?></p>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Need Help?</h4>
                            <div class="space-y-2">
                                <a href="#" class="flex items-center text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-question-circle mr-2"></i>
                                    FAQ & Support
                                </a>
                                <a href="#" class="flex items-center text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-phone mr-2"></i>
                                    Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Load Paystack script -->
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        function payWithPaystack() {
            const amount = 25000; // Fixed renewal amount
            const email = '<?php echo $currentUser['email']; ?>';
            const userName = '<?php echo $currentUser['username']; ?>';
            const companyName = '<?php echo $company['name']; ?>';

            const handler = PaystackPop.setup({
                key: 'pk_test_fdeb97ce15dc119e28cc589fcb24fac669b14f81', // Replace with your Paystack public key
                email: email,
                amount: amount * 100, // Convert to kobo
                currency: "NGN",
                ref: 'RENEW_' + Math.floor((Math.random() * 1000000000) + 1),
                metadata: {
                    custom_fields: [{
                            display_name: "User Name",
                            variable_name: "user_name",
                            value: userName
                        },
                        {
                            display_name: "Company Name",
                            variable_name: "company_name",
                            value: companyName
                        }
                    ]
                },
                callback: function(response) {
                    // Payment was successful
                    const reference = response.reference;
                    window.location.href = 'renew-subscription.php?reference=' + reference + '&type=renewal';
                },
                onClose: function() {
                    alert('Payment window closed. If you already made payment, your subscription will be renewed shortly.');
                }
            });
            handler.openIframe();
        }
    </script>

    <?php require_once '../../includes/footer.php'; ?>
</body>

</html>