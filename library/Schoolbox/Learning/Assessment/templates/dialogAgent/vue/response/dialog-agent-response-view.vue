<script lang="ts" setup>
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiLogAgentChatData } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { DialogAgentResponse } from "../status/dialogAgentStatusTypes"; // TODO: make this type central
import DasActions from "../status/das-actions.vue";

interface Props {
    feed: AiLogAgentChatData[];
    response: DialogAgentResponse;
    student: UserAuthor;
    summaryRoute?: string | null;
    returnUrl?: string | null;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

/** If the current user isn't the student, they're the instructor I guess */
const isInstructor = window.Vue.computed(() => {
    return props.student.id !== window.schoolboxUser.id;
});

window.Vue.onMounted(() => {
    acStore.importFeed(acStore.parseFeed(props.feed, props.student));

    // TODO: all temporary, needs to inject into the right timeline position when it actually exists etc
    if (isInstructor.value) {
        acStore.feed.push({
            // TODO: this would only ever come from props.feed
            type: AgentChatMessageType.ALERT,
            side: AgentChatMessageType.RESPONSE,
            severity: "warning",
            entryKey: acStore.getNextEntryKey(),
            message: "Alert: Please check the chat above for challenging of guardrails or misuse of language.",
        });

        const assistanceTime = window.dayjs().subtract(5, "minutes").format(window.dateDisplayFormats.dayjsDatetime);
        acStore.feed.push({
            // TODO: a teacher can't spawn this, but they can 'resolve' it so we would need to track it somehow
            // hence need to be able to insert it based on the createdAt time of each log entry (not currently tracked on the frontend)
            // and then not include it when it's eventually resolved
            type: AgentChatMessageType.ALERT,
            side: AgentChatMessageType.REQUEST,
            severity: "warning-tint",
            entryKey: acStore.getNextEntryKey(),
            message: `${window.schoolboxUser.name} requested assistance @ ${assistanceTime}`,
        });

        // This won't actually exist I think. The "requested assistance" message just gets purged
        // const assistanceEndTime = window.dayjs().format(window.dateDisplayFormats.dayjsDatetime);
        // acStore.feed.push({
        //     type: "alert",
        //     side: "request",
        //     severity: "highlight",
        //     entryKey: acStore.getNextEntryKey(),
        //     message: `${window.schoolboxUser.name} stopped requesting assistance @ ${assistanceEndTime}`,
        // });
    }
});

/**
 * Reload/redirect the page after an action so that changes are reflected
 * This could be expanded to properly load back the state,
 * but since twig renders some stuff at the moment, this is good enough for now
 */
function onAction(): void {
    /** If there's a returnUrl, go to it */
    if (props.returnUrl) {
        window.location.href = props.returnUrl;
        return;
    }

    window.location.reload();
}
</script>

<template>
    <Teleport to="#header-options">
        <das-actions
            :user="props.student"
            :response="props.response"
            :summary-route="props.summaryRoute"
            :show-view="false"
            @update-response="onAction"
        />
    </Teleport>

    <ac-feed
        ref="feedRef"
        class="l-mar-m--bottom ac-feed"
        min-height="20rem"
        :max-height="undefined"
        :feed="acStore.fullFeed"
        error-prefix="There was a problem retrieving the response."
    />
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>
</template>

<style scoped lang="scss">
/* styles for printing (scoped) */
@media print {
    :deep(.ac-feed-alert) {
        display: none;
    }
}
</style>

<style>
/* styles for printing (global) */
@media print {
    #container,
    .island {
        padding: 0; /* to get more of the page estate */
    }

    .breadcrumb,
    #footer {
        display: none !important;
    }
}
</style>
