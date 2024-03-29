<?php
/**
 * Admin new renewal order email
 *
 * forked from WooCommerce_Subscription\Templates by Brent Shepherd
 * @package WooCommerce_Subscriptions/Templates/Emails
 * @version 2.6.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

do_action('woocommerce_email_header', $email_heading, $email); ?>

<?php /* translators: $1: customer's billing first name and last name */ ?>
    <p><?php printf(esc_html_x('You have received a recurring donation renewal from %1$s. Their donation is as follows:', 'Used in admin email: new renewal order', 'wc-donation-platform'), esc_html($order->get_formatted_billing_full_name())); ?></p>

<?php
/**
 * @hooked WC_Subscriptions_Email::order_details() Shows the order details table.
 * @since 2.1.0
 */
do_action('woocommerce_subscriptions_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email);
