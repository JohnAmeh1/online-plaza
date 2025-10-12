<?php
class URLSecurity {
    private $encryption_key;
    
    public function __construct() {
        $this->encryption_key = getenv('ENCRYPTION_KEY') ?: 'your-secure-encryption-key-here';
    }
    
    public function generateSecureUrl($baseUrl, $params, $includeUserCheck = false) {
        if ($includeUserCheck) {
            $params['user_id'] = $_SESSION['user_id'];
            $params['timestamp'] = time();
        }
        
        $data = json_encode($params);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        $token = base64_encode($iv . $encrypted);
        
        return $baseUrl . '?t=' . urlencode($token);
    }
    
    public function extractSecureParams($useToken = true) {
        if ($useToken && isset($_GET['t'])) {
            $token = base64_decode($_GET['t']);
            $iv = substr($token, 0, 16);
            $encrypted = substr($token, 16);
            $data = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
            
            if ($data) {
                $params = json_decode($data, true);
                
                // Validate timestamp (expire after 1 hour)
                if (isset($params['timestamp']) && (time() - $params['timestamp']) < 3600) {
                    return $params;
                }
            }
        }
        return null;
    }
}

class CSRFProtection {
    public static function generateToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>