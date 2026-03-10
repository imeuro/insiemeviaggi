(() => {
	'use strict';

	const config = window.LTC_WC_ANALYTICS_EXPORT;
	if (!config || !config.adminPostUrl || !config.exportNonce) {
		return;
	}

	const state = {
		isExporting: false,
		form: null,
	};

	const padNumber = (value) => String(value).padStart(2, '0');

	const formatDateTime = (date, endOfDay) => {
		const year = date.getFullYear();
		const month = padNumber(date.getMonth() + 1);
		const day = padNumber(date.getDate());
		const time = endOfDay ? '23:59:59' : '00:00:00';
		return `${year}-${month}-${day} ${time}`;
	};

	const startOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0);
	const endOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59);

	const getPeriodRange = (period) => {
		const today = new Date();
		const normalizedPeriod = String(period || '').toLowerCase();

		if (normalizedPeriod === 'today') {
			return {
				after: formatDateTime(startOfDay(today), false),
				before: formatDateTime(endOfDay(today), true),
			};
		}

		if (normalizedPeriod === 'yesterday') {
			const yesterday = new Date(today);
			yesterday.setDate(yesterday.getDate() - 1);
			return {
				after: formatDateTime(startOfDay(yesterday), false),
				before: formatDateTime(endOfDay(yesterday), true),
			};
		}

		if (normalizedPeriod === 'week') {
			const start = new Date(today);
			start.setDate(start.getDate() - 6);
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(today), true),
			};
		}

		if (normalizedPeriod === 'last_week') {
			const end = new Date(today);
			end.setDate(end.getDate() - 1);
			const start = new Date(end);
			start.setDate(start.getDate() - 6);
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(end), true),
			};
		}

		if (normalizedPeriod === 'month') {
			const start = new Date(today.getFullYear(), today.getMonth(), 1);
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(today), true),
			};
		}

		if (normalizedPeriod === 'last_month') {
			const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
			const end = new Date(today.getFullYear(), today.getMonth(), 0);
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(end), true),
			};
		}

		if (normalizedPeriod === 'quarter' || normalizedPeriod === 'last_quarter') {
			const currentQuarter = Math.floor(today.getMonth() / 3);
			const targetQuarter = normalizedPeriod === 'last_quarter' ? currentQuarter - 1 : currentQuarter;
			const year = targetQuarter < 0 ? today.getFullYear() - 1 : today.getFullYear();
			const quarterIndex = (targetQuarter + 4) % 4;
			const start = new Date(year, quarterIndex * 3, 1);
			const end = normalizedPeriod === 'last_quarter'
				? new Date(year, quarterIndex * 3 + 3, 0)
				: today;
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(end), true),
			};
		}

		if (normalizedPeriod === 'year') {
			const start = new Date(today.getFullYear(), 0, 1);
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(today), true),
			};
		}

		if (normalizedPeriod === 'last_year') {
			const start = new Date(today.getFullYear() - 1, 0, 1);
			const end = new Date(today.getFullYear() - 1, 11, 31);
			return {
				after: formatDateTime(startOfDay(start), false),
				before: formatDateTime(endOfDay(end), true),
			};
		}

		return null;
	};

	const buildReportArgs = () => {
		const params = new URLSearchParams(window.location.search);
		const reportArgs = {};
		const allowedKeys = new Set([
			'before',
			'after',
			'orderby',
			'order',
			'product_includes',
			'product_excludes',
			'variation_includes',
			'variation_excludes',
			'coupon_includes',
			'coupon_excludes',
			'tax_rate_includes',
			'tax_rate_excludes',
			'status_is',
			'status_is_not',
			'customer_type',
			'refunds',
			'match',
			'order_includes',
			'order_excludes',
			'attribute_is',
			'attribute_is_not',
			'segmentby',
			'segments',
			'search',
		]);
		params.forEach((value, key) => {
			if (!allowedKeys.has(key)) {
				return;
			}
			if (value === '') {
				return;
			}
			reportArgs[key] = value;
		});

		if (!reportArgs.after || !reportArgs.before) {
			const period = params.get('period');
			const range = getPeriodRange(period);
			if (range) {
				reportArgs.after = range.after;
				reportArgs.before = range.before;
			}
		}

		reportArgs.page = 1;
		reportArgs.extended_info = true;
		reportArgs.force_cache_refresh = true;
		return reportArgs;
	};

	const isExportTrigger = (element) => {
		if (!element) {
			return false;
		}
		const label = (
			element.getAttribute('aria-label') ||
			element.getAttribute('title') ||
			element.textContent ||
			''
		).trim().toLowerCase();

		if (!label) {
			return false;
		}

		return label.includes('scarica') || label.includes('download');
	};

	const cleanupExportState = () => {
		state.isExporting = false;
		if (state.form && state.form.parentNode) {
			state.form.parentNode.removeChild(state.form);
		}
		state.form = null;
	};

	const startExport = async () => {
		if (state.isExporting) {
			return;
		}

		state.isExporting = true;

		try {
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = config.adminPostUrl;
			form.style.display = 'none';

			const actionInput = document.createElement('input');
			actionInput.type = 'hidden';
			actionInput.name = 'action';
			actionInput.value = 'ltc_export_orders_report';

			const nonceInput = document.createElement('input');
			nonceInput.type = 'hidden';
			nonceInput.name = 'ltc_export_nonce';
			nonceInput.value = config.exportNonce;

			const argsInput = document.createElement('input');
			argsInput.type = 'hidden';
			argsInput.name = 'report_args';
			argsInput.value = JSON.stringify(buildReportArgs());

			form.appendChild(actionInput);
			form.appendChild(nonceInput);
			form.appendChild(argsInput);

			document.body.appendChild(form);
			state.form = form;
			form.submit();
		} catch (error) {
			console.error('Errore export report ordini.', error);
			cleanupExportState();
		}
	};

	document.addEventListener('click', (event) => {
		const trigger = event.target.closest('button, a');
		if (!isExportTrigger(trigger)) {
			return;
		}

		event.preventDefault();
		startExport();
	});
})();
