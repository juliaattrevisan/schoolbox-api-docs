<script>

/**
 * Contains the shaps modify fileters Campus, Role, ePortfolio, Year and Token
 */

import { EventBus } from 'v-tables-3-select-rows';
import FilterEvents from './shapsListFilterEvents';

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
        courses: {
            type: Array,
            required: true,
        },
        provisioned: {
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
        onCampusChange (event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.CAMPUS}`, event.target.value); //picked up by shaps-list.vue
        },

        /**
         * Show any rows that contain the selected role
         *
         * @param { Event }
         */
        onRoleChange (event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.ROLE}`, event.target.value); //picked up by shaps-list.vue
        },

        /**
         * Show any rows that contain the selected course
         *
         * @param { Event }
         */
        onCourseChange (event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.COURSE}`, event.target.value); //picked up by shaps-list.vue
        },

        /**
         * Show any rows that contain the selected provision
         *
         * @param { Event }
         */
        onProvisionedChange (event) {
            EventBus.emit(`vue-tables.filter::${FilterEvents.PROVISIONED}`, event.target.value); //picked up by shaps-list.vue
        },
    }

}
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
            <label>Courses</label>
            <select @change="onCourseChange($event)">
                <option value="">Any</option>
                <option value="null">Not set</option>
                <option v-for="course in courses" :value="course.name" :key="course.id">
                    {{ course.name }}
                </option>
            </select>
        </div>
        <div class="filters__column">
            <label>Provisioned</label>
            <select @change="onProvisionedChange($event)">
                <option value="">Any</option>
                <option value="1">Provisioned</option>
                <option value="0">Not provisioned</option>
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
