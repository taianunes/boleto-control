<?php
/**
 * Home View: HTML for admin page
 */

defined( 'ABSPATH' ) or die;

?>
<div class="wrap">
	<h1><?php _e( 'Boleto Control:Home', 'boleto-control' ); ?></h1><br>
	<h2><?php  _e( 'Single Actions', 'boleto-control' ); ?> </h2>
	<form method='POST'>
		<h4><?php  _e( 'Create order for Current User', 'boleto-control' ); ?> </h4>
		<?php submit_button( 'Create Order', 'secondary', 'single_order', false ); ?>
	<form>
	<br>
	<br>
	<form method='POST'>
		<h4><?php  _e( 'Create order for informed email', 'boleto-control' ); ?> </h4>
		<p>
			<label>User Email: </label>
			<input type='email' name='user[email]'>
		</p>
		<?php submit_button( 'Create Order', 'secondary', 'single_order', false ); ?>
	<form>
	<br>
	<br>
	<h2><?php _e( 'Group Actions ', 'boleto-control' ); ?></h2><br>
	<?php submit_button( 'Create Group Orders', 'primary', 'bulk_order', false ); ?>
</div>
