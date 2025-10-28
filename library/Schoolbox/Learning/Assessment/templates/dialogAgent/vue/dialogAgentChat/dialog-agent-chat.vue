<script lang="ts" setup>
import type { AiSession } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";

interface Props {
    generateRoute: string;
    aiSessionId: AiSession["id"];
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

window.Vue.onMounted(() => {
    acStore.init(props.generateRoute);
});
</script>

<template>
    <div class="actions-small-1 l-mar-s--bottom">
        <!-- TODO: Implement Request Teacher Help -->
        <button class="button">Request Teacher Help</button>
        <!-- TODO: Implement Submit -->
        <!-- TODO: Implement SOBO -->
        <nav><button class="button submit icon-approve">Submit</button></nav>
    </div>

    <ac-feed :feed="acStore.fullFeed" class="l-mar-m--bottom" style="min-height: 50vh" />
    <ac-prompt
        prompt-placeholder="PLACEHOLDER TEXT TO BE CONFIRMED"
        :prompt-attributes="[]"
        :ai-session-id="props.aiSessionId"
    />
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>
</template>

<style scoped lang="scss"></style>
