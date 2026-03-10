import FormFeEvents from "../../../../core/assets/js/form-fe-events";

declare const eSendCf7FormsSettings: {
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

export default class Cf7FormFeEvents extends FormFeEvents {
	init() {
		this.formSelectors = eSendCf7FormsSettings.formSelectors;
		this.ajaxUrl= eSendCf7FormsSettings.ajaxUrl;
		this.nonce = eSendCf7FormsSettings.nonce;
		this.viewedThreshold = eSendCf7FormsSettings.viewedThreshold;
		this.viewedAction = eSendCf7FormsSettings.viewedAction;
		this.abandonedAction = eSendCf7FormsSettings.abandonedAction;
		this.debugOn = eSendCf7FormsSettings.debugOn;
		this.idPrefix = eSendCf7FormsSettings.idPrefix;
	}

	getFormId(form: Element): string {
		return form.querySelector( 'input[name="_wpcf7"]' )?.getAttribute( 'value' ) || '';
	}

	getPostId(form: Element): string {
		return form.querySelector( 'input[name="_wpcf7_container_post"]' )?.getAttribute( 'value' ) || '';
	}
}

window.addEventListener( 'load', () => {
	new Cf7FormFeEvents();
} );
