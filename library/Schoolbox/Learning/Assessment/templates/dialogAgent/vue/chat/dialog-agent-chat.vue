<script lang="ts" setup>
import { useMousePressed } from "@vueuse/core";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiLogAgentChatData } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { ApiDialogAgentActionResponse, DialogAgentResponse } from "../status/dialogAgentStatusTypes"; // TODO: make this type central

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

const isHelpRequestChanging = window.Vue.ref(false);
const helpRequestError = window.Vue.ref<string | null>(null);

/**
 * When a learner clicks "Request Teacher Help" or "Help No Longer Needed"
 * @param endpoint - The endpoint to call
 */
async function changeHelpRequest(endpoint: string): Promise<void> {
    isHelpRequestChanging.value = true;
    helpRequestError.value = null;
    const response = await apiRequest<ApiDialogAgentActionResponse>({
        method: "POST",
        url: endpoint,
    });
    isHelpRequestChanging.value = false;

    if (!response.success) {
        // TODO: If a student requests assistance, then the instructor resolves it somewhere else, you'd get a 403 here.
        // handling that is a bit messy, the best way is likely to have an endpoint where the frontend can query the current ApiDialogAgentActionResponse from
        // if (
        //     typeof response.data === "object" &&
        //     response.data !== null &&
        //     "status" in response.data &&
        //     response.data.status === 403
        // ) {
        //     // fetch the latest ApiDialogAgentActionResponse since we need the updated routes/state/etc
        //     return;
        // }

        const requestRoute = currentResponse.value._routes[DialogAgentResponseAction.ACTION_ASSISTANCE_REQUEST];
        helpRequestError.value = `Unable to ${endpoint === requestRoute ? "request for assistance" : "resolve assistance request"}: ${response.message}`;
        return;
    }

    currentResponse.value = response.data.response;
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

const feedRef = window.Vue.ref<InstanceType<typeof AcFeed> | null>(null);
const feedEl = window.Vue.computed(() => feedRef.value?.feedEl as HTMLElement | null);

const { pressed: mousePressed } = useMousePressed();

/**
 * Update the max feed height on mount and on resize
 */
function updateFeedHeight(): void {
    /**
     * Ignore if the mouse is currently held down
     * It's possible that they are resizing the textarea, which would cause some janky behaviour otherwise
     */
    if (mousePressed.value) {
        return;
    }

    const footer = document.querySelector("#footer");
    const content = document.querySelector("#content");
    const container = document.querySelector("#container");
    if (!feedEl.value || !footer || !content || !container) return;

    const feedRect = feedEl.value.getBoundingClientRect();
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

/**
 * When the mouse is released, update the feed height (in case they were resizing the textarea)
 * slight delay to ensure things are stable first
 */
function onMouseUp(): void {
    setTimeout(updateFeedHeight, 10);
}

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);

window.Vue.onMounted(() => {
    if (!feedEl.value) {
        console.warn("<dialog-agent-chat>: unable to find feed container");
        return;
    }

    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateFeedHeight);
    resizeObserver.value.observe(feedEl.value.parentElement!);
    resizeObserver.value.observe(document.querySelector("#container")!);

    // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
    updateFeedHeight();
    window.addEventListener("DOMContentLoaded", updateFeedHeight, { once: true });
    document.addEventListener("mouseup", onMouseUp);
});

window.Vue.onUnmounted(() => {
    // Clean up event listeners
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }
    window.removeEventListener("DOMContentLoaded", updateFeedHeight);
    document.removeEventListener("mouseup", onMouseUp);
});
</script>

<template>
    <div class="actions-small-1 l-mar-s--bottom no-print">
        <button
            v-if="!isInstructor && currentResponse._routes.assistanceRequest"
            class="button"
            type="button"
            :disabled="isHelpRequestChanging"
            @click="changeHelpRequest(currentResponse._routes.assistanceRequest)"
        >
            {{ isHelpRequestChanging ? "Requesting Assistance..." : "Request Teacher Help" }}
        </button>
        <nav>
            <button class="button submit icon-approve" type="submit" @click="submitResponse">
                {{ isInstructor ? `Submit on behalf of ${props.student.fullName}` : "Submit" }}
            </button>
        </nav>
    </div>

    <ac-feed
        ref="feedRef"
        class="l-mar-m--bottom"
        min-height="20rem"
        :max-height="maxFeedHeight"
        :feed="acStore.fullFeed"
        error-prefix="There was a problem retrieving the response."
        initially-scrolled
    >
        <template #header>
            <div
                v-if="!isInstructor && currentResponse.assistanceRequestedAt !== null"
                class="alert-box highlight l-mar--0 l-pad-xs l-pad-m--left"
            >
                <div class="l-flex l-flex--justify-space l-flex--align-center">
                    <span>Help Requested! Your teacher will be informed. You can keep working while you wait.</span>
                    <button
                        v-if="currentResponse._routes.assistanceResolve"
                        class="button highlight l-mar--0"
                        type="button"
                        :disabled="isHelpRequestChanging"
                        @click="changeHelpRequest(currentResponse._routes.assistanceResolve)"
                    >
                        Help No Longer Needed
                    </button>
                </div>
            </div>

            <div v-if="helpRequestError" class="alert-box alert">
                <span>{{ helpRequestError }}</span>
                <a href="#" class="close" @click.prevent="helpRequestError = null">×</a>
            </div>
        </template>
    </ac-feed>
    <div class="no-print">
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
    </div>
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>
</template>

<style scoped lang="scss">
/* styles for printing (scoped) */
@media print {
    .no-print {
        display: none;
    }

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
