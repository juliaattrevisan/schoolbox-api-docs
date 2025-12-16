<script setup lang="ts">
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import type { AgentChatFeedMessage } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiLogFormat, AiSessionLogApiResponse } from "./payload";

interface Props {
    sessionLog: AiSessionLogApiResponse<AiLogFormat.AGENT_CHAT>;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const sessionFeed = window.Vue.computed<AgentChatFeedMessage[]>(() => {
    const firstLog = props.sessionLog[0];
    if (firstLog === undefined) {
        return [];
    }

    return acStore.parseFeed(
        props.sessionLog.flatMap((entry) => [entry.data.input, entry.data.output]),
        firstLog.createdBy,
    );
});
</script>

<template>
    <ac-feed :feed="sessionFeed" :allow-selection="false" error-prefix="There was a problem retrieving the response." />
</template>

<style scoped></style>
