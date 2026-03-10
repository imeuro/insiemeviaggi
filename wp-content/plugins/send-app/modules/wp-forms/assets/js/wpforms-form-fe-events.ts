import FormFeEvents from "../../../../core/assets/js/form-fe-events";

declare const eSendWpformsSettings: {
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

declare const wpforms: any;

export default class WpFormsFormFeEvents extends FormFeEvents {
	init() {
		this.formSelectors = eSendWpformsSettings.formSelectors;
		this.ajaxUrl = eSendWpformsSettings.ajaxUrl;
		this.nonce = eSendWpformsSettings.nonce;
		this.viewedThreshold = eSendWpformsSettings.viewedThreshold;
		this.viewedAction = eSendWpformsSettings.viewedAction;
		this.abandonedAction = eSendWpformsSettings.abandonedAction;
		this.debugOn = eSendWpformsSettings.debugOn;
		this.idPrefix = eSendWpformsSettings.idPrefix;
	}
	
	getFormId(form: Element): string {
		return form.querySelector( 'input[name="wpforms[id]"]' )?.getAttribute( 'value' ) || '';
	}

	getPostId(form: Element): string {
		return form.querySelector( 'input[name="page_id"]' )?.getAttribute( 'value' ) || '';
	}

	prepareFormData(form: Element, action: string): FormData {
		const formData = super.prepareFormData(form, action);
		
		// Add WPForms specific data
		const pageTitle = form.querySelector( 'input[name="page_title"]' )?.getAttribute( 'value' ) || '';
		const pageUrl = form.querySelector( 'input[name="page_url"]' )?.getAttribute( 'value' ) || '';
		const urlReferer = form.querySelector( 'input[name="url_referer"]' )?.getAttribute( 'value' ) || '';

		if (pageTitle) formData.append('page_title', pageTitle);
		if (pageUrl) formData.append('page_url', pageUrl);
		if (urlReferer) formData.append('url_referer', urlReferer);

		return formData;
	}
}
window.addEventListener( 'load', () => {
	if (typeof wpforms !== 'undefined') {
			new WpFormsFormFeEvents();
	}
} );