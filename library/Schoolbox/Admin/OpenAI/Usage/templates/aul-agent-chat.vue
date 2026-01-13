<script setup lang="ts">
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import type { AnyAgentChatFeedMessage } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { AiFeature } from "./payload";
import type { AiLogFormat, AiSessionLogApiResponse } from "./payload";

interface Props {
    feature: AiFeature;
    sessionLog: AiSessionLogApiResponse<AiLogFormat.AGENT_CHAT>;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const sessionFeed = window.Vue.computed<AnyAgentChatFeedMessage[]>(() => {
    const firstLog = props.sessionLog[0];
    if (firstLog === undefined) {
        return [];
    }

    return acStore.parseFeed(
        props.sessionLog.flatMap((entry) => [entry.data.input, entry.data.output]),
        firstLog.createdBy,
    );
});

const agentIcon = window.Vue.computed(() => {
    if (props.feature === AiFeature.DIALOG_AGENT) {
        return "dialog-agent";
    }

    return "ai";
});
</script>

<template>
    <ac-feed
        :feed="sessionFeed"
        :allow-selection="false"
        error-prefix="There was a problem retrieving the response."
        :agent-icon="agentIcon"
        show-timestamps
    />
</template>

<style scoped></style>
