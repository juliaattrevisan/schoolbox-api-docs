<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { DialogAgentParticipant } from "./dialogAgentStatusTypes";

interface Props {
    participant: DialogAgentParticipant;
}

const props = defineProps<Props>();

/**
 * When the user clicks on "Submit on behalf of"
 */
async function onSobo(): Promise<void> {
    if (!props.participant._routes.sobo) return;

    const toastContext = `on behalf of ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Submitting ${toastContext}`);
    const response = await apiRequest({
        method: "POST",
        url: props.participant._routes.sobo,
    });

    toast.timeout({
        text: response.success ? `Submitted ${toastContext}` : `Failed to submit ${toastContext}`,
        isSuccess: response.success,
    });
}

/**
 * When the user clicks on "Reset"
 */
async function onReset(): Promise<void> {
    if (!props.participant._routes.reset) return;

    const toastContext = `${locale.dialogAgent.proper()} for ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Resetting ${toastContext}...`);
    const response = await apiRequest({
        method: "POST",
        url: props.participant._routes.reset,
    });

    toast.timeout({
        text: response.success ? `Reset ${toastContext}` : `Failed to reset ${toastContext}`,
        isSuccess: response.success,
    });
}

/**
 * When the user clicks on "Reopen"
 */
async function onReopen(): Promise<void> {
    if (!props.participant._routes.reopen) return;

    const toastContext = `${locale.dialogAgent.proper()} for ${props.participant.user.fullName}`;
    const toast = window.sbProps.toastActivate(`Reopening ${toastContext}...`);
    const response = await apiRequest({
        method: "POST",
        url: props.participant._routes.reopen,
    });

    toast.timeout({
        text: response.success ? `Reopened ${toastContext}` : `Failed to reopen ${toastContext}`,
        isSuccess: response.success,
    });
}

/**
 * When the user clicks on "Mark as assisted"
 */
async function onMarkAsAssisted(): Promise<void> {
    if (!props.participant._routes.resolved) return;

    const toastContext = `${props.participant.user.fullName} as assisted`;
    const toast = window.sbProps.toastActivate(`Marking ${toastContext}...`);
    const response = await apiRequest({
        method: "POST",
        url: props.participant._routes.resolved,
    });

    toast.timeout({
        text: response.success ? `Marked ${toastContext}` : `Failed to mark ${toastContext}`,
        isSuccess: response.success,
    });
}
</script>

<template>
    <li v-if="props.participant._routes.view">
        <a :href="props.participant._routes.view" class="icon-view">
            {{ `View ${locale.dialogAgent.proper()}` }}
        </a>
    </li>
    <li v-if="props.participant._routes.summary">
        <a :href="props.participant._routes.summary" class="icon-history">
            {{ `${locale.dialogAgent.proper()} History` }}
        </a>
    </li>
    <li v-if="props.participant._routes.sobo">
        <button class="icon-approve" @click="onSobo">
            {{ `Submit on behalf of ${props.participant.user.fullName}` }}
        </button>
    </li>
    <li v-if="props.participant._routes.reset">
        <button class="icon-refresh" @click="onReset">
            {{ `Reset ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant._routes.reopen">
        <button class="icon-undo" @click="onReopen">
            {{ `Reopen ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant._routes.resolved">
        <button class="icon-medical" @click="onMarkAsAssisted">
            {{ `Mark as assisted` }}
        </button>
    </li>
</template>
