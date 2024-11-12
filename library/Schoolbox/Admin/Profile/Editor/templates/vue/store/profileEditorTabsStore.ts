import { defineStore } from "pinia";
import { cloneDeep } from "lodash";
import { until } from "@vueuse/core";
import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { useProfileEditorStore } from "./profileEditorStore";
import { fetchProfileEditorEndpoint } from "./profileEditorUtils";
import type {
    ProfileEditorTab,
    ProfileEditorTabSettingKey,
    ProfileEditorTabSettingOption,
} from "../types/profileEditorTypes";

type UpdateTabSettingsAction = {
    type: "UPDATE_TAB_SETTINGS";
    tabId: ProfileEditorTab["id"]; // The tab to update
};

type ResequenceTabsAction = {
    type: "RESEQUENCE_TABS";
    // The tabs that are "targeted" by the resequence action
    // - this is entirely for tracking purposes and is not used to actually resequence
    tabIds: string[];
};

// A type of action that can be queued
type TabAction = UpdateTabSettingsAction | ResequenceTabsAction;

// An action that is currently in the queue, with additional metadata to keep track of it
type QueuedTabAction = TabAction & {
    actionId: number;
    isProcessing: boolean;
};

export const useProfileEditorTabsStore = defineStore("profileEditorTabsStore", () => {
    const peStore = useProfileEditorStore();

    // The current queue of actions to be processed
    const actionQueue = window.Vue.ref<QueuedTabAction[]>([]);

    // A map of tab keys to any errors that occurred while processing actions for that tab
    const actionErrors = window.Vue.ref<{ [key: string]: string }>({});

    /**
     * Process updating tab settings for a specific tab after ensuring all prior actions are completed
     * @param tabId - The id of the tab to update
     */
    async function processUpdateTabSettings(tabId: ProfileEditorTab["id"]): Promise<void> {
        if (!peStore.selectedRoleType || !peStore.savedSelectedRoleType) return;

        const currentTab = peStore.selectedRoleType.tabs.find((t) => t.id === tabId);
        if (!currentTab) throw new Error("Tab not found");

        toast.activate(`Saving settings for ${currentTab.label}...`);

        const endpoint = peStore.selectedRoleType._routes.postTabSettings.replace("{tabId}", tabId);

        const response = await fetchProfileEditorEndpoint(endpoint, {
            method: "POST",
            body: JSON.stringify({ tabSettings: currentTab.tabSettings }),
            headers: { "Content-Type": "application/json" },
        });

        if (!response.success) {
            actionErrors.value[tabId] = response.message ?? "An unknown error occurred";
            toast.timeout({ text: `Failed to save settings for ${currentTab.label}`, isSuccess: false });
            return;
        }

        const savedTab = peStore.savedSelectedRoleType.tabs.find((t) => t.id === tabId);
        if (savedTab) {
            savedTab.tabSettings = cloneDeep(currentTab.tabSettings);
        }

        toast.timeout({ text: `Saved settings for ${currentTab.label}`, isSuccess: true });
    }

    /**
     * Process resequencing tabs after ensuring all prior actions are completed
     * @param tabIds - The tabs relevant to the resequence action (for error reporting purposes, not used to resequence)
     */
    async function processResequenceTabs(tabIds: string[]): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");
        if (!peStore.savedSelectedRoleType) throw new Error("No role type selected");

        toast.activate("Resequencing tabs...");

        const endpoint = peStore.selectedRoleType._routes.postTabSequence;
        const sequence = peStore.selectedRoleType.tabs.map((f) => f.id);

        const response = await fetchProfileEditorEndpoint(endpoint, {
            method: "POST",
            body: JSON.stringify({ sequence }),
            headers: { "Content-Type": "application/json" },
        });

        if (!response.success) {
            tabIds.forEach((tabId) => {
                actionErrors.value[tabId] = response.message ?? "An unknown error occurred";
            });
            toast.timeout({ text: "Failed to resequence tabs", isSuccess: false });
            return;
        }

        peStore.savedSelectedRoleType.tabs = cloneDeep(peStore.selectedRoleType.tabs);
        toast.timeout({ text: "Resequenced tabs", isSuccess: true });
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
            case "UPDATE_TAB_SETTINGS":
                await processUpdateTabSettings(action.tabId);
                break;
            case "RESEQUENCE_TABS":
                await processResequenceTabs(action.tabIds);
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
    async function queueAction(action: TabAction): Promise<void> {
        const actionId = Math.floor(Math.random() * 1000000); // Generate a random action ID for tracking
        const queuedAction: QueuedTabAction = { ...action, actionId, isProcessing: false };

        // Remove any errors for the tab(s) since it's being reprocessed
        if ("tabId" in action) {
            delete actionErrors.value[action.tabId];
        }
        if ("tabIds" in action) {
            action.tabIds.forEach((tabId) => delete actionErrors.value[tabId]);
        }

        if (queuedAction.type === "RESEQUENCE_TABS") {
            // Remove any other resequence actions from the queue that are still pending, as they are redundant
            // If any do exist, add their tab list to the current action so whatever is tracking it can still know
            const resequenceActions = actionQueue.value.filter((a) => a.type === "RESEQUENCE_TABS");
            if (resequenceActions.length > 0) {
                queuedAction.tabIds.push(...resequenceActions.flatMap((a) => a.tabIds));
            }
            actionQueue.value = actionQueue.value.filter((a) => a.type !== "RESEQUENCE_TABS");
        } else {
            // Otherwise, if there's a resequence action in the queue, move it to the very end so it's processed last
            const resequenceActionIndex = actionQueue.value.findIndex((a) => a.type === "RESEQUENCE_TABS");
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
                if (action.type === "RESEQUENCE_TABS") return a.type === "RESEQUENCE_TABS";

                // Otherwise, check if the action has been processed by matching the action ID
                return a.actionId === actionId;
            }),
        ).toBe(false);
    }

    /**
     * Trigger updating tab settings
     * @param tabId - The key of the tab to update
     */
    async function saveTabSettings(tabId: ProfileEditorTab["id"]): Promise<void> {
        await queueAction({ type: "UPDATE_TAB_SETTINGS", tabId });
    }

    /**
     * Resequence a tab to a new index
     * @param oldIndex - The original index of the tab
     * @param newIndex - The new index of the tab
     */
    async function resequenceTab(oldIndex: number, newIndex: number): Promise<void> {
        if (!peStore.selectedRoleType) throw new Error("No role type selected");

        // Update UI immediately
        const [movedTab] = peStore.selectedRoleType.tabs.splice(oldIndex, 1);
        peStore.selectedRoleType.tabs.splice(newIndex, 0, movedTab);

        await queueAction({ type: "RESEQUENCE_TABS", tabIds: [movedTab.id] });
    }

    /**
     * Whether the settings for a given tab are currently unsaved
     * @param tabId - The tabId to check
     * @returns true if the tab settings are unsaved, false otherwise
     */
    function isTabSettingsUnsaved(tabId: ProfileEditorTab["id"]): boolean {
        if (peStore.selectedRoleType === null) return false;
        if (peStore.savedSelectedRoleType === null) return false;

        const activeTab = peStore.selectedRoleType.tabs.find((f) => f.id === tabId);
        const savedTab = peStore.savedSelectedRoleType.tabs.find((f) => f.id === tabId);

        // If the tab itself hasn't been saved, then treat the settings as saved for now
        if (activeTab === undefined || savedTab === undefined) return false;

        // Check if the tab settings are different, each setting list *should* be sorted, so we can compare them directly
        return Object.keys(activeTab.tabSettings).some(
            (key) => JSON.stringify(activeTab.tabSettings[key]) !== JSON.stringify(savedTab.tabSettings[key]),
        );
    }

    /**
     * Update the tab settings for a tab
     * Note, this does NOT persist the changes to the server - use saveTabSettings for that
     * @param tabId - The key of the tab to update
     * @param tabSettingKey - The key of the tab setting to update
     * @param tabSettingOptionId - The id of the option to set
     * @param isSelected - Whether the option is selected or deselected
     */
    function updateTabSettingOption(
        tabId: ProfileEditorTab["id"],
        tabSettingKey: ProfileEditorTabSettingKey,
        tabSettingOptionId: ProfileEditorTabSettingOption["id"],
        isSelected: boolean,
    ): void {
        if (peStore.selectedRoleType === null) throw new Error("No role type selected");

        const activeTab = peStore.selectedRoleType.tabs.find((f) => f.id === tabId);
        if (activeTab === undefined) throw new Error(`Tab ${tabId} not found`);

        // Always filter the settings to avoid duplicates
        const filteredSettings = activeTab.tabSettings[tabSettingKey].filter((id) => id !== tabSettingOptionId);

        // Add the setting if it is selected
        if (isSelected) {
            filteredSettings.push(tabSettingOptionId);
        }

        filteredSettings.sort(); // Sort the settings to ensure consistency and allow for easier comparison
        activeTab.tabSettings[tabSettingKey] = filteredSettings;
    }

    /**
     * Get an identifier for a given tab to be used for data-test attributes
     * or other keys such as for a label:for connection
     * Includes the selected role type for uniqueness between role types
     * This should be extended with more specificity depending on the context
     * @param tabId - The tab to get the identifier for
     * @returns The identifier for the tab
     */
    function getTabIdentifier(tabId: ProfileEditorTab["id"]): string {
        const roleType = peStore.selectedRoleType?.roleType ?? "unknown";
        return `pe-${roleType}-tabs-tab-${tabId}`;
    }
    return {
        actionQueue,
        actionErrors,
        saveTabSettings,
        resequenceTab,
        isTabSettingsUnsaved,
        updateTabSettingOption,
        getTabIdentifier,
    };
});
