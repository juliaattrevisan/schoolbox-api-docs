import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import tinycolor from "tinycolor2";
import "./colour-picker.css";

const swatchColours: string[] = window.sbProps.colourDefaults["swatches"];

const colorPickerDefaults = {
    theme: "default",
    margin: 10,
    format: "hex",
    alpha: true,
    focusInput: true,
    swatches: swatchColours,
};

Coloris.init();

document.addEventListener("colourPickerInitialize", (e) => {
    const options = Object.assign(colorPickerDefaults, e.detail.options);

    const targetEl = document.querySelector(e.detail.target);
    if (!targetEl) {
        console.warn(`colourPickerInitialize: target element ${e.detail.target} not found`)
    }

    // If value is of format "ffffff" then append "#" to correct HEX format, otherwise leave as is
    if (targetEl.value && targetEl.value[0] !== "#" && [3, 6, 8].includes(targetEl.value.length)) {
        targetEl.value = `#${targetEl.value}`;
    }

    Coloris({
        el: e.detail.target,
        ...options,
    });
});

window.tinycolor = tinycolor;
