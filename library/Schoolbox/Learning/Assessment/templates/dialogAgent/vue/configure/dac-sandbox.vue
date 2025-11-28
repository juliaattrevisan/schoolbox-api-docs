<script setup lang="ts">
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";

interface Props {
    openingMessage: string;
    postMessage: string;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const aiSessionId = window.Vue.ref<string>(""); // immediately set by resetFeed()

/**
 * Reset the feed to the initial state (just an opening message)
 */
function resetFeed(): void {
    /** each new chat gets a unique session ID (uuidv4) */
    aiSessionId.value = crypto.randomUUID();

    acStore.resetFeed();

    acStore.feed.push({
        type: AgentChatMessageType.RESPONSE,
        status: "success",
        entryKey: acStore.getNextEntryKey(),
        message: props.openingMessage,
        responseId: null,
        image: null,
    });
}

window.Vue.onMounted(() => {
    resetFeed();
});
</script>

<template>
    <ac-feed
        class="l-mar-m--bottom"
        min-height="20rem"
        max-height="70vh"
        :feed="acStore.fullFeed"
        error-prefix="There was a problem retrieving the response."
    />
    <ac-prompt
        :request-config="{
            messageRoute: props.postMessage,
            aiSessionId,
            requireImage: false,
        }"
        prompt-placeholder="Type a response here..."
        :prompt-attributes="[]"
    />
</template>

<style scoped></style>
