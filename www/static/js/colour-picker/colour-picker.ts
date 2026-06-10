import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import tinycolor from "tinycolor2";
import { colourPickerInitialiseEventName, type ColourPickerInitialiseEvent } from "./colourPickerEvent";
import "./colour-picker.css";

const colorPickerDefaults: Coloris.ColorisOptions = {
    el: [],
    theme: "default",
    margin: 10,
    format: "hex",
    alpha: true,
    focusInput: true,
    swatches: window.sbProps.colourDefaults.swatches,
};

let colourPickerInstanceId = 0;

/**
 * Build the selector Coloris uses to apply virtual instance options to a colour picker input.
 * @param targetEl The input element being initialised.
 * @param el The original Coloris target from the initialise event.
 * @returns A selector that matches the input element.
 */
function getColourPickerInstanceSelector(targetEl: HTMLInputElement, el: Coloris.ColorisOptions["el"]): string {
    if (typeof el === "string") {
        return el;
    }
    const instanceId = `colour-picker-instance-${colourPickerInstanceId}`;
    colourPickerInstanceId += 1;
    targetEl.dataset.colourPickerInstance = instanceId;
    return `[data-colour-picker-instance="${instanceId}"]`;
}

/**
 * Remove binding-only options so the remaining options can be applied as a Coloris virtual instance.
 * @param colorisOptions The event-supplied colour picker options.
 * @returns Options supported by Coloris virtual instances.
 */
function getColourPickerInstanceOptions(
    colorisOptions: ColourPickerInitialiseEvent["detail"],
): Partial<Coloris.ColorisVirtualInstanceOptions> {
    const instanceOptions = { ...colorisOptions } as Partial<ColourPickerInitialiseEvent["detail"]> &
        Partial<Coloris.ColorisVirtualInstanceOptions>;
    // Remove the options that are only available globally: https://github.com/mdbassit/Coloris#simulating-multiple-instances
    delete instanceOptions.el;
    delete instanceOptions.wrap;
    delete instanceOptions.rtl;
    delete instanceOptions.inline;
    delete instanceOptions.defaultColor;
    delete instanceOptions.a11y;
    return instanceOptions;
}

/**
 * Bind Coloris to the target input without applying per-field options as global picker state.
 * @param targetEl The input element being initialised.
 * @param colorisOptions The event-supplied colour picker options.
 */
function bindColourPicker(targetEl: HTMLInputElement, colorisOptions: ColourPickerInitialiseEvent["detail"]): void {
    const bindingOptions: Coloris.ColorisOptions = {
        el: targetEl,
    };
    if (colorisOptions.wrap !== undefined) {
        bindingOptions.wrap = colorisOptions.wrap;
    }
    Coloris(bindingOptions);
}

Coloris.init();
Coloris(colorPickerDefaults);

document.addEventListener(colourPickerInitialiseEventName, (e) => {
    const colorisOptions = e.detail;

    const targetEl =
        typeof colorisOptions.el === "string" ? document.querySelector(colorisOptions.el) : colorisOptions.el;

    if (!(targetEl instanceof HTMLInputElement)) {
        throw new Error(`colourPickerInitialise: target element is not an input element`);
    }

    // If value is of format "ffffff" then append "#" to correct HEX format, otherwise leave as is
    if (targetEl.value && targetEl.value[0] !== "#" && [3, 6, 8].includes(targetEl.value.length)) {
        targetEl.value = `#${targetEl.value}`;
    }

    bindColourPicker(targetEl, colorisOptions);
    Coloris.setInstance(
        getColourPickerInstanceSelector(targetEl, colorisOptions.el),
        getColourPickerInstanceOptions(colorisOptions),
    );
});

window.tinycolor = tinycolor;
