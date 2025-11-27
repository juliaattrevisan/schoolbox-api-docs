<script lang="ts" setup>
import { getRfc3339String } from "@js/util/dateUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiLogAgentChatData } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { DialogAgentResponse } from "../status/dialogAgentStatusTypes"; // TODO: make this type central

interface Props {
    feed: AiLogAgentChatData[];
    response: DialogAgentResponse;
    student: UserAuthor;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const currentResponse = window.Vue.ref<DialogAgentResponse>(props.response);

/** If the current user isn't the student, they're the instructor I guess */
const isInstructor = window.Vue.computed(() => {
    return props.student.id !== window.schoolboxUser.id;
});

window.Vue.onMounted(() => {
    currentResponse.value = props.response;
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
 * When a learner clicks "Request Teacher Help" or "Help No Longer Needed"
 * @param helpRequested - Whether this should ask for help, or clear the flag
 */
function changeHelpRequest(helpRequested: boolean): void {
    const assistanceTime = window.dayjs().format(window.dateDisplayFormats.dayjsDatetime);
    if (helpRequested) {
        currentResponse.value.assistanceRequestedAt = getRfc3339String(window.dayjs());

        if (isInstructor.value) {
            acStore.feed.push({
                type: AgentChatMessageType.ALERT,
                side: AgentChatMessageType.REQUEST,
                severity: "warning-tint",
                entryKey: acStore.getNextEntryKey(),
                message: `${window.schoolboxUser.name} requested assistance @ ${assistanceTime}`,
            });
        }
    } else {
        currentResponse.value.assistanceRequestedAt = null;

        if (isInstructor.value) {
            acStore.feed.push({
                type: AgentChatMessageType.ALERT,
                side: AgentChatMessageType.REQUEST,
                severity: "highlight",
                entryKey: acStore.getNextEntryKey(),
                message: `${window.schoolboxUser.name} stopped requesting assistance @ ${assistanceTime}`,
            });
        }
    }

    console.warn("TODO"); // TODO
}

/**
 * When a learner or instructor clicks the submit button inside the modal
 */
function submitResponse(): void {
    // TODO: show a modal first, for students and/or staff?

    console.warn("TODO"); // TODO
}

/**
 * The max height of the feed area, responsive to screen size
 */
const maxFeedHeight = window.Vue.ref("50vh");

const feedContainerEl = window.Vue.useTemplateRef<HTMLDivElement>("feedContainerRef");

/**
 * Update the max feed height on mount and on resize
 */
function updateFeedHeight(): void {
    const footer = document.querySelector("#footer");
    const content = document.querySelector("#content");
    const container = document.querySelector("#container");
    if (!feedContainerEl.value || !footer || !content || !container) return;

    const feedRect = feedContainerEl.value.getBoundingClientRect();
    const footerRect = footer.getBoundingClientRect();
    const contentRect = content.getBoundingClientRect();
    const bodyRect = document.body.getBoundingClientRect();

    const totalUsedHeight =
        footerRect.height + // subtract the footer height
        (feedRect.y - bodyRect.y) + // the height between the top of the body (to account for scroll) and the top of the feed
        (contentRect.bottom - feedRect.bottom) + // the extra space below the feed until the bottom of the used content area
        parseInt(getComputedStyle(container).paddingBottom, 10);

    maxFeedHeight.value = `${window.innerHeight - totalUsedHeight}px`;
}

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);

window.Vue.onMounted(() => {
    if (!feedContainerEl.value) {
        console.warn("<dialog-agent-chat>: unable to find feed container");
        return;
    }

    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateFeedHeight);
    resizeObserver.value.observe(feedContainerEl.value);
    resizeObserver.value.observe(document.querySelector("#container")!);

    // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
    updateFeedHeight();
    window.addEventListener("DOMContentLoaded", updateFeedHeight, { once: true });
});

window.Vue.onUnmounted(() => {
    // Clean up event listeners
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }
});
</script>

<template>
    <div class="actions-small-1 l-mar-s--bottom">
        <button
            v-if="!isInstructor && currentResponse.assistanceRequestedAt === null"
            class="button"
            type="button"
            @click="changeHelpRequest(true)"
        >
            Request Teacher Help
        </button>
        <nav>
            <button class="button submit icon-approve" type="submit" @click="submitResponse">
                {{ isInstructor ? `Submit on behalf of ${props.student.fullName}` : "Submit" }}
            </button>
        </nav>
    </div>

    <div ref="feedContainerRef">
        <ac-feed
            class="l-mar-m--bottom"
            style="height: 100%; min-height: 20rem"
            :style="{ maxHeight: maxFeedHeight }"
            :feed="acStore.fullFeed"
            error-prefix="There was a problem retrieving the response."
        >
            <template #header>
                <div
                    v-if="!isInstructor && currentResponse.assistanceRequestedAt !== null"
                    class="alert-box warning-tint l-mar--0 l-pad-xs l-pad-m--left"
                >
                    <div class="l-flex l-flex--justify-space l-flex--align-center">
                        <span>Help Requested! Your teacher will be informed. You can keep working while you wait.</span>
                        <button
                            class="button warning l-mar--0 dac-feed-help-dismiss"
                            type="button"
                            @click="changeHelpRequest(false)"
                        >
                            Help No Longer Needed
                        </button>
                    </div>
                </div>
            </template>
        </ac-feed>
    </div>
    <ac-prompt
        v-if="props.response._routes.message"
        :request-config="{
            messageRoute: props.response._routes.message,
            aiSessionId: null /* session ID is already attached to a response and handled in the backend */,
            requireImage: false,
        }"
        prompt-placeholder="Type a response here..."
        :prompt-attributes="[]"
    />
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>
</template>

<style scoped lang="scss">
/** force the button to have white text, honestly idk about this but we're going with it for now */
.dac-feed-help-dismiss {
    color: white;
}
</style>
