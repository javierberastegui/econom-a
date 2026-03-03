<div class="ccf-app" data-ccf-view="income-form">
	<div class="ccf-card">
		<h3>Ingresos mensuales</h3>
		<form id="ccf-income-form" class="ccf-grid two-col">
			<label>Mes (YYYY-MM)<input type="month" name="month_key" value="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>" required></label>
			<label>Ingreso Perfil A<input type="number" name="income_a" step="0.01" min="0" required></label>
			<label>Nota Perfil A<input type="text" name="notes_a"></label>
			<label>Ingreso Perfil B<input type="number" name="income_b" step="0.01" min="0" required></label>
			<label>Nota Perfil B<input type="text" name="notes_b"></label>
			<button type="submit">Guardar ingresos</button>
		</form>
		<div class="ccf-inline-actions">
			<button type="button" id="ccf-preview-allocation">Preview asignación</button>
			<button type="button" id="ccf-run-allocation">Ejecutar asignación</button>
		</div>
		<p class="ccf-feedback" id="ccf-income-feedback"></p>
	</div>
</div>
