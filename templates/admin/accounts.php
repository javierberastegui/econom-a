<div class="wrap ccf-admin-wrap"><h1>Cuenta común</h1>
<?php $common = ! empty( $accounts ) ? $accounts[0] : array(); ?>
<form method="post"><?php wp_nonce_field( 'ccf_save_account_action' ); ?>
<input type="hidden" name="id" value="<?php echo esc_attr( $common['id'] ?? '' ); ?>">
<input type="hidden" name="type" value="common">
<input type="text" name="name" placeholder="Nombre" required value="<?php echo esc_attr( $common['name'] ?? 'Cuenta común' ); ?>">
<input type="text" name="slug" placeholder="slug" required value="<?php echo esc_attr( $common['slug'] ?? 'cuenta-comun' ); ?>">
<label><input type="checkbox" name="is_visible" value="1" <?php checked( ! empty( $common['is_visible'] ) || empty( $common ) ); ?>>Visible</label>
<label><input type="checkbox" name="allow_manual" value="1" <?php checked( ! empty( $common['allow_manual'] ) || empty( $common ) ); ?>>Manual</label>
<label><input type="checkbox" name="monthly_process" value="1" <?php checked( ! empty( $common['monthly_process'] ) || empty( $common ) ); ?>>Mensual</label>
<label><input type="checkbox" name="status" value="active" <?php checked( empty( $common ) || 'active' === ( $common['status'] ?? 'active' ) ); ?>>Activa</label>
<button class="button button-primary" name="ccf_save_account" value="1">Guardar</button></form>
<table class="widefat striped"><thead><tr><th>ID</th><th>Nombre</th><th>Slug</th><th>Tipo</th><th>Estado</th></tr></thead><tbody><?php if ( $common ) : ?><tr><td><?php echo esc_html( $common['id'] ); ?></td><td><?php echo esc_html( $common['name'] ); ?></td><td><?php echo esc_html( $common['slug'] ); ?></td><td><?php echo esc_html( $common['type'] ); ?></td><td><?php echo esc_html( $common['status'] ); ?></td></tr><?php endif; ?></tbody></table></div>
