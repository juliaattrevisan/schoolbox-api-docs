<script setup lang="ts">
/**
 * To display recent history on a selected mark book item as well
 * as provide quick links to further actions
 */

import { until } from "@vueuse/core";
import type { Ref } from "vue";
import {
    ACTIVITY_TYPE_TASK,
    ACTIVITY_TYPE_PROJECT,
    ACTIVITY_TYPE_DUE_WORK,
    ACTIVITY_TYPE_QUIZ,
} from "@schoolboxAssessment/Types/assessmentTypes";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import SmallCard from "@schoolboxVue/components/card/small-card.vue";
import PopOutMenuItem from "@schoolboxVue/components/menu/pop-out-menu-item.vue";
import PopOutMenuDivider from "@schoolboxVue/components/menu/pop-out-menu-divider.vue";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookPopoutSelectedCellInfo, MarkbookClassImplActivityParticipant } from "./types/payload";

const mbStore = useMarkbookStore();

/**
 * an activity linked to the selected mark book item
 */
type ActivityItem = {
    type: string;
    date: {
        datetime: string;
        short: string;
        relative: string;
    };
    effector: {
        id: number;
        name: string;
    };
    submitted?: boolean;
    submittedOnTime?: boolean;
    published?: boolean;
};

// FloatingUI specific, declared here as showPopOut relies on one of these values existing.
const popOutElement = window.Vue.ref<HTMLDivElement | null>(null);
const referenceElement = window.Vue.ref<HTMLDivElement | null>(null);

const isLoading = window.Vue.ref(false);
const hasLoadError = window.Vue.ref(false);
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
        participant,
    };
});

const routes = window.Vue.computed(() => {
    const studentRoutes = cellInfo.value ? cellInfo.value.aImpl._routes.student[cellInfo.value.studentId] : undefined;

    return {
        dataEndpoint: studentRoutes?.["learningAssessmentTimeline.getFullTimeline"] ?? null,
        updateRoute: studentRoutes?.["learningDueWorkResponse.getParticularResponse"] ?? null,
        reviewRoute: studentRoutes?.["learningDueWorkResponse.getParticularResponse"] ?? null,
        publishRoute: studentRoutes?.["learningDueWorkFeedback.postPublishFeedback"] ?? null,
        excludeRoute: studentRoutes?.["learningAssessment.postExclude"] ?? null,
        includeRoute: studentRoutes?.["learningAssessment.postInclude"] ?? null,
        historyRoute: studentRoutes?.["learningAssessment.getSummary"] ?? null,
        postFeedback: studentRoutes?.postFeedback ?? null,
    };
});

const markbookElement = window.Vue.ref<HTMLDivElement | null>(null);

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
    if (cellInfo.value.participant.isExcluded) {
        return "The student has been excluded. You may include this student in this activity";
    }
    if (isActivityEmpty.value) {
        return "There has been no activity yet, you may create feedback or exclude this student from this activity";
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
 * Used to determine the text shown for the view status link / menu
 */
const viewStatusText = window.Vue.computed(() => {
    if (cellInfo.value === null) {
        return "";
    }
    if (cellInfo.value.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK) {
        return "Submission History";
    }
    if (cellInfo.value.activity.assessmentType === ACTIVITY_TYPE_TASK) {
        return "Student View";
    }
    if (cellInfo.value.activity.assessmentType === ACTIVITY_TYPE_PROJECT) {
        return "View Progress";
    }
    return "View Attempts";
});

/**
 *  Fetches an array from the dataEndpoint, which should return a
 *  an array of items corresponding to recent activity events.
 */
async function getActivity(): Promise<void> {
    if (cellInfo.value === null) {
        throw new Error("No selected cell to fetch activity for");
    }

    const route = routes.value.dataEndpoint;
    if (route === null) {
        throw new Error("Unable to find route for retrieving activity items");
    }

    try {
        isLoading.value = true;
        const response = await fetch(route, {
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        if (response.ok) {
            const activityItemsResponse = (await response.json()) as ActivityItem[];
            activityItems.value = activityItemsResponse;
            hasLoadError.value = false;
        } else {
            hasLoadError.value = true;
        }

        // TODO: Also need to go and populate participant.quiz (responseId/maxMark), but that endpoint doesn't exist yet
        if (cellInfo.value.activity.assessmentType === ACTIVITY_TYPE_QUIZ && cellInfo.value.participant) {
            cellInfo.value.participant.quiz = {
                responseId: null, // TODO see above...
                maxMark: null, // TODO see above...
            };
        }
    } catch (error) {
        hasLoadError.value = true;
    } finally {
        isLoading.value = false;
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
        hasLoadError.value = true;
        console.warn("No target specified for menu item selection");
        return;
    }

    // No specific action, just redirect
    if (action === null) {
        // we should append the returnUrl, for all calls, so we can get back to the markbook on save.
        const returnUrl = `?returnUrl=${btoa(window.location.pathname)}`;
        window.location.href = target + returnUrl;
        return;
    }

    if (cellInfo.value === null) {
        throw new Error("No selected cell to perform action on");
    }

    const { activity, participant } = cellInfo.value;

    // Not as basic redirect, js fetch things...
    try {
        isLoading.value = true;
        const response = await fetch(target, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        if (response.ok) {
            hasLoadError.value = false;

            // if action is exclude then toggle the exclusion UI
            if (action === "exclude" && participant !== null) {
                toggleExclusion(participant);
            }

            // If including student reload the activity list
            if (activity.assessmentType === ACTIVITY_TYPE_DUE_WORK) {
                await getActivity();
            } else {
                activityItems.value = [];
            }
            // close once changed (this is also to avoid a bug where the pop out doesn't close onClickOutside any more)
            close();
        } else {
            hasLoadError.value = true;
        }
    } catch (error) {
        hasLoadError.value = true;
        console.error(error);
    } finally {
        isLoading.value = false;

        // trigger a markbook reload for actions that can't just apply reactively
        if (action !== "exclude") {
            reloadMarkbook();
        }
    }
}

/**
 * Trigger the update feedback UI to appear for this cell
 */
function onUpdateFeedbackSelected(): void {
    if (cellInfo.value === null) {
        throw new Error("No selected cell to update feedback for");
    }

    // Close the popout
    close();

    // If this is already selected, then close it instead I guess
    // TODO: Maybe when a cell is selected, any other popout click just immediately opens that relevant feedback... hmm
    const newCell = {
        classImplId: cellInfo.value.classImplId,
        activityId: cellInfo.value.activityId,
        studentId: cellInfo.value.studentId,
    };

    if (JSON.stringify(newCell) === JSON.stringify(mbStore.feedbackSelectedCell)) {
        mbStore.feedbackSelectedCell = null;
        return;
    }

    mbStore.feedbackSelectedCell = newCell;
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
            return;
        }

        // To avoid accidentally opening the popout for the wrong cell, or closing the popout unexpectedly
        // if a user clicks on another cell while one is saving or loading already
        await until(isLoading).toBe(false);

        // A linked button has been selected so populate the pop out
        selectedCell.value = newValue;
        if (cellInfo.value === null) {
            throw new Error("No cell info available for selected cell");
        }

        referenceElement.value = cellInfo.value.referenceElement;
        isExpanded.value = false;

        if ([ACTIVITY_TYPE_TASK, ACTIVITY_TYPE_PROJECT].includes(cellInfo.value.activity.assessmentType)) {
            hasLoadError.value = false;
            isLoading.value = false;
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
    if (item.type === "response" && item.submitted && item.submittedOnTime) {
        return "submitted on time";
    }
    if (item.type === "response" && item.submitted && !item.submittedOnTime) {
        return "submitted late";
    }
    if (item.type === "response") {
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
        <div v-if="hasLoadError">
            <pop-out-menu-item class="is-disabled">
                <div class="warning-message">
                    There was an error during data retrieval. Please
                    <a href="" title="refresh the page"> refresh the page </a>
                    to try again and contact your support if the problem persists
                </div>
            </pop-out-menu-item>
        </div>
        <div v-else-if="isLoading || cellInfo === null" class="pop-out-menu__loader">
            <div class="loader"></div>
        </div>
        <div v-else ref="markbookElement" class="pop-out-menu" :class="{ 'is-expanded': isExpanded }">
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
                        :class="{ 'is-disabled': isExpansionDisabled || isActivityEmpty }"
                        @selected="onToggleSelected()"
                    >
                        {{ viewAllText }}
                    </pop-out-menu-item>
                </template>
                <pop-out-menu-divider />
                <template v-if="cellInfo.participant && !cellInfo.participant.isExcluded">
                    <template v-if="cellInfo.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK">
                        <pop-out-menu-item
                            v-if="!cellInfo.participant.isExcluded"
                            :class="{ 'is-disabled': !routes.postFeedback }"
                            :link="routes.updateRoute ?? undefined"
                            force-callback
                            @selected="onUpdateFeedbackSelected"
                        >
                            Update Feedback
                        </pop-out-menu-item>
                        <pop-out-menu-item
                            :class="{ 'is-disabled': !routes.reviewRoute || isActivityEmpty || !hasSubmissions }"
                            :link="routes.reviewRoute ?? undefined"
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
                        <pop-out-menu-item
                            v-if="!cellInfo.participant.isExcluded"
                            :class="{ 'is-disabled': !routes.postFeedback }"
                            :link="routes.updateRoute ?? undefined"
                            force-callback
                            @selected="onUpdateFeedbackSelected"
                        >
                            Update Feedback
                        </pop-out-menu-item>
                    </template>
                </template>
                <pop-out-menu-item
                    v-if="cellInfo.participant && cellInfo.participant.isExcluded"
                    :class="{ 'is-disabled': !routes.includeRoute || !cellInfo.participant.isExcluded }"
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
                            (isActivityEmpty && cellInfo.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK),
                    }"
                    :link="routes.historyRoute ?? undefined"
                >
                    {{ viewStatusText }}
                </pop-out-menu-item>
            </div>
        </div>
        <reveal-modal ref="excludeModalEl">
            <h2>Please confirm</h2>
            <p>The student has submissions and/or feedback for this assessment.</p>
            <p>Exclusion from this assessment will remove access to these submissions and/or feedback. Proceed?</p>
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
    min-height: 8rem;
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
