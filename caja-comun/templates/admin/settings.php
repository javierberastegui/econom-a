<?php
$session_hours = (int) $this->settings_repository->get( 'ccf_frontend_session_hours', '12' );
$enabled       = '1' === $this->settings_repository->get( 'ccf_enable_frontend_app', '1' );
$pin_enabled   = '1' === $this->settings_repository->get( 'ccf_frontend_profile_pin_enabled', '0' );
$profile_a     = (string) $this->settings_repository->get( 'ccf_frontend_profile_a_name', 'Perfil A' );
$profile_b     = (string) $this->settings_repository->get( 'ccf_frontend_profile_b_name', 'Perfil B' );
?>
<div class="wrap ccf-admin-wrap">
	<h1>Ajustes frontend privado</h1>
	<form method="post">
		<?php wp_nonce_field( 'ccf_save_frontend_settings_action' ); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="ccf_frontend_password">Contraseña del hogar</label></th><td><input type="password" id="ccf_frontend_password" name="ccf_frontend_password" class="regular-text" /><p class="description">Se almacena como hash (password_hash).</p></td></tr>
			<tr><th><label for="ccf_frontend_session_hours">Duración sesión (horas)</label></th><td><input type="number" min="1" max="168" id="ccf_frontend_session_hours" name="ccf_frontend_session_hours" value="<?php echo esc_attr( $session_hours ); ?>" /></td></tr>
			<tr><th>Activar app frontend</th><td><label><input type="checkbox" name="ccf_enable_frontend_app" value="1" <?php checked( $enabled ); ?> /> Habilitado</label></td></tr>
			<tr><th>Activar PIN por perfil</th><td><label><input type="checkbox" name="ccf_frontend_profile_pin_enabled" value="1" <?php checked( $pin_enabled ); ?> /> (opcional, reservado)</label></td></tr>
			<tr><th><label for="ccf_frontend_profile_a_name">Nombre Perfil A</label></th><td><input type="text" id="ccf_frontend_profile_a_name" name="ccf_frontend_profile_a_name" value="<?php echo esc_attr( $profile_a ); ?>" /></td></tr>
			<tr><th><label for="ccf_frontend_profile_b_name">Nombre Perfil B</label></th><td><input type="text" id="ccf_frontend_profile_b_name" name="ccf_frontend_profile_b_name" value="<?php echo esc_attr( $profile_b ); ?>" /></td></tr>
		</table>
		<p><button class="button button-primary" name="ccf_save_frontend_settings" value="1">Guardar ajustes frontend</button></p>
	</form>
	<h2>Shortcodes frontend Fase 3</h2>
	<code>[ccf_login]</code> <code>[ccf_app]</code> <code>[ccf_dashboard]</code> <code>[ccf_income_form]</code> <code>[ccf_transaction_form]</code> <code>[ccf_transactions_list]</code> <code>[ccf_logout]</code>
</div>
