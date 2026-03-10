declare const ajaxurl: string;

(() => {
	function selectDeactivateLink(): HTMLAnchorElement | null {
		return document.querySelector(
			'#the-list [data-slug="send-app"] span.deactivate a'
		);
	}

	function getModal(): HTMLDialogElement | null {
		return document.getElementById('send-app-deactivate-feedback-modal') as HTMLDialogElement | null;
	}

	function getForm(): HTMLFormElement | null {
		return document.getElementById('send-app-deactivate-feedback-dialog-form') as HTMLFormElement | null;
	}

	function getSubmitButton(): HTMLButtonElement | null {
		return document.querySelector('.send-app-dialog-submit') as HTMLButtonElement | null;
	}

	function getSkipButton(): HTMLButtonElement | null {
		return document.querySelector('.send-app-dialog-skip') as HTMLButtonElement | null;
	}

	async function sendFeedback(form: HTMLFormElement): Promise<void> {
		const formData = new FormData(form);
		await fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		});
	}

	function bindRadioChange(modal: HTMLDialogElement) {
		const inputs = modal.querySelectorAll<HTMLInputElement>('.send-app-deactivate-feedback-dialog-input');
		inputs.forEach((input) => {
			input.addEventListener('change', () => {
				modal.setAttribute('data-feedback-selected', input.value);
			});
			if (input.checked) {
				modal.setAttribute('data-feedback-selected', input.value);
			}
		});
	}

	function init() {
		const deactivateLink = selectDeactivateLink();
		const modal = getModal();
		const form = getForm();
		const submitBtn = getSubmitButton();
		const skipBtn = getSkipButton();

		if (!deactivateLink || !modal || !form || !submitBtn || !skipBtn) {
			return;
		}

		bindRadioChange(modal);

		let targetHref = '';

		deactivateLink.addEventListener('click', (event) => {
			event.preventDefault();
			targetHref = deactivateLink.getAttribute('href') || '';
			if (typeof modal.showModal === 'function') {
				modal.showModal();
			} else {
				window.location.href = targetHref;
			}
		});

		submitBtn.addEventListener('click', async () => {
			submitBtn.classList.add('send-app-loading');
			try {
				await sendFeedback(form);
			} catch (e) {
				// ignore errors and proceed to deactivate
			} finally {
				window.location.href = targetHref || deactivateLink.getAttribute('href') || '';
			}
		});

		skipBtn.addEventListener('click', () => {
			window.location.href = targetHref || deactivateLink.getAttribute('href') || '';
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

