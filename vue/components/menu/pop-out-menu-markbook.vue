<script setup lang="ts">

import { reactive } from 'vue';

import PopOut from '../popOut/pop-out.vue';
import SmallCard from '../card/small-card.vue';
import MenuItem from './pop-out-menu-item.vue';
import MenuDivider from './pop-out-menu-divider.vue';
import UserPhoto from '../userPhoto/user-photo.vue';

/**
 * TODO
 * - Don't generate cards from props, generate from
 *   elsewhere
 * - Have menu items become dynamic (v-for) ?
 * - Disable menu options if no feedback etc
 */
const data = reactive({
    disableExpansion: false as Boolean,
    isExpanded: false as Boolean,
    viewAllText: getViewAllText(false)
});

/**
 *  Yes, you're wrapping basically a ternary, but that
 *  means if you want to change the wording you don't
 *  have to do it in two places at once now.
 */
function getViewAllText(isExpanded:Boolean){
    if(props.historyItems.length <= 0){
        return 'No recent activity';
    }
    else if(isExpanded) {
        return 'Hide recent activity'
    }
    else {
        return 'View more recent activity'
    }
}

function toggleExpanded(){
    data.isExpanded = !data.isExpanded;
    data.viewAllText = getViewAllText(data.isExpanded);
}

/**
 *  Translate types into more human-readable 'action' format.
 */
function getAction(type:String){
    const translations = {
        feedback: 'submitted on time',
        response: 'provided feedback',
        view: 'viewed the feedback'
    };

    //Return empty string on not found
    return translations[type] || '';
}

const props = defineProps({
    historyItems: {
        type: Array,
        required: true,
        default: [],
    },
});

/**
 *  Create our cards. We iterate through the cards we've received,
 *  reformatting some of the data into the expected format. This
 *  is to handle things like creating an image url and formatting
 *  the 'type' action into something more human-readable.
 */
const cards = props.historyItems.map(item => {
    return {
        type: item.type,
        name: item.effector.name,
        action: getAction(item.type),
        shortTime: item.date.short,
        relativeTime: item.date.relative,
        img: {
            src: `/profile.php?id=${item.effector.id}&size=square32`,
            size: 32,
        }
    }
});

/*  If we have no recent activity to view or less than one item to expand,
then we should disable the view more activity option */
if(cards.length <= 1){
    data.disableExpansion = true;
}

</script>

<template>
    <PopOut class="c-pop-out-menu" :class="{ 'is-expanded': data.isExpanded }">
        <div class="s-collapsible">
            <SmallCard v-for="item in cards">
                <UserPhoto v-bind="item.img"></UserPhoto>
                <div class="c-card__details">
                    <div class="c-card__text">{{ item.name }} {{ item.action }}</div>
                    <div class="c-card__subtext">{{ item.shortTime }}</div>
                </div>
            </SmallCard>
        </div>
        <div class="c-pop-out-menu__options">
            <MenuItem
                @click="toggleExpanded();"
                :class="{ 'is-disabled': data.disableExpansion }"
            >
                {{ data.viewAllText }}
            </MenuItem>
            <MenuDivider />
            <MenuItem>Update feedback</MenuItem>
            <MenuItem>Review submission</MenuItem>
            <MenuItem>Publish feedback</MenuItem>
            <MenuItem>Exclude student</MenuItem>
            <MenuItem>Submission history</MenuItem>
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
