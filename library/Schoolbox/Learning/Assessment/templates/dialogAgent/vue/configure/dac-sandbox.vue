<script setup lang="ts">
import { getRfc3339String } from "@js/util/dateUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";

interface Props {
    openingMessage: string;
    postMessage: string;
    isDirty: boolean;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const aiSessionId = window.Vue.ref<string>(""); // immediately set by resetFeed()

const isConfirmModalOpen = window.Vue.ref<boolean>(false);

/**
 * Reset the feed to the initial state (just an opening message)
 */
function resetFeed(): void {
    /** each new chat gets a unique session ID (uuidv4) */
    aiSessionId.value = crypto.randomUUID();

    acStore.resetFeed();

    acStore.feed.push({
        type: AgentChatMessageType.RESPONSE,
        data: {
            status: "success",
            responseId: null,
            message: props.openingMessage,
        },
        meta: null,
        entryKey: acStore.getNextEntryKey(),
        createdAt: getRfc3339String(new Date()),
    });

    isConfirmModalOpen.value = false;
}

window.Vue.onMounted(() => {
    resetFeed();
});

defineExpose({ resetFeed });
</script>

<template>
    <div class="content">
        <div v-if="props.isDirty" class="alert-box highlight" data-test="dac-sandbox-alert-dirty">
            You have unsaved changes, this sandbox will not reflect the current configuration until you save.
        </div>

        <ac-feed
            class="l-mar-m--bottom"
            min-height="20rem"
            max-height="70vh"
            :feed="acStore.fullFeed"
            :allow-retry="!!props.postMessage"
            error-prefix="There was a problem retrieving the response."
            agent-icon="dialog-agent"
            show-timestamps
        />

        <ac-prompt
            :request-config="{
                messageRoute: props.postMessage,
                aiSessionId,
                requireImage: false,
            }"
            prompt-placeholder="Type a response here..."
            prompt-button-title="Send message"
            :prompt-attributes="[]"
        />
        <span class="meta">AI sometimes gets things wrong — please check important details.</span>
    </div>

    <div class="component-action">
        <section>
            <button class="button" data-test="dac-sandbox-reset" @click="isConfirmModalOpen = true">
                Reset Sandbox
            </button>
        </section>
    </div>

    <basic-modal v-model:is-open="isConfirmModalOpen" has-background-click size="s">
        <h2>Please Confirm</h2>
        <p>Are you sure you want to reset the sandbox? This will clear all existing messages.</p>
        <ul class="flex-list buttons">
            <li>
                <button class="button submit" data-test="dac-sandbox-reset-confirm" @click="resetFeed">Reset</button>
            </li>
            <li><button class="button" @click="isConfirmModalOpen = false">Cancel</button></li>
        </ul>
    </basic-modal>
</template>

<style scoped></style>
