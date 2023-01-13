<script setup lang="ts">

import { reactive } from 'vue';
import PopOut from '../popOut/pop-out.vue';
import SmallCard from '../card/small-card.vue';
import MenuItem from './pop-out-menu-item.vue';
import MenuDivider from './pop-out-menu-divider.vue';
import UserPhoto from '../userPhoto/user-photo.vue';

/**
 * TODO
 * Add loading state to menu whilst waiting for cards to load async
 * Handle errors from fetching the data
 * Handle having new data passed in (one markbook popout, load new data and reuse?)
 */

const props = defineProps({
dataEndpoint: {
    type: String,
    required: true,
},
updateRoute: {
    type: String,
    required: true,
},
reviewRoute: {
    type: String,
    required: true,
},
publishRoute: {
    type: String,
    required: true,
},
excludeRoute: {
    type: String,
    required: true,
},
historyRoute: {
    type: String,
    required: true,
}
});

const data = reactive({
    zeroState: false as boolean,
    disableExpansion: true as boolean,
    isExpanded: false as boolean,
    viewAllText: 'There has been no activity yet, you may create feedback or exclude this student from this activity',
    cards: [],
});

//  Call this afterwards as it relies on the existence of data.cards
setViewAllText();

/**
 * The MenuItem is expecting a function to be passed to it to
 * be called when clicked on - as we have five menu items that
 * are just links, we can easily generate 'navigate to page'
 * functions for these items by calling getClickFunction.
 *
 * MenuItems are written this way for reusability - maybe in
 * future we want a MenuItem that does something other than just
 * redirect a user. We're able to do this now without modifying
 * the MenuItem Vue class.
 *
 * @param {string} target  - The URL we want to navigate to.
 * @returns {Function} - A function redirecting the window to another page.
 */
function getClickFunction(target:string){
    return () => {
        window.location.href = target;
    };
}

/**
 *  Fetches an array from the routes.dataEndpoint, which should return a
 *  an array of items corresponding to recent activity events. Also has
 *  logic to determine if we have enough activity items to warrant enabling
 *  the 'view more activity' button.
 *
 *  TODO - Better handling of error state when fetch fails.
 */
function fetchRecentActivity() {
    fetch(routes.dataEndpoint, {
        headers: {
            'Accept': 'application/json',
        }
    })
    .then (response => response.json())
        .then (payload => {
            if(payload.length === 0){
                data.zeroState = true;
            }
            else if (payload.length === 1){
                data.disableExpansion = true;
            }
            else {
                data.disableExpansion = false;
            }
            data.cards = generateCards(payload);
            setViewAllText();
        })
        .catch(e => {});
};

/**
 *  Used to determine the text shown on the 'recent activity' menu
 *  item and provide more context to users. If our button is disabled,
 *  /why/ is it disabled? Helps users answer the question of why
 *  the activity they're expecting to see might not be there.
 */
function setViewAllText(){
    if (data.cards.length === 0){
        data.viewAllText = 'There has been no activity yet, you may create feedback or exclude this student from this activity';
    }
    else if (data.cards.length === 1){
        data.viewAllText = 'No additional recent activity';
    }
    else if (data.isExpanded){
        data.viewAllText = 'Hide recent activity';
    }
    else {
        data.viewAllText = 'View more recent activity';
    }
}

function toggleExpanded(){
    data.isExpanded = !data.isExpanded;
    setViewAllText();
}

/**
 *  Translate types into more human-readable 'action' format.
 */
function getAction(item){
    let returnString = '';

    switch(item.type){
        case 'feedback':
            returnString = 'provided feedback';
            break;
        case 'response':
            //  Check if an item was submitted late, adjust text accordingly
            returnString = `submitted ${item.submittedOnTime ? ' on time' : 'late'}`;
            break;
        case 'view':
            returnString = 'viewed the feedback'
            break;
        default:
            break;
    }

    return returnString;
}

const routes = {
    dataEndpoint: props.dataEndpoint,
    updateRoute: props.updateRoute,
    reviewRoute: props.reviewRoute,
    publishRoute: props.publishRoute,
    excludeRoute: props.excludeRoute,
    historyRoute: props.historyRoute
}

fetchRecentActivity();

/**
 *  Create our cards. We iterate through the cards we've received,
 *  reformatting some of the data into the expected format. This
 *  is to handle things like creating an image url and formatting
 *  the 'type' action into something more human-readable, which
 *  isn't provided to us in the raw response blob.
 *
 *  @param {object} payload - the response from fetchRecentActivity,
 *  aka an array of recent activity events.
 *  @returns {object} - A collection of objects containing recent
 *  activity events for our SmallCard components.
 */
function generateCards(payload){
    return payload.map(item => {
        return {
            vueKey: `${item.date.datetime.replaceAll(' ', '_')}_${item.effector.name.replaceAll(' ','').toLowerCase()}`,
            type: item.type,
            name: item.effector.name,
            action: getAction(item),
            shortTime: item.date.short,
            relativeTime: item.date.relative,
            img: {
                src: `/portrait.php?id=${item.effector.id}&size=square32`,
                size: 32,
            }
        }
    });
}

</script>

<template>
    <PopOut
        class="c-pop-out-menu"
        :class="{ 'is-expanded': data.isExpanded }"
    >
        <div className="s-collapsible">
            <SmallCard v-for="item in data.cards">
                <UserPhoto v-bind="item.img"></UserPhoto>
                <div className="c-card__details">
                    <div className="c-card__text">{{ item.name }} {{ item.action }}</div>
                    <div className="c-card__subtext">{{ item.shortTime }}</div>
                </div>
            </SmallCard>
        </div>
        <div className="c-pop-out-menu__options">
            <MenuItem
                @click="toggleExpanded();"
                :class="{ 'is-disabled': data.disableExpansion || data.zeroState }"
            >
                {{ data.viewAllText }}
            </MenuItem>
            <MenuDivider />
            <MenuItem
                :class="{ 'is-disabled': !routes.updateRoute }"
                :on-click-action="getClickFunction(routes.updateRoute)"
            >
                Update feedback
            </MenuItem>
            <MenuItem
                :class="{ 'is-disabled': !routes.reviewRoute || data.zeroState }"
                :on-click-action="getClickFunction(routes.reviewRoute)"
            >
                Review submission
            </MenuItem>
            <MenuItem
                :class="{ 'is-disabled': !routes.publishRoute || data.zeroState }"
                :on-click-action="getClickFunction(routes.publishRoute)"
            >
                Publish feedback
            </MenuItem>
            <MenuItem
                :class="{ 'is-disabled': !routes.excludeRoute }"
                :on-click-action="getClickFunction(routes.excludeRoute)"
            >
                Exclude student
            </MenuItem>
            <MenuItem
                :class="{ 'is-disabled': !routes.historyRoute || data.zeroState }"
                :on-click-action="getClickFunction(routes.historyRoute)"
            >
                Submission history
            </MenuItem>
        </div>
    </PopOut>
</template>

<style lang="scss">
.c-pop-out-menu{
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
