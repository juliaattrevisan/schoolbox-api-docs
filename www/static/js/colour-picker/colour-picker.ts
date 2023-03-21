import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import tinycolor from "tinycolor2";
import "./colour-picker.css";

const colorPickerDefaults = {
    theme: "default",
    margin: 10,
    format: "hex",
    alpha: true,
    focusInput: true,
    swatches: window.sbProps.colourDefaults
};

Coloris.init();

document.addEventListener("colourPickerInitialize", (e) => {
    const options = Object.assign(colorPickerDefaults, e.detail.options);

    Coloris({
        el: e.detail.target,
        ...options,
    });
});

window.tinycolor = tinycolor;
