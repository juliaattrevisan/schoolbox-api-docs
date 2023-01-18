<script setup lang="ts">
/**
 * To display recent history on a selected mark book item as well
 * as provide quick links to futher actions
 *
 * @todo
 * Add loading state to menu whilst waiting for cards to load async
 * Handle errors from fetching the data
 */

import { ref, computed, nextTick, onMounted, onUnmounted } from "vue";
import type { Ref } from "vue";
import { useFloating, autoUpdate, flip, shift, offset} from '@floating-ui/vue';
import PopOut from "../popOut/pop-out.vue";
import SmallCard from "../card/small-card.vue";
import PopOutMenuItem from "./pop-out-menu-item.vue";
import PopOutMenuDivider from "./pop-out-menu-divider.vue";
import UserPhoto from "../userPhoto/user-photo.vue";

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
    submittedOnTime?: string;
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
    historyRoute: string;
};

// FloatingUI specific, declared here as showPopOut relies on one of these values existing.
const popOutElement = ref<HTMLDivElement | null>(null);
const referenceElement = ref(null);

const isLoading = ref(false);
const isSelected = ref(false);
const hasLoadError = ref(false);
const showPopOut = computed(() => isSelected.value );
const isExpanded = ref(false); // is recent activity expanded
const activityItems: Ref<ActivityItem[]> = ref([]); // list of recent activity items to display
const isActivityEmpty = computed(() => activityItems.value.length === 0);
const isExpansionDisabled = computed(() => activityItems.value.length === 1);
const routes: Ref<Routes> = ref({
    dataEndpoint: "",
    updateRoute: "",
    reviewRoute: "",
    publishRoute: "",
    excludeRoute: "",
    historyRoute: "",
});
const markbookElement = ref<HTMLDivElement | null>(null);

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
        referenceElement.value = event.detail.target;
        await getActivity();
        if (!hasLoadError.value) {
            await nextTick();
        }
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
        isSelected.value = false;
        referenceElement.value = null;
    }
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
    const action = {
        feedback: "provided feedback",
        response: `submitted ${item.submittedOnTime ? " on time" : "late"}`, //  Check if an item was submitted late, adjust text accordingly
        view: "viewed the feedback",
    };
    return action[item.type];
}

/**
 * When available show/hide the additional activity items
 */
function onToggleSelected() {
    isExpanded.value = !isExpanded.value;
}

/**
 * Navigate to a new page
 *
 * @param { string } target - The URL we want to navigate to.
 */
function onMenuItemSelected(target: string) {
    window.location.href = target;
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
            <pop-out-menu-item class="is-disabled"> There was an error during data retrieval. </pop-out-menu-item>
        </div>
        <div v-if="isLoading && !hasLoadError" class="c-pop-out-menu__loader">
            <div class="loader"></div>
        </div>
        <div v-if="!isLoading && !hasLoadError" class="c-pop-out-menu" ref="markbookElement" :class="{ 'is-expanded': isExpanded }">
            <div className="s-collapsible">
                <small-card v-for="(activityItem, index) in activityItems" :key="index">
                    <user-photo
                        :src="`/portrait.php?id=${activityItem.effector.id}&size=square32`"
                        :size="32"
                    ></user-photo>
                    <div className="c-card__details">
                        <div className="c-card__text">
                            {{ activityItem.effector.name }} {{ getAction(activityItem) }}
                        </div>
                        <div className="c-card__subtext">{{ activityItem.date.short }}</div>
                    </div>
                </small-card>
            </div>
            <div className="c-pop-out-menu__options">
                <pop-out-menu-item
                    :class="{ 'is-disabled': isExpansionDisabled || isActivityEmpty }"
                    @selected="onToggleSelected()"
                >
                    {{ viewAllText }}
                </pop-out-menu-item>
                <pop-out-menu-divider />
                <pop-out-menu-item
                    :class="{ 'is-disabled': !routes.updateRoute }"
                    @selected="onMenuItemSelected(routes.updateRoute)"
                >
                    Update feedback
                </pop-out-menu-item>
                <pop-out-menu-item
                    :class="{ 'is-disabled': !routes.reviewRoute || isActivityEmpty }"
                    @selected="onMenuItemSelected(routes.reviewRoute)"
                >
                    Review submission
                </pop-out-menu-item>
                <pop-out-menu-item
                    :class="{ 'is-disabled': !routes.publishRoute || isActivityEmpty }"
                    @selected="onMenuItemSelected(routes.publishRoute)"
                >
                    Publish feedback
                </pop-out-menu-item>
                <pop-out-menu-item
                    :class="{ 'is-disabled': !routes.excludeRoute }"
                    @selected="onMenuItemSelected(routes.excludeRoute)"
                >
                    Exclude student
                </pop-out-menu-item>
                <pop-out-menu-item
                    :class="{ 'is-disabled': !routes.historyRoute || isActivityEmpty }"
                    @selected="onMenuItemSelected(routes.historyRoute)"
                >
                    Submission history
                </pop-out-menu-item>
            </div>
        </div>
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
</style>
