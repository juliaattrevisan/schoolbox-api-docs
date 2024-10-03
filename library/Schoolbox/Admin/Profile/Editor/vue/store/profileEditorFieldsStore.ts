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

type ResequenceFieldsAction = {
    type: "RESEQUENCE_FIELDS";
    // The fields that are "targeted" by the resequence action
    // - this is entirely for tracking purposes and is not used to actually resequence
    fields: string[];
};

// A type of action that can be queued
type FieldAction = EnableFieldAction | DisableFieldAction | ResequenceFieldsAction;

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
     * Process resequencing fields after ensuring all prior actions are completed
     */
    async function processResequenceFields(): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");
        if (!peStore.savedSelectedRoleType) throw new Error("No role type selected");

        const endpoint = peStore.endpoints.adminProfileEditorPostFieldSequence.replace(
            "{roleType}",
            peStore.selectedRoleType.roleType,
        );
        const sequence = peStore.selectedRoleType.fields.selected.map((f) => f.field);

        try {
            await fetch(endpoint, {
                method: "POST",
                body: JSON.stringify({ sequence }),
                headers: { "Content-Type": "application/json" },
            });
            peStore.savedSelectedRoleType.fields.selected = cloneDeep(peStore.selectedRoleType.fields.selected);
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
            case "RESEQUENCE_FIELDS":
                await processResequenceFields();
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

    return {
        isFieldDisabled,
        actionQueue,
        enableField,
        disableField,
        resequenceField,
    };
});
