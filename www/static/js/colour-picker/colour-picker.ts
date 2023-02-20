import "@melloware/coloris/dist/coloris.css";
import Coloris from "@melloware/coloris";
import "./colour-picker.css";

const colorPickerDefaults = {
    theme: "pill",
    margin: 10,
    format: "rgb",
    alpha: true,
    focusInput: true,
    defaultColor: "#000000",
    swatches: [
        "#264653",
        "#2a9d8f",
        "#e9c46a",
        "#f4a261",
        "#e76f51",
        "#d62828",
        "#023e8a",
        "#0077b6",
        "#0096c7",
        "#00b4d8",
        "#48cae4",
        "#86acff",
    ],
};

Coloris.init();

document.addEventListener("colourPickerInitialize", (e) => {
    const options = Object.assign(colorPickerDefaults, e.detail.options);

    Coloris({
        el: e.detail.target,
        ...options,
    });
});
