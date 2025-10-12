<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Redirect if user is already a vendor
if ($currentUser['user_type'] === 'vendor') {
    header('Location: /online-plaza/company/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $description = trim($_POST['description']);
    $contact_email = trim($_POST['contact_email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validation
    if (empty($company_name) || empty($description) || empty($contact_email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid contact email address.';
    } else {
        // Check if company name already exists
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE name = ?");
        $stmt->execute([$company_name]);
        
        if ($stmt->fetch()) {
            $error = 'Company name already exists. Please choose a different name.';
        } else {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Insert company with pending subscription status
                $stmt = $pdo->prepare("INSERT INTO companies (user_id, name, description, contact_email, phone, address, subscription_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
                $stmt->execute([$currentUser['id'], $company_name, $description, $contact_email, $phone, $address]);
                $company_id = $pdo->lastInsertId();
                
                $pdo->commit();
                
                // Store company ID in session for payment processing
                $_SESSION['pending_company_id'] = $company_id;
                $_SESSION['pending_company_name'] = $company_name;
                
                // Redirect to payment page
                header('Location: /online-plaza/company/vendor_payment.php');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'An error occurred while setting up your vendor account. Please try again.';
                error_log("Vendor setup error: " . $e->getMessage());
            }
        }
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-500 to-blue-500 p-8 text-white text-center">
            <h1 class="text-3xl font-extrabold mb-2">Become a Vendor</h1>
            <p class="text-green-100 text-lg">Start your business journey on Online Plaza</p>
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
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payment Notice -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-1 mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-yellow-800 mb-2">Monthly Subscription Required</h3>
                        <p class="text-yellow-700 mb-3">
                            To become a vendor and maintain your store on Martly, you need to pay a monthly subscription fee of <strong class="text-red-600">₦25,000</strong>.
                        </p>
                        <ul class="text-yellow-700 text-sm space-y-1">
                            <li>• Your store will be active for 30 days from payment</li>
                            <li>• You'll receive renewal reminders 7 days before expiry</li>
                            <li>• Failure to renew will result in store suspension</li>
                            <li>• Stores expired for more than 30 days will be permanently deleted</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Registration Form -->
                <div class="lg:col-span-2">
                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="company_name" class="block text-base font-medium text-gray-700 mb-2">
                                Company Name *
                            </label>
                            <input type="text" id="company_name" name="company_name" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                   placeholder="Enter your company name"
                                   value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                            <p class="text-sm text-gray-500 mt-1">Choose a unique name for your business</p>
                        </div>

                        <div>
                            <label for="description" class="block text-base font-medium text-gray-700 mb-2">
                                Company Description *
                            </label>
                            <textarea id="description" name="description" required rows="4"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                      placeholder="Describe your company, products, and mission"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <p class="text-sm text-gray-500 mt-1">Tell customers about your business</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="contact_email" class="block text-base font-medium text-gray-700 mb-2">
                                    Contact Email *
                                </label>
                                <input type="email" id="contact_email" name="contact_email" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                       placeholder="business@email.com"
                                       value="<?php echo isset($_POST['contact_email']) ? htmlspecialchars($_POST['contact_email']) : ''; ?>">
                            </div>

                            <div>
                                <label for="phone" class="block text-base font-medium text-gray-700 mb-2">
                                    Phone Number
                                </label>
                                <input type="tel" id="phone" name="phone"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                       placeholder="+1 (555) 123-4567"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>

                        <div>
                            <label for="address" class="block text-base font-medium text-gray-700 mb-2">
                                Business Address
                            </label>
                            <textarea id="address" name="address" rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                      placeholder="Enter your business address (optional)"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>

                        <div class="flex flex-col md:flex-row items-center justify-between pt-6 border-t border-gray-200 gap-4">
                            <a href="/online-plaza/profile/index.php" class="text-gray-600 hover:text-gray-800 font-medium flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Profile
                            </a>
                            <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-8 py-3 rounded-lg hover:from-green-600 hover:to-blue-600 transition font-semibold flex items-center">
                                <i class="fas fa-store mr-2"></i>
                                Continue to Payment
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Benefits & Information -->
                <div class="space-y-6">
                    <!-- Pricing Card -->
                    <div class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg p-6 text-white">
                        <div class="text-center mb-4">
                            <h3 class="text-xl font-bold">Vendor Subscription</h3>
                            <div class="mt-2">
                                <span class="text-3xl font-bold">₦25,000</span>
                                <span class="text-purple-200">/month</span>
                            </div>
                        </div>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2"></i>
                                Full store access
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2"></i>
                                Unlimited products
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2"></i>
                                Analytics dashboard
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2"></i>
                                Customer support
                            </li>
                        </ul>
                    </div>

                    <!-- Vendor Benefits -->
                    <div class="bg-gradient-to-br from-green-50 to-blue-50 border border-green-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-crown text-yellow-500 mr-2"></i>
                            Vendor Benefits
                        </h3>
                        <ul class="space-y-3">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span class="text-gray-700">Create and manage your company profile</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span class="text-gray-700">Post updates and promotions</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span class="text-gray-700">List unlimited products</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <span class="text-gray-700">Reach thousands of customers</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Support -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
                        <p class="text-sm text-gray-600 mb-3">
                            Our support team is here to help you get started.
                        </p>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-envelope mr-2"></i>
                                support@onlineplaza.com
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-phone mr-2"></i>
                                +1 (555) 123-4567
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const companyNameInput = document.getElementById('company_name');
    
    // Real-time company name validation
    companyNameInput.addEventListener('blur', function() {
        const companyName = this.value.trim();
        if (companyName.length > 0) {
            validateCompanyName(companyName);
        }
    });
    
    function validateCompanyName(companyName) {
        // You could add AJAX validation here to check if company name is available
        console.log('Validating company name:', companyName);
    }
    
    // Form submission enhancement
    form.addEventListener('submit', function(e) {
        const companyName = companyNameInput.value.trim();
        const description = document.getElementById('description').value.trim();
        const contactEmail = document.getElementById('contact_email').value.trim();
        
        if (companyName.length < 2) {
            e.preventDefault();
            showError('Company name must be at least 2 characters long.');
            return;
        }
        
        if (description.length < 10) {
            e.preventDefault();
            showError('Please provide a more detailed company description (at least 10 characters).');
            return;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    });
});

function showError(message) {
    // Remove existing error notifications
    const existingErrors = document.querySelectorAll('.custom-error');
    existingErrors.forEach(error => error.remove());
    
    // Create error notification
    const errorDiv = document.createElement('div');
    errorDiv.className = 'custom-error bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6';
    errorDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    const form = document.querySelector('form');
    form.parentNode.insertBefore(errorDiv, form);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}
</script>

<?php require_once '../includes/footer.php'; ?>

