<script lang="ts" setup>
import { useEventBus, useMousePressed, useTimestamp } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import { getRfc3339String } from "@js/util/dateUtils";
import type { DateRFC3339 } from "@js/util/dateUtils";
import { apiRequest, getApiProblemStatus } from "@js/util/sbx/apiUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import { acPromptEventKey } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatSymbols";
import type { AgentChatLogEntry } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { ApiDialogAgentActionResponse, DialogAgentResponse } from "../status/dialogAgentStatusTypes"; // TODO: make this type central

interface Props {
    feed: AgentChatLogEntry[];
    response: DialogAgentResponse;
    student: UserAuthor;
    duration: number | null;
    closeAt: DateRFC3339;
    returnUrl: string;
}

/**
 * When the time's up modal is triggered, delay the redirect slightly so the user sees it
 * so that they aren't confused about what just happened
 */
const REDIRECT_DELAY_MS = 3000;

/* students can't submit after the close date, so trigger that kind of submission slightly earlier */
const CLOSE_DATE_BUFFER_MS = 5000;

const props = defineProps<Props>();
const acStore = useAgentChatStore();
const acPromptEventBus = useEventBus(acPromptEventKey);

const currentResponse = window.Vue.ref<DialogAgentResponse>(props.response);

window.Vue.onMounted(() => {
    currentResponse.value = props.response;
    acStore.importFeed(acStore.parseFeed(props.feed, props.student));
});

const currentTimestamp = useTimestamp({ interval: 500 });

/** How long until the response must be submitted(in seconds) */
const timeLeft = window.Vue.computed<number>(() => {
    const startedAt = currentResponse.value.startedAt ? new Date(currentResponse.value.startedAt).getTime() : null;

    /** Duration is only relevant after starting and if it's set */
    const durationFinishAt = startedAt !== null && props.duration !== null ? startedAt + props.duration * 1000 : null;
    const closeAt = new Date(props.closeAt).getTime() - CLOSE_DATE_BUFFER_MS;

    /** The earlier of the two possible end times is the actual end time */
    const endAt = durationFinishAt !== null && durationFinishAt < closeAt ? durationFinishAt : closeAt;
    return Math.ceil((endAt - currentTimestamp.value) / 1000);
});

/**
 * Re-fetch the latest response data from the server and update the currentResponse
 * @returns the API response
 */
async function refetchResponseData(): Promise<ReturnType<typeof apiRequest<ApiDialogAgentActionResponse>>> {
    if (!currentResponse.value._routes.status) {
        throw new Error("unable to refresh response data, missing status route"); // shouldn't get here
    }

    isLoading.value = true;
    const response = await apiRequest<ApiDialogAgentActionResponse>({
        method: "GET",
        url: currentResponse.value._routes.status,
    });
    isLoading.value = false;

    if (response.success) {
        currentResponse.value = response.data.response;
    }

    return response;
}

acPromptEventBus.on(({ guardrailTriggered }) => {
    /** As soon as the first message has been sent, update startedAt so the duration timer will work */
    if (currentResponse.value.startedAt === null) {
        currentResponse.value.startedAt = getRfc3339String(window.dayjs.unix(currentTimestamp.value / 1000));
    }

    /** When a guardrail is triggered */
    if (guardrailTriggered) {
        // Assume that you can no longer message or request/resolve assistance
        currentResponse.value._routes.message = null;
        currentResponse.value._routes.assistanceRequest = null;
        currentResponse.value._routes.assistanceResolve = null;

        // Then refetch the actual response data to ensure we're fully up to date
        void refetchResponseData();
    }
});

const isLoading = window.Vue.ref(false);

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
        if (getApiProblemStatus(response) === 403 && currentResponse.value._routes.status) {
            const statusResponse = await refetchResponseData();
            if (statusResponse.success) {
                return;
            }
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
 * After submission, redirect to the return URL
 * @param delayRedirect - If true, will delay the redirect slightly (this is so that the "Time's Up" modal
 * doesn't flash before redirecting, leaving the user confused as to what just happened)
 */
function redirectAfterSubmit(delayRedirect: boolean): void {
    setTimeout(
        () => {
            window.location.href = props.returnUrl;
        },
        delayRedirect ? REDIRECT_DELAY_MS : 0,
    );
}

/**
 * When a learner or instructor clicks the submit button inside the modal
 * @param delayRedirect - see redirectAfterSubmit
 */
async function submitResponse(delayRedirect: boolean): Promise<void> {
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
    redirectAfterSubmit(delayRedirect);
}

/**
 * Open the submit confirmation modal
 */
async function openSubmitModal(): Promise<void> {
    isSubmitModalOpen.value = true;

    /** If the feed isn't empty and we don't have a submit route yet, we might be out of date... try refetch first */
    if (acStore.feed.length > 0 && !currentResponse.value._routes.submit && currentResponse.value._routes.status) {
        await refetchResponseData();
    }
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

/** As soon as duration is exceeded, submit the response (no more messages can be sent at this point) */
const durationExceeded = window.Vue.computed<boolean>(() => timeLeft.value <= 0);
window.Vue.watch(
    durationExceeded,
    async (exceeded) => {
        if (exceeded && !isSubmitting.value && !isLoading.value) {
            /**
             * Open the modal first to show that time is up
             * and also to make sure the submit route exists (it might not if you have only sent messages)
             */
            await openSubmitModal();
            /**
             * Force the submission to occur immediately,
             * if the route doesn't exist then the close date likely passed before sending a message
             */
            if (currentResponse.value._routes.submit) {
                await submitResponse(true);
            } else {
                isSubmitting.value = true;
                redirectAfterSubmit(true);
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="actions-small-1 l-mar-s--bottom no-print">
        <button
            v-if="currentResponse._routes.assistanceRequest"
            class="button"
            type="button"
            :disabled="isHelpRequestChanging || isLoading || undefined"
            data-test="dialog-agent-response-request-help"
            @click="changeHelpRequest(currentResponse._routes.assistanceRequest)"
        >
            {{ isHelpRequestChanging ? "Requesting Assistance..." : "Request Teacher Help" }}
        </button>
        <nav>
            <button
                class="button submit icon-approve"
                type="submit"
                data-test="dialog-agent-response-submit"
                @click="openSubmitModal"
            >
                Submit
            </button>
        </nav>
    </div>

    <ac-feed
        ref="feedRef"
        class="l-mar-m--bottom ac-feed"
        min-height="20rem"
        :max-height="maxFeedHeight"
        :feed="acStore.fullFeed"
        error-prefix="There was a problem retrieving the response."
        :allow-retry="!!currentResponse._routes.message"
        initially-scrolled
        agent-icon="dialog-agent"
        show-timestamps
    >
        <template #header>
            <div
                v-if="currentResponse.assistanceRequestedAt !== null"
                class="alert-box highlight l-pad-xs l-pad-m--left"
            >
                <div class="l-flex l-flex--justify-space l-flex--align-center">
                    <span>Help Requested! Your teacher will be informed. You can keep working while you wait.</span>
                    <button
                        v-if="currentResponse._routes.assistanceResolve"
                        class="button highlight l-mar--0"
                        type="button"
                        :disabled="isHelpRequestChanging || isLoading || undefined"
                        data-test="dialog-agent-response-request-help-dismiss"
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
            v-if="currentResponse._routes.message"
            :request-config="{
                messageRoute: currentResponse._routes.message,
                aiSessionId: null /* session ID is already attached to a response and handled in the backend */,
                requireImage: false,
            }"
            prompt-placeholder="Type a response here..."
            prompt-button-title="Send message"
            :prompt-attributes="[]"
            :disabled="isSubmitting"
        />
    </div>
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>

    <basic-modal v-model:is-open="isSubmitModalOpen" has-background-click :is-close-disabled="isSubmitting">
        <template v-if="durationExceeded">
            <h2>Time's Up</h2>
            <p>Please wait while your response is submitted.</p>
            <div v-if="submitError" class="alert-box alert">
                <span>{{ submitError }}</span>
                <a href="#" class="close" @click.prevent="submitError = null">×</a>
            </div>
        </template>
        <div v-else-if="isLoading" class="loader"></div>
        <template v-else-if="currentResponse._routes.submit">
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
                    <button
                        class="button submit"
                        :disabled="isSubmitting || undefined"
                        data-test="dialog-agent-response-submit-confirm"
                        @click="() => submitResponse(false)"
                    >
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
