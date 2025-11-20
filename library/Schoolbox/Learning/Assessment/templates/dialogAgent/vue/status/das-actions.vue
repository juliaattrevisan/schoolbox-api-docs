<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { ApiDialogAgentActionResponse, DialogAgentParticipant } from "./dialogAgentStatusTypes";

interface Props {
    participant: DialogAgentParticipant;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: "updateParticipant", data: ApiDialogAgentActionResponse): void;
}>();

/**
 * Trigger an action for the participant and update their data accordingly
 * @param actionUrl - The URL to send the action request to
 * @param toast - A toast that is already active to update when done
 * @param successMessage - Toast message to show on success
 * @param failureMessage - Toast message to show on failure
 */
async function triggerAction(
    actionUrl: string,
    toast: ReturnType<typeof window.sbProps.toastActivate>,
    successMessage: string,
    failureMessage: string,
): Promise<void> {
    const response = await apiRequest<ApiDialogAgentActionResponse>({
        method: "POST",
        url: actionUrl,
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
    if (!props.participant.response?._routes.sobo) return;

    const toastContext = `on behalf of ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Submitting ${toastContext}`);

    await triggerAction(
        props.participant.response._routes.sobo,
        toast,
        `Submitted ${toastContext}`,
        `Failed to submit ${toastContext}`,
    );
}

/**
 * When the user clicks on "Reset"
 */
async function onReset(): Promise<void> {
    if (!props.participant._routes.reset) return;

    const toastContext = `${locale.dialogAgent.proper()} for ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Resetting ${toastContext}...`);
    await triggerAction(
        props.participant._routes.reset,
        toast,
        `Reset ${toastContext}`,
        `Failed to reset ${toastContext}`,
    );
}

/**
 * When the user clicks on "Reopen"
 */
async function onReopen(): Promise<void> {
    if (!props.participant.response?._routes.reopen) return;

    const toastContext = `${locale.dialogAgent.proper()} for ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Reopening ${toastContext}...`);
    await triggerAction(
        props.participant.response._routes.reopen,
        toast,
        `Reopened ${toastContext}`,
        `Failed to reopen ${toastContext}`,
    );
}

/**
 * When the user clicks on "Resolve Assistance Request"
 */
async function onResolve(): Promise<void> {
    if (!props.participant.response?._routes.resolve) return;

    const toastContext = `assistance request from ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Resolving ${toastContext}...`);
    await triggerAction(
        props.participant.response._routes.resolve,
        toast,
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
    <li v-if="props.participant._routes.reset">
        <button class="icon-refresh" @click="onReset">
            {{ `Reset ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant.response?._routes.reopen">
        <button class="icon-undo" @click="onReopen">
            {{ `Reopen ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant.response?._routes.resolve">
        <button class="icon-medical" @click="onResolve">
            {{ `Resolve Assistance Request` }}
        </button>
    </li>
</template>
