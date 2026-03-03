<div class="ccf-app" data-ccf-view="transactions-list">
	<div class="ccf-card">
		<h3>Listado de transacciones</h3>
		<form id="ccf-transactions-filters" class="ccf-grid two-col">
			<label>Mes<input type="month" name="month" value="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>"></label>
			<label>Tipo<select name="type"><option value="">Todos</option><option value="expense">expense</option><option value="transfer">transfer</option><option value="adjustment">adjustment</option></select></label>
			<label>Categoría<select name="category_id" id="ccf-filter-category"></select></label>
			<label>Cuenta<select name="account_id" id="ccf-filter-account"></select></label>
			<label>Estado<select name="status"><option value="">Todos</option><option value="posted">revisado</option><option value="pending">pendiente</option></select></label>
			<label>Adjunto<select name="has_attachment"><option value="">Todos</option><option value="1">Con adjunto</option><option value="0">Sin adjunto</option></select></label>
			<button type="submit">Aplicar filtros</button>
		</form>
		<div class="ccf-table-wrap"><table id="ccf-transactions-table"><thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Importe</th><th>Estado</th><th>Acciones</th></tr></thead><tbody></tbody></table></div>
	</div>
</div>
