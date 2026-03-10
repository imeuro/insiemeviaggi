import { Iframe } from './iframe';

// Message Types
type MessageType = 
	| 'get-site-config'
	| 'connect/reconnect'
	| 'connect/switch-domain'
	| 'connect/authorize'
	| 'get-integrations'
	| 'get-integrations-status'
	| 'get-forms'
	| 'integrations/forms/all'
	| 'integrations-disconnect'
	| 'integrations-connect'
	| 'connect-plugin'
	| 'get-forms-list-by-source-id'
	| 'gallery-images'
	| 'activate-form-tracking'
	| 'activate-plugin'
	| 'create-plugin-forms'
	| 'sync-woo'
	| 'set-current-path'
	| 'connect/refresh-token';

// Data Payload Interfaces
interface GalleryParams {
	search?: string;
	per_page?: number;
	page?: number;
}

interface FormTrackingData {
	sourceNameId: string;
	formId: string;
	status: boolean;
}

interface GalleryMessageData {
	search?: string;
	per_page?: number;
	page?: number;
}

// Generic Message Event Interface
interface AppMessageEvent {
	message: MessageType;
	data?: string | FormTrackingData | GalleryMessageData | any;
}

declare const eSendAdminAppConfig: {
	baseRestUrl: string;
	nonce: string;
	headers: Record<string, string>;
};

const App = (function () {
	class SingletonApp {
		private static instance: SingletonApp;
		iframe: Iframe;

		private constructor() {
			this.iframe = new Iframe();
			this.init();
		}

		static getInstance(): SingletonApp {
			if (!SingletonApp.instance) {
				SingletonApp.instance = new SingletonApp();
			}
			return SingletonApp.instance;
		}

		private init() {
			this.initEventListeners();
		}

		private async getSiteConfig(
			streamName = 'get-site-config'
		): Promise<void> {
			const data = {
				streamName,
				data: this.getInitData(),
			};

			this.sendMessage(data, '*');
		}

		private async reconnect(): Promise<void> {
			await this.streamData(['connect/reconnect', '*'], 'POST');
		}

		private async authorize(): Promise<void> {
			const res = await this.streamData(
				['connect/authorize', '*'],
				'POST'
			);
			if (res.data) {
				window.open(res.data as string, '_self');
			}
		}

		private async switchDomain(): Promise<void> {
			const res = await this.streamData(
				['connect/switch_domain', '*'],
				'POST'
			);

			if (res.data) {
				window.open(res.data as string, '_self');
			}
		}

		private async sendHeaderData(): Promise<void> {
			await this.streamData(['connect/refresh-token', '*'], 'POST');
		}

		private async handleGetGallery(data: GalleryMessageData): Promise<void> {
			const params = data;
			let galleryStream = 'gallery-images';
			
			if (params && Object.keys(params).length > 0) {
				const urlParams = new URLSearchParams();
				
				if (params.search?.trim()) {
					urlParams.append('search', params.search);
				}
				if (params.per_page) {
					urlParams.append('per_page', params.per_page.toString());
				}
				if (params.page) {
					urlParams.append('page', params.page.toString());
				}
				
				const queryString = urlParams.toString();
				if (queryString) {
					galleryStream += '?' + queryString;
				}
			}
			
			await this.streamData([galleryStream, '*'], 'GET', {}, {}, 'gallery-images');
		}

		private refreshListener() {
			document.addEventListener('DOMContentLoaded', () => {
				const [navigationEntry] = performance.getEntriesByType(
					'navigation'
				) as PerformanceNavigationTiming[];

				if (navigationEntry && navigationEntry.type === 'reload') {
					localStorage.setItem('refresh', 'true');
				}
			});
		}

		private closeListener() {
			window.addEventListener('unload', () => {
				if (!localStorage.getItem('refresh')) {
					this.resetLocalStorage();
				}
			});
		}

		private initEventListeners() {
			this.refreshListener();
			this.closeListener();

			window.addEventListener('message', async (event: MessageEvent) => {
				const { message, data } = event.data;

				switch (message) {
					case 'get-site-config':
						await this.getSiteConfig();
						break;
					case 'connect/reconnect':
						await this.reconnect();
						break;
					case 'connect/switch-domain':
						await this.switchDomain();
						break;
					case 'connect/authorize':
						await this.authorize();
						break;
					case 'get-integrations':
						await this.streamData(['integrations', '*']);
						break;
					case 'get-integrations-status':
						await this.streamData(['get-integrations-status', '*']);
						break;
					case 'get-forms':
						await this.streamData(['forms', '*']);
						break;
					case 'integrations/forms/all':
						await this.streamData(['integrations/forms/all', '*']);
						break;
					case 'integrations-disconnect':
						await this.streamData(
							[`integrations/${data}/disconnect`, '*'],
							'POST',
							{
								sourceNameId: data,
							}
						);
						break;
					case 'integrations-connect':
						await this.streamData(
							[`integrations/${data}/connect`, '*'],
							'POST',
							{
								sourceNameId: data,
							}
						);
						break;
					case 'connect-plugin':
						await this.streamData(['connect-plugin', '*'], 'POST', {
							sourceNameId: data,
						});
						break;
					case 'get-forms-list-by-source-id':
						await this.streamData([
							`integrations/${data}/forms`,
							'*',
						], 'GET', {}, {}, message);
						break;
					case 'gallery-images':
						await this.handleGetGallery(data);
						break;
					case 'activate-form-tracking':
						await this.streamData(
							[
								`integrations/${data.sourceNameId}/forms/${data.formId}`,
								'*',
							],
							'POST',
							{
								trackingEnabled: data.status,
							},
							{},
							message
						);
						break;

					case 'activate-plugin':
						await this.streamData(
							['activate-plugin', '*'],
							'POST',
							{
								sourceNameId: data,
							}
						);
						break;
					case 'create-plugin-forms':
						await this.streamData(
							['create-plugin-forms', '*'],
							'POST'
						);
						break;
					case 'sync-woo':
						await this.streamData(['sync-woo', '*']);
						break;
					case 'set-current-path':
						localStorage.setItem('route', data as string);
						break;
					case 'connect/refresh-token':
						await this.sendHeaderData();
						break;
					default:
						// eslint-disable-next-line no-console
						console.warn(
							'Unknown request from plugin iframe (react app):',
							message
						);
				}
			});
		}

		getStreamNames(): string[][] {
			return [
				['integrations', '*'],
				['forms', '*'],
				['deactivate-plugin', '*'],
				['activate-plugin', '*'],
			];
		}

		async streamData(
			streamNames: string[],
			method = 'GET',
			body: Record<string, unknown> = {},
			headers: Record<string, string> = {},
			routeNickname: string = ''
		): Promise<Record<string, unknown>> {
			headers = {
				'X-WP-Nonce': eSendAdminAppConfig.nonce,
				'Content-Type': 'application/json',
				...eSendAdminAppConfig.headers,
			};

			const data = await this.fetchStreamData(
				streamNames[0],
				method,
				body,
				headers
			);
			data.streamName = streamNames[0];
			if ( routeNickname !== '' ) {
				data.streamName = routeNickname;
			}
			this.sendMessage(data, '*');
			return data;
		}

		sendMessage(
			message: Record<string, unknown>,
			targetOrigin = '*'
		): void {
			this.iframe.sendMessage(message, targetOrigin);
		}

		resetLocalStorage(): void {
			localStorage.removeItem('refresh');
			localStorage.removeItem('route');
		}

		getInitData(): Record<string, unknown> {
			return eSendAdminAppConfig;
		}

		private async fetchStreamData(
			stream: string,
			method = 'GET',
			body: Record<string, unknown> | null = null,
			headers: Record<string, string> | null = null
		): Promise<Record<string, unknown>> {
			const restUrl = `${eSendAdminAppConfig.baseRestUrl}e-send/v1/${stream}`;
			const options: JQueryAjaxSettings = {
				url: restUrl,
				method,
			};

			if (headers) {
				options.headers = headers;
			}
			if (method.toUpperCase() === 'POST' && body) {
				options.data = JSON.stringify(body);
			}

			try {
				const response = await jQuery.ajax(options);
				return response;
			} catch (error) {
				this.sendMessage({
					error: error?.statusText || 'error',
					data: `Failed to fetch data for stream ${stream}`
				});
				throw error; // Ensure errors propagate for debugging
			}
		}
	}

	return SingletonApp.getInstance();
})();

export default App;
