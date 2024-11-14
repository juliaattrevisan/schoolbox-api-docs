import { defineStore } from "pinia";
import { cloneDeep } from "lodash";
import { until } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { useProfileEditorStore } from "./profileEditorStore";
import { fetchProfileEditorEndpoint } from "./profileEditorUtils";
import type {
    ProfileEditorFieldSettingKey,
    ProfileEditorFieldSettingOption,
    ProfileEditorActiveField,
    ProfileEditorAvailableField,
} from "../types/profileEditorTypes";

type EnableFieldAction = {
    type: "ENABLE_FIELD";
    field: string; // The field to enable
    index: number; // The index at which to insert the field
};

type DisableFieldAction = {
    type: "DISABLE_FIELD";
    field: string; // The field to disable
};

type UpdateFieldSettingsAction = {
    type: "UPDATE_FIELD_SETTINGS";
    field: string; // The field to update
};

type ResequenceFieldsAction = {
    type: "RESEQUENCE_FIELDS";
    // The fields that are "targeted" by the resequence action
    // - this is entirely for tracking purposes and is not used to actually resequence
    fields: string[];
};

// A type of action that can be queued
type FieldAction = EnableFieldAction | DisableFieldAction | UpdateFieldSettingsAction | ResequenceFieldsAction;

// An action that is currently in the queue, with additional metadata to keep track of it
type QueuedFieldAction = FieldAction & {
    actionId: number;
    isProcessing: boolean;
};

export const useProfileEditorFieldsStore = defineStore("profileEditorFieldsStore", () => {
    const peStore = useProfileEditorStore();

    // The current queue of actions to be processed
    const actionQueue = window.Vue.ref<QueuedFieldAction[]>([]);

    // A map of field keys to any errors that occurred while processing actions for that field
    const actionErrors = window.Vue.ref<{ [key: string]: string }>({});

    /**
     * Get the name of a field as it should be displayed in the UI
     * If the field has a relevant terminology key, it will be translated,
     * otherwise falls back to the displayAs property
     * @param field - The field to get the display name of
     * @returns The display name of the field
     */
    function getFieldName(field: ProfileEditorAvailableField | ProfileEditorActiveField): string {
        if (field.terminology && locale[field.terminology]) {
            return locale[field.terminology].proper();
        }

        return field.displayAs;
    }

    /**
     * Get the type of an active field (selected or mandatory)
     * @param field - The field to get the type of
     * @returns The type of the field, or null if it is not active
     */
    function getActiveFieldType(field: string): "selected" | "mandatory" | null {
        if (peStore.selectedRoleType === null) return null;

        if (peStore.selectedRoleType.fields.mandatory.some((f) => f.field === field)) return "mandatory";
        if (peStore.selectedRoleType.fields.selected.some((f) => f.field === field)) return "selected";

        return null;
    }

    /**
     * Process enabling a field after ensuring all prior actions are completed
     * @param field - The key of the field to enable
     * @param index - The index at which to insert the field
     */
    async function processEnableField(field: string, index: number): Promise<void> {
        if (!peStore.selectedRoleType) return;
        if (!peStore.savedSelectedRoleType) return;

        const selectedField = peStore.selectedRoleType.fields.selected.find((f) => f.field === field);
        if (!selectedField) throw new Error("Field not found in selected fields.");

        toast.activate(`Adding ${selectedField.displayAs}...`);

        const endpoint = peStore.selectedRoleType._routes.postFieldAdd.replace("{fieldId}", field);

        const response = await fetchProfileEditorEndpoint(endpoint, {
            method: "POST",
            body: JSON.stringify({ index }),
            headers: { "Content-Type": "application/json" },
        });

        if (!response.success) {
            actionErrors.value[field] = response.message ?? "An unknown error occurred";
            toast.timeout({ text: `Failed to add ${selectedField.displayAs}`, isSuccess: false });
            return;
        }

        peStore.savedSelectedRoleType.fields.selected.splice(index, 0, cloneDeep(selectedField));
        toast.timeout({ text: `Added ${selectedField.displayAs}`, isSuccess: true });
    }

    /**
     * Process disabling a field after ensuring all prior actions are completed
     * @param field - The key of the field to disable
     */
    async function processDisableField(field: string): Promise<void> {
        if (!peStore.selectedRoleType) return;
        if (!peStore.savedSelectedRoleType) return;

        const selectedField = peStore.selectedRoleType.fields.selected.find((f) => f.field === field);
        if (!selectedField) throw new Error("Field not found in selected fields.");

        toast.activate(`Removing ${selectedField.displayAs}...`);

        const endpoint = peStore.selectedRoleType._routes.postFieldRemove.replace("{fieldId}", field);

        const response = await fetchProfileEditorEndpoint(endpoint, { method: "POST" });

        if (!response.success) {
            actionErrors.value[field] = response.message ?? "An unknown error occurred";
            toast.timeout({ text: `Failed to remove ${selectedField.displayAs}`, isSuccess: false });
            return;
        }

        peStore.selectedRoleType.fields.selected = peStore.selectedRoleType.fields.selected.filter(
            (f) => f.field !== field,
        );
        peStore.savedSelectedRoleType.fields.selected = peStore.savedSelectedRoleType.fields.selected.filter(
            (f) => f.field !== field,
        );

        toast.timeout({ text: `Removed ${selectedField.displayAs}`, isSuccess: true });
    }

    /**
     * Process updating field settings for a specific field after ensuring all prior actions are completed
     * @param field - The key of the field to update
     */
    async function processUpdateFieldSettings(field: string): Promise<void> {
        if (!peStore.selectedRoleType || !peStore.savedSelectedRoleType) return;

        const activeFieldType = getActiveFieldType(field);
        if (activeFieldType === null) throw new Error("Field not found in active fields.");

        const currentField = peStore.selectedRoleType.fields[activeFieldType].find((f) => f.field === field);
        if (!currentField) throw new Error("Field not found in active fields.");

        toast.activate(`Saving settings for ${currentField.displayAs}...`);

        const endpoint = peStore.selectedRoleType._routes.postFieldSettings.replace("{fieldId}", field);

        const response = await fetchProfileEditorEndpoint(endpoint, {
            method: "POST",
            body: JSON.stringify({ fieldSettings: currentField.fieldSettings }),
            headers: { "Content-Type": "application/json" },
        });

        if (!response.success) {
            actionErrors.value[field] = response.message ?? "An unknown error occurred";
            toast.timeout({ text: `Failed to save settings for ${currentField.displayAs}`, isSuccess: false });
            return;
        }

        const savedField = peStore.savedSelectedRoleType.fields[activeFieldType].find((f) => f.field === field);
        if (savedField) {
            savedField.fieldSettings = cloneDeep(currentField.fieldSettings);
        }

        toast.timeout({ text: `Saved settings for ${currentField.displayAs}`, isSuccess: true });
    }

    /**
     * Process resequencing fields after ensuring all prior actions are completed
     * @param fields - The fields relevant to the resequence action (for error reporting purposes, not used to resequence)
     */
    async function processResequenceFields(fields: string[]): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");
        if (!peStore.savedSelectedRoleType) throw new Error("No role type selected");

        toast.activate("Resequencing fields...");

        const endpoint = peStore.selectedRoleType._routes.postFieldSequence;
        const sequence = peStore.selectedRoleType.fields.selected.map((f) => f.field);

        const response = await fetchProfileEditorEndpoint(endpoint, {
            method: "POST",
            body: JSON.stringify({ sequence }),
            headers: { "Content-Type": "application/json" },
        });

        if (!response.success) {
            fields.forEach((field) => {
                actionErrors.value[field] = response.message ?? "An unknown error occurred";
            });
            toast.timeout({ text: "Failed to resequence fields", isSuccess: false });
            return;
        }

        peStore.savedSelectedRoleType.fields.selected = cloneDeep(peStore.selectedRoleType.fields.selected);
        toast.timeout({ text: "Resequenced fields", isSuccess: true });
    }

    /**
     * Process the action queue in sequence
     */
    async function processQueue(): Promise<void> {
        if (actionQueue.value.length === 0) return;

        // Find the next action in the queue that hasn't been processed yet
        const action = actionQueue.value[0];
        action.isProcessing = true;

        switch (action.type) {
            case "ENABLE_FIELD":
                await processEnableField(action.field, action.index);
                break;
            case "DISABLE_FIELD":
                await processDisableField(action.field);
                break;
            case "UPDATE_FIELD_SETTINGS":
                await processUpdateFieldSettings(action.field);
                break;
            case "RESEQUENCE_FIELDS":
                await processResequenceFields(action.fields);
                break;
            default:
                ((a: never): Error => new Error(a))(action); // Assert exhaustive switch
        }

        action.isProcessing = false; // In case something is specifically tracking this object
        actionQueue.value.shift(); // Remove the action from the queue since it's been processed

        // Process the next action in the queue
        if (actionQueue.value.length > 0) {
            await processQueue();
        }
    }

    /**
     * Add an action to the queue and start processing if not already doing so
     * @param action - The action to be added to the queue
     * @returns A promise that resolves when the action has been processed
     */
    async function queueAction(action: FieldAction): Promise<void> {
        const actionId = Math.floor(Math.random() * 1000000); // Generate a random action ID for tracking
        const queuedAction: QueuedFieldAction = { ...action, actionId, isProcessing: false };

        // Remove any errors for the field(s) since it's being reprocessed
        if ("field" in action) {
            delete actionErrors.value[action.field];
        }
        if ("fields" in action) {
            action.fields.forEach((field) => delete actionErrors.value[field]);
        }

        if (queuedAction.type === "RESEQUENCE_FIELDS") {
            // Remove any other resequence actions from the queue that are still pending, as they are redundant
            // If any do exist, add their field list to the current action so whatever is tracking it can still know
            const resequenceActions = actionQueue.value.filter((a) => a.type === "RESEQUENCE_FIELDS");
            if (resequenceActions.length > 0) {
                queuedAction.fields.push(...resequenceActions.flatMap((a) => a.fields));
            }
            actionQueue.value = actionQueue.value.filter((a) => a.type !== "RESEQUENCE_FIELDS");
        } else {
            // Otherwise, if there's a resequence action in the queue, move it to the very end so it's processed last
            const resequenceActionIndex = actionQueue.value.findIndex((a) => a.type === "RESEQUENCE_FIELDS");
            if (resequenceActionIndex !== -1) {
                const resequenceAction = actionQueue.value[resequenceActionIndex];
                actionQueue.value.splice(resequenceActionIndex, 1);
                actionQueue.value.push(resequenceAction);
            }
        }

        actionQueue.value.push(queuedAction);

        // Start processing the queue if not already doing so
        if (actionQueue.value.length <= 1) {
            void processQueue();
        }

        // Wait until the action has been processed before continuing
        await until(() =>
            actionQueue.value.some((a) => {
                // If the action is a resequence, check if there is any resequence action in the queue
                if (action.type === "RESEQUENCE_FIELDS") return a.type === "RESEQUENCE_FIELDS";

                // Otherwise, check if the action has been processed by matching the action ID
                return a.actionId === actionId;
            }),
        ).toBe(false);
    }

    /**
     * Trigger a field to be enabled (added to the selected fields)
     * @param field - The key of the field to enable
     * @param index - The index at which to insert the field (default: 0)
     */
    async function enableField(field: string, index: number = 0): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");

        const availableField = peStore.selectedRoleType.fields.available.find((f) => f.field === field);
        if (availableField === undefined) throw new Error(`Field ${field} not found`);
        if (getActiveFieldType(field) !== null) return;

        // a map of each field setting key to an empty array to start with (all settings are deselected)
        const emptyFieldSettings = Object.fromEntries(Object.keys(peStore.fieldSettings).map((key) => [key, []]));

        // Immediate UI preview
        peStore.selectedRoleType.fields.selected.splice(index, 0, {
            field: availableField.field,
            displayAs: availableField.displayAs,
            icon: availableField.icon,
            css: availableField.css,
            terminology: availableField.terminology,
            fieldSettings: emptyFieldSettings,
        });

        // Timeout so the loading state renders nicely and the user can see the change
        await new Promise((resolve) => {
            setTimeout(resolve, 300);
        });

        // Queue action
        await queueAction({ type: "ENABLE_FIELD", field, index });
    }

    /**
     * Trigger a field to be disabled (removed from the selected fields)
     * @param field - The key of the field to disable
     */
    async function disableField(field: string): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");

        // Queue action without immediately removing it from the UI so we can show a loading state
        await queueAction({ type: "DISABLE_FIELD", field });
    }

    /**
     * Trigger updating field settings
     * @param field - The key of the field to update
     */
    async function saveFieldSettings(field: string): Promise<void> {
        await queueAction({ type: "UPDATE_FIELD_SETTINGS", field });
    }

    /**
     * Resequence a field to a new index
     * @param oldIndex - The original index of the field
     * @param newIndex - The new index of the field
     */
    async function resequenceField(oldIndex: number, newIndex: number): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");

        // Update UI immediately
        const [movedField] = peStore.selectedRoleType.fields.selected.splice(oldIndex, 1);
        peStore.selectedRoleType.fields.selected.splice(newIndex, 0, movedField);

        await queueAction({ type: "RESEQUENCE_FIELDS", fields: [movedField.field] });
    }

    /**
     * Whether the settings for a given field are currently unsaved
     * @param field - The field to check
     * @returns true if the field settings are unsaved, false otherwise
     */
    function isFieldSettingsUnsaved(field: string): boolean {
        if (peStore.selectedRoleType === null) return false;
        if (peStore.savedSelectedRoleType === null) return false;

        const activeFieldType = getActiveFieldType(field);
        if (activeFieldType === null) return false;

        const activeField = peStore.selectedRoleType.fields[activeFieldType].find((f) => f.field === field);
        const savedField = peStore.savedSelectedRoleType.fields[activeFieldType].find((f) => f.field === field);

        // If the field itself hasn't been saved, then treat the settings as saved for now
        if (activeField === undefined || savedField === undefined) return false;

        // Check if the field settings are different, each setting list *should* be sorted, so we can compare them directly
        return Object.keys(activeField.fieldSettings).some(
            (key) => JSON.stringify(activeField.fieldSettings[key]) !== JSON.stringify(savedField.fieldSettings[key]),
        );
    }

    /**
     * Update the field settings for an active (selected or mandatory) field
     * Note, this does NOT persist the changes to the server - use saveFieldSettings for that
     * @param field - The key of the field to update
     * @param fieldSettingKey - The key of the field setting to update
     * @param fieldSettingOptionId - The id of the option to set
     * @param isSelected - Whether the option is selected or deselected
     */
    function updateFieldSettingOption(
        field: ProfileEditorActiveField["field"],
        fieldSettingKey: ProfileEditorFieldSettingKey,
        fieldSettingOptionId: ProfileEditorFieldSettingOption["id"],
        isSelected: boolean,
    ): void {
        if (peStore.selectedRoleType === null) throw new Error("No role type selected");

        const activeFieldType = getActiveFieldType(field);
        if (activeFieldType === null) throw new Error(`Field ${field} not found`);

        const activeField = peStore.selectedRoleType.fields[activeFieldType].find((f) => f.field === field);
        if (activeField === undefined) throw new Error(`Field ${field} not found`);

        // Always filter the settings to avoid duplicates
        const filteredSettings = activeField.fieldSettings[fieldSettingKey].filter((id) => id !== fieldSettingOptionId);

        // Add the setting if it is selected
        if (isSelected) {
            filteredSettings.push(fieldSettingOptionId);
        }

        filteredSettings.sort(); // Sort the settings to ensure consistency and allow for easier comparison
        activeField.fieldSettings[fieldSettingKey] = filteredSettings;
    }

    /**
     * Get an identifier for a given field to be used for data-test attributes
     * or other keys such as for a label:for connection
     * Includes the selected role type for uniqueness between role types
     * This should be extended with more specificity depending on the context
     * @param field - The field to get the identifier for
     * @returns The identifier for the field
     */
    function getFieldIdentifier(field: ProfileEditorAvailableField | ProfileEditorActiveField): string {
        const roleType = peStore.selectedRoleType?.roleType ?? "unknown";
        const fieldType = "fieldSettings" in field ? "active" : "available";
        return `pe-${roleType}-fields-${fieldType}-item-${field.field}`;
    }

    return {
        getFieldName,
        actionQueue,
        actionErrors,
        getActiveFieldType,
        enableField,
        disableField,
        saveFieldSettings,
        resequenceField,
        isFieldSettingsUnsaved,
        updateFieldSettingOption,
        getFieldIdentifier,
    };
});
