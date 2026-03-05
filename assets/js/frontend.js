(function () {
	const root = document.querySelector('[data-ccf-view="app"]');
	if (!root) return;

	const messenger = (window.CH_Messenger && typeof window.CH_Messenger.toast === 'function') ? window.CH_Messenger : null;

const cfg = window.CCF_FRONTEND || {};
	const charts = {};
	const state = {
		currentMonth: new Date().toISOString().slice(0, 7),
		accounts: [],
		categories: [],
		rows: [],
		commonAccountId: null
	};

	const api = async (path, options = {}) => {
		const isForm = options.body instanceof FormData;
		const headers = Object.assign({}, options.headers || {});
		if (!isForm) headers['Content-Type'] = 'application/json';
		if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
		const response = await fetch((cfg.apiBase || '') + path, Object.assign({}, options, { headers }));
		const data = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(data.message || data.error || 'No se pudo completar la operación.');
		return data;
	};

	const fmt = (n) => `${Number(n || 0).toFixed(2).replace('.', ',')} €`;
	const safeLabel = (v, fallback) => (v === undefined || v === null || String(v).trim() === '' ? fallback : String(v));
	const setFeedback = (el, msg, isError = false) => {
		if (!el) return;
		el.textContent = msg || '';
		el.classList.toggle('is-error', isError);
		if (msg && messenger && typeof messenger.toast === 'function') {
			try { messenger.toast(msg, isError ? 'error' : 'info'); } catch (e) {}
		}
	};

	const monthInput = document.getElementById('ccf-month');
	const tableBody = document.querySelector('#ccf-transactions-table tbody');
	const movementsSection = document.getElementById('ccf-movements-section');
	const tableWrap = document.getElementById('ccf-table-wrap');
	const toggleMovementsBtn = document.getElementById('ccf-toggle-movements');
	const pendingPill = document.getElementById('ccf-pending-pill');
	const emptyState = document.getElementById('ccf-empty-state');
	const tableFeedback = document.getElementById('ccf-table-feedback');
	const modal = document.getElementById('ccf-movement-modal');
	const movementForm = document.getElementById('ccf-movement-form');
	const modalFeedback = document.getElementById('ccf-modal-feedback');
	const inlineAttachment = document.getElementById('ccf-inline-attachment');
	const modalTitle = document.getElementById('ccf-modal-title');
	const categorySelect = document.getElementById('ccf-category-select');
	const categoryEmpty = document.getElementById('ccf-category-empty');
	const categoryWrap = document.getElementById('ccf-create-category-wrap');
	const categoryInput = document.getElementById('ccf-create-category-name');
	const categoryCreateFeedback = document.getElementById('ccf-category-create-feedback');
	const commonAccountInfo = document.getElementById('ccf-common-account-info');
	const field = (name) => movementForm ? movementForm.elements.namedItem(name) : null;

	const notifyInBrowser = async (title, body) => {
		if (!('Notification' in window)) return;
		try {
			if (Notification.permission === 'default') {
				const permission = await Notification.requestPermission();
				if (permission !== 'granted') return;
			}
			if (Notification.permission !== 'granted') return;
			if ('serviceWorker' in navigator) {
				const registration = await navigator.serviceWorker.getRegistration();
				if (registration && typeof registration.showNotification === 'function') {
					await registration.showNotification(title, {
						body,
						icon: cfg.notificationIcon || cfg.pwaIcon || undefined,
						badge: cfg.notificationBadge || cfg.pwaBadge || undefined,
						tag: 'ccf-frontend-notification'
					});
					return;
				}
			}
			new Notification(title, {
				body,
				icon: cfg.notificationIcon || cfg.pwaIcon || undefined,
				tag: 'ccf-frontend-notification'
			});
		} catch (err) {
			// no-op
		}
	};

	const openMovementModal = () => {
		if (!modal) return;
		if (typeof modal.showModal === 'function') {
			try {
				modal.showModal();
				return;
			} catch (err) {
				// fallback below
			}
		}
		modal.setAttribute('open', 'open');
	};

	const closeMovementModal = () => {
		if (!modal) return;
		if (typeof modal.close === 'function') {
			try {
				modal.close();
				return;
			} catch (err) {
				// fallback below
			}
		}
		modal.removeAttribute('open');
	};

	const fillSelect = (el, rows, placeholder, emptyLabel, emptyNode) => {
		el.innerHTML = '';
		const defaultOpt = document.createElement('option');
		defaultOpt.value = '';
		defaultOpt.textContent = rows.length ? placeholder : emptyLabel;
		el.appendChild(defaultOpt);
		rows.forEach((row) => {
			const opt = document.createElement('option');
			opt.value = row.id;
			opt.textContent = row.name;
			el.appendChild(opt);
		});
		if (emptyNode) emptyNode.hidden = rows.length > 0;
	};

	const renderCatalogs = () => {
		fillSelect(categorySelect, state.categories, 'Selecciona categoría', 'No hay categorías creadas todavía', categoryEmpty);
		commonAccountInfo.hidden = !!state.commonAccountId;
	};

	const renderKpis = (summary, budgetActual) => {
		const income = Number(summary.income_total || 0);
		const expense = Number((budgetActual && budgetActual.actual_expense) || summary.common_expense || 0);
		document.getElementById('ccf-kpi-income').textContent = fmt(income);
		document.getElementById('ccf-kpi-expense').textContent = fmt(expense);
		document.getElementById('ccf-kpi-balance').textContent = fmt(income - expense);
		document.getElementById('ccf-kpi-common').textContent = fmt(summary.common_budget || 0);
	};

	const destroyChart = (id) => {
		if (charts[id]) {
			charts[id].destroy();
			charts[id] = null;
		}
	};

	const toggleEmptyForChart = (canvasId, hasData) => {
		const wrap = document.getElementById(canvasId).closest('.ccf-chart-card');
		const empty = wrap ? wrap.querySelector('.ccf-chart-empty') : null;
		if (empty) empty.hidden = !!hasData;
	};

	const axisColor = '#bfdbfe';
	const legendColor = '#dbeafe';
	const gridColor = 'rgba(148, 163, 184, 0.14)';
	const chartColors = ['#7dd3fc', '#60a5fa', '#a78bfa', '#f9a8d4', '#34d399', '#fbbf24', '#fb7185', '#c084fc'];

	const euroTick = (value) => {
		const n = Number(value || 0);
		return n.toLocaleString('es-ES', { maximumFractionDigits: 0 });
	};

	const baseChartOptions = (extra = {}) => ({
		responsive: true,
		maintainAspectRatio: false,
		animation: false,
		layout: { padding: 6 },
		plugins: {
			legend: {
				display: true,
				position: 'bottom',
				labels: {
					color: legendColor,
					boxWidth: 14,
					usePointStyle: true,
					padding: 14,
					font: { size: window.innerWidth < 720 ? 11 : 12 }
				}
			},
			tooltip: {
				callbacks: {
					label(context) {
						const label = context.dataset?.label || context.label || '';
						return `${label}: ${fmt(context.parsed.y ?? context.parsed ?? 0)}`;
					}
				}
			}
		},
		scales: {
			x: {
				ticks: { color: axisColor, maxRotation: 0, autoSkipPadding: 10 },
				grid: { color: 'transparent' },
				border: { color: gridColor }
			},
			y: {
				beginAtZero: true,
				ticks: { color: axisColor, callback: euroTick },
				grid: { color: gridColor },
				border: { color: gridColor }
			}
		}
	});

	const monthOffset = (monthKey, offset) => {
		const [year, month] = String(monthKey || '').split('-').map(Number);
		const base = new Date(Date.UTC(year || new Date().getUTCFullYear(), (month || 1) - 1, 1));
		base.setUTCMonth(base.getUTCMonth() + offset);
		return `${base.getUTCFullYear()}-${String(base.getUTCMonth() + 1).padStart(2, '0')}`;
	};

	const renderCharts = (incomeLine, byCat, trend) => {
		const incomeSeries = Array.isArray(incomeLine?.series) ? incomeLine.series : [];
		const incomeLabels = incomeSeries.map((row) => safeLabel(row.month_key, state.currentMonth));
		const incomeData = incomeSeries.map((row) => Number(row.income_total || 0));
		const expenseData = incomeSeries.map((row) => Number(row.actual_expense || 0));
		const balanceData = incomeSeries.map((row) => Number(row.income_total || 0) - Number(row.actual_expense || 0));

		destroyChart('income');
		const hasIncomeData = incomeData.some((n) => n > 0) || expenseData.some((n) => n > 0);
		toggleEmptyForChart('ccf-chart-income', hasIncomeData);
		if (hasIncomeData) {
			const incomeCanvas = document.getElementById('ccf-chart-income');
			const singleMonth = incomeLabels.length <= 1;
			charts.income = new Chart(incomeCanvas, {
				type: singleMonth ? 'bar' : 'line',
				data: singleMonth
					? {
						labels: ['Ingresos', 'Gastos', 'Balance'],
						datasets: [{
							label: safeLabel(incomeLabels[0], state.currentMonth),
							data: [incomeData[0] || 0, expenseData[0] || 0, balanceData[0] || 0],
							backgroundColor: ['#7dd3fc', '#fb7185', '#34d399'],
							borderRadius: 10,
							borderSkipped: false
						}]
					}
					: {
						labels: incomeLabels,
						datasets: [
							{ label: 'Ingresos', data: incomeData, borderColor: '#7dd3fc', backgroundColor: 'rgba(125, 211, 252, .18)', pointBackgroundColor: '#7dd3fc', tension: .32, fill: false },
							{ label: 'Gasto real', data: expenseData, borderColor: '#fb7185', backgroundColor: 'rgba(251, 113, 133, .18)', pointBackgroundColor: '#fb7185', tension: .32, fill: false },
							{ label: 'Balance', data: balanceData, borderColor: '#34d399', backgroundColor: 'rgba(52, 211, 153, .18)', pointBackgroundColor: '#34d399', tension: .32, fill: false }
						]
					},
				options: baseChartOptions(singleMonth ? { plugins: { legend: { position: 'top' } } } : {})
			});
		}

		const categorySeries = (Array.isArray(byCat) ? byCat : [])
			.map((row) => ({
				label: safeLabel(row.category_name, 'Sin categoría'),
				value: Number(row.total || 0)
			}))
			.filter((row) => row.value > 0);

		destroyChart('category');
		const hasCategoryData = categorySeries.length > 0;
		toggleEmptyForChart('ccf-chart-category', hasCategoryData);
		if (hasCategoryData) {
			charts.category = new Chart(document.getElementById('ccf-chart-category'), {
				type: 'doughnut',
				data: {
					labels: categorySeries.map((row) => row.label),
					datasets: [{
						data: categorySeries.map((row) => row.value),
						backgroundColor: categorySeries.map((_, index) => chartColors[index % chartColors.length]),
						borderColor: '#dbeafe',
						borderWidth: 2,
						hoverOffset: 8
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					animation: false,
					cutout: '62%',
					plugins: {
						legend: {
							position: 'top',
							labels: {
								color: legendColor,
								boxWidth: 14,
								padding: 12,
								font: { size: window.innerWidth < 720 ? 11 : 12 }
							}
						},
						tooltip: {
							callbacks: {
								label(context) {
									const value = Number(context.parsed || 0);
									return `${context.label}: ${fmt(value)}`;
								}
							}
						}
					}
				}
			});
		}

		const trendSeries = Array.isArray(trend) ? trend : [];
		const trendLabels = trendSeries.map((row) => safeLabel(row.month_key, state.currentMonth));
		const trendBudget = trendSeries.map((row) => Number(row.common_budget || 0));
		const trendExpense = trendSeries.map((row) => Number(row.actual_expense || 0));
		const hasTrendData = trendBudget.some((n) => n > 0) || trendExpense.some((n) => n > 0);

		destroyChart('trend');
		toggleEmptyForChart('ccf-chart-trend', hasTrendData);
		if (hasTrendData) {
			charts.trend = new Chart(document.getElementById('ccf-chart-trend'), {
				type: 'bar',
				data: {
					labels: trendLabels,
					datasets: [
						{ label: 'Presupuesto', data: trendBudget, backgroundColor: '#60a5fa', borderRadius: 8, borderSkipped: false },
						{ label: 'Gasto real', data: trendExpense, backgroundColor: '#fb7185', borderRadius: 8, borderSkipped: false }
					]
				},
				options: baseChartOptions()
			});
		}
	};

	const transactionTypeLabel = (type) => {
		switch (String(type || '').toLowerCase()) {
			case 'income': return 'Ingreso';
			case 'expense': return 'Gasto';
			case 'adjustment': return 'Ajuste';
			default: return safeLabel(type, '-');
		}
	};

	const transactionStatusLabel = (status) => {
		switch (String(status || '').toLowerCase()) {
			case 'posted': return 'Revisado';
			case 'pending': return 'Pendiente';
			default: return safeLabel(status, 'Pendiente');
		}
	};

	const renderTransactionCell = (label, value) => `<td data-label="${label}">${value}</td>`;

	const pendingStatuses = new Set(['pending']);

	const setMovementsCollapsed = (collapsed) => {
		if (!tableWrap || !toggleMovementsBtn || !movementsSection) return;
		movementsSection.classList.toggle('is-collapsed', !!collapsed);
		tableWrap.hidden = !!collapsed;
		emptyState.hidden = collapsed || state.rows.length > 0;
		toggleMovementsBtn.textContent = collapsed ? `Ver movimientos (${state.rows.length})` : 'Ocultar movimientos';
	};

	const syncMovementsVisibility = () => {
		const pendingCount = state.rows.filter((row) => pendingStatuses.has(String(row.status || '').toLowerCase())).length;
		if (pendingPill) {
			pendingPill.hidden = false;
			pendingPill.textContent = pendingCount > 0 ? `${pendingCount} pendiente${pendingCount === 1 ? '' : 's'}` : 'Todo al día';
			pendingPill.classList.toggle('is-ok', pendingCount === 0);
		}
		setMovementsCollapsed(pendingCount === 0 && state.rows.length > 0);
	};

	const fetchTransactions = async () => {
		const data = await api(`transactions?month=${state.currentMonth}&limit=200`);
		const rows = (data.data || []).map((tx) => {
			const account = state.accounts.find((a) => Number(a.id) === Number(tx.source_account_id || tx.destination_account_id));
			const category = state.categories.find((c) => Number(c.id) === Number(tx.category_id));
			return Object.assign({}, tx, {
				account_name: account ? account.name : 'Cuenta común',
				category_name: category ? category.name : ''
			});
		});
		await Promise.all(rows.map(async (row) => {
			const atts = await api(`transactions/${row.id}/attachments`);
			row.attachment_count = (atts.data || []).length;
			row.has_ticket = row.attachment_count > 0;
		}));
		state.rows = rows;
		tableBody.innerHTML = '';
		emptyState.hidden = rows.length > 0;

		rows.forEach((tx) => {
			const tr = document.createElement('tr');
			const hasTicket = tx.has_ticket || Number(tx.attachment_count || 0) > 0;
			const actionButtons = `
				<div class="ccf-row-actions">
					<button type="button" class="ccf-btn ccf-btn-soft" data-action="edit" data-id="${tx.id}">Editar</button>
					<button type="button" class="ccf-btn ccf-btn-soft" data-action="attach" data-id="${tx.id}">Adjuntar</button>
					<button type="button" class="ccf-btn ccf-btn-soft" data-action="ticket" data-id="${tx.id}">Ver</button>
					<button type="button" class="ccf-btn ccf-btn-danger" data-action="delete" data-id="${tx.id}">Borrar</button>
				</div>`;
			tr.innerHTML = [
				renderTransactionCell('Fecha', safeLabel(tx.transaction_date, '-')),
				renderTransactionCell('Tipo', transactionTypeLabel(tx.type)),
				renderTransactionCell('Concepto', safeLabel(tx.description, 'Sin concepto')),
				renderTransactionCell('Categoría', safeLabel(tx.category_name, 'Sin categoría')),
				renderTransactionCell('Cuenta', safeLabel(tx.account_name, 'Cuenta común')),
				renderTransactionCell('Importe', fmt(tx.amount)),
				renderTransactionCell('Ticket', `<span class="ccf-ticket ${hasTicket ? 'is-on' : ''}">${hasTicket ? 'Con ticket' : 'Sin ticket'}</span>`),
				renderTransactionCell('Estado', transactionStatusLabel(tx.status)),
				renderTransactionCell('Acciones', actionButtons)
			].join('');
			tableBody.appendChild(tr);
		});
		syncMovementsVisibility();
	};

	const refreshAll = async () => {
		setFeedback(tableFeedback, 'Actualizando…');
		try {
			const trendFrom = monthOffset(state.currentMonth, -5);
			const [summary, incomeLine, byCat, trend, budgetActual] = await Promise.all([
				api(`dashboard/month-summary?month_key=${state.currentMonth}`),
				api(`charts/income-vs-common?from=${state.currentMonth}&to=${state.currentMonth}`),
				api(`charts/common-expense-by-category?month=${state.currentMonth}`),
				api(`charts/common-budget-trend?from=${trendFrom}&to=${state.currentMonth}`),
				api(`charts/common-budget-vs-actual?month=${state.currentMonth}`)
			]);
			renderKpis(summary, budgetActual);
			renderCharts(incomeLine, byCat, trend);
			await fetchTransactions();
			setFeedback(tableFeedback, 'Datos al día.');
		} catch (err) {
			setFeedback(tableFeedback, err.message, true);
		}
	};

	const isCategoryRequired = (type) => ['expense', 'income'].includes(type);
	const validateBeforeSave = (payload) => {
		if (!payload.transaction_date) return 'Debes seleccionar una fecha.';
		if (!payload.type) return 'Debes seleccionar un tipo de movimiento.';
		if (!payload.description || payload.description.trim().length === 0) return 'Debes indicar un concepto.';
		if (!payload.amount || Number(payload.amount) <= 0) return 'El importe no es válido.';
		if (!state.commonAccountId) return 'No hay una cuenta común activa. Crea una en administración.';
		if (isCategoryRequired(payload.type) && !payload.category_id) return 'Debes seleccionar una categoría.';
		return '';
	};

	const normalizeTransactionError = (message) => {
		const msg = String(message || '').toLowerCase();
		if (msg.includes('seleccionar una categoría') || msg.includes('missing_category')) return 'Debes seleccionar una categoría.';
		if (msg.includes('cuenta común')) return 'No hay una cuenta común activa. Crea una en administración.';
		if (msg.includes('common')) return 'Solo se pueden usar cuentas comunes.';
		if (msg.includes('amount') || msg.includes('importe')) return 'El importe no es válido.';
		return message || 'No se pudo guardar el movimiento.';
	};

	const normalizeCategoryError = (message) => message || 'No se pudo crear la categoría.';

	const loadCatalogs = async () => {
		const [acc, cat] = await Promise.all([
			api('accounts?status=active&type=common').catch(() => ({ data: [] })),
			api('categories?active=1').catch(() => ({ data: [] }))
		]);
		state.accounts = acc.data || [];
		state.categories = cat.data || [];
		state.commonAccountId = state.accounts.length ? Number(state.accounts[0].id) : null;
		if (field('source_account_id')) field('source_account_id').value = state.commonAccountId || '';
		renderCatalogs();
	};

	const openModal = (tx) => {
		movementForm.reset();
		setFeedback(modalFeedback, '');
		setFeedback(categoryCreateFeedback, '');
		categoryWrap.hidden = true;
		if (field('id')) field('id').value = tx?.id || '';
		if (field('transaction_date')) field('transaction_date').value = tx?.transaction_date || `${state.currentMonth}-01`;
		if (field('type')) field('type').value = tx?.type || 'expense';
		if (field('description')) field('description').value = tx?.description || '';
		if (field('category_id')) field('category_id').value = tx?.category_id || '';
		if (field('source_account_id')) field('source_account_id').value = tx?.source_account_id || state.commonAccountId || '';
		if (field('amount')) field('amount').value = tx?.amount || '';
		if (field('status')) field('status').value = tx?.status || 'posted';
		modalTitle.textContent = tx ? 'Editar movimiento' : 'Nuevo movimiento';
		openMovementModal();
	};

	if (toggleMovementsBtn) {
		toggleMovementsBtn.addEventListener('click', () => {
			const collapsed = movementsSection.classList.contains('is-collapsed');
			setMovementsCollapsed(!collapsed);
		});
	}

	document.getElementById('ccf-new-movement').addEventListener('click', () => openModal());
	document.getElementById('ccf-modal-close').addEventListener('click', () => closeMovementModal());
	document.getElementById('ccf-open-create-category').addEventListener('click', () => {
		categoryWrap.hidden = false;
		categoryInput.focus();
	});
	document.getElementById('ccf-create-category-cancel').addEventListener('click', () => {
		categoryWrap.hidden = true;
		setFeedback(categoryCreateFeedback, '');
	});

	categorySelect.addEventListener('change', () => {
		if (field('category_id') && field('category_id').value) setFeedback(modalFeedback, '');
	});

	document.getElementById('ccf-create-category-submit').addEventListener('click', async () => {
		const name = categoryInput.value.trim();
		if (!name) return setFeedback(categoryCreateFeedback, 'Escribe un nombre para la categoría.', true);
		try {
			setFeedback(modalFeedback, '');
			const response = await api('categories', { method: 'POST', body: JSON.stringify({ name, active: 1 }) });
			await loadCatalogs();
			const createdId = Number(response?.id || response?.data?.id || 0);
			if (createdId > 0) {
				if (field('category_id')) field('category_id').value = String(createdId);
			} else {
				const createdCategory = state.categories.find((category) => String(category.name || '').toLowerCase() === name.toLowerCase());
				if (field('category_id')) field('category_id').value = createdCategory ? String(createdCategory.id) : '';
			}
			if (!field('category_id') || !field('category_id').value) throw new Error('Categoría creada, pero no se pudo seleccionar automáticamente. Selecciónala manualmente.');
			categoryInput.value = '';
			categoryWrap.hidden = true;
			setFeedback(categoryCreateFeedback, 'Categoría creada correctamente.');
			setFeedback(modalFeedback, '');
		} catch (err) {
			setFeedback(categoryCreateFeedback, normalizeCategoryError(err.message), true);
		}
	});

	monthInput.addEventListener('change', () => {
		state.currentMonth = monthInput.value;
		refreshAll();
	});

	movementForm.addEventListener('submit', async (e) => {
		e.preventDefault();
		const fd = new FormData(movementForm);
		const txId = fd.get('id');
		const payload = {
			transaction_date: fd.get('transaction_date'),
			type: fd.get('type'),
			description: fd.get('description'),
			category_id: fd.get('category_id') || null,
			source_account_id: state.commonAccountId,
			amount: fd.get('amount'),
			status: fd.get('status')
		};
		const validationError = validateBeforeSave(payload);
		if (validationError) return setFeedback(modalFeedback, validationError, true);
		try {
			const result = txId
				? await api(`transactions/${txId}`, { method: 'PUT', body: JSON.stringify(payload) })
				: await api('transactions', { method: 'POST', body: JSON.stringify(payload) });
			const realId = txId || result.id;
			if (!realId) throw new Error('No se pudo guardar el movimiento.');
			const files = document.getElementById('ccf-attachments').files;
			if (files.length) {
				const upload = new FormData();
				Array.from(files).forEach((f) => upload.append('files[]', f));
				await api(`transactions/${realId}/attachments`, { method: 'POST', body: upload });
			}
			closeMovementModal();
			await refreshAll();
			setFeedback(tableFeedback, 'Movimiento guardado correctamente.');
			await notifyInBrowser(
				txId ? 'Movimiento actualizado' : 'Movimiento creado',
				payload.description ? `Concepto: ${payload.description}` : 'Se ha guardado correctamente en tu cuenta común.'
			);
		} catch (err) {
			setFeedback(modalFeedback, normalizeTransactionError(err.message), true);
		}
	});

	tableBody.addEventListener('click', async (e) => {
		const btn = e.target.closest('button[data-action][data-id]');
		if (!btn) return;
		const id = Number(btn.dataset.id);
		const tx = state.rows.find((row) => Number(row.id) === id);
		if (!tx) return;
		try {
			if (btn.dataset.action === 'edit') return openModal(tx);
			if (btn.dataset.action === 'delete') {
				if (!window.confirm('¿Borrar este movimiento?')) return;
				await api(`transactions/${id}`, { method: 'DELETE' });
			}
			if (btn.dataset.action === 'attach') {
				inlineAttachment.onchange = async () => {
					if (!inlineAttachment.files.length) return;
					const upload = new FormData();
					upload.append('file', inlineAttachment.files[0]);
					await api(`transactions/${id}/attachments`, { method: 'POST', body: upload });
					await refreshAll();
					inlineAttachment.value = '';
				};
				inlineAttachment.click();
				return;
			}
			if (btn.dataset.action === 'ticket') {
				const atts = await api(`transactions/${id}/attachments`);
				if (!atts.data || !atts.data.length) return setFeedback(tableFeedback, 'Este movimiento aún no tiene ticket.', true);
				window.open(atts.data[0].url, '_blank');
				return;
			}
			await refreshAll();
		} catch (err) {
			setFeedback(tableFeedback, err.message, true);
		}
	});

	(async () => {
		try {
			await loadCatalogs();
			monthInput.value = state.currentMonth;
			await refreshAll();
		} catch (err) {
			setFeedback(tableFeedback, normalizeTransactionError(err.message), true);
		}
	})();

	// Hub simple (Control Hogar + Caja Común): si ambos están en la página, crear pestañas y alternar.
	if (document.getElementById('ch-hub-tabs')) { return; }
  (function initCHCCFHub(){
    const ch = document.querySelector('.ch-app');
    const ccf = document.querySelector('.ccf-app');
    if (!ch || !ccf) return;
    if (document.getElementById('ch-ccf-hub-tabs')) return;

    const wrap = document.createElement('div');
    wrap.id = 'ch-ccf-hub-tabs';
    wrap.className = 'ch-hub-tabs-wrap';

    const tabs = document.createElement('div');
    tabs.className = 'ch-user-buttons ch-hub-tabs';

    const btn = (label, key) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      b.className = 'ch-user-select';
      b.dataset.hubKey = key;
      return b;
    };

    const b1 = btn('Control Hogar', 'ch');
    const b2 = btn('Caja Común', 'ccf');
    tabs.appendChild(b1);
    tabs.appendChild(b2);
    wrap.appendChild(tabs);

    // Insertar justo antes del primer módulo (normalmente Control Hogar).
    const parent = ch.parentElement;
    parent.insertBefore(wrap, ch);

    const setActive = (key) => {
      const showCH = key === 'ch';
      ch.style.display = showCH ? '' : 'none';
      ccf.style.display = showCH ? 'none' : '';
      [b1, b2].forEach(b => {
        const active = b.dataset.hubKey === key;
        b.classList.toggle('is-active', active);
      });
      try { localStorage.setItem('ch_ccf_active_tab', key); } catch(e) {}
    };

    b1.addEventListener('click', () => setActive('ch'));
    b2.addEventListener('click', () => setActive('ccf'));

    let initial = 'ccf';
    try { initial = localStorage.getItem('ch_ccf_active_tab') || 'ccf'; } catch(e) {}
    if (initial !== 'ch' && initial !== 'ccf') initial = 'ccf';
    setActive(initial);
  })();
})();
