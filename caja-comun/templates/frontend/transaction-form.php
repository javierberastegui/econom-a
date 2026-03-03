<div class="ccf-app" data-ccf-view="transaction-form">
	<div class="ccf-card">
		<h3>Nueva transacción</h3>
		<form id="ccf-transaction-form" class="ccf-grid two-col" enctype="multipart/form-data">
			<label>Tipo
				<select name="type"><option value="expense">expense</option><option value="transfer">transfer</option><option value="adjustment">adjustment</option></select>
			</label>
			<label>Importe<input type="number" name="amount" min="0.01" step="0.01" required></label>
			<label>Fecha<input type="date" name="transaction_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required></label>
			<label>Descripción<input type="text" name="description" required></label>
			<label>Nota rápida<input type="text" name="quick_note"></label>
			<label>Categoría<select name="category_id" id="ccf-category-select"></select></label>
			<label>Cuenta origen<select name="source_account_id" id="ccf-source-account"></select></label>
			<label>Cuenta destino<select name="destination_account_id" id="ccf-destination-account"></select></label>
			<label>Estado
				<select name="status"><option value="posted">revisado</option><option value="pending">pendiente</option></select>
			</label>
			<label>Adjuntos<input type="file" name="files[]" id="ccf-attachments" multiple accept="image/*,application/pdf"></label>
			<button type="submit">Guardar transacción</button>
		</form>
		<p class="ccf-feedback" id="ccf-transaction-feedback"></p>
	</div>
</div>
