import { isReadable, TinyColor } from "@ctrl/tinycolor";
import type { ComputedRef, Ref } from "vue";
import type { SkinConfigData, SkinConfigOption, SkinConfigOptionErrorMap } from "../../js/types/skinConfigTypes";

const INVALID_COLOUR_ERROR = "Enter a valid colour.";
const LOW_CONTRAST_ERROR = "Colour contrast must meet AA standard.";

/**
 * Collect all Sonar colour options from the current Skin Config payload.
 * @param configs The current Skin Config payload.
 * @returns The flattened list of Sonar colour options.
 */
function getSonarColourOptions(configs: SkinConfigData): SkinConfigOption[] {
    return Object.values(configs.sonar)
        .flatMap((subcategories) => subcategories)
        .flatMap((subcategory) => subcategory.options)
        .filter((option) => option.type === "colour");
}

/**
 * Determine whether the provided value is a valid colour string.
 * @param value The colour value to validate.
 * @returns True when the value can be parsed as a colour.
 */
function isValidColour(value: string | null): value is string {
    return value !== null && new TinyColor(value).isValid;
}

/**
 * Build reactive Sonar colour validation state from the current Skin Config draft.
 * @param configs The current draft Skin Config state.
 * @returns Reactive colour validation errors and a summary error flag.
 */
export function useSkinConfigContrastValidation(configs: Ref<SkinConfigData>): {
    colourErrors: ComputedRef<SkinConfigOptionErrorMap>;
    hasColourErrors: ComputedRef<boolean>;
} {
    const colourErrors = window.Vue.computed<SkinConfigOptionErrorMap>(() => {
        if (!configs.value.toggle.enabled) {
            return {};
        }

        const colourOptions = getSonarColourOptions(configs.value);
        const optionsByCustomProperty = new Map<string, SkinConfigOption>();
        const errors: SkinConfigOptionErrorMap = {};

        colourOptions.forEach((option) => {
            if (option.customProperty !== null) {
                optionsByCustomProperty.set(option.customProperty, option);
            }
        });

        colourOptions.forEach((option) => {
            if (!isValidColour(option.value)) {
                errors[option.id] = INVALID_COLOUR_ERROR;
                return;
            }

            if (option.contrast === null) {
                return;
            }

            const contrastOption = optionsByCustomProperty.get(option.contrast);
            if (contrastOption === undefined) {
                return;
            }

            if (!isValidColour(contrastOption.value)) {
                errors[option.id] = INVALID_COLOUR_ERROR;
                return;
            }

            if (!isReadable(option.value, contrastOption.value, { level: "AA", size: "small" })) {
                errors[option.id] = LOW_CONTRAST_ERROR;
            }
        });

        return errors;
    });

    const hasColourErrors = window.Vue.computed(() => Object.keys(colourErrors.value).length > 0);

    return {
        colourErrors,
        hasColourErrors,
    };
}
