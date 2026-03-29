<?php
/*
Plugin Name: Webwynk Paypal Payment
Plugin URI: https://webwynk.com
Description: Advanced PayPal Smart Payment Form with SaaS UI
Version: 1.0
Author: Webwynk
Author URI: https://webwynk.com
*/

if (!defined('ABSPATH'))
    exit;

// Activation Hook
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_payments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        payment_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        amount decimal(10,2) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// Enqueue Admin Scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'paypal-form') === false)
        return;
    wp_enqueue_style('webwynk-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
});

// Enqueue Frontend Scripts
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('paypal-form-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('paypal-form-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], false, true);
    wp_localize_script('paypal-form-script', 'ajax_obj', ['ajaxurl' => admin_url('admin-ajax.php')]);
});

// Admin menu
add_action('admin_menu', function () {
    add_menu_page('WebWynk PayPal', 'WebWynk PayPal', 'manage_options', 'paypal-form-dashboard', 'paypal_dashboard_page', 'dashicons-performance');
});

// Dashboard Page
function paypal_dashboard_page()
{
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
    $client_id = get_option('paypal_client_id');
    $is_connected = !empty($client_id);

    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_payments';

    // Stats
    $total_payments = $wpdb->get_var("SELECT SUM(amount) FROM $table_name") ?: 0;
    $transaction_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name") ?: 0;
    $currency = get_option('paypal_currency', 'USD');

?>
    <div class="wpp-dashboard">
        <div class="wpp-header">
            <h1>WebWynk PayPal Dashboard</h1>
            <div class="wpp-status-badge <?php echo $is_connected ? 'wpp-connected' : 'wpp-disconnected'; ?>">
                ● <?php echo $is_connected ? 'PayPal Connected' : 'PayPal Disconnected'; ?>
            </div>
        </div>

        <nav class="wpp-tabs">
            <a href="?page=paypal-form-dashboard&tab=overview" class="wpp-tab <?php echo $active_tab == 'overview' ? 'wpp-active' : ''; ?>">Overview</a>
            <a href="?page=paypal-form-dashboard&tab=transactions" class="wpp-tab <?php echo $active_tab == 'transactions' ? 'wpp-active' : ''; ?>">Transactions</a>
            <a href="?page=paypal-form-dashboard&tab=settings" class="wpp-tab <?php echo $active_tab == 'settings' ? 'wpp-active' : ''; ?>">Settings</a>
        </nav>

        <div class="wpp-tab-content">
            <?php if ($active_tab == 'overview'): ?>
                <div class="wpp-grid">
                    <div class="wpp-card wpp-status-card <?php echo $is_connected ? 'wpp-status-connected' : 'wpp-status-disconnected'; ?>">
                        <div class="wpp-card-title">Connection Status</div>
                        <div class="wpp-card-value"><?php echo $is_connected ? 'Active' : 'Inactive'; ?></div>
                        <p><?php echo $is_connected ? 'Your PayPal Client ID is configured.' : 'Please configure your Client ID in settings.'; ?></p>
                    </div>
                    <div class="wpp-card">
                        <div class="wpp-card-title">Total Revenue</div>
                        <div class="wpp-card-value"><?php echo esc_html($currency) . ' ' . number_format($total_payments, 2); ?></div>
                        <p>Total processed payments</p>
                    </div>
                    <div class="wpp-card">
                        <div class="wpp-card-title">Transactions</div>
                        <div class="wpp-card-value"><?php echo esc_html($transaction_count); ?></div>
                        <p>Total number of payments</p>
                    </div>
                </div>

                <div class="wpp-card">
                    <div class="wpp-card-title">Recent Activity</div>
                    <?php
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY payment_time DESC LIMIT 5");
        if ($results): ?>
                        <table class="wpp-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $row): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row->payment_time)); ?></td>
                                        <td><?php echo esc_html($row->name); ?><br><small><?php echo esc_html($row->email); ?></small></td>
                                        <td><strong><?php echo esc_html($currency) . ' ' . number_format($row->amount, 2); ?></strong></td>
                                    </tr>
                                <?php
            endforeach; ?>
                            </tbody>
                        </table>
                    <?php
        else: ?>
                        <p>No transactions recorded yet.</p>
                    <?php
        endif; ?>
                </div>

            <?php
    elseif ($active_tab == 'transactions'): ?>
                <div class="wpp-card">
                    <div class="wpp-card-title">All Transactions</div>
                    <?php
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY payment_time DESC");
        if ($results): ?>
                        <table class="wpp-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $row): ?>
                                    <tr>
                                        <td><?php echo esc_html($row->payment_time); ?></td>
                                        <td><?php echo esc_html($row->name); ?></td>
                                        <td><?php echo esc_html($row->email); ?></td>
                                        <td><?php echo esc_html($row->phone); ?></td>
                                        <td><?php echo esc_html($currency) . ' ' . number_format($row->amount, 2); ?></td>
                                    </tr>
                                <?php
            endforeach; ?>
                            </tbody>
                        </table>
                    <?php
        else: ?>
                        <p>No transactions found.</p>
                    <?php
        endif; ?>
                </div>

            <?php
    elseif ($active_tab == 'settings'): ?>
                <div class="wpp-card">
                    <div class="wpp-card-title">PayPal Settings</div>
                    <form method="post" action="options.php">
                        <?php
        settings_fields('paypal_settings_group');
?>
                        <div class="wpp-form-group">
                            <label>Business Email</label>
                            <input type='email' name='paypal_email' value='<?php echo esc_attr(get_option('paypal_email')); ?>' />
                        </div>
                        <div class="wpp-form-group">
                            <label>Mode</label>
                            <?php $mode = get_option('paypal_mode', 'sandbox'); ?>
                            <select name="paypal_mode">
                                <option value="sandbox" <?php selected($mode, 'sandbox'); ?>>Sandbox</option>
                                <option value="live" <?php selected($mode, 'live'); ?>>Live</option>
                            </select>
                        </div>
                        <div class="wpp-form-group">
                            <label>Currency</label>
                            <?php $currency = get_option('paypal_currency', 'USD'); ?>
                            <select name="paypal_currency">
                                <option value="USD" <?php selected($currency, 'USD'); ?>>USD</option>
                                <option value="INR" <?php selected($currency, 'INR'); ?>>INR</option>
                                <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR</option>
                            </select>
                        </div>
                        <div class="wpp-form-group">
                            <label>Client ID</label>
                            <input type='text' name='paypal_client_id' value='<?php echo esc_attr(get_option('paypal_client_id')); ?>' />
                        </div>
                        <?php submit_button('Save Configuration', 'wpp-save-btn'); ?>
                    </form>
                </div>
            <?php
    endif; ?>
        </div>
    </div>
    <?php
}

// Register settings
add_action('admin_init', function () {
    register_setting('paypal_settings_group', 'paypal_email');
    register_setting('paypal_settings_group', 'paypal_mode');
    register_setting('paypal_settings_group', 'paypal_currency');
    register_setting('paypal_settings_group', 'paypal_client_id');
});

// Shortcode
add_shortcode('paypal_form', function () {

    $client_id = get_option('paypal_client_id');
    $currency = get_option('paypal_currency', 'USD');

    if (empty($client_id)) {
        return '<p style="color:red;">PayPal Client ID not configured. Please check plugin settings.</p>';
    }

    ob_start();
?>

    <div class="wpp-plugin-wrapper">
        <div class="wpp-split-layout">
            <!-- Left Panel: Information -->
            <div class="wpp-info-panel">
                <div class="wpp-info-content">
                    <h2 class="wpp-main-title">Complete Your Payment</h2>
                    <p class="wpp-main-desc">Securely complete your transaction using PayPal or Credit Card. Your sensitive data is protected by industry-leading encryption.</p>
                    
                    <div class="wpp-trust-badges">
                        <div class="wpp-trust-item">
                            <div class="wpp-trust-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            </div>
                            <div class="wpp-trust-text">
                                <strong>Secure Checkout</strong>
                                <span>SSL Encrypted Transaction</span>
                            </div>
                        </div>
                        <div class="wpp-trust-item">
                            <div class="wpp-trust-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            </div>
                            <div class="wpp-trust-text">
                                <strong>Instant Activation</strong>
                                <span>Get access immediately after payment</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Form -->
            <div class="wpp-form-panel">
                <div class="wpp-form-container">
                    <div class="wpp-step-counter" id="wpp-step-counter">STEP 1 OF 2</div>
                    
                    <div class="wpp-progress-bar-container">
                        <div class="wpp-progress-bar" id="wpp-progress-bar"></div>
                    </div>

                    <form id="wpp-paypal-form">
                        <!-- Step 1: User Details -->
                        <div class="wpp-form-step wpp-active" id="wpp-step-1">
                            <div class="wpp-input-group">
                                <label>Full Name</label>
                                <input type="text" name="custom_name" placeholder="John Doe" required>
                            </div>
                            <div class="wpp-input-group">
                                <label>Email Address</label>
                                <input type="email" name="custom_email" placeholder="john@example.com" required>
                            </div>
                            <div class="wpp-input-group">
                                <label>Phone Number</label>
                                <input type="text" name="custom_phone" placeholder="+1 234 567 890" required>
                            </div>
                            <div class="wpp-input-group">
                                <label>Amount (<?php echo esc_html($currency); ?>)</label>
                                <input type="number" name="amount" placeholder="10.00" step="0.01" required>
                            </div>
                            <button type="button" class="wpp-next-btn" id="wpp-to-step-2">Next Step</button>
                        </div>

                        <!-- Step 2: Payment Options -->
                        <div class="wpp-form-step" id="wpp-step-2">
                            <h3 class="wpp-step-title">Payment Details</h3>
                            <div class="wpp-payment-summary">
                                <div class="wpp-summary-header">Order Summary</div>
                                <div class="wpp-summary-item">
                                    <span>Customer:</span>
                                    <span id="wpp-summary-name">-</span>
                                </div>
                                <div class="wpp-summary-item">
                                    <span>Email:</span>
                                    <span id="wpp-summary-email">-</span>
                                </div>
                                <div class="wpp-summary-total">
                                    <span>Total Amount:</span>
                                    <div class="wpp-amount-box">
                                        <span id="wpp-summary-amount">0.00</span> <span class="wpp-currency"><?php echo esc_html($currency); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div id="wpp-paypal-button-container"></div>
                            
                            <button type="button" class="wpp-back-btn" id="wpp-back-to-step-1">Change details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>"></script>

    <?php
    return ob_get_clean();
});

// AJAX save
add_action('wp_ajax_save_payment', 'save_payment_data');
add_action('wp_ajax_nopriv_save_payment', 'save_payment_data');

function save_payment_data()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_payments';

    $wpdb->insert(
        $table_name,
    [
        'name' => sanitize_text_field($_POST['name']),
        'email' => sanitize_email($_POST['email']),
        'phone' => sanitize_text_field($_POST['phone']),
        'amount' => floatval($_POST['amount']),
    ]
    );

    wp_send_json_success();
}
