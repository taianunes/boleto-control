<?php
/**
 * Home View: HTML for home page
 */

defined( 'WPINC' ) or die;

?>
<div class="wrap">
	<form method='POST'>
	<h1><?php esc_html_e( 'Boleto Control: Admin Menu', 'boleto-control' ); ?></h1><br>
	<h2><?php  esc_html_e( 'Single Actions', 'boleto-control' ); ?> </h2>
		<h4><?php  esc_html_e( 'Create order for Current User', 'boleto-control' ); ?> </h4>
		<?php submit_button( esc_attr__( 'Create Order', 'boleto-control' ), 'secondary', 'single_order', false ); ?>
	<br>
	<br>
	<h4><?php  esc_html_e( 'Create order for informed email', 'boleto-control' ); ?> </h4>
		<p>
			<label>User Email: </label>
			<input type='email' name='user[email]'>
		</p>
		<?php submit_button( esc_attr__( 'Create Order', 'boleto-control' ), 'secondary', 'single_order', true ); ?>


	<h2><?php esc_html_e( 'Group Actions ', 'boleto-control' ); ?></h2><br>
	<?php submit_button( esc_attr__( 'Create Group Orders', 'boleto-control' ), 'primary', 'bulk_order', false ); ?>
	<form>
</div>