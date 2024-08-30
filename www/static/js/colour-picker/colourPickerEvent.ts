export const colourPickerInitialiseEventName = "colourPickerInitialise";

export interface ColourPickerInitialiseEvent extends CustomEvent {
    detail: Partial<Coloris.ColorisOptions> & Pick<Coloris.ColorisOptions, "el">; // el is required, others are optional
}

/**
 * Trigger the colour picker initialise event
 * @param {ColourPickerInitialiseEvent["detail"]} detail The options to initialise the colour picker with
 */
export function initColourPicker(detail: ColourPickerInitialiseEvent["detail"]): void {
    document.dispatchEvent(new CustomEvent(colourPickerInitialiseEventName, { detail }));
}

export interface ColourPickerDocumentEventHandlerMap {
    [colourPickerInitialiseEventName]: ColourPickerInitialiseEvent;
}
