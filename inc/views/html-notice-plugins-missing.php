<?php
/**
 * Admin View: Notice - WooCommerce and/or WooCommerce-Boleto missing.
 */

defined( 'WPINC' ) or die;

$plugin_slug = array ('WooCommerce', 'WooCommerce-Boleto');
?>

<div class="error">
	<p>
		<strong><?php _e( 'Boleto Control Disabled', 'boleto-control' ) ?></strong><br>
		<?php _e( 'This plugin depends on the last version of the plugins listed below:<br>', 'boleto-control' );
		foreach ($plugin_slug as $plugin) {
			// Gets url for notice
			if ( current_user_can( 'install_plugins' ) ) {
				$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . strtolower($plugin) ), 'install-plugin_' . strtolower($plugin)  );
			} else {
				$url = 'http://wordpress.org/plugins/' . $plugin;
			}
			// Prints notice text
			printf( '%s', '<a href="' . esc_url( $url ) . '">' . __( str_replace('-', ' ', $plugin), 'boleto-control' ) . '</a><br>' );
		}
		?>
	</p>
</div>