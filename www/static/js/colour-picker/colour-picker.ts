import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import tinycolor from "tinycolor2";
import "./colour-picker.css";

// These are for the colour picker swatches
// We want to use the first 3 unique colours from skin config, and then add the constant colours
const skinColours: string[] = window.sbProps.colourDefaults["skin"];
const constantColours: string[] = window.sbProps.colourDefaults["constant"];

// We only want the first 3 unique skinColours, if there are less than 3 unique colours, we'll use the first 3 colours but bias towards the unique colours
const uniqueSkinColours = [...new Set(skinColours)];
const chosenSkinColours =
    uniqueSkinColours.length > 3
        ? uniqueSkinColours.slice(0, 3)
        : [...uniqueSkinColours, ...skinColours].slice(0, 3);

const colorPickerDefaults = {
    theme: "default",
    margin: 10,
    format: "hex",
    alpha: true,
    focusInput: true,
    swatches: [...chosenSkinColours, ...constantColours],
};

Coloris.init();

document.addEventListener("colourPickerInitialize", (e) => {
    const options = Object.assign(colorPickerDefaults, e.detail.options);

    const targetEl = document.querySelector(e.detail.target);
    if (!targetEl) {
        console.warn(`colourPickerInitialize: target element ${e.detail.target} not found`)
    }

    // If value is of format "ffffff" then append "#" to correct HEX format, otherwise leave as is
    if (targetEl.value && targetEl.value[0] !== "#" && targetEl.value.length === 6) {
        targetEl.value = `#${targetEl.value}`;
    }

    Coloris({
        el: e.detail.target,
        ...options,
    });
});

window.tinycolor = tinycolor;
