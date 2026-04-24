/**
 * Centralise Skin Config Sonar colour validation in one composable so the form
 * can reactively report invalid colours and low-contrast combinations without
 * duplicating contrast-checking logic across components or template code.
 */
import tinycolor from "tinycolor2";
import type { ComputedRef, Ref } from "vue";
import type {
    SkinConfigContrastToken,
    SkinConfigData,
    SkinConfigOption,
    SkinConfigOptionErrorMap,
} from "../../js/types/skinConfigTypes";

const INVALID_COLOUR_ERROR = "Enter a valid colour.";
type ContrastTarget = {
    label: string;
    value: string | null;
};

/**
 * Build the low contrast validation message for a colour option.
 * @param contrastTarget The option or design token that the edited colour must contrast against.
 * @returns The user-facing validation message.
 */
function getLowContrastError(contrastTarget: ContrastTarget): string {
    return `Must contrast sufficiently against ${contrastTarget.label}`;
}

/**
 * Normalise a configurable option into a contrast target.
 * @param option The option that the edited colour must contrast against.
 * @returns The resolved contrast target.
 */
function getOptionContrastTarget(option: SkinConfigOption): ContrastTarget {
    return {
        label: option.title ?? option.name,
        value: option.value,
    };
}

/**
 * Normalise a fixed design token into a contrast target.
 * @param contrastToken The fixed design token metadata.
 * @returns The resolved contrast target.
 */
function getFixedContrastTarget(contrastToken: SkinConfigContrastToken): ContrastTarget {
    return {
        label: contrastToken.label,
        value: contrastToken.value,
    };
}

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
    return value !== null && tinycolor(value).isValid();
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

            const contrastTarget =
                option.contrast in configs.value.contrastTokens
                    ? getFixedContrastTarget(configs.value.contrastTokens[option.contrast])
                    : (() => {
                          const contrastOption = optionsByCustomProperty.get(option.contrast);

                          return contrastOption === undefined ? null : getOptionContrastTarget(contrastOption);
                      })();

            if (contrastTarget === null) {
                return;
            }

            if (!isValidColour(contrastTarget.value)) {
                errors[option.id] = INVALID_COLOUR_ERROR;
                return;
            }

            if (!tinycolor.isReadable(option.value, contrastTarget.value, { level: "AA", size: "small" })) {
                errors[option.id] = getLowContrastError(contrastTarget);
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
