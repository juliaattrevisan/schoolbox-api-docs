import { defineStore } from "pinia";
import { cloneDeep } from "lodash";
import { until } from "@vueuse/core";
import { useProfileEditorStore } from "./profileEditorStore";

type EnableFieldAction = {
    type: "ENABLE_FIELD";
    field: string; // The field to enable
    index: number; // The index at which to insert the field
};

type DisableFieldAction = {
    type: "DISABLE_FIELD";
    field: string; // The field to disable
};

// A type of action that can be queued
type FieldAction = EnableFieldAction | DisableFieldAction;

// An action that is currently in the queue, with additional metadata to keep track of it
type QueuedFieldAction = FieldAction & {
    actionId: number;
    isProcessing: boolean;
};

export const useProfileEditorFieldsStore = defineStore("profileEditorFieldsStore", () => {
    const peStore = useProfileEditorStore();
    const actionQueue = window.Vue.ref<QueuedFieldAction[]>([]);

    /**
     * Whether a field can be selected or not
     * @param field - The field to check
     * @returns true if the field is disabled (and can't be selected), false otherwise
     */
    function isFieldDisabled(field: string): boolean {
        if (peStore.selectedRoleType === null) return true;
        if (peStore.selectedRoleType.fields.selected.some((selectedField) => selectedField.field === field)) {
            return true;
        }
        return false;
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

        const endpoint = peStore.endpoints.adminProfileEditorPostFieldAdd
            .replace("{roleType}", peStore.selectedRoleType.roleType)
            .replace("{fieldId}", field);

        try {
            await fetch(endpoint, {
                method: "POST",
                body: JSON.stringify({ index }),
                headers: { "Content-Type": "application/json" },
            });
            peStore.savedSelectedRoleType.fields.selected.splice(index, 0, cloneDeep(selectedField));
        } catch {
            // TODO: Handle failure
        }
    }

    /**
     * Process disabling a field after ensuring all prior actions are completed
     * @param field - The key of the field to disable
     */
    async function processDisableField(field: string): Promise<void> {
        if (!peStore.selectedRoleType) return;
        if (!peStore.savedSelectedRoleType) return;

        const endpoint = peStore.endpoints.adminProfileEditorPostFieldRemove
            .replace("{roleType}", peStore.selectedRoleType.roleType)
            .replace("{fieldId}", field);

        try {
            await fetch(endpoint, { method: "POST" });
            peStore.selectedRoleType.fields.selected = peStore.selectedRoleType.fields.selected.filter(
                (f) => f.field !== field,
            );
            peStore.savedSelectedRoleType.fields.selected = peStore.savedSelectedRoleType.fields.selected.filter(
                (f) => f.field !== field,
            );
        } catch {
            // TODO: Handle failure
        }
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

        actionQueue.value.push(queuedAction);

        // Start processing the queue if not already doing so
        if (actionQueue.value.length <= 1) {
            void processQueue();
        }

        await until(() => actionQueue.value.some((a) => a.actionId === actionId)).toBe(false);
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

        if (isFieldDisabled(field)) throw new Error(`Field ${field} is already selected`);

        // a map of each field setting key to an empty array to start with (all settings are deselected)
        const emptyFieldSettings = Object.fromEntries(Object.keys(peStore.fieldSettings).map((key) => [key, []]));

        // Immediate UI preview
        peStore.selectedRoleType.fields.selected.splice(index, 0, {
            field: availableField.field,
            displayAs: availableField.displayAs,
            icon: availableField.icon,
            css: availableField.css,
            fieldSettings: emptyFieldSettings,
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

    return {
        isFieldDisabled,
        actionQueue,
        enableField,
        disableField,
    };
});
