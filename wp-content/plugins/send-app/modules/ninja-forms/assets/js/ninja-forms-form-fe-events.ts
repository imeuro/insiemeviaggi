import FormFeEvents from "../../../../core/assets/js/form-fe-events";

declare const eSendNinjaFormsSettings: {
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

export default class NinjaFormsFormFeEvents extends FormFeEvents {
	init() {
		this.formSelectors = eSendNinjaFormsSettings.formSelectors;
		this.ajaxUrl = eSendNinjaFormsSettings.ajaxUrl;
		this.nonce = eSendNinjaFormsSettings.nonce;
		this.viewedThreshold = eSendNinjaFormsSettings.viewedThreshold;
		this.viewedAction = eSendNinjaFormsSettings.viewedAction;
		this.abandonedAction = eSendNinjaFormsSettings.abandonedAction;
		this.debugOn = eSendNinjaFormsSettings.debugOn;
		this.idPrefix = eSendNinjaFormsSettings.idPrefix;
	}
	
	getFormId(form: Element): string {
		const formContainer = form.closest('.nf-form-cont');
		const formId = formContainer?.id?.match(/nf-form-(\d+)-cont/)?.[1] || '';
		return formId;
	}

	getPostId(form: Element): string {
		const postId = document.body.className.match(/page-id-(\d+)/)?.[1] || 'error-getting-post-id';
		return postId;
	}
}

window.addEventListener('load', () => {
	new NinjaFormsFormFeEvents();
});
