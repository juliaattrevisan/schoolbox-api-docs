import { defineStore } from "pinia";
import { cloneDeep } from "lodash";
import { until } from "@vueuse/core";
import type { ProfileEditorRoleType, ProfileEditorEndpoints, ProfileEditorPayload } from "../profileEditorTypes";

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

export const useProfileEditorStore = defineStore("profileEditorStore", () => {
    const isLoading = window.Vue.ref<boolean>(true);
    const roleTypes = window.Vue.ref<ProfileEditorPayload["data"]["roleTypes"]>([]);
    const savedRoleTypes = window.Vue.ref<typeof roleTypes.value>([]);
    const fieldSettings = window.Vue.ref<ProfileEditorPayload["data"]["fieldSettings"]>({});
    const selectedRoleType = window.Vue.ref<ProfileEditorRoleType | null>(null);

    const savedSelectedRoleType = window.Vue.computed<typeof selectedRoleType.value>(() => {
        const currentRoleType = selectedRoleType.value;
        if (currentRoleType === null) return null;

        return savedRoleTypes.value.find((roleType) => roleType.roleType === currentRoleType.roleType) ?? null;
    });

    const endpoints = window.Vue.ref<ProfileEditorEndpoints>({
        adminProfileEditorPostFieldAdd: "",
        adminProfileEditorPostFieldRemove: "",
        adminProfileEditorPostFieldSettings: "",
        adminProfileEditorPostFieldSequence: "", // TODO: replace and handle null for all these
    });

    const actionQueue = window.Vue.ref<QueuedFieldAction[]>([]);

    /**
     * Initialise the store with the relevant endpoints and data
     * @param $endpoints - The endpoints required for the profile editor
     * @param $roleTypes - The current role types
     * @param $fieldSettings - The current field settings
     */
    function init(
        $endpoints: ProfileEditorEndpoints,
        $roleTypes: ProfileEditorPayload["data"]["roleTypes"],
        $fieldSettings: ProfileEditorPayload["data"]["fieldSettings"],
    ): void {
        endpoints.value = $endpoints;
        roleTypes.value = $roleTypes;
        savedRoleTypes.value = cloneDeep($roleTypes);
        fieldSettings.value = $fieldSettings;
    }

    /**
     * Set the currently selected role type
     * @param roleType the role type to set as selected
     */
    function setSelectedRoleType(roleType: ProfileEditorRoleType): void {
        selectedRoleType.value = roleType;
    }

    /**
     * Whether a field can be selected or not
     * @param field - The field to check
     * @returns true if the field is disabled (and can't be selected), false otherwise
     */
    function isFieldDisabled(field: string): boolean {
        if (selectedRoleType.value === null) return true;
        if (selectedRoleType.value.fields.selected.some((selectedField) => selectedField.field === field)) {
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
        if (!selectedRoleType.value) return;
        if (!savedSelectedRoleType.value) return;

        const selectedField = selectedRoleType.value.fields.selected.find((f) => f.field === field);
        if (!selectedField) throw new Error("Field not found in selected fields.");

        const endpoint = endpoints.value.adminProfileEditorPostFieldAdd
            .replace("{roleType}", selectedRoleType.value.roleType)
            .replace("{fieldId}", field);

        try {
            await fetch(endpoint, {
                method: "POST",
                body: JSON.stringify({ index }),
                headers: { "Content-Type": "application/json" },
            });
            savedSelectedRoleType.value.fields.selected.splice(index, 0, cloneDeep(selectedField));
        } catch {
            // TODO: Handle failure
        }
    }

    /**
     * Process disabling a field after ensuring all prior actions are completed
     * @param field - The key of the field to disable
     */
    async function processDisableField(field: string): Promise<void> {
        if (!selectedRoleType.value) return;
        if (!savedSelectedRoleType.value) return;

        const endpoint = endpoints.value.adminProfileEditorPostFieldRemove
            .replace("{roleType}", selectedRoleType.value.roleType)
            .replace("{fieldId}", field);

        try {
            await fetch(endpoint, { method: "POST" });
            selectedRoleType.value.fields.selected = selectedRoleType.value.fields.selected.filter(
                (f) => f.field !== field,
            );
            savedSelectedRoleType.value.fields.selected = savedSelectedRoleType.value.fields.selected.filter(
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
        if (!selectedRoleType.value) throw new Error("No role type selected");

        const availableField = selectedRoleType.value.fields.available.find((f) => f.field === field);
        if (availableField === undefined) throw new Error(`Field ${field} not found`);

        if (isFieldDisabled(field)) throw new Error(`Field ${field} is already selected`);

        // a map of each field setting key to an empty array to start with (all settings are deselected)
        const emptyFieldSettings = Object.fromEntries(Object.keys(fieldSettings.value).map((key) => [key, []]));

        // Immediate UI preview
        selectedRoleType.value.fields.selected.splice(index, 0, {
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
        if (!selectedRoleType.value) throw new Error("No role type selected");

        // Queue action without immediately removing it from the UI so we can show a loading state
        await queueAction({ type: "DISABLE_FIELD", field });
    }

    return {
        init,
        isLoading,
        roleTypes,
        fieldSettings,
        savedRoleTypes,
        selectedRoleType,
        savedSelectedRoleType,
        setSelectedRoleType,
        isFieldDisabled,
        queueAction,
        actionQueue,
        enableField,
        disableField,
    };
});
