/**
 * Composable for managing Skin Config state in the Vue component, including draft state management,
 * dirty-state tracking, and syncing with the colour picker UI.
 */

import type { Ref } from "vue";
import type { SkinConfigData, SkinConfigOption } from "../../js/types/skinConfigTypes";

/**
 * Serialize the current config state for dirty-state comparison.
 * @param value The current Skin Config payload.
 * @returns A stable serialized snapshot for dirty-state comparison.
 */
function serializeConfigs(value: SkinConfigData): string {
    return JSON.stringify(value);
}

/**
 * Clone the config state so form edits do not mutate the saved baseline.
 * @param value The current Skin Config payload.
 * @returns A deep-cloned Skin Config payload.
 */
function cloneConfigs(value: SkinConfigData): SkinConfigData {
    return JSON.parse(serializeConfigs(value)) as SkinConfigData;
}

/**
 * Build a lookup of active colour config options by config name.
 * @param value The current Skin Config payload.
 * @returns Colour options from the currently active Skin Config section.
 */
function getActiveColourOptionsByName(value: SkinConfigData): Map<string, SkinConfigOption> {
    const activeConfigs = value.toggle.enabled ? value.sonar : value.existing;
    const colourOptions = Object.values(activeConfigs)
        .flatMap((subcategories) => subcategories)
        .flatMap((subcategory) => subcategory.options)
        .filter((option) => option.type === "colour");

    return new Map(colourOptions.map((option) => [option.name, option]));
}

/**
 * Composable for Skin Config draft state, drawer visibility, and colour picker syncing.
 * @param initialConfigs Initial server-provided Skin Config payload.
 * @param isProcessing Reactive save-processing state.
 * @param schedulePreviewRefresh Preview refresh callback to run after undo.
 * @returns Reactive draft state and helpers for drawer and save baseline management.
 */
export function useSkinConfigState(
    initialConfigs: SkinConfigData,
    isProcessing: Ref<boolean>,
    schedulePreviewRefresh: () => void,
): {
    configs: Ref<SkinConfigData>;
    saveContainer: Ref<HTMLDivElement | null>;
    hasUnsavedChanges: Readonly<Ref<boolean>>;
    initialiseColourPickers: () => Promise<void>;
    resetToSavedState: () => Promise<void>;
    markCurrentAsSaved: () => Promise<void>;
} {
    const savedConfigs = window.Vue.ref(cloneConfigs(initialConfigs));
    const configs = window.Vue.ref<SkinConfigData>(cloneConfigs(initialConfigs));
    const saveContainer = window.Vue.ref<HTMLDivElement | null>(null);
    const initialConfigSnapshot = window.Vue.ref(serializeConfigs(savedConfigs.value));
    const currentConfigSnapshot = window.Vue.computed(() => serializeConfigs(configs.value));
    const hasUnsavedChanges = window.Vue.computed(() => initialConfigSnapshot.value !== currentConfigSnapshot.value);

    /**
     * Initialise colour picker inputs once the component has rendered.
     */
    async function initialiseColourPickers(): Promise<void> {
        await window.Vue.nextTick();
        const colourOptionsByName = getActiveColourOptionsByName(configs.value);
        document.querySelectorAll("[data-colour-picker]").forEach((element) => {
            if (!(element instanceof HTMLInputElement) || element.id === "") {
                return;
            }
            const option = colourOptionsByName.get(element.name);
            document.dispatchEvent(
                new CustomEvent("colourPickerInitialise", {
                    detail: {
                        el: `#${element.id}`,
                        format: "rgb",
                        alpha: option?.hasAlpha !== false,
                    },
                }),
            );
        });
    }

    /**
     * Trigger input events so the colour picker UI reflects the current input values.
     */
    async function updateColourPickerColours(): Promise<void> {
        await window.Vue.nextTick();
        document.querySelectorAll("[data-colour-picker]").forEach((element) => {
            if (!(element instanceof HTMLInputElement)) {
                return;
            }
            element.dispatchEvent(new Event("input", { bubbles: true }));
        });
    }

    /**
     * Restore the last saved config state.
     */
    async function resetToSavedState(): Promise<void> {
        if (isProcessing.value) {
            return;
        }
        configs.value = cloneConfigs(savedConfigs.value);
        await updateColourPickerColours();
        schedulePreviewRefresh();
    }

    /**
     * Commit the current draft as the saved baseline.
     */
    async function markCurrentAsSaved(): Promise<void> {
        savedConfigs.value = cloneConfigs(configs.value);
        initialConfigSnapshot.value = serializeConfigs(savedConfigs.value);
        await updateColourPickerColours();
    }

    /**
     * Initialise the colour pickers when the component mounts so they reflect the initial config values.
     */
    window.Vue.onMounted(() => {
        void initialiseColourPickers();
    });

    return {
        configs,
        saveContainer,
        hasUnsavedChanges,
        initialiseColourPickers,
        resetToSavedState,
        markCurrentAsSaved,
    };
}
