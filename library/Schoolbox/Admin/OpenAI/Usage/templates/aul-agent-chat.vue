<script setup lang="ts">
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import type {
    AgentChatFeedMessage,
    AgentChatRequest,
    AgentChatResponse,
} from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiFeature, AiSessionLogApiResponse } from "./payload";

interface Props {
    sessionLog: AiSessionLogApiResponse<AiFeature.IMAGE>;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const sessionFeed = window.Vue.computed<AgentChatFeedMessage[]>(() => {
    return props.sessionLog.flatMap((entry) => {
        const req: AgentChatRequest = {
            type: "request",
            entryKey: acStore.getNextEntryKey(),
            user: entry.createdBy,
            payload: {
                userPrompt: entry.data.request,
                aiSessionId: entry.sessionId,
                // the rest of these are never stored in the log
                attributes: {},
                imageUrl: null,
                previousResponseId: null,
            },
            file: null,
        };

        const res: AgentChatResponse = {
            ...entry.data.response,
            type: "response",
            entryKey: acStore.getNextEntryKey(),
        };

        return [req, res];
    });
});
</script>

<template>
    <ac-feed :feed="sessionFeed" :allow-selection="false" error-prefix="There was a problem retrieving the response." />
</template>

<style scoped></style>
