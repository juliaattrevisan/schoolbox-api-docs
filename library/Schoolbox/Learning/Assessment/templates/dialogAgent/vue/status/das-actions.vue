<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { ApiDialogAgentActionResponse, DialogAgentParticipant } from "./dialogAgentStatusTypes";
import DasActionsModal from "./das-actions-modal.vue";

interface Props {
    participant: DialogAgentParticipant;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: "updateParticipant", data: ApiDialogAgentActionResponse): void;
}>();

export type ConfirmableAction = (typeof confirmableActions)[number];
const confirmableActions = [
    DialogAgentResponseAction.ACTION_SOBO,
    DialogAgentResponseAction.ACTION_RESET,
    DialogAgentResponseAction.ACTION_REOPEN,
] as const;

const actionToConfirm = window.Vue.ref<{
    action: ConfirmableAction;
    resolve: (confirmed: boolean) => void;
} | null>(null);

const isConfirmModalOpen = window.Vue.computed({
    get: () => actionToConfirm.value !== null,
    set: (val: boolean) => {
        if (!val && actionToConfirm.value !== null) {
            actionToConfirm.value.resolve(false);
            actionToConfirm.value = null;
        }
    },
});

/**
 * Is an action confirmable? (mainly here as a type guard)
 * @param action The action to check
 * @returns Whether the action is confirmable
 */
function isConfirmableAction(action: DialogAgentResponseAction): action is ConfirmableAction {
    return confirmableActions.includes(action as ConfirmableAction);
}

/**
 * Open the confirmation modal for a given action and wait for a response
 * If the action doesn't require confirmation, this resolves immediately
 * @param action The action to confirm
 * @returns Whether the action should proceed
 */
async function confirmAction(action: ConfirmableAction): Promise<boolean> {
    return new Promise((resolve) => {
        actionToConfirm.value = {
            action,
            resolve: (confirmed: boolean): void => {
                resolve(confirmed);
                actionToConfirm.value = null;
            },
        };
    });
}

/**
 * Trigger an action for the participant and update their data accordingly
 * @param action - The action to perform
 * @param waitingMessage - Toast message to show while waiting for the action to complete
 * @param successMessage - Toast message to show on success
 * @param failureMessage - Toast message to show on failure
 */
async function triggerAction(
    action: DialogAgentResponseAction,
    waitingMessage: string,
    successMessage: string,
    failureMessage: string,
): Promise<void> {
    // If the URL for this action doesn't exist, no permission - shouldn't get here
    if (!props.participant.response?._routes[action]) throw new Error(`${action} not permitted`);

    // Prompt for confirmation if needed
    if (isConfirmableAction(action) && !(await confirmAction(action))) {
        return; // action was cancelled
    }

    // Perform the action
    const toast = window.sbProps.toastActivate(waitingMessage);
    const response = await apiRequest<ApiDialogAgentActionResponse>({
        method: "POST",
        url: props.participant.response._routes[action],
    });

    if (!response.success) {
        toast.timeout({ text: failureMessage, isSuccess: false });
        return;
    }

    toast.timeout({ text: successMessage, isSuccess: true });

    // Update the stored participant data
    emit("updateParticipant", response.data);
}

/**
 * When the user clicks on "Submit on behalf of"
 */
async function onSobo(): Promise<void> {
    const toastContext = `on behalf of ${props.participant.user.fullName}`;
    await triggerAction(
        DialogAgentResponseAction.ACTION_SOBO,
        `Submitting ${toastContext}`,
        `Submitted ${toastContext}`,
        `Failed to submit ${toastContext}`,
    );
}

/**
 * When the user clicks on "Reset"
 */
async function onReset(): Promise<void> {
    const toastContext = `${locale.dialogAgent.proper()} for ${props.participant.user.fullName}`;
    await triggerAction(
        DialogAgentResponseAction.ACTION_RESET,
        `Resetting ${toastContext}...`,
        `Reset ${toastContext}`,
        `Failed to reset ${toastContext}`,
    );
}

/**
 * When the user clicks on "Reopen"
 */
async function onReopen(): Promise<void> {
    const toastContext = `${locale.dialogAgent.proper()} for ${props.participant.user.fullName}`;
    await triggerAction(
        DialogAgentResponseAction.ACTION_REOPEN,
        `Reopening ${toastContext}...`,
        `Reopened ${toastContext}`,
        `Failed to reopen ${toastContext}`,
    );
}

/**
 * When the user clicks on "Resolve Assistance Request"
 */
async function onResolve(): Promise<void> {
    const toastContext = `assistance request from ${props.participant.user.fullName}`;
    await triggerAction(
        DialogAgentResponseAction.ACTION_ASSISTANCE_RESOLVE,
        `Resolving ${toastContext}...`,
        `Resolved ${toastContext}`,
        `Failed to resolve ${toastContext}`,
    );
}
</script>

<template>
    <li v-if="props.participant.response?._routes.view">
        <a :href="props.participant.response._routes.view" class="icon-view">
            {{ `View ${locale.dialogAgent.proper()}` }}
        </a>
    </li>
    <li v-if="props.participant._routes.summary">
        <a :href="props.participant._routes.summary" class="icon-history">
            {{ `${locale.dialogAgent.proper()} History` }}
        </a>
    </li>
    <li v-if="props.participant.response?._routes.sobo">
        <button class="icon-approve" @click="onSobo">
            {{ `Submit on behalf of ${props.participant.user.fullName}` }}
        </button>
    </li>
    <li v-if="props.participant.response?._routes.reset">
        <button class="icon-refresh" @click="onReset">
            {{ `Reset ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant.response?._routes.reopen">
        <button class="icon-undo" @click="onReopen">
            {{ `Reopen ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant.response?._routes.assistanceResolve">
        <button class="icon-medical" @click="onResolve">
            {{ `Resolve Assistance Request` }}
        </button>
    </li>

    <basic-modal v-model:is-open="isConfirmModalOpen" has-background-click>
        <das-actions-modal
            v-if="actionToConfirm !== null"
            :participant="props.participant"
            :action="actionToConfirm.action"
            @confirm="actionToConfirm.resolve(true)"
            @cancel="actionToConfirm.resolve(false)"
        />
    </basic-modal>
</template>
