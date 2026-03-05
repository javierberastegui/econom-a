(function () {
	if (!window.Chart || !window.ccfDashboardCharts) return;
	const data = window.ccfDashboardCharts;

	const mk = (id, config) => {
		const el = document.getElementById(id);
		if (el) new Chart(el, config);
	};

	mk('ccf-chart-income', {
		type: 'line',
		data: {
			labels: data.income_vs_split_vs_common.labels || [],
			datasets: [
				{ label: 'Ingresos', data: data.income_vs_split_vs_common.income || [] },
				{ label: 'Presupuesto común', data: data.income_vs_split_vs_common.common_budget || [] },
				{ label: 'Gasto real', data: data.income_vs_split_vs_common.actual_expense || [] }
			]
		}
	});

	mk('ccf-chart-budget-expense', {
		type: 'bar',
		data: {
			labels: ['Presupuesto común', 'Gasto real'],
			datasets: [{ label: '€', data: [Number(data.budget_vs_expense.budget || 0), Number(data.budget_vs_expense.expense || 0)] }]
		}
	});

	mk('ccf-chart-categories', {
		type: 'pie',
		data: {
			labels: (data.common_expense_by_category || []).map((c) => c.category_name || 'Sin categoría'),
			datasets: [{ data: (data.common_expense_by_category || []).map((c) => Number(c.total || 0)) }]
		}
	});

	mk('ccf-chart-evolution', {
		type: 'bar',
		data: {
			labels: (data.common_budget_evolution || []).map((r) => r.month_key),
			datasets: [
				{ label: 'Presupuesto común', data: (data.common_budget_evolution || []).map((r) => Number(r.common_budget || 0)) },
				{ label: 'Gasto real', data: (data.common_budget_evolution || []).map((r) => Number(r.actual_expense || 0)) }
			]
		}
	});
})();
