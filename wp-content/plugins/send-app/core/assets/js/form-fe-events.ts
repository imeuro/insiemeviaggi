import {getFormLocation} from "./utils";

export default abstract class FormFeEvents {
	protected observer: MutationObserver | null = null;
	protected formSelectors: string[];
	protected ajaxUrl: string;
	protected nonce: string;
	protected viewedThreshold: number;
	protected viewedAction: string;
	protected abandonedAction: string;
	protected debugOn: boolean;
	protected idPrefix: string;

	protected abandonedEvents: string[] = [ 'beforeunload' ];

	constructor() {
		this.init();
		this.initTracking();
		this.observeDynamicForms();
	}

	abstract init(): void;

	abstract getPostId( form: Element ): string;

	abstract getFormId( form: Element ): string;

	getFormIdAndPosId( form?: Element ): {
		formId: string;
		postId: string;
	} {
		if ( !form ) {
			return { formId: '', postId: '' };
		}

		const postId =
			document.body.className.match(/page-id-(\d+)/)?.[1] ||
			this.getPostId( form );
		const formId = this.getFormId( form ) ?  this.idPrefix + this.getFormId( form ) : '' ;

		return { formId, postId };
	}

	/**
	 * Sets up a MutationObserver to monitor the DOM for dynamically added forms.
	 * Observes the document body for any added child nodes and checks if they match form selectors.
	 * If a matching form is found, it reruns the tracking logic.
	 * @private
	 */
	observeDynamicForms() {
		this.observer = new MutationObserver( ( mutations ) => {
			mutations.forEach( ( mutation ) => {
				if (
					mutation.type === 'childList' &&
					mutation.addedNodes.length > 0
				) {
					mutation.addedNodes.forEach( ( node) => {
						if ( node instanceof Element ) {
							this.handleNewForm( node );
						}
					} );
				}
			} );
		} );

		this.observer.observe( document.body, {
			childList: true,
			subtree: true,
		} );
	}

	/**
	 * Processes a newly added DOM element to determine if it is a form or contains forms.
	 * If the element matches a known form selector and tracking is enabled for that integration,
	 * it sets up tracking for the form(s).
	 * @param {Element} element - The newly added DOM element to process.
	 * @private
	 */
	handleNewForm( element: Element ) {
		const selectorString = this.formSelectors.join(',');
		const formsSet = new Set<Element>();

		if (element.matches(selectorString)) {
			formsSet.add(element);
		}
		element.querySelectorAll(selectorString).forEach(form => formsSet.add( form ) );

		// Process unique forms
		formsSet.forEach( form => {
			this.trackViewedForms( form );
			this.trackAbandonedForms( form );
		} );
	}

	/**
	 * Initializes tracking for all active and enabled integrations.
	 * Loads the integrations, determines which are active and enabled,
	 * and sets up tracking for their forms.
	 * @private
	 */
	initTracking(): void {
		const selectorString = this.formSelectors.join(',');

		const formsSet = new Set<Element>();

		document.querySelectorAll( selectorString).forEach(form => formsSet.add( form ) );

		formsSet.forEach((form) => {
			this.trackViewedForms(form);
			this.trackAbandonedForms(form);
		});
	}

	/**
	 * Tracks when a form is viewed by observing its visibility.
	 * Sends a request when the form is fully visible.
	 * @param {Element} form  - The form element to track.
	 * @private
	 */
	trackViewedForms( form: Element ): void {
		const visibilityObserver = new IntersectionObserver(
			( entries ) => {
				entries.forEach( async ( entry ) => {
					if (
						entry.isIntersecting &&
						entry.intersectionRatio >= this.viewedThreshold
					) {
						const formData = this.prepareFormData( form, this.viewedAction );
						if ( formData.has('form_id') ) {
							const response = await fetch(this.ajaxUrl, {method: 'POST', body: formData});
							if (!response.ok && this.debugOn) {
								console.error('Error tracking form view:', response.statusText);
							}
						}
						visibilityObserver.unobserve(entry.target);
					}
				});
			},
			{ threshold: [ this.viewedThreshold ] }
		);

		visibilityObserver.observe(form);
	}

	/**
	 * Tracks when a form is abandoned (user focuses on the form but leaves the page without submitting).
	 * @param {Element}     form        - The form element to track.
	 * @private
	 */
	trackAbandonedForms( form: Element ): void {
		let formFocused = false;
		let formSubmitted = false;

		form.querySelectorAll( 'input, textarea' ).forEach( ( field) => {
			field.addEventListener( 'focus', () => ( formFocused = true ) );
		} );

		form.addEventListener( 'submit', () => ( formSubmitted = true ) );

		this.abandonedEvents.forEach( ( eventName ) => {
			window.addEventListener( eventName, async () => {
				if ( formFocused && !formSubmitted ) {
					const formData = this.prepareFormData( form, this.abandonedAction );
					if ( formData.has( 'form_id' ) ) {
						const response = await fetch(this.ajaxUrl, {method: 'POST', body: formData});
						if ( !response.ok && this.debugOn ) {
							console.error('Error tracking abandoned form:', response.statusText);
						}
					}
				}
			} )
		} );
	}

	/**
	 *
	 * @param {Element} form
	 * @param action
	 *
	 * @private
	 */
	prepareFormData( form: Element, action: string ): FormData {
		const formData = new FormData();
		const { formId, postId } = this.getFormIdAndPosId( form );

		if ( formId ) {
			formData.append( 'action', action );
			formData.append( 'nonce', this.nonce );
			formData.append( 'form_id', formId );
			formData.append( 'page_id', postId );
			formData.append( 'location', getFormLocation( form ) );
		}

		return formData;
	}
}
