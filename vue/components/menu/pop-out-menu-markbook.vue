<script setup lang="ts">

import { onMounted, reactive } from 'vue';
import PopOut from '../popOut/pop-out.vue';
import SmallCard from '../card/small-card.vue';
import MenuItem from './pop-out-menu-item.vue';
import MenuDivider from './pop-out-menu-divider.vue';
import UserPhoto from '../userPhoto/user-photo.vue';
import { createReadStream } from 'fs';


/**
 * TODO
 * - Make call for data
 * - Don't generate cards from props, generate from
 *   elsewhere
 * - Have menu items become dynamic (v-for)
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
    disableExpansion: false as boolean,
    isExpanded: false as boolean,
    viewAllText: setViewAllText({isExpanded: false}),
    cards: [],
});

/**
 * The MenuItem is expecting a function to be passed to it to
 * be called when clicked on - as we have five menu items that
 * are just links, we can easily generate 'navigate to page'
 * functions for these items by calling this.
 *
 * @param {string} target  - The URL we want to navigate to.
 * @returns {Function} - A function redirecting the window to another page.
 */
function getClickFunction(target:string){
    return () => {
        window.location.href = target;
    };
}

function fetchRecentActivity() {
    fetch(routes.dataEndpoint, {
        headers: {
            'Accept': 'application/json',
        }
    })
    .then (response => response.json())
        .then (payload => {
            data.cards = generateCards(payload);
            if (payload.length <= 1){
                data.disableExpansion = true;
            }
            else {
                data.disableExpansion = false;
            }
            setViewAllText({
                    isExpanded: data.disableExpansion,
                    cardCount: data.cards.length
                }
            );
        })
        .catch(e => {});
};

/**
 * TODO use is disabled to figure out the text to display
 */
function setViewAllText({ isExpanded, cardCount }){
    if (typeof cardCount !== 'undefined' && cardCount < 2){
        if (cardCount === 0)
            return 'No recent activity found';
        if(cardCount === 1)
            return 'No additional recent activity';
    }

    if (isExpanded) {
        return 'Hide recent activity'
    }
    return 'View more recent activity'
}

function toggleExpanded(){
    data.isExpanded = !data.isExpanded;
    data.viewAllText = setViewAllText({ isExpanded: data.isExpanded });
}

/**
 *  Translate types into more human-readable 'action' format.
 */
function getAction(type:string){
    const translations = {
        feedback: 'provided feedback',
        response: 'submitted on time',
        view: 'viewed the feedback'
    };

    //Return empty string on not found
    return translations[type] || '';
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
 *  the 'type' action into something more human-readable.
 */
function generateCards(payload){
    return payload.map(item => {
        return {
            vueKey: `${item.date.datetime.replaceAll(' ', '_')}_${item.effector.name.replaceAll(' ').toLowerCase()}`,
            type: item.type,
            name: item.effector.name,
            action: getAction(item.type),
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
                :class="{ 'is-disabled': data.disableExpansion }"
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
                :class="{ 'is-disabled': !routes.reviewRoute }"
                :on-click-action="getClickFunction(routes.reviewRoute)"
            >
                Review submission
            </MenuItem>
            <MenuItem
                :class="{ 'is-disabled': !routes.publishRoute }"
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
                :class="{ 'is-disabled': !routes.historyRoute }"
                :on-click-action="getClickFunction(routes.historyRoute)"
            >
                Submission history
            </MenuItem>
        </div>
    </PopOut>
</template>

<style lang="scss">
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
