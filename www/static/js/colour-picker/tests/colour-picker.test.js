jest.mock("@melloware/coloris", () => {
    const coloris = jest.fn();
    coloris.init = jest.fn();
    coloris.setInstance = jest.fn();

    return {
        __esModule: true,
        default: coloris,
    };
});

import Coloris from "@melloware/coloris";
import { colourPickerInitialiseEventName } from "../colourPickerEvent";
import "../colour-picker";

const mockColoris = Coloris;

function initialiseColourPicker(detail) {
    document.dispatchEvent(
        new CustomEvent(colourPickerInitialiseEventName, {
            detail,
        }),
    );
}

describe("colour-picker", () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <input id="alpha-off" type="text" />
            <input id="alpha-on" type="text" />
            <input id="default-alpha" type="text" />
        `;
    });

    test("initialises each input with independent alpha settings", () => {
        initialiseColourPicker({
            el: "#alpha-off",
            format: "rgb",
            alpha: false,
        });
        initialiseColourPicker({
            el: "#alpha-on",
            format: "rgb",
            alpha: true,
        });
        initialiseColourPicker({
            el: "#default-alpha",
            format: "rgb",
        });

        expect(mockColoris).toHaveBeenCalledTimes(4);
        expect(mockColoris).toHaveBeenNthCalledWith(
            1,
            expect.objectContaining({
                alpha: true,
                el: [],
                format: "hex",
            }),
        );
        expect(mockColoris).toHaveBeenNthCalledWith(2, {
            el: document.querySelector("#alpha-off"),
        });
        expect(mockColoris).toHaveBeenNthCalledWith(3, {
            el: document.querySelector("#alpha-on"),
        });
        expect(mockColoris).toHaveBeenNthCalledWith(4, {
            el: document.querySelector("#default-alpha"),
        });

        expect(mockColoris.setInstance).toHaveBeenCalledTimes(3);
        expect(mockColoris.setInstance).toHaveBeenNthCalledWith(
            1,
            "#alpha-off",
            expect.objectContaining({
                alpha: false,
                format: "rgb",
            }),
        );
        expect(mockColoris.setInstance).toHaveBeenNthCalledWith(
            2,
            "#alpha-on",
            expect.objectContaining({
                alpha: true,
                format: "rgb",
            }),
        );
        expect(mockColoris.setInstance).toHaveBeenNthCalledWith(3, "#default-alpha", {
            format: "rgb",
        });
        expect(mockColoris.setInstance.mock.calls[0][1]).not.toHaveProperty("el");
    });
});
