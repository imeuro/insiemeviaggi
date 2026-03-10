export interface ESendAdminAppConfig {
	baseRestUrl: string;
	iframeUrl: string;
	nonce: string;
	isConnected: boolean;
	isValidRedirectUri: boolean;
	storeCurrency: string;
	headers: {
		'x-elementor-send': string;
		'x-elementor-apps': string;
		Authorization?: string;
	};
	siteData: {
		clientId: string;
		pluginVersion: string;
	};
}

// TODO: consider moving this to a global.d.ts file
declare global {
	interface Window {
		eSendAdminAppConfig: ESendAdminAppConfig;
	}

	let eSendAdminAppConfig: ESendAdminAppConfig;
}

export const config: ESendAdminAppConfig = window.eSendAdminAppConfig;
