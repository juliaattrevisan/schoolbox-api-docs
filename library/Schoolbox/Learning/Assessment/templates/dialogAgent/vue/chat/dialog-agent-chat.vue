<script lang="ts" setup>
import locale from "@js/terminology/rosettaStone";
import { useMousePressed } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import { apiRequest, getApiProblemStatus } from "@js/util/sbx/apiUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiLogAgentChatData } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { ApiDialogAgentActionResponse, DialogAgentResponse } from "../status/dialogAgentStatusTypes"; // TODO: make this type central
import DasActions from "../status/das-actions.vue";

interface Props {
    feed: AiLogAgentChatData[];
    response: DialogAgentResponse;
    student: UserAuthor;
    summaryRoute?: string | null;
    returnUrl: string;
    readonly: boolean;
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
    if (props.readonly && isInstructor.value) {
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
        let errorMessage: string | null = response.message;

        /**
         * If a student requests assistance, then the instructor resolves it somewhere else, you'd get a 403 here.
         * When that occurs, attempt to find the latest response data to sync back up.
         */
        if (getApiProblemStatus(response) === 403 && props.response._routes.status) {
            const statusResponse = await apiRequest<ApiDialogAgentActionResponse>({
                method: "GET",
                url: props.response._routes.status,
            });

            if (statusResponse.success) {
                currentResponse.value = statusResponse.data.response;
                return;
            }

            errorMessage = statusResponse.message;
        }

        const requestRoute = currentResponse.value._routes[DialogAgentResponseAction.ACTION_ASSISTANCE_REQUEST];
        helpRequestError.value = `Unable to ${endpoint === requestRoute ? "request for assistance" : "resolve assistance request"}: ${errorMessage}`;
        return;
    }

    currentResponse.value = response.data.response;
}

const isSubmitModalOpen = window.Vue.ref(false);
const isSubmitting = window.Vue.ref(false);
const submitError = window.Vue.ref<string | null>(null);

/**
 * When a learner or instructor clicks the submit button inside the modal
 */
async function submitResponse(): Promise<void> {
    if (!currentResponse.value._routes.submit) {
        throw new Error("unable to submit, missing submit route"); // shouldn't get here
    }

    isSubmitting.value = true;
    submitError.value = null;
    const response = await apiRequest<ApiDialogAgentActionResponse>({
        method: "POST",
        url: currentResponse.value._routes.submit,
    });

    if (!response.success) {
        isSubmitting.value = false;
        submitError.value = response.message;
        return;
    }

    /** If successful, redirect to the return URL - keep isSubmitting as true as the page will soon unload */
    window.location.href = props.returnUrl;
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

/**
 * Reload the page after an action so that changes are reflected
 * This could be expanded to properly load back the state,
 * but since twig renders some stuff at the moment, this is good enough for now
 */
function onAction(): void {
    window.location.reload();
}
</script>

<template>
    <Teleport v-if="props.readonly" to="#header-options">
        <das-actions
            :user="props.student"
            :response="currentResponse"
            :summary-route="props.summaryRoute"
            :show-view="false"
            @update-response="onAction"
        />
    </Teleport>

    <div v-else class="actions-small-1 l-mar-s--bottom no-print">
        <button
            v-if="currentResponse._routes.assistanceRequest"
            class="button"
            type="button"
            :disabled="isHelpRequestChanging"
            @click="changeHelpRequest(currentResponse._routes.assistanceRequest)"
        >
            {{ isHelpRequestChanging ? "Requesting Assistance..." : "Request Teacher Help" }}
        </button>
        <nav>
            <button class="button submit icon-approve" type="submit" @click="isSubmitModalOpen = true">Submit</button>
        </nav>
    </div>

    <ac-feed
        ref="feedRef"
        class="l-mar-m--bottom ac-feed"
        min-height="20rem"
        :max-height="maxFeedHeight"
        :feed="acStore.fullFeed"
        error-prefix="There was a problem retrieving the response."
        initially-scrolled
    >
        <template #header>
            <div
                v-if="!props.readonly && currentResponse.assistanceRequestedAt !== null"
                class="alert-box highlight l-pad-xs l-pad-m--left"
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
            v-if="!props.readonly && props.response._routes.message"
            :request-config="{
                messageRoute: props.response._routes.message,
                aiSessionId: null /* session ID is already attached to a response and handled in the backend */,
                requireImage: false,
            }"
            prompt-placeholder="Type a response here..."
            :prompt-attributes="[]"
            :disabled="isSubmitting"
        />
    </div>
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>

    <basic-modal v-model:is-open="isSubmitModalOpen" has-background-click :is-close-disabled="isSubmitting">
        <template v-if="props.response._routes.submit">
            <h2>Confirm Submission</h2>
            <p>
                Are you sure you want to finalise this {{ locale.dialogAgent.singular() }}? Once submitted, you will not
                be able to make further changes.
            </p>
            <div v-if="submitError" class="alert-box alert">
                <span>{{ submitError }}</span>
                <a href="#" class="close" @click.prevent="submitError = null">×</a>
            </div>
            <ul class="flex-list buttons">
                <li>
                    <button class="button submit" :disabled="isSubmitting || undefined" @click="submitResponse">
                        {{ isSubmitting ? "Submitting... please wait" : "Submit" }}
                    </button>
                </li>
                <li><button class="button" @click="isSubmitModalOpen = false">Cancel</button></li>
            </ul>
        </template>
        <template v-else>
            <h2>Nothing To Submit</h2>
            <p>
                You haven't made any responses for this {{ locale.dialogAgent.singular() }} yet. Please complete the
                {{ locale.activity.singular() }} and try again.
            </p>
            <button class="button full-width" @click="isSubmitModalOpen = false">Close</button>
        </template>
    </basic-modal>
</template>

<style scoped lang="scss">
.ac-feed .alert-box {
    margin-bottom: 0;
}

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
