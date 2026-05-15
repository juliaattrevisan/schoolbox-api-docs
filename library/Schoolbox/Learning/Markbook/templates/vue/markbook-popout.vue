<script setup lang="ts">
/**
 * To display recent history on a selected mark book item as well
 * as provide quick links to further actions
 */

import { until } from "@vueuse/core";
import type { Ref } from "vue";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import {
    ACTIVITY_TYPE_TASK,
    ACTIVITY_TYPE_PROJECT,
    ACTIVITY_TYPE_DUE_WORK,
    ACTIVITY_TYPE_QUIZ,
    ACTIVITY_TYPE_DIALOG_AGENT,
    ACTIVITY_TYPE_REPORTING,
} from "@schoolboxAssessment/Types/assessmentTypes";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import SmallCard from "@schoolboxVue/components/card/small-card.vue";
import PopOutMenuItem from "@schoolboxVue/components/menu/pop-out-menu-item.vue";
import PopOutMenuDivider from "@schoolboxVue/components/menu/pop-out-menu-divider.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookPopoutSelectedCellInfo, MarkbookClassImplActivityParticipant } from "./types/payload";

const mbStore = useMarkbookStore();

/**
 * an activity linked to the selected mark book item
 */
type ActivityItem = {
    date: {
        datetime: string;
        short: string;
        relative: string;
    };
    effector: {
        id: number;
        name: string;
    };
} & (
    | { type: "response"; assistanceRequested: boolean; submitted: boolean; submittedOnTime: boolean }
    | { type: "feedback"; published: boolean }
    | { type: "view" }
);

// FloatingUI specific, declared here as showPopOut relies on one of these values existing.
const popOutElement = window.Vue.ref<HTMLDivElement | null>(null);
const referenceElement = window.Vue.ref<HTMLDivElement | null>(null);

const isExpanded = window.Vue.ref(false); // is recent activity expanded
const activityItems: Ref<ActivityItem[]> = window.Vue.ref([]); // list of recent activity items to display
// has an event of type 'response' and response.submitted === true;
const hasSubmissions = window.Vue.computed(() =>
    activityItems.value.some((item) => item.type === "response" && item.submitted),
);
// has an event of type 'feedback'
const hasFeedback = window.Vue.computed(() => activityItems.value.some((item) => item.type === "feedback"));
// has an event of type 'feedback' and feedback.published === false
const hasDraftFeedback = window.Vue.computed(() =>
    activityItems.value.some((item) => item.type === "feedback" && item.published === false),
);
// is the user excluded from the assessment
const isActivityEmpty = window.Vue.computed(() => activityItems.value.length === 0);
const isExpansionDisabled = window.Vue.computed(() => activityItems.value.length === 1);

/**
 * The currently selected cell, this is not set to null when the popout is closed
 * in case a user is interacting with a modal (i.e. exclude) or something else and it closes the popout
 * we still need to know which cell was selected to be able to perform actions on it
 */
const selectedCell = window.Vue.ref<MarkbookPopoutSelectedCellInfo | null>(null);

/**
 * All the data relating to the currently selected cell in the markbook
 */
const cellInfo = window.Vue.computed(() => {
    if (mbStore.data === null || selectedCell.value === null) {
        return null;
    }

    const activity = mbStore.data.assessments[selectedCell.value.activityId];
    if (activity === undefined) {
        console.warn("Activity not found for selected cell", selectedCell.value);
        return null;
    }

    const classImpl = mbStore.data.classImpls[selectedCell.value.classImplId];
    if (activity === undefined) {
        console.warn("Class implementation not found for selected cell", selectedCell.value);
        return null;
    }

    const student = classImpl.students[selectedCell.value.studentId];
    if (student === undefined) {
        console.warn("Student not found for selected cell", selectedCell.value);
        return null;
    }

    const aImpl = classImpl.assessments[selectedCell.value.activityId];
    if (aImpl === undefined) {
        console.warn("aImpl not found for selected cell", selectedCell.value);
        return null;
    }

    // It's possible to render this popout for a student that isn't a participant in the class
    const participant = aImpl.participants[student.id] ?? null;

    return {
        ...selectedCell.value,
        activity,
        classImpl,
        aImpl,
        student,
        participant,
    };
});

const routes = window.Vue.computed(() => {
    const studentRoutes = cellInfo.value ? cellInfo.value.aImpl._routes.student[cellInfo.value.studentId] : undefined;

    return {
        timelineEndpoint: studentRoutes?.["learningAssessmentTimeline.getFullTimeline"] ?? null,
        infoEndpoint: studentRoutes?.["learningAssessment.getInfo"] ?? null,
        updateRoute: studentRoutes?.["learningDueWorkResponse.getParticularResponse"] ?? null,
        reviewRoute: studentRoutes?.["learningDueWorkResponse.getParticularResponse"] ?? null,
        publishRoute: studentRoutes?.postPublishFeedback ?? null,
        excludeRoute: studentRoutes?.["learningAssessment.postExclude"] ?? null,
        includeRoute: studentRoutes?.["learningAssessment.postInclude"] ?? null,
        historyRoute: studentRoutes?.["learningAssessment.getSummary"] ?? null,
        postFeedback: studentRoutes?.postFeedback ?? null,
        getAssessmentParticipationForm: studentRoutes?.getAssessmentParticipationForm ?? null,
    };
});

// Exclude confirm, popout
const excludeModalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);

/**
 * When the pop out is closed, clear out the selected state
 */
function onPopOutClosed(): void {
    mbStore.popoutSelectedCell = null;
    referenceElement.value = null;
}

const { onButtonSelected, close } = usePopOut(
    referenceElement,
    popOutElement,
    "",
    "right-start",
    undefined,
    undefined,
    undefined,
    onPopOutClosed,
);

/**
 *  Used to determine the text shown on the "recent activity" menu
 *  item and provide more context to users. If our button is disabled,
 *  /why/ is it disabled? Helps users answer the question of why
 *  the activity they're expecting to see might not be there.
 */
const viewAllText = window.Vue.computed(() => {
    if (cellInfo.value === null) {
        return "";
    }
    if ([ACTIVITY_TYPE_TASK, ACTIVITY_TYPE_PROJECT].includes(cellInfo.value.activity.assessmentType)) {
        return "";
    }
    if (cellInfo.value.participant === null) {
        return "The student is no longer a participant in the class.";
    }

    const getAssessmentParticipationRoute =
        cellInfo.value?.aImpl._routes.student[cellInfo.value.studentId].getAssessmentParticipationForm ?? null;
    if (cellInfo.value.participant.isExcluded) {
        if (getAssessmentParticipationRoute === null) {
            return "The student has been excluded. You may include this student in this activity";
        }
        return `The student has been excluded. Visit the <a href="${getAssessmentParticipationRoute}">participation page</a> of the original learning activity to customise their status`;
    }
    if (isActivityEmpty.value) {
        if (getAssessmentParticipationRoute === null) {
            return "There has been no activity yet, you may create feedback or exclude this student from this activity";
        }
        return `There has been no activity yet, you may create feedback or visit the <a href="${getAssessmentParticipationRoute}">participation page</a> of the original learning activity to customise their status`;
    }
    if (isExpansionDisabled.value) {
        return "No additional recent activity";
    }
    if (isExpanded.value) {
        return "Hide recent activity";
    }
    return "View more recent activity";
});

/**
 * Used to determine the text shown for the view submission history link / menu
 */
const viewHistoryText = window.Vue.computed(() => {
    if (cellInfo.value === null) {
        return "";
    }
    switch (cellInfo.value.activity.assessmentType) {
        case ACTIVITY_TYPE_DUE_WORK:
            return "Submission History";
        case ACTIVITY_TYPE_QUIZ:
            return "Attempt History";
        case ACTIVITY_TYPE_DIALOG_AGENT:
            return `${locale.dialogAgent.proper()} History`;
        case ACTIVITY_TYPE_TASK:
            return "Student View";
        case ACTIVITY_TYPE_PROJECT:
            return "View Progress";
        case ACTIVITY_TYPE_REPORTING:
            return "View"; // TODO: figure out what this is eventually, but can wait til' we have the place it goes to sorted out
        default:
            return "View Attempts";
    }
});

/**
 * Check if any menu items below the divider will be visible
 */
const hasVisibleMenuItems = window.Vue.computed(() => {
    if (cellInfo.value === null) {
        return false;
    }

    const { participant, activity } = cellInfo.value;

    // Check if any of the conditional menu items will be shown
    const hasParticipantMenuItems =
        participant &&
        !participant.isExcluded &&
        [ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ, ACTIVITY_TYPE_REPORTING].includes(activity.assessmentType);

    const hasIncludeMenuItem =
        participant && participant.isExcluded && routes.value.getAssessmentParticipationForm === null;

    const hasExcludeMenuItem =
        participant && !participant.isExcluded && activity.assessmentType !== ACTIVITY_TYPE_PROJECT;

    const hasHistoryMenuItem = participant && !participant.isExcluded;

    return hasParticipantMenuItems || hasIncludeMenuItem || hasExcludeMenuItem || hasHistoryMenuItem;
});

/**
 *  Fetches an array from the dataEndpoint, which should return a
 *  an array of items corresponding to recent activity events.
 */
async function getActivity(): Promise<void> {
    if (cellInfo.value === null) {
        throw new Error("No selected cell to fetch activity for");
    }

    const timelineRoute = routes.value.timelineEndpoint;
    const infoRoute = routes.value.infoEndpoint;

    mbStore.popoutLoadError = false;
    mbStore.isPopoutLoading = true;

    try {
        if (timelineRoute === null || infoRoute === null) {
            throw new Error("Unable to find timelineRoute/infoRoute for retrieving activity items");
        }

        const timelineResponse = await fetch(timelineRoute, {
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        if (!timelineResponse.ok) {
            throw new Error(`Failed to fetch activity items: ${timelineResponse.statusText}`);
        }

        const activityItemsResponse = (await timelineResponse.json()) as ActivityItem[];
        activityItems.value = activityItemsResponse;

        /* and now fetch some additional activity data so that we can mark feedback
         * technically this *could* be split out to be elsewhere or happen on request to actually "Update Feedback",
         * but for now this is far more convenient and simple
         */
        if (
            [ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ, ACTIVITY_TYPE_REPORTING].includes(
                cellInfo.value.activity.assessmentType,
            ) &&
            cellInfo.value.participant
        ) {
            await mbStore.updateExtraInfo(infoRoute, cellInfo.value.participant);
        }
    } catch (error) {
        console.error("Error fetching activity items:", error);
        mbStore.popoutLoadError = true;
    } finally {
        mbStore.isPopoutLoading = false;
    }
}

/**
 * hack to refresh the markbook
 * clone from `library/Schoolbox/Filter/templates/panel.twig` to trigger the js on-click function
 *         // Listen for clicks on the submit button
 *         $submitButton.on('click', function(event){
 *             event.preventDefault();
 *             updateOriginalSelections();
 *             fetchReport();
 *             return false; // don't bubble
 *         });
 */
function reloadMarkbook(): void {
    onPopOutClosed();

    // reload the markbook
    document.getElementById("submit_button")?.click();
}

/**
 * UI toggle student to exclusion display
 * are change from excluded included and visa-versa
 * @param participant - The participant to toggle exclusion for
 */
function toggleExclusion(participant: MarkbookClassImplActivityParticipant): void {
    // invert the excluded option that is currently the only post system
    participant.isExcluded = !participant.isExcluded;
}

/**
 * The returnUrl to use when selecting a menu item that redirects,
 * so that users will return to the markbook after performing an action
 */
// const returnUrl = `?returnUrl=${btoa(window.location.pathname)}`;

/**
 * Navigate to a new page
 * @param target - The URL we want to navigate to.
 * @param action - Should we redirect, or do a specific action? (exclude,publish)
 */
async function onMenuItemSelected(target: string | null, action: string | null = null): Promise<void> {
    // No target was specified, the user likely doesn't have access to the route
    if (target === null) {
        mbStore.popoutLoadError = true;
        console.warn("No target specified for menu item selection");
        return;
    }

    // No specific action, just redirect
    if (action === null) {
        // we should append the returnUrl, for all calls, so we can get back to the markbook on save.
        window.location.href = getReturnUrl(target);
        return;
    }

    if (cellInfo.value === null) {
        throw new Error("No selected cell to perform action on");
    }

    const { activity, participant } = cellInfo.value;

    // Not as basic redirect, js fetch things...
    try {
        mbStore.isPopoutLoading = true;
        const response = await fetch(target, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        if (response.ok) {
            mbStore.popoutLoadError = false;

            // if action is exclude then toggle the exclusion UI
            if (action === "exclude" && participant !== null) {
                toggleExclusion(participant);
            }

            // If including student reload the activity list
            if (
                [
                    ACTIVITY_TYPE_DUE_WORK,
                    ACTIVITY_TYPE_QUIZ,
                    ACTIVITY_TYPE_DIALOG_AGENT,
                    ACTIVITY_TYPE_REPORTING,
                ].includes(activity.assessmentType)
            ) {
                await getActivity();
            } else {
                activityItems.value = [];
            }
            // close once changed (this is also to avoid a bug where the pop out doesn't close onClickOutside any more)
            close();
        } else {
            mbStore.popoutLoadError = true;
        }
    } catch (error) {
        mbStore.popoutLoadError = true;
        console.error(error);
    } finally {
        mbStore.isPopoutLoading = false;

        // trigger a markbook reload for actions that can't just apply reactively
        if (action !== "exclude") {
            reloadMarkbook();
        }
    }
}

const canUpdateFeedback = window.Vue.computed(() => {
    if (cellInfo.value === null) {
        return false;
    }

    const { participant, aImpl, activity } = cellInfo.value;

    // must be a due work, reporting, or quiz activity
    const validActivityTypes = [ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ, ACTIVITY_TYPE_REPORTING];
    if (!validActivityTypes.includes(activity.assessmentType)) {
        return false;
    }

    // participant must exist and not be excluded
    if (participant === null || participant.isExcluded) {
        return false;
    }

    // must have a postFeedback route
    if (!routes.value.postFeedback) {
        return false;
    }

    /* you must have the ability to either enter a mark or a comment (reporting can disable both at once) */
    if (!aImpl.feedbackCommentsEnable && aImpl.instructorMarkScheme.template.length === 0) {
        return false;
    }

    return true;
});

/**
 * Trigger the update feedback UI to appear for this cell
 */
function onUpdateFeedbackSelected(): void {
    if (cellInfo.value === null) {
        throw new Error("No selected cell to update feedback for");
    }

    if (!canUpdateFeedback.value) {
        return;
    }

    // Close the popout
    close();

    mbStore.setFeedbackSelectedCell({
        classImplId: cellInfo.value.classImplId,
        activityId: cellInfo.value.activityId,
        studentId: cellInfo.value.studentId,
    });
}

/**
 * Exclude route.
 * Checks if the student has any feedback or submissions, if they do not, then it just excludes. else will prompt
 */
async function excludeCheck(): Promise<void> {
    // does the user have work?
    if (hasSubmissions.value || hasFeedback.value) {
        // has some kind of activity.. prompt for exclusion
        if (excludeModalEl.value) excludeModalEl.value.open();
    } else {
        // no work or feedback
        await onMenuItemSelected(routes.value.excludeRoute, "exclude");
    }
}

/**
 * close the exclude modal function
 */
function closeExclude(): void {
    if (excludeModalEl.value === null) return;
    excludeModalEl.value.close();
}

/**
 * actually exclude the student from the assessment
 */
async function exclude(): Promise<void> {
    closeExclude();
    await onMenuItemSelected(routes.value.excludeRoute, "exclude");
}

/**
 * Open the pop out modal, waits for a tick to ensure the element is available
 * since we just changed it.
 */
async function openPopOut(): Promise<void> {
    await window.Vue.nextTick();
    await onButtonSelected();
}

/**
 * React to changes in the selected popout cell
 */
window.Vue.watch(
    () => mbStore.popoutSelectedCell,
    async (newValue) => {
        if (newValue === null) {
            // The popout was closed by something else
            referenceElement.value = null;
            close();
            return;
        }

        // To avoid accidentally opening the popout for the wrong cell, or closing the popout unexpectedly
        // if a user clicks on another cell while one is saving or loading already
        await until(() => mbStore.isPopoutLoading).toBe(false);

        // A linked button has been selected so populate the pop out
        selectedCell.value = newValue;
        if (cellInfo.value === null) {
            throw new Error("No cell info available for selected cell");
        }

        referenceElement.value = cellInfo.value.referenceElement;
        isExpanded.value = false;

        if ([ACTIVITY_TYPE_TASK, ACTIVITY_TYPE_PROJECT].includes(cellInfo.value.activity.assessmentType)) {
            mbStore.popoutLoadError = false;
            mbStore.isPopoutLoading = false;
            activityItems.value = [];
            return;
        }

        void getActivity();
        void openPopOut();
    },
    { immediate: true },
);

/**
 * Translate types into more human-readable "action" format.
 * @param item - the item to check the action on
 * @returns - the string to return
 */
function getAction(item: ActivityItem): string {
    // If we have a response, we have to consider additional scenarios.
    if (item.type === "response") {
        if (item.submitted && !item.submittedOnTime) {
            return "submitted late";
        }

        if (item.submitted) {
            return "submitted on time";
        }

        if (item.assistanceRequested) {
            return "requested assistance";
        }

        return "drafted a response";
    }

    if (item.type === "view") {
        return "viewed the assessment";
    }

    if (item.type === "feedback") {
        return `${item.published ? "published" : "drafted"} feedback`;
    }

    // At least return blank as we're expected to return something
    return "";
}

/**
 * When available show/hide the additional activity items
 */
function onToggleSelected(): void {
    isExpanded.value = !isExpanded.value;
}
</script>

<template>
    <div ref="popOutElement" class="pop-out" style="display: none">
        <div v-if="mbStore.popoutLoadError">
            <pop-out-menu-item class="is-disabled">
                <div class="warning-message">
                    There was an error during data retrieval. Please
                    <a href="" title="refresh the page"> refresh the page </a>
                    to try again and contact your support if the problem persists
                </div>
            </pop-out-menu-item>
        </div>
        <div v-else-if="mbStore.isPopoutLoading || cellInfo === null" class="pop-out-menu__loader">
            <div class="loader"></div>
        </div>
        <div v-else class="pop-out-menu" :class="{ 'is-expanded': isExpanded }">
            <div class="collapsible">
                <small-card v-for="(activityItem, index) in activityItems" :key="index">
                    <user-photo :user-id="activityItem.effector.id" render="tiny" rounded-corners></user-photo>
                    <div class="c-card__details">
                        <div class="c-card__text">{{ activityItem.effector.name }} {{ getAction(activityItem) }}</div>
                        <div class="c-card__subtext">{{ activityItem.date.short }}</div>
                    </div>
                </small-card>
            </div>
            <div class="pop-out-menu__options">
                <template v-if="cellInfo.activity.assessmentType !== ACTIVITY_TYPE_PROJECT">
                    <pop-out-menu-item
                        :just-text="
                            isExpansionDisabled || isActivityEmpty || routes.getAssessmentParticipationForm !== null
                                ? true
                                : undefined
                        "
                        @selected="onToggleSelected()"
                    >
                        <!-- eslint-disable-next-line vue/no-v-html | This is safe because viewAllText is controlled in this component -->
                        <span v-html="viewAllText"></span>
                    </pop-out-menu-item>
                </template>
                <pop-out-menu-divider v-if="hasVisibleMenuItems" />
                <template v-if="cellInfo.participant && !cellInfo.participant.isExcluded">
                    <template v-if="cellInfo.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK">
                        <pop-out-menu-item
                            v-if="!cellInfo.participant.isExcluded"
                            :class="{ 'is-disabled': !canUpdateFeedback }"
                            :link="routes.updateRoute ? getReturnUrl(routes.updateRoute) : undefined"
                            force-callback
                            @selected="onUpdateFeedbackSelected"
                        >
                            Update Feedback
                        </pop-out-menu-item>
                        <pop-out-menu-item
                            :class="{ 'is-disabled': !routes.reviewRoute || isActivityEmpty || !hasSubmissions }"
                            :link="routes.reviewRoute ? getReturnUrl(routes.reviewRoute) : undefined"
                        >
                            Review Submission
                        </pop-out-menu-item>
                        <!-- TODO: make this publish logic the same as the rest I guess -->
                        <pop-out-menu-item
                            :class="{ 'is-disabled': !routes.publishRoute || isActivityEmpty || !hasDraftFeedback }"
                            @selected="onMenuItemSelected(routes.publishRoute, 'publish')"
                        >
                            Publish Feedback
                        </pop-out-menu-item>
                    </template>
                    <template v-else-if="cellInfo.activity.assessmentType === ACTIVITY_TYPE_QUIZ">
                        <!-- notable ommission of :link, routes.updateRoute is due work only it seems -->
                        <pop-out-menu-item
                            v-if="!cellInfo.participant.isExcluded"
                            :class="{ 'is-disabled': !canUpdateFeedback }"
                            force-callback
                            @selected="onUpdateFeedbackSelected"
                        >
                            Update Feedback
                        </pop-out-menu-item>
                    </template>
                    <template v-else-if="cellInfo.activity.assessmentType === ACTIVITY_TYPE_REPORTING">
                        <!-- notable ommission of :link, routes.updateRoute is due work only it seems -->
                        <pop-out-menu-item
                            v-if="!cellInfo.participant.isExcluded"
                            :class="{ 'is-disabled': !canUpdateFeedback }"
                            force-callback
                            @selected="onUpdateFeedbackSelected"
                        >
                            Update Feedback
                        </pop-out-menu-item>
                        <!-- TODO: make this publish logic the same as the rest I guess -->
                        <pop-out-menu-item
                            :class="{ 'is-disabled': !routes.publishRoute || isActivityEmpty || !hasDraftFeedback }"
                            @selected="onMenuItemSelected(routes.publishRoute, 'publish')"
                        >
                            Publish Feedback
                        </pop-out-menu-item>
                    </template>
                </template>
                <!-- Only show the 'Include Student' button if it's the original assessment and not a customisation -->
                <pop-out-menu-item
                    v-if="
                        cellInfo.participant &&
                        cellInfo.participant.isExcluded &&
                        routes.getAssessmentParticipationForm === null
                    "
                    :class="{ 'is-disabled': !cellInfo.participant.isExcluded }"
                    @selected="onMenuItemSelected(routes.includeRoute, 'exclude')"
                >
                    Include Student
                </pop-out-menu-item>
                <pop-out-menu-item
                    v-if="
                        cellInfo.participant &&
                        !cellInfo.participant.isExcluded &&
                        cellInfo.activity.assessmentType !== ACTIVITY_TYPE_PROJECT
                    "
                    :class="{ 'is-disabled': !routes.excludeRoute && !cellInfo.participant.isExcluded }"
                    @selected="excludeCheck()"
                >
                    Exclude Student
                </pop-out-menu-item>
                <pop-out-menu-item
                    v-if="cellInfo.participant && !cellInfo.participant.isExcluded"
                    :class="{
                        'is-disabled':
                            !routes.historyRoute ||
                            cellInfo.participant.isExcluded ||
                            (isActivityEmpty && cellInfo.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK) ||
                            (isActivityEmpty && cellInfo.activity.assessmentType === ACTIVITY_TYPE_DIALOG_AGENT) ||
                            (isActivityEmpty && cellInfo.activity.assessmentType === ACTIVITY_TYPE_REPORTING),
                    }"
                    :link="routes.historyRoute ? getReturnUrl(routes.historyRoute) : undefined"
                >
                    {{ viewHistoryText }}
                </pop-out-menu-item>
            </div>
        </div>
        <reveal-modal ref="excludeModalEl">
            <h2>Please confirm</h2>
            <p>The student has submissions and/or feedback for this {{ locale.assessment.singular() }}.</p>
            <p>
                Exclusion from this {{ locale.assessment.singular() }} will remove access to these submissions and/or
                feedback. Proceed?
            </p>
            <ul class="flex-list buttons">
                <li>
                    <button id="excludeStudent-exclude-button" class="button submit" @click.prevent="exclude()">
                        Yes
                    </button>
                </li>
                <li>
                    <button id="excludeStudent-cancel-button" class="button" @click.prevent="closeExclude()">
                        Cancel
                    </button>
                </li>
            </ul>
        </reveal-modal>
    </div>
</template>

<style lang="scss" scoped>
.pop-out {
    background-color: #fff;
    border-radius: 0.25rem;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.2);
    min-width: 16rem;
    padding: 0.5rem 0;
    position: absolute;
    z-index: var(--sbx-depth-pop-over);

    &__divider {
        border: 1px solid rgba(255, 0, 0, 0.15);
        margin-block-end: 0;
        margin-block-start: 0.5rem;
    }
}

.pop-out-menu {
    white-space: normal;
    width: 16rem;

    &__loader {
        width: 12rem;
    }
}

// Locally scoped class specifically for the error warning message
.warning-message {
    max-width: 16rem;

    // Allow pointer events on a disabled parent container.
    a {
        pointer-events: all;
    }
}

.is-expanded {
    .collapsible > .c-card {
        display: flex;
    }

    .pop-out-menu__options {
        .c-menu-item,
        .c-menu-item__divider {
            display: none;
        }

        .c-menu-item:first-child {
            display: block;
        }
    }
}

.collapsible {
    max-height: 20rem;
    overflow-y: auto;

    .c-card {
        display: none;

        &:first-child {
            display: flex;
        }
    }
}
</style>
