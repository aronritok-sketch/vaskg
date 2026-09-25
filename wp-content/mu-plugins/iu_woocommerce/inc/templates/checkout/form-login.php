<?php
/**
 * Checkout login form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-login.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.0.0
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('iu-modal-style');
wp_enqueue_script('iu-modal-view-script');

$registration_at_checkout   = WC_Checkout::instance()->is_registration_enabled();
$login_reminder_at_checkout = 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' );

if ( is_user_logged_in() ) {
	return;
}

if ( $login_reminder_at_checkout ) : ?>
	<div class="woocommerce-form-login-toggle">
		<?php
		wc_print_notice(
			apply_filters( 'woocommerce_checkout_login_message', esc_html__( 'Returning customer?', 'woocommerce' ) ) . // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			' <a href="#modal[loginModal]">' . esc_html__( 'Click here to login', 'woocommerce' ) . '</a>',
			'notice'
		);
		?>
	</div>
	<?php
endif;

if ( $registration_at_checkout || $login_reminder_at_checkout ) :

	// Always show the form after a login attempt.
	$show_form = isset( $_POST['login'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	echo '<div id="loginModal" class="iu-modal">
		<div class="iu-modal-dialog">
			<a class="iu-modal-close"><span class="dashicons dashicons-no-alt"></a>
			<div class="iu-modal-content">
	';

	echo '<h3 class="iu-heading">Belépés</h3>';

	woocommerce_login_form(
		array(
			'message'  => esc_html__( 'If you have shopped with us before, please enter your details below. If you are a new customer, please proceed to the Billing section.', 'woocommerce' ),
			'redirect' => wc_get_checkout_url(),
			'hidden'   => ! $show_form,
		)
	);

	echo '</div>
		</div>
	</div>';

endif;
