<?php
/**
 * Show messages (success type).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/notices/success.php.
 *
 * CartTrigger – Quantity Discounts extends this template with support for a
 * custom CSS class passed via wc_add_notice( $message, $type, ['pbd_class' => 'my-class'] ).
 * If your theme already overrides this template, add the following two lines
 * inside the foreach loop to keep custom class support:
 *
 *   $ctqds_class = ! empty( $notice['data']['pbd_class'] ) ? ' ' . esc_attr( $notice['data']['pbd_class'] ) : '';
 *   // then append <?php echo esc_attr( $ctqds_class ); ?> to the class="" attribute of the container.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.0.0
 */

defined( 'ABSPATH' ) || exit;

foreach ( $notices as $notice ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variable
    $ctqds_class = ! empty( $notice['data']['pbd_class'] ) ? ' ' . $notice['data']['pbd_class'] : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variable
?>
<div class="woocommerce-message<?php echo esc_attr( $ctqds_class ); ?>"<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted WooCommerce core function ?>>
    <?php echo wc_kses_notice( $notice['notice'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<?php endforeach; ?>
