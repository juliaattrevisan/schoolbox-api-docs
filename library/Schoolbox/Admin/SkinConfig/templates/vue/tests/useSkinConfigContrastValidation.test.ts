import { ref } from "vue";
import type { SkinConfigData, SkinConfigOption } from "../../js/types/skinConfigTypes";
import { useSkinConfigContrastValidation } from "../composable/useSkinConfigContrastValidation";

/**
 * Build a colour option for contrast validation tests.
 * @param overrides Option properties to override.
 * @returns A Skin Config colour option.
 */
function buildColourOption(overrides: Partial<SkinConfigOption> = {}): SkinConfigOption {
    return {
        id: 101,
        name: "skin_primary_colour",
        value: "#999999",
        title: "Primary Colour",
        description: "Set the primary brand colour.",
        customProperty: "--sbx-config-primary-colour",
        contrast: "background",
        hasAlpha: true,
        type: "colour",
        selectOptions: [],
        editable: true,
        hidden: false,
        ...overrides,
    };
}

/**
 * Build a Skin Config payload for contrast validation tests.
 * @param optionOverrides Option properties to override.
 * @param enabled Whether Sonar is enabled.
 * @returns A Skin Config payload.
 */
function buildConfigs(optionOverrides: Partial<SkinConfigOption> = {}, enabled: boolean = true): SkinConfigData {
    return {
        contrastTokens: {
            background: {
                label: "Page background",
                value: "#ffffff",
            },
        },
        toggle: {
            enabled,
            title: "Enable Sonar UI",
            description: null,
        },
        existing: {},
        sonar: {
            Colours: [
                {
                    name: null,
                    options: [buildColourOption(optionOverrides)],
                },
            ],
        },
    };
}

test("reports low contrast errors with the current and target readability ratios", () => {
    const configs = ref(buildConfigs());
    const { colourErrors, hasColourErrors } = useSkinConfigContrastValidation(configs);

    expect(colourErrors.value).toEqual({
        101: "Insufficient contrast against Page background. Ratio: 2.85 (Minimum: 4.5 required).",
    });
    expect(hasColourErrors.value).toBe(true);
});

test("does not report errors for readable colours", () => {
    const configs = ref(buildConfigs({ value: "#666666" }));
    const { colourErrors, hasColourErrors } = useSkinConfigContrastValidation(configs);

    expect(colourErrors.value).toEqual({});
    expect(hasColourErrors.value).toBe(false);
});

test("keeps invalid edited colour errors unchanged", () => {
    const configs = ref(buildConfigs({ value: "not-a-colour" }));
    const { colourErrors, hasColourErrors } = useSkinConfigContrastValidation(configs);

    expect(colourErrors.value).toEqual({
        101: "Enter a valid colour.",
    });
    expect(hasColourErrors.value).toBe(true);
});

test("does not report colour errors when Sonar is disabled", () => {
    const configs = ref(buildConfigs({}, false));
    const { colourErrors, hasColourErrors } = useSkinConfigContrastValidation(configs);

    expect(colourErrors.value).toEqual({});
    expect(hasColourErrors.value).toBe(false);
});
