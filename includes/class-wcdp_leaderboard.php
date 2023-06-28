<?php
/**
 * This class displays a leaderboard of your donations
 *
 * * @since 1.3.0
 */

if (!defined('ABSPATH')) exit;

class WCDP_Leaderboard
{
    /**
     * Bootstraps the class and hooks required actions & filters.
     */
    public function __construct()
    {
        //Leaderboard shortcode
        add_shortcode('wcdp_leaderboard', array($this, 'wcdp_leaderboard'));

        //Delete cache on order change
        add_action('woocommerce_order_status_changed', array($this, 'delete_old_latest_orders_cache'), 10, 4);
    }

    /**
     * get an array with all WooCommerce orders
     * @param $orderby string
     * @return array
     */
    private function get_orders_db(string $orderby): array
    {
        $args = array(
            'limit' => apply_filters("wcdp_max_order_cache", 1000),
            'status' => 'completed',
            'order'   => 'DESC',
        );
        if ($orderby === 'date') {
            $args['orderby'] = 'date';
        } else {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_order_total';
        }

        $all_orders = wc_get_orders($args);

        $orders_clean = array();
        foreach ($all_orders as $order) {
            $product_ids = array();
            foreach ($order->get_items() as $item) {
                $product_ids[] = $item->get_product_id();
            }

            $orders_clean[] = array(
                'date' => $order->get_date_created()->getTimestamp(),
                'first' => $order->get_billing_first_name(),
                'last' =>  $order->get_billing_last_name(),
                'city' => $order->get_billing_city(),
                'country' => $order->get_billing_country(),
                'zip' => $order->get_billing_postcode(),
                'total' => $order->get_total(),
                'cy' => $order->get_currency(),
                'ids' => $product_ids,
            );
        }
        return $orders_clean;
    }

    /**
     * Return all the latest WooCommerce orders
     * @param string $orderby date or total
     * @return array
     */
    private function get_orders(string $orderby) : array {
        $cache_key = 'wcdp_orders_' . $orderby;
        $all_orders = json_decode(get_transient($cache_key), true);

        if (empty($all_orders)) {
            $all_orders = $this->get_orders_db($orderby);
            set_transient($cache_key, json_encode($all_orders), apply_filters("wcdp_cache_expiration", 6 * HOUR_IN_SECONDS));
        }
        return $all_orders;
    }

    /**
     * Return orders that included at least one of the specified ids
     * @param $limit
     * @param $ids
     * @param string $orderby date or total
     * @return array
     */
    private function wcdp_get_orders($limit, $ids, string $orderby): array
    {
        $all_orders = $this->get_orders($orderby);
        if ($ids === '-1') {
            if ($limit === -1) return $all_orders;
            return array_slice($all_orders, 0, $limit);
        }

        $filtered_orders = array_filter($all_orders, function ($order) use ($ids) {
            return !empty(array_intersect($order['ids'], $ids));
        });
        if ($limit === -1) return $filtered_orders;
        return array_slice($filtered_orders, 0, $limit);
    }

    /**
     * Generate the HTML output for the orders
     * @param $orders
     * @param $title
     * @param $subtitle
     * @return string
     */
    public function generate_leaderboard($orders, $title, $subtitle, int $style): string
    {
        $title = sanitize_text_field($title);
        $subtitle = sanitize_text_field($subtitle);

        $output = '
            <style>
                .wcdp-leaderboard {
                  list-style: none;
                  padding: 0;
                  margin: 0;
                }
                
                .wcdp-leaderboard .wcdp-leaderboard-li {
                  padding: 10px;
                  margin-bottom: 10px;
                }
                
                .wcdp-leaderboard .wcdp-leaderboard-title, .wcdp-leaderboard .woocommerce-Price-amount, .wcdp-leaderboard .wcdp-emphasized {
                  font-weight: bold;
                }
            </style>
            <ul class="wcdp-leaderboard">';
        foreach ($orders as $order) {
            $placeholders = array(
                '{firstname}' => esc_html($order['first']),
                '{firstname_initial}' => esc_html($this->get_initials($order['first'])),
                '{lastname_initial}' => esc_html($this->get_initials($order['last'])),
                '{amount}' => wc_price($order['total'], array('currency' => $order['cy'],)),
                '{timediff}' => $this->get_human_time_diff($order['date']),
                '{datetime}' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $order['date']),
                '{date}' => date_i18n(get_option('date_format'), $order['date']),
                '{city}' => esc_html($order['city']),
                '{country}' => WC()->countries->countries[esc_attr($order['country'])],
                '{country_code}' => esc_html($order['country']),
                '{postcode}' => esc_html($order['zip']),
                '{currency}' => esc_html($order['cy']),
            );

            $output .= '<li class="wcdp-leaderboard-li">';
            $output .= '<span class="wcdp-leaderboard-title">' . strtr($title, $placeholders) . '</span><br>';
            $output .= '<span class="wcdp-leaderboard-subtitle">' . strtr($subtitle, $placeholders) . '</span><br>';
            $output .= '</li>';
        }
        $output .= '</ul>';

        return $output;
    }

    /**
     * Leaderboard Shortcode
     * @param $atts
     * @return string
     */
    function wcdp_leaderboard($atts): string
    {
        // Do not allow executing this Shortcode via AJAX
        if (wp_doing_ajax()) return "";

        // Extract attributes
        $atts = shortcode_atts(array(
            'limit'     => 100,
            'ids'       => '-1',
            'title'     => '{firstname} donated {amount}',
            'subtitle'  => '{timediff}',
            'orderby'   => 'date',
            "style"     => 1,
        ), $atts, 'latest_orders');

        $atts['orderby'] = $atts['orderby'] === 'date' ? 'date' : 'price';

        $limit = intval($atts['limit']);
        $ids = explode(',', $atts['ids']);;

        // Get the latest orders
        $orders = $this->wcdp_get_orders($limit, $ids, $atts['orderby']);

        // Generate the HTML output
        return $this->generate_leaderboard($orders, $atts['title'], $atts['subtitle'], (int) $style);
    }

    function delete_old_latest_orders_cache($order_id, $old_status, $new_status, $order): void
    {
        if ($old_status !== 'completed' && $new_status !== 'completed') return;

        foreach (['date', 'total'] as $orderby) {
            $cache_key = 'wcdp_orders_' . $orderby;
            $timeout = get_option('_transient_timeout_' . $cache_key);

            if ($timeout && time() + apply_filters("wcdp_cache_expiration", 6 * HOUR_IN_SECONDS) - $timeout > 90) {
                delete_transient($cache_key);
                delete_transient($cache_key . '_timestamp');
            }
        }

    }

    /**
     * Get the initials of a name
     * @param $name
     * @return string
     */
    private function get_initials($name): string
    {
        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1))  . '.';
        }
        return $initials;
    }

    /**
     * Returns human time diff. Expects $timestamp to be in the past
     *
     * @param $timestamp int UNIX timestamp
     * @return string
     */
    private function get_human_time_diff(int $timestamp ): string {
        $human_diff = '<span class="wcdp-emphasized">' . human_time_diff( $timestamp ) . '</span>';
        return sprintf( __( '%s ago', 'wc-donation-platform' ), $human_diff );
    }
}
