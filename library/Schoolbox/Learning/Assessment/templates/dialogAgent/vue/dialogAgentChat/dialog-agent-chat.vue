<script lang="ts" setup>
import type { AiSession } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";

// TODO: define this properly somewhere based on a translator in the backend
type DialogAgentActivity = {
    id: number;
    openingMessage: string | null;
    // _routes: {
    //     postMessage: string;
    // };
};

type DialogAgentResponse = {
    aiSessionId: AiSession["id"];
    assistanceRequestedAt: string | null;
};

interface Props {
    generateRoute: string;
    activity: DialogAgentActivity;
    response: DialogAgentResponse;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const currentResponse = window.Vue.ref<DialogAgentResponse>(props.response);

window.Vue.onMounted(() => {
    currentResponse.value = props.response;

    acStore.init(props.generateRoute);

    if (props.activity.openingMessage !== null) {
        // TODO: I wonder if the backend should add this when it provides us the full chat?
        acStore.feed.push({
            type: "response",
            status: "success",
            entryKey: acStore.getNextEntryKey(),
            responseId: null,
            image: null,
            message: props.activity.openingMessage,
        });
    }
});

/**
 * When a learner clicks "Request Teacher Help" or "Help No Longer Needed"
 * @param helpRequested - Whether this should ask for help, or clear the flag
 */
function changeHelpRequest(helpRequested: boolean): void {
    if (helpRequested) {
        currentResponse.value.assistanceRequestedAt = window.dayjs().format(window.dateDisplayFormats.dayjsDateLong);
    } else {
        currentResponse.value.assistanceRequestedAt = null;
    }

    // TODO
}
</script>

<template>
    <div class="actions-small-1 l-mar-s--bottom">
        <!-- TODO: Implement Request Teacher Help -->
        <button v-if="currentResponse.assistanceRequestedAt === null" class="button" @click="changeHelpRequest(true)">
            Request Teacher Help
        </button>
        <!-- TODO: Implement Submit -->
        <!-- TODO: Implement SOBO -->
        <nav><button class="button submit icon-approve">Submit</button></nav>
    </div>

    <ac-feed :feed="acStore.fullFeed" class="l-mar-m--bottom" style="min-height: 50vh">
        <template #header>
            <div
                v-if="currentResponse.assistanceRequestedAt !== null"
                class="alert-box warning-tint l-mar--0 l-pad-xs l-pad-m--left"
            >
                <div class="l-flex l-flex--justify-space l-flex--align-center">
                    <span>Help Requested! Your teacher will be informed. You can keep working while you wait.</span>
                    <button class="button warning l-mar--0 dac-feed-help-dismiss" @click="changeHelpRequest(false)">
                        Help No Longer Needed
                    </button>
                </div>
            </div>
        </template>
    </ac-feed>
    <ac-prompt
        prompt-placeholder="Type response here"
        :prompt-attributes="[]"
        :ai-session-id="currentResponse.aiSessionId"
    />
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>
</template>

<style scoped lang="scss">
/** force the button to have white text, honestly idk about this but we're going with it for now */
.dac-feed-help-dismiss {
    color: white;
}
</style>
