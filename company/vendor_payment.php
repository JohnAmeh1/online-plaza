<?php
require_once '../includes/config.php';

// Check if user has a pending company for payment
if (!isset($_SESSION['pending_company_id']) || !isset($_SESSION['pending_company_name'])) {
    header('Location: /online-plaza/company/become_vendor.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();
$company_id = $_SESSION['pending_company_id'];
$company_name = $_SESSION['pending_company_name'];
$subscription_amount = 2500000; // ₦25,000 in kobo (25,000 * 100)

$error = '';
$success = '';

// Handle Paystack callback
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];

    // Verify payment with Paystack
    $paystack_secret_key = 'sk_test_b91e0557f6dca556b24425e6f6683cba1e86c25b';

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
            "Authorization: Bearer " . $paystack_secret_key,
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

        if ($result->status && $result->data->status == 'success') {
            // Payment was successful
            $pdo->beginTransaction();

            try {
                // Calculate subscription dates
                $start_date = date('Y-m-d H:i:s');
                $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));

                // Insert into vendor_subscriptions table
                $stmt = $pdo->prepare("INSERT INTO vendor_subscriptions (user_id, company_id, paystack_reference, amount, status, start_date, expiry_date, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $currentUser['id'],
                    $company_id,
                    $reference,
                    $subscription_amount / 100, // Convert from kobo to naira for database storage
                    $start_date,
                    $expiry_date
                ]);

                // Update company subscription status
                $stmt = $pdo->prepare("UPDATE companies SET subscription_status = 'active' WHERE id = ?");
                $stmt->execute([$company_id]);

                // Update user type to vendor
                $stmt = $pdo->prepare("UPDATE users SET user_type = 'vendor' WHERE id = ?");
                $stmt->execute([$currentUser['id']]);

                $pdo->commit();

                // Clear session variables
                unset($_SESSION['pending_company_id']);
                unset($_SESSION['pending_company_name']);
                unset($_SESSION['pending_subscription_amount']);

                // Set success message
                $_SESSION['success_message'] = "Payment successful! Your vendor account has been activated. Your subscription is valid until " . date('F j, Y', strtotime($expiry_date)) . ".";

                // Redirect to vendor dashboard
                header('Location: /online-plaza/company/dashboard.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating your account: " . $e->getMessage();
                error_log("Payment success but DB update failed: " . $e->getMessage());
            }
        } else {
            $error = "Payment verification failed. Please contact support.";
        }
    }
}

// Handle payment cancellation
if (isset($_GET['cancelled'])) {
    $error = "Payment was cancelled. Please try again.";
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-500 to-blue-500 p-8 text-white text-center">
            <h1 class="text-3xl font-extrabold mb-2">Complete Vendor Registration</h1>
            <p class="text-green-100 text-lg">Final step to activate your vendor account</p>
            <div class="mt-4 flex justify-center">
                <div class="w-16 h-1 bg-white rounded-full"></div>
            </div>
        </div>

        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <a href="/online-plaza/profile/become_vendor.php" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition font-semibold">
                            Try Again
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Payment Details -->
                <div class="lg:col-span-2">
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Order Summary</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Company Name:</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($company_name); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Subscription Plan:</span>
                                <span class="font-semibold">Monthly Vendor Subscription</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Duration:</span>
                                <span class="font-semibold">30 Days</span>
                            </div>
                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex justify-between items-center text-lg">
                                    <span class="text-gray-800 font-semibold">Total Amount:</span>
                                    <span class="text-green-600 font-bold">₦25,000</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <!-- Paystack Payment -->
                        <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-6 mb-4">
                            <div class="flex items-center mb-4">
                                <img src="../assets/paystack.png" alt="Paystack" class="h-8 mr-3">
                                <h4 class="text-lg font-semibold text-gray-800">Pay with Paystack</h4>
                            </div>
                            <p class="text-gray-600 mb-4">Secure payment via Paystack. Accepts cards, bank transfers, and USSD.</p>
                            <button onclick="payWithPaystack()" class="w-full bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition font-semibold flex items-center justify-center">
                                <i class="fas fa-credit-card mr-2"></i>
                                Pay ₦25,000 with Paystack
                            </button>
                        </div>
                    </div>

                    <!-- Support Info -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mt-6">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-yellow-500 text-xl mt-1 mr-4"></i>
                            <div>
                                <h4 class="text-lg font-semibold text-yellow-800 mb-2">Need Help with Payment?</h4>
                                <p class="text-yellow-700 mb-2">
                                    If you encounter any issues during payment, please contact our support team.
                                </p>
                                <div class="text-sm text-yellow-700">
                                    <div class="flex items-center mb-1">
                                        <i class="fas fa-envelope mr-2"></i>
                                        support@onlineplaza.com
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-phone mr-2"></i>
                                        +234 1 123 4567
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary & Benefits -->
                <div class="space-y-6">
                    <!-- Order Summary Card -->
                    <div class="bg-gradient-to-br from-green-500 to-blue-500 rounded-lg p-6 text-white">
                        <h3 class="text-xl font-bold mb-4 text-center">Order Summary</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Subscription:</span>
                                <span class="font-semibold">Monthly</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Amount:</span>
                                <span class="font-bold">₦25,000</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Duration:</span>
                                <span>30 Days</span>
                            </div>
                            <div class="border-t border-green-400 pt-3 mt-3">
                                <div class="flex justify-between text-lg">
                                    <span>Total:</span>
                                    <span class="font-bold">₦25,000</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- What You Get -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-gift text-green-500 mr-2"></i>
                            What You Get
                        </h3>
                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span>Full vendor dashboard access</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span>Unlimited product listings</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span>Sales analytics and reports</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span>Customer management tools</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span>Promotional features</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span>Priority support</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Security Badge -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                        <i class="fas fa-shield-alt text-green-500 text-3xl mb-3"></i>
                        <h4 class="font-semibold text-gray-800 mb-2">Secure Payment</h4>
                        <p class="text-sm text-gray-600">
                            Your payment information is encrypted and secure. We never store your card details.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Paystack Payment Script -->
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
    function payWithPaystack() {
        const paymentButton = event.target;
        const originalText = paymentButton.innerHTML;

        // Show loading state
        paymentButton.disabled = true;
        paymentButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

        const handler = PaystackPop.setup({
            key: 'pk_test_fdeb97ce15dc119e28cc589fcb24fac669b14f81',
            email: '<?php echo $currentUser['email']; ?>',
            amount: 2500000, // ₦25,000 in kobo (25,000 * 100)
            currency: 'NGN',
            ref: 'VENDOR-<?php echo $company_id; ?>-' + Math.floor((Math.random() * 1000000000) + 1),
            metadata: {
                custom_fields: [{
                        display_name: "Company Name",
                        variable_name: "company_name",
                        value: "<?php echo $company_name; ?>"
                    },
                    {
                        display_name: "User ID",
                        variable_name: "user_id",
                        value: "<?php echo $currentUser['id']; ?>"
                    },
                    {
                        display_name: "Subscription Type",
                        variable_name: "subscription_type",
                        value: "monthly_vendor"
                    }
                ]
            },
            callback: function(response) {
                // Payment was successful - redirect to verify payment
                window.location.href = '/online-plaza/company/vendor_payment.php?reference=' + response.reference;
            },
            onClose: function() {
                // Payment was cancelled
                paymentButton.disabled = false;
                paymentButton.innerHTML = originalText;
                window.location.href = '/online-plaza/company/vendor_payment.php?cancelled=true';
            }
        });
        handler.openIframe();
    }
</script>

<?php require_once '../includes/footer.php'; ?>