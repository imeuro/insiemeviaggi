export class Iframe {
	constructor() {
		this.init();
	}

	init() {
		const app = document.querySelector('.send-app-admin-app');
		app.classList.add('esend-app');
		const iframe = document.createElement('iframe');
		iframe.classList.add('esend-iframe');
		iframe.setAttribute('allow', 'clipboard-write; fullscreen');
		iframe.setAttribute('allowfullscreen', 'true');
		let url = null;
		if (localStorage.getItem('refresh')) {
			const path = localStorage.getItem('route');
			if (path) {
				url = `${eSendAdminAppConfig.iframeUrl}${path}`;
			} else {
				url = eSendAdminAppConfig.iframeUrl;
			}
			localStorage.removeItem('refresh');
		} else {
			url = eSendAdminAppConfig.iframeUrl;
		}

		// Constructing the URL
		const srcUrl = new URL(url);
		srcUrl.searchParams.set('t', new Date().getTime());
		iframe.src = srcUrl.toString();
		app.appendChild(iframe);
		this.iframe = iframe;
	}

	sendMessage(message, targetOrigin = '*') {
		const domain = window.location.hostname;
		this.iframe.contentWindow.postMessage(
			{ message, domain },
			targetOrigin
		);
	}
}
