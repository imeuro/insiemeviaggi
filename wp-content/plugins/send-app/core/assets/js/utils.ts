type FormLocation = 'page' | 'header' | 'footer' | 'popup';

/**
 * Determines the location of a form on the page.
 * @param {Element} form - The form element to analyze.
 * @return {FormLocation} The location of the form (`header`, `footer`, `popup`, or `page`).
 */
export function getFormLocation( form: Element ): FormLocation {
	// TODO: refactor this to use a more efficient method
	if ( hasAncestorTag( form, 'header' ) ) return 'header';
	if ( hasAncestorTag( form, 'footer' ) ) return 'footer';
	return getAncestorAttribute( form, 'data-elementor-type' );
}

/**
 * Checks if an element or its ancestors match a specific tag name or have a specified attribute with a given value.
 * @param {Element} element          - The element to start searching from.
 * @param {string}  tagName          - The tag name to search for in the element's ancestors.
 * @return {boolean} `true` if the condition is met, otherwise `false`.
 * @private
 */
function hasAncestorTag(
	element: Element,
	tagName: string,
): boolean {
	while (element) {
		if (tagName && element.tagName?.toLowerCase() === tagName.toLowerCase())
			return true;
		element = element.parentElement!;
	}
	return false;
}

/**
 * Checks if an element or its ancestors match a specific tag name or have a specified attribute with a given value.
 * @param {Element} element          - The element to start searching from.
 * @param {string}  [attributeName]  - The attribute name to check.
 * @return FormLocation
 * @private
 */
function getAncestorAttribute(
	element: Element,
	attributeName: string,
): FormLocation {
	const supportedValues = [ 'header', 'footer', 'popup'];
	while (element) {
		const attributeValue = element.getAttribute(attributeName);
		if (supportedValues.includes(attributeValue)) {
			return attributeValue as FormLocation;
		}
		element = element.parentElement!;
	}

	return 'page';
}
