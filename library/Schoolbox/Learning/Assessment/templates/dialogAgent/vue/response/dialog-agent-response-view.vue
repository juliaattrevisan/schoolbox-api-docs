<script lang="ts" setup>
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import type { AgentChatLogEntry } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { DialogAgentResponse } from "../status/dialogAgentStatusTypes"; // TODO: make this type central
import DasActions from "../status/das-actions.vue";

interface Props {
    feed: AgentChatLogEntry[];
    response: DialogAgentResponse;
    student: UserAuthor;
    summaryRoute?: string | null;
    returnUrl?: string | null;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

window.Vue.onMounted(() => {
    acStore.importFeed(acStore.parseFeed(props.feed, props.student));
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
