import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import tinycolor from "tinycolor2";
import { colourPickerInitialiseEventName } from "./colourPickerEvent";
import "./colour-picker.css";

const colorPickerDefaults = {
    theme: "default",
    margin: 10,
    format: "hex",
    alpha: true,
    focusInput: true,
    swatches: window.sbProps.colourDefaults.swatches,
};

Coloris.init();

document.addEventListener(colourPickerInitialiseEventName, (e) => {
    const colorisOptions: Coloris.ColorisOptions = Object.assign(colorPickerDefaults, e.detail);

    const targetEl =
        typeof colorisOptions.el === "string" ? document.querySelector(colorisOptions.el) : colorisOptions.el;

    if (!(targetEl instanceof HTMLInputElement)) {
        throw new Error(`colourPickerInitialise: target element is not an input element`);
    }

    // If value is of format "ffffff" then append "#" to correct HEX format, otherwise leave as is
    if (targetEl.value && targetEl.value[0] !== "#" && [3, 6, 8].includes(targetEl.value.length)) {
        targetEl.value = `#${targetEl.value}`;
    }

    Coloris(colorisOptions);
});

window.tinycolor = tinycolor;
