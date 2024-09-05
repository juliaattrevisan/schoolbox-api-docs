<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Contains the bulk modify fileters Campus, Role, ePortfolio, Year and Token
 */

import { EventBus } from "v-tables-3-select-rows";
import FilterEvents from "./userBulkModifyFilterEvents";

export default {
    props: {
        campuses: {
            type: Array,
            required: true,
        },
        roles: {
            type: Array,
            required: true,
        },
        yearLevels: {
            type: Array,
            required: true,
        },
        eportfolioEnabled: {
            type: Boolean,
            required: true,
        },
    },

    methods: {
        /**
         * Show any rows that contain the selected campus
         *
         * @param { Event }
         */
        onCampusChange(event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.CAMPUS}`, event.target.value); //picked up by user-bulk-modify.vue
        },

        /**
         * Show any rows that contain the selected role
         *
         * @param { Event }
         */
        onRoleChange(event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.ROLE}`, event.target.value); //picked up by user-bulk-modify.vue
        },

        /**
         * Show any rows that contain the selected year
         *
         * @param { Event }
         */
        onYearChange(event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.YEAR}`, event.target.value); //picked up by user-bulk-modify.vue
        },

        /**
         * Show any rows that contain the selected token
         *
         * @param { Event }
         */
        onTokenChange(event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.TOKEN}`, event.target.value); //picked up by user-bulk-modify.vue
        },

        /**
         * Show any rows that contain the selected ePortfolio
         *
         * @param { Event }
         */
        onEportfolioChange(event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.EPORTFOLIO}`, event.target.value); //picked up by user-bulk-modify.vue
        },
    },
};
</script>

<template>
    <div class="filters">
        <div class="filters__column">
            <label>Campuses</label>
            <select @change="onCampusChange($event)">
                <option value="">Any</option>
                <option v-for="campus in campuses" :value="campus.name" :key="campus.id">
                    {{ campus.name }}
                </option>
            </select>
        </div>
        <div class="filters__column">
            <label>Role</label>
            <select @change="onRoleChange($event)">
                <option value="">Any</option>
                <option v-for="role in roles" :value="role.name" :key="role.id">
                    {{ role.name }}
                </option>
            </select>
        </div>
        <div class="filters__column">
            <label>Year Level</label>
            <select @change="onYearChange($event)">
                <option value="">Any</option>
                <option value="null">Not set</option>
                <option v-for="yearLevel in yearLevels" :value="yearLevel.name" :key="yearLevel.id">
                    {{ yearLevel.name }}
                </option>
            </select>
        </div>
        <div class="filters__column">
            <label>Token</label>
            <select @change="onTokenChange($event)">
                <option value="">Any</option>
                <option value="1">Set</option>
                <option value="0">Not set</option>
            </select>
        </div>
        <div v-if="eportfolioEnabled" class="filters__column">
            <label>ePortfolio</label>
            <select @change="onEportfolioChange($event)">
                <option value="">Any</option>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
            </select>
        </div>
    </div>
</template>

<style lang="scss" scoped>
$toWrapFlex: 1000px;

.filters {
    background: white;
    display: flex;
    gap: 1rem;
    padding: 0 1rem 1rem 1rem;
    @media (max-width: $toWrapFlex) {
        flex-wrap: wrap; //start allowing list on multiple lines
    }
}

.filters__column {
    flex: 1 1 0; //keep both sides equal
    @media (max-width: $toWrapFlex) {
        flex: none; //back to default state
    }
}
</style>
