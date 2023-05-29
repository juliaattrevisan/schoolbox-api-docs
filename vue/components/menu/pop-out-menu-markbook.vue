<script setup lang="ts">
/**
 * To display recent history on a selected mark book item as well
 * as provide quick links to futher actions
 */

import { ref, computed, onMounted, onUnmounted } from "vue";
import type { Ref } from "vue";
import { useFloating, autoUpdate, flip, shift, offset} from '@floating-ui/vue';
import PopOut from "../popOut/pop-out.vue";
import SmallCard from "../card/small-card.vue";
import PopOutMenuItem from "./pop-out-menu-item.vue";
import PopOutMenuDivider from "./pop-out-menu-divider.vue";
import UserPhoto from "../userPhoto/user-photo.vue";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";

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

/**
 * the routes to load the activity data, as well as links to further actions
 * like 'Update feedback' or 'Exclude Student'
 */
type Routes = {
    dataEndpoint: string;
    updateRoute: string;
    reviewRoute: string;
    publishRoute: string;
    excludeRoute: string;
    includeRoute: string;
    historyRoute: string;
};

// FloatingUI specific, declared here as showPopOut relies on one of these values existing.
const popOutElement = ref<HTMLDivElement | null>(null);
const referenceElement = ref<HTMLDivElement | null>(null);
const assessmentType = ref('');

const isLoading = ref(false);
const isSelected = ref(false);
const hasLoadError = ref(false);
const showPopOut = computed(() => isSelected.value );
const isExpanded = ref(false); // is recent activity expanded
const activityItems: Ref<ActivityItem[]> = ref([]); // list of recent activity items to display
// has an event of type 'response' and response.submitted === true;
const hasSubmissions = computed(() => activityItems.value.some(item => item.type === 'response' && item.submitted));
// has an event of type 'feedback'
const hasFeedback = computed(() => activityItems.value.some(item => item.type === 'feedback'));
// has an event of type 'feedback' and feedback.published === false
const hasDraftFeedback = computed(() => activityItems.value.some(item => item.type === 'feedback' && item.published === false));
// is the user excluded from the assessment
const isExcluded = ref(false);
const isActivityEmpty = computed(() => activityItems.value.length === 0);
const isExpansionDisabled = computed(() => activityItems.value.length === 1);
const routes: Ref<Routes> = ref({
    dataEndpoint: "",
    updateRoute: "",
    reviewRoute: "",
    publishRoute: "",
    excludeRoute: "",
    includeRoute: "",
    historyRoute: "",
});
const markbookElement = ref<HTMLDivElement | null>(null);

// Exclude confirm, popout
const excludeModalEl = ref(null);


/**
 * Middleware are optional modifiers you can provide to floating-ui. Things that determine how
 * a floating item should flip, shift itself or rotate to prevent overflowing a parent container.
 */
const { x, y, strategy } = useFloating(referenceElement, popOutElement, {
    placement: 'right-start',
    middleware: [
        offset({
            mainAxis: 4
        }),
        flip({
            crossAxis: false
        }),
        shift({
            padding: 16
        }),
    ],
    whileElementsMounted: autoUpdate,
});

/**
 *  Used to determine the text shown on the "recent activity" menu
 *  item and provide more context to users. If our button is disabled,
 *  /why/ is it disabled? Helps users answer the question of why
 *  the activity they're expecting to see might not be there.
 */
const viewAllText = computed(() => {
    if ('Due Work' !== assessmentType.value) {
        return "";
    }
    if (isExcluded.value) {
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
const viewStatusText = computed(() => {
    if ('Due Work' === assessmentType.value) {
        return "Submission History";
    }
    if ('Task' === assessmentType.value) {
        return "Student View";
    }
    if ('Project' === assessmentType.value) {
        return "View Progress";
    }
    return "View Attempts";
});

/**
 * Exclude route.
 * Checks if the student has any feedback or submissions, if they do not, then it just excludes. else will prompt
 */
function excludeCheck() {
    // does the user have work?
    if (hasSubmissions.value || hasFeedback.value) {
        // has some kind of activity.. prompt for exclusion
        excludeModalEl.value.open();
    } else {
        // no work or feedback
        onMenuItemSelected(routes.value.excludeRoute, 'exclude');
    }
}

/**
 * actually exclude the student from the assessment
 */
function exclude() {
    closeExclude();
    onMenuItemSelected(routes.value.excludeRoute, 'exclude');
}

/**
 * close the exclude modal function
 */
function closeExclude() {
    excludeModalEl.value.close();
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
function reloadMarkbook() {
    closePopOut();

    // reload the markbook
    document.getElementById('submit_button').click();

    /**
     * disable the markbook popout
     */
    const elements = document.querySelectorAll('[data-pop-out-menu]');
    elements.forEach((cell) => {
        delete cell.dataset.popOutMenu;
    });
}

/**
 * Get the latest routes to display within the popout
 *
 * @param { Routes } newRoutes - the routes linked to the selection
 * @returns { Routes } - the updated routes
 */
function updateRoutes(newRoutes: Routes) {
    return {
        dataEndpoint: newRoutes.dataEndpoint,
        updateRoute: newRoutes.updateRoute,
        reviewRoute: newRoutes.reviewRoute,
        publishRoute: newRoutes.publishRoute,
        excludeRoute: newRoutes.excludeRoute,
        includeRoute: newRoutes.includeRoute,
        historyRoute: newRoutes.historyRoute,
    };
}

/**
 *  Fetches an array from the dataEndpoint, which should return a
 *  an array of items corresponding to recent activity events.
 */
async function getActivity() {
    try {
        isLoading.value = true;
        const response = await fetch(routes.value.dataEndpoint, {
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
    } catch (error) {
        hasLoadError.value = true;
    } finally {
        isLoading.value = false;
    }
}

/**
 * A linked button has been selected so populate the pop out
 *
 * @param { CustomEvent } event - the event fired from the mark book
 */
async function onPopOutSelected(event: CustomEvent) {
    // Make sure we're not reloading a menu we already have open.
    if(referenceElement.value !== event.detail.target){
        routes.value = updateRoutes(event.detail.routes as Routes);
        isSelected.value = true;
        isExpanded.value = false;
        isExcluded.value = "true" === event.detail.isExcluded; //make bool from string issues....
        assessmentType.value = event.detail.assessmentType;
        referenceElement.value = event.detail.target;
        if (('Task' === assessmentType.value) || ('Project' === assessmentType.value)) {
            hasLoadError.value = false;
            isLoading.value = false;
            activityItems.value = [];
            return;
        }
        await getActivity();
    }
}

/**
 * to be able to close the search results
 * when selection outside is made
 *
 * @param { event } event - the mouse event
 */
function onMouseClick(event: Event) {
    if (showPopOut.value
        && markbookElement.value !== null
        && !markbookElement.value.contains(event.target)
        && referenceElement.value !== event.target
    ){
        // Prevent the pop out from closing if the exclude modal is open and the click is within the modal.
        if (excludeModalEl.value !== null
            && excludeModalEl.value.modal[0].contains(event.target)
        ) {
            return;
        }

        closePopOut();
    }
}

/**
 * Close the pop out
 */
function closePopOut() {
    isSelected.value = false;
    // Clear out any selected states.
    referenceElement.value?.classList.remove('is-selected');
    referenceElement.value = null;
}

/**
 * Listen for selections to display the pop out as well as
 * any mouse clicks so we can close the pop out when suitable
 */
onMounted(() => {
    /**
     * Start listening for selection events
     * Currenty dispatched from library/Schoolbox/Learning/Markbook/templates/markbookOuter.twig
     */
    document.addEventListener("pop-out-menu-markbook__selected", (event) => onPopOutSelected(event));
    document.addEventListener("click", onMouseClick);
});

/**
 * Remove the added listeners
 */
onUnmounted(() => {
    document.removeEventListener("pop-out-menu-markbook__selected", onPopOutSelected);
    document.removeEventListener("click", onMouseClick);
});

/**
 * Translate types into more human-readable "action" format.
 *
 * @param { object } item - the item to check the action on
 * @returns { string } - the string to return
 */
function getAction(item: ActivityItem): string {

    // If we have a response, we have to consider additional scenarios.
    if (item.type === 'response' && item.submitted && item.submittedOnTime) {
        return 'submitted on time';
    };
    if (item.type === 'response' && item.submitted && !item.submittedOnTime) {
        return 'submitted late';
    }
    if (item.type === 'response') {
        return 'drafted a response';
    }
    if (item.type === 'view') {
        return 'viewed the assessment';
    }
    if (item.type === 'feedback') {
        return `${item.published ? 'published' : 'drafted'} feedback`;
    }

    // At least return blank as we're expeceted to return something
    return '';
}

/**
 * When available show/hide the additional activity items
 */
function onToggleSelected() {
    isExpanded.value = !isExpanded.value;
}

/**
 * UI toggle student to exclusion display
 * are change from excluded included and visa-versa
 */
function toggleExclusion() {
    // invert the excluded option that is currently the only post system
    isExcluded.value = !isExcluded.value;
    // update the data location in html, so if you click back in it doesn't break...
    const element = referenceElement.value;
    if (element) {
        element.classList.toggle('excluded');
        const currentValue = element.getAttribute('data-is-excluded');
        const newValue = currentValue === 'true' ? 'false' : 'true';
        element.setAttribute('data-is-excluded', newValue);
    }
}


/**
 * Navigate to a new page
 *
 * @param { string } target - The URL we want to navigate to.
 * @param { string } action - Should we redirect, or do a specific action? (exclude,publish)
 */
async function onMenuItemSelected(target: string, action: string|null = null) {
    // No specific action, just redirect
    if (null === action) {
        // we should append the returnUrl, for all calls, so we can get back to the markbook on save.
        let returnUrl = '?returnUrl=' + btoa(window.location.pathname);
        window.location.href = target + returnUrl;
        return;
    }

    // Not as basic redirect, js fetch things...
    try {
        isLoading.value = true;
        const response = await fetch(target, {
            method: 'POST',
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        if (response.ok) {
            hasLoadError.value = false;

            //if action is exclude then toggle the exclusion UI
            if ('exclude' === action) {
                toggleExclusion();
            }

            // If including student reload the activity list
            if ('Due Work' === assessmentType.value) {
                await getActivity();
            } else {
                activityItems.value = [];
            }
        } else {
            hasLoadError.value = true;
        }
    } catch (error) {
        hasLoadError.value = true;
    } finally {
        isLoading.value = false;
        //trigger a markbook reload
        reloadMarkbook();
    }
}
</script>

<template>
    <pop-out
        v-if="showPopOut"
        ref="popOutElement"
        :style="{
            position: strategy,
            top: `${ y ?? 0 }px`,
            left: `${ x ?? 0 }px`,
        }"
    >
        <div v-if="hasLoadError">
            <pop-out-menu-item class="is-disabled">
                <div class="s-warning-message">
                    There was an error during data retrieval. Please
                    <a href="" title="refresh the page"> refresh the page </a>
                    to try again and contact your support if the problem persists
                </div>
            </pop-out-menu-item>
        </div>
        <div v-else-if="isLoading && !hasLoadError" class="c-pop-out-menu__loader">
            <div class="loader"></div>
        </div>
        <div v-else class="c-pop-out-menu" ref="markbookElement" :class="{ 'is-expanded': isExpanded }">
            <div class="s-collapsible">
                <small-card v-for="(activityItem, index) in activityItems" :key="index">
                    <user-photo
                        :src="`/portrait.php?id=${activityItem.effector.id}&size=square32`"
                        :size="32"
                    ></user-photo>
                    <div class="c-card__details">
                        <div class="c-card__text">
                            {{ activityItem.effector.name }} {{ getAction(activityItem) }}
                        </div>
                        <div class="c-card__subtext">{{ activityItem.date.short }}</div>
                    </div>
                </small-card>
            </div>
            <div class="c-pop-out-menu__options">
                <pop-out-menu-item
                    :class="{ 'is-disabled': isExpansionDisabled || isActivityEmpty }"
                    @selected="onToggleSelected()"
                >
                    {{ viewAllText }}
                </pop-out-menu-item>
                <template v-if="'Due Work' === assessmentType & !isExcluded">
                <pop-out-menu-divider />
                    <pop-out-menu-item v-if="!isExcluded"
                        :class="{ 'is-disabled': !routes.updateRoute }"
                        @selected="onMenuItemSelected(routes.updateRoute)"
                    >
                        Update Feedback
                    </pop-out-menu-item>
                    <pop-out-menu-item
                        :class="{ 'is-disabled': !routes.reviewRoute || isActivityEmpty || !hasSubmissions }"
                        @selected="onMenuItemSelected(routes.reviewRoute)"
                    >
                        Review Submission
                    </pop-out-menu-item>
                    <pop-out-menu-item
                        :class="{ 'is-disabled': !routes.publishRoute || isActivityEmpty || !hasDraftFeedback }"
                        @selected="onMenuItemSelected(routes.publishRoute, 'publish')"
                    >
                        Publish Feedback
                    </pop-out-menu-item>
                </template>
                <pop-out-menu-item v-if="isExcluded"
                                   :class="{ 'is-disabled': !isExcluded }"
                                   @selected="onMenuItemSelected(routes.includeRoute, 'exclude')"
                >
                    Include Student
                </pop-out-menu-item>
                <pop-out-menu-item v-else-if="'Project' !== assessmentType"
                    :class="{ 'is-disabled': isExcluded }"
                    @selected="excludeCheck()"
                >
                    Exclude Student
                </pop-out-menu-item>
                <pop-out-menu-item v-if="!isExcluded"
                    :class="{ 'is-disabled': !routes.historyRoute || isExcluded || (isActivityEmpty && 'Due Work' === assessmentType) }"
                    @selected="onMenuItemSelected(routes.historyRoute)"
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
                    <button
                        @click.prevent="exclude()"
                        class="button submit"
                        id="excludeStudent-exclude-button"
                    >
                        Yes
                    </button>
                </li>
                <li>
                    <button
                        @click.prevent="closeExclude()"
                        class="button"
                        id="excludeStudent-cancel-button"
                    >
                        Cancel
                    </button>
                </li>
            </ul>
        </reveal-modal>
    </pop-out>
</template>

<style lang="scss">
.c-pop-out-menu {
    white-space: normal;
    width: 16rem;

    &__loader{
        width: 12rem;
    }
}

// Locally scoped class specifically for the error warning message
.s-warning-message{
    max-width: 16rem;

    // Allow pointer events on a disabled parent container.
    a {
        pointer-events: all;
    }
}

.is-expanded {
    .s-collapsible > .c-card {
        display: flex;
    }

    .c-pop-out-menu__options {
        .c-menu-item,
        .c-menu-item__divider {
            display: none;
        }

        .c-menu-item:first-child {
            display: block;
        }
    }
}

.s-collapsible {
    max-height: 20rem;
    overflow-y: auto;

    .c-card {
        display: none;

        &:first-child {
            display: flex;
        }
    }
}
// Additions to fix the exclude additional reveal-model
.reveal-modal-bg {
    z-index: 100002;
}
.reveal-modal {
    z-index: 100003;
}
</style>
