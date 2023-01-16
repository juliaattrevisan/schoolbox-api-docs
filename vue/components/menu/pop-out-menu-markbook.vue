<script setup lang="ts">

import { ref } from "vue";
import type { Ref } from "vue"
import PopOut from "../popOut/pop-out.vue";
import SmallCard from "../card/small-card.vue";
import PopOutMenuItem from "./pop-out-menu-item.vue";
import PopOutMenuDivider from "./pop-out-menu-divider.vue";
import UserPhoto from "../userPhoto/user-photo.vue";

/**
 * TODO
 * Add loading state to menu whilst waiting for cards to load async
 * Handle errors from fetching the data
 * Handle having new data passed in (one markbook popout, load new data and reuse?)
 */

const isActive = ref(false);
const isRecentActivityEmpty = ref(false);
const disableExpansion = ref(true);
const isExpanded = ref(false);
const viewAllText = ref("There has been no activity yet, you may create feedback or exclude this student from this activity");
const cards = ref([]);

//  Call this afterwards as it relies on the existence of cards
// setViewAllText();

/**
 * Navigate to a new page
 *
 * @param {string} target  - The URL we want to navigate to.
 */
function onMenuItemSelected(target:string){
    window.location.href = target;
}

/**
 *  Fetches an array from the routes.dataEndpoint, which should return a
 *  an array of items corresponding to recent activity events. Also has
 *  logic to determine if we have enough activity items to warrant enabling
 *  the "view more activity" button.
 *
 *  TODO - Better handling of error state when fetch fails.
 */
async function fetchRecentActivity() {
    try {
        const response = await fetch(routes.value.dataEndpoint, {
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
            }
        });
        const activityItems = await response.json() as ActivityItem[];
        if (activityItems.length === 0){
            isRecentActivityEmpty.value = true;
        }
        else if (activityItems.length === 1){
            disableExpansion.value = true;
        }
        else {
            disableExpansion.value = false;
        }
        cards.value = generateCards(activityItems);
        setViewAllText();
        isActive.value = true;
    } catch (error) {
        console.warn(error);
    }
};

/**
 *  Used to determine the text shown on the "recent activity" menu
 *  item and provide more context to users. If our button is disabled,
 *  /why/ is it disabled? Helps users answer the question of why
 *  the activity they"re expecting to see might not be there.
 */
function setViewAllText(){
    if (cards.value.length === 0){
        viewAllText.value = "There has been no activity yet, you may create feedback or exclude this student from this activity";
    }
    else if (cards.value.length === 1){
        viewAllText.value = "No additional recent activity";
    }
    else if (isExpanded.value){
        viewAllText.value = "Hide recent activity";
    }
    else {
        viewAllText.value = "View more recent activity";
    }
}

function toggleExpanded(){
    isExpanded.value = !isExpanded.value;
    setViewAllText();
}

/**
 * Translate types into more human-readable "action" format.
 *
 * @param { object } item - the item to check the action on
 * @returns { string } - the string to return
 */
function getAction(item){
    let returnString = "";
    switch(item.type){
        case "feedback":
            returnString = "provided feedback";
            break;
        case "response":
            //  Check if an item was submitted late, adjust text accordingly
            returnString = `submitted ${item.submittedOnTime ? " on time" : "late"}`;
            break;
        case "view":
            returnString = "viewed the feedback"
            break;
        default:
            break;
    }
    return returnString;
}

type ActivityItemDates = {
    datetime: string;
    short: string;
    relative: string;
}

type ActivityItemEffector = {
    id: number;
    name: string;
}

type ActivityItem = {
    type: string;
    date: ActivityItemDates;
    effector: ActivityItemEffector;
}

/**
 *  Create our cards. We iterate through the cards we"ve received,
 *  reformatting some of the data into the expected format. This
 *  is to handle things like creating an image url and formatting
 *  the "type" action into something more human-readable, which
 *  isn"t provided to us in the raw response blob.
 *
 *  @param {object} activityItems - the response from fetchRecentActivity,
 *  aka an array of recent activity events.
 *  @returns {object} - A collection of objects containing recent
 *  activity events for our SmallCard components.
 */
function generateCards(activityItems: ActivityItem[]){
    return activityItems.map((activityItem: ActivityItem) => {
        return {
            // vueKey: `${activityItem.date.datetime.replaceAll(" ", "_")}_${activityItem.effector.name.replaceAll(" ","").toLowerCase()}`,
            type: activityItem.type,
            name: activityItem.effector.name,
            // action: getAction(item),
            shortTime: activityItem.date.short,
            relativeTime: activityItem.date.relative,
            img: {
                src: `/portrait.php?id=${activityItem.effector.id}&size=square32`,
                size: 32,
            }
        }
    });
}

type Routes = {
    dataEndpoint: string;
    updateRoute: string | undefined;
    reviewRoute: string | undefined;
    publishRoute: string | undefined;
    excludeRoute: string | undefined;
    historyRoute: string | undefined;
}

const routes: Ref<Routes | undefined> = ref();

document.addEventListener("pop-out-menu-markbook__selected", (event) => {
    const eventRoutes: Routes = event.detail.routes as Routes;
    routes.value = {
        dataEndpoint: eventRoutes.dataEndpoint,
        updateRoute: eventRoutes.updateRoute,
        reviewRoute: eventRoutes.reviewRoute,
        publishRoute: eventRoutes.publishRoute,
        excludeRoute: eventRoutes.excludeRoute,
        historyRoute: eventRoutes.historyRoute,
    }
    fetchRecentActivity();
});

</script>

<template>
    <pop-out
        v-if="isActive"
        class="c-pop-out-menu"
        :class="{ 'is-expanded': isExpanded }"
    >
        <div className="s-collapsible">
            <small-card v-for="item in cards">
                <user-photo :src="item.img.src" :size="item.img.size"></user-photo>
                <div className="c-card__details">
                    <div className="c-card__text">{{ item.name }} {{ item.action }}</div>
                    <div className="c-card__subtext">{{ item.shortTime }}</div>
                </div>
            </small-card>
        </div>
        <div className="c-pop-out-menu__options">
            <pop-out-menu-item
                :class="{ 'is-disabled': disableExpansion || isRecentActivityEmpty }"
                @click="toggleExpanded();"
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
                :class="{ 'is-disabled': !routes.reviewRoute || isRecentActivityEmpty }"
                @selected="onMenuItemSelected(routes.reviewRoute)"
            >
                Review submission
            </pop-out-menu-item>
            <pop-out-menu-item
                :class="{ 'is-disabled': !routes.publishRoute || isRecentActivityEmpty }"
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
                :class="{ 'is-disabled': !routes.historyRoute || isRecentActivityEmpty }"
                @selected="onMenuItemSelected(routes.historyRoute)"
            >
                Submission history
            </pop-out-menu-item>
        </div>
    </pop-out>
</template>

<style lang="scss">
.c-pop-out-menu{
    /* TODO - Remove temporary styling code once positioning handled by external library */
    left: 200px;
    position: absolute;
    top: 500px;
    white-space: normal;
    width: 16rem;
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

        .c-menu-item:first-child{
            display: block;
        }

    }
}

.s-collapsible {
    max-height: 20rem;
    overflow-y: auto;

    .c-card{
        display: none;

        &:first-child{
            display: flex;
        }
    }
}

</style>
