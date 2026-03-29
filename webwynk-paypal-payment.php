<?php
/*
Plugin Name: WebWynk Paypal Payment
Description: Advanced PayPal Smart Payment Form with SaaS UI
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

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

// Enqueue scripts
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('paypal-form-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('paypal-form-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], false, true);
    wp_localize_script('paypal-form-script', 'ajax_obj', ['ajaxurl' => admin_url('admin-ajax.php')]);
});

// Admin menu
add_action('admin_menu', function () {
    add_menu_page('PayPal Form', 'PayPal Form', 'manage_options', 'paypal-form-settings', 'paypal_settings_page');
    add_submenu_page('paypal-form-settings', 'Transactions', 'Transactions', 'manage_options', 'paypal-form-transactions', 'paypal_transactions_page');
});

// Settings page
function paypal_settings_page() {
    ?>
    <div class="wrap">
        <h2>PayPal Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('paypal_settings_group');
            do_settings_sections('paypal-form-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Transactions page
function paypal_transactions_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_payments';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY payment_time DESC");
    ?>
    <div class="wrap">
        <h2>PayPal Transactions</h2>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th class="manage-column column-columnname" scope="col">Date</th>
                    <th class="manage-column column-columnname" scope="col">Name</th>
                    <th class="manage-column column-columnname" scope="col">Email</th>
                    <th class="manage-column column-columnname" scope="col">Phone</th>
                    <th class="manage-column column-columnname" scope="col">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results): ?>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->payment_time); ?></td>
                            <td><?php echo esc_html($row->name); ?></td>
                            <td><?php echo esc_html($row->email); ?></td>
                            <td><?php echo esc_html($row->phone); ?></td>
                            <td><?php echo esc_html($row->amount); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No transactions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Register settings
add_action('admin_init', function () {
    register_setting('paypal_settings_group', 'paypal_email');
    register_setting('paypal_settings_group', 'paypal_mode');
    register_setting('paypal_settings_group', 'paypal_currency');
    register_setting('paypal_settings_group', 'paypal_client_id');

    add_settings_section('paypal_section', 'PayPal Configuration', null, 'paypal-form-settings');

    add_settings_field('paypal_email', 'Business Email', function () {
        echo "<input type='email' name='paypal_email' value='".esc_attr(get_option('paypal_email'))."' />";
    }, 'paypal-form-settings', 'paypal_section');

    add_settings_field('paypal_mode', 'Mode', function () {
        $mode = get_option('paypal_mode', 'sandbox');
        ?>
        <select name="paypal_mode">
            <option value="sandbox" <?php selected($mode, 'sandbox'); ?>>Sandbox</option>
            <option value="live" <?php selected($mode, 'live'); ?>>Live</option>
        </select>
        <?php
    }, 'paypal-form-settings', 'paypal_section');

    add_settings_field('paypal_currency', 'Currency', function () {
        $currency = get_option('paypal_currency', 'USD');
        ?>
        <select name="paypal_currency">
            <option value="USD" <?php selected($currency, 'USD'); ?>>USD</option>
            <option value="INR" <?php selected($currency, 'INR'); ?>>INR</option>
            <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR</option>
        </select>
        <?php
    }, 'paypal-form-settings', 'paypal_section');

    add_settings_field('paypal_client_id', 'Client ID', function () {
        echo "<input type='text' name='paypal_client_id' value='".esc_attr(get_option('paypal_client_id'))."' />";
    }, 'paypal-form-settings', 'paypal_section');
});

// Shortcode
add_shortcode('paypal_form', function () {

    $client_id = get_option('paypal_client_id');
    $currency = get_option('paypal_currency', 'USD');

    ob_start();
    ?>

    <form id="paypal-form">
        <input type="text" name="custom_name" placeholder="Name" required>
        <input type="email" name="custom_email" placeholder="Email" required>
        <input type="text" name="custom_phone" placeholder="Phone" required>
        <input type="number" name="amount" placeholder="Amount" required>

        <div id="paypal-button-container"></div>
    </form>

    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>"></script>

    <?php
    return ob_get_clean();
});

// AJAX save
add_action('wp_ajax_save_payment', 'save_payment_data');
add_action('wp_ajax_nopriv_save_payment', 'save_payment_data');

function save_payment_data() {
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
