<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { apiRequest, getApiProblemStatus } from "@js/util/sbx/apiUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { UserAuthor, UserCC } from "@schoolboxLibrary/types/User";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { ApiDialogAgentActionResponse, DialogAgentResponse } from "./dialogAgentStatusTypes";
import DasActionsModal from "./das-actions-modal.vue";

interface Props {
    user: UserCC | UserAuthor;
    response: DialogAgentResponse | null;
    summaryRoute?: string | null;
    showView?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    summaryRoute: null,
    showView: true,
    showSubmit: false,
});
const emit = defineEmits<{
    (e: "updateResponse", data: ApiDialogAgentActionResponse): void;
}>();

const isSameUser = window.Vue.computed(() => props.user.id === window.schoolboxUser.id);

export type ConfirmableAction = (typeof confirmableActions)[number];
const confirmableActions = [
    DialogAgentResponseAction.ACTION_SUBMIT,
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
    if (!props.response?._routes[action]) throw new Error(`${action} not permitted`);

    // Prompt for confirmation if needed
    if (isConfirmableAction(action) && !(await confirmAction(action))) {
        return; // action was cancelled
    }

    // Perform the action
    const toast = window.sbProps.toastActivate(waitingMessage);
    const response = await apiRequest<ApiDialogAgentActionResponse>({
        method: "POST",
        url: props.response._routes[action],
    });

    let updatedData: ApiDialogAgentActionResponse | null = null;
    if (response.success) {
        updatedData = response.data;
    } else if (getApiProblemStatus(response) === 403 && props.response._routes.status) {
        /**
         * If this action fails due to a status change elsewhere, then you'd get a 403 here.
         * When that occurs, attempt to find the latest response data to sync back up.
         */
        const statusResponse = await apiRequest<ApiDialogAgentActionResponse>({
            method: "GET",
            url: props.response._routes.status,
        });

        /** If the action we tried to do is still available, then there's some other issue. Otherwise update the stored data */
        if (statusResponse.success && !statusResponse.data.response._routes[action]) {
            updatedData = statusResponse.data;
        }
    }

    if (updatedData === null) {
        toast.timeout({ text: failureMessage, isSuccess: false });
        return;
    }

    toast.timeout({ text: successMessage, isSuccess: true });

    // Update the stored participant data
    emit("updateResponse", updatedData);
}

/**
 * When the user clicks on "Submit on behalf of"
 */
async function onSubmit(): Promise<void> {
    const toastContext = isSameUser.value ? locale.dialogAgent.proper() : `on behalf of ${props.user.fullName}`;
    await triggerAction(
        DialogAgentResponseAction.ACTION_SUBMIT,
        `Submitting ${toastContext}`,
        `Submitted ${toastContext}`,
        `Failed to submit ${toastContext}`,
    );
}

/**
 * When the user clicks on "Reset"
 */
async function onReset(): Promise<void> {
    const toastContext = `${locale.dialogAgent.proper()} for ${props.user.fullName}`;
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
    const toastContext = `${locale.dialogAgent.proper()} for ${props.user.fullName}`;
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
    const toastContext = `assistance request from ${props.user.fullName}`;
    await triggerAction(
        DialogAgentResponseAction.ACTION_ASSISTANCE_RESOLVE,
        `Resolving ${toastContext}...`,
        `Resolved ${toastContext}`,
        `Failed to resolve ${toastContext}`,
    );
}
</script>

<template>
    <li v-if="props.showView && props.response?._routes.view">
        <a :href="props.response._routes.view" data-test="das-actions-view">
            <sbx-icon name="view"></sbx-icon>
            {{ `View ${locale.dialogAgent.proper()}` }}
        </a>
    </li>
    <li v-if="props.summaryRoute">
        <a :href="props.summaryRoute" class="icon-history" data-test="das-actions-summary">
            {{ `${locale.dialogAgent.proper()} History` }}
        </a>
    </li>
    <li v-if="props.response?._routes.submit">
        <button data-test="das-actions-submit" @click="onSubmit">
            <sbx-icon name="approve"></sbx-icon>
            {{ isSameUser ? `Submit ${locale.dialogAgent.proper()}` : `Submit on behalf of ${props.user.fullName}` }}
        </button>
    </li>
    <li v-if="props.response?._routes.reset">
        <button data-test="das-actions-reset" @click="onReset">
            <sbx-icon name="refresh"></sbx-icon>
            {{ `Reset ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.response?._routes.reopen">
        <button data-test="das-actions-reopen" @click="onReopen">
            <sbx-icon name="undo"></sbx-icon>
            {{ `Reopen ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.response?._routes.assistanceResolve">
        <button class="icon-medical" data-test="das-actions-assistanceResolve" @click="onResolve">
            {{ `Resolve Assistance Request` }}
        </button>
    </li>

    <basic-modal v-model:is-open="isConfirmModalOpen" has-background-click>
        <das-actions-modal
            v-if="actionToConfirm !== null"
            :user="props.user"
            :action="actionToConfirm.action"
            @confirm="actionToConfirm.resolve(true)"
            @cancel="actionToConfirm.resolve(false)"
        />
    </basic-modal>
</template>
