import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import { fromRatio, isReadable, mostReadable, names, random, readability, TinyColor } from "@ctrl/tinycolor";
import { colourPickerInitialiseEventName } from "./colourPickerEvent";
import "./colour-picker.css";

type TinyColorInput = ConstructorParameters<typeof TinyColor>[0];
type TinyColorValue = Exclude<TinyColorInput, undefined>;
const hexNames = Object.fromEntries(Object.entries(names).map(([name, hex]) => [hex, name]));

/**
 * Preserve the legacy global tinycolor(...) factory while using the ctrl tinycolor package internally.
 * @param color Color input passed through to TinyColor.
 * @returns TinyColor instance for the supplied input.
 */
function tinycolor(color: TinyColorInput): TinyColor {
    return new TinyColor(color);
}

const tinycolorCompat = Object.assign(tinycolor, {
    TinyColor,
    equals(color1?: TinyColorInput, color2?: TinyColorInput) {
        if (!color1 || !color2) {
            return false;
        }

        return new TinyColor(color1).equals(color2);
    },
    fromRatio(ratio?: TinyColorInput) {
        if (!ratio || typeof ratio !== "object") {
            return new TinyColor(ratio);
        }

        return fromRatio(ratio as Parameters<typeof fromRatio>[0]);
    },
    hexNames,
    isReadable,
    mix(color1: TinyColorValue, color2: TinyColorValue, amount?: number) {
        return new TinyColor(color1).mix(color2, amount);
    },
    mostReadable,
    names,
    random,
    readability,
}) as Window["tinycolor"];

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

window.tinycolor = tinycolorCompat as unknown as typeof window.tinycolor;
