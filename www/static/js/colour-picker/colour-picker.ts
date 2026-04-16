import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import { isReadable, mostReadable, readability, TinyColor } from "@ctrl/tinycolor";
import { colourPickerInitialiseEventName } from "./colourPickerEvent";
import "./colour-picker.css";

type TinyColorInput = ConstructorParameters<typeof TinyColor>[0];

function tinycolor(color: TinyColorInput): TinyColor {
    return new TinyColor(color);
}

Object.assign(tinycolor, {
    TinyColor,
    isReadable,
    mostReadable,
    readability,
});

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

    Coloris({
        ...colorisOptions,
        el: targetEl,
    });
});

window.tinycolor = tinycolor;
