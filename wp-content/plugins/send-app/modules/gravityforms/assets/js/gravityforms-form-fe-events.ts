import FormFeEvents from "../../../../core/assets/js/form-fe-events";

declare const eSendGravityformsSettings: {
	observer: MutationObserver | null;
	formSelectors: string[];
	ajaxUrl: string;
	nonce: string;
	viewedThreshold: number;
	viewedAction: string;
	abandonedAction: string;
	debugOn: boolean;
	idPrefix: string;
};

export default class GravityformsFormFeEvents extends FormFeEvents {
	init() {
		this.formSelectors = eSendGravityformsSettings.formSelectors;
                this.ajaxUrl = eSendGravityformsSettings.ajaxUrl;
		this.nonce = eSendGravityformsSettings.nonce;
		this.viewedThreshold = eSendGravityformsSettings.viewedThreshold;
		this.viewedAction = eSendGravityformsSettings.viewedAction;
		this.abandonedAction = eSendGravityformsSettings.abandonedAction;
		this.debugOn = eSendGravityformsSettings.debugOn;
		this.idPrefix = eSendGravityformsSettings.idPrefix;
	}

	getFormId(form: Element): string {
		return form.getAttribute('data-formid') || '';
	}

	getPostId(form: Element): string {
		// Gravity Forms doesn't have a built-in post ID field like CF7
		// We'll extract it from the page body class or use a fallback method
		const bodyClass = document.body.className;
		const pageIdMatch = bodyClass.match(/page-id-(\d+)/);
		return pageIdMatch ? pageIdMatch[1] : '';
	}
}

window.addEventListener( 'load', () => {
	new GravityformsFormFeEvents();
} );
