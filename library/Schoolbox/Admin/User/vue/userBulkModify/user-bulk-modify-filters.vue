<script>

/**
 * Contains the bulk modify fileters Campus, Role, ePortfolio, Year and Token
 */

import { EventBus } from 'v-tables-3-select-rows';
import FilterEvents from './userBulkModifyFilterEvents';

export default {

    props: {
        selectedFilters: {
            type: Object, 
            required: true, 
        },
        campuses: {
            type: Array,
            required: true
        },
        roles: {
            type: Array,
            required: true
        },
        yearLevels: {
            type: Array,
            required: true
        },
        eportfolioEnabled: {
            type: Boolean,
            required: true,
        },
    },

    // data () {
    //     return {
    //         similarityScoreSelection: 0,
    //         isResubmissionRequiredChecked: true,
    //         isMarkingRequiredChecked: true,
    //         isReviewedChecked: true,
    //     }
    // },

    mounted () {
        // this.initResetFiltersListener();
    },

    methods: {
        
        /**
         * Reset all the filters back to there base level
         */
        initResetFiltersListener () {
            // document.addEventListener(FilterEvents.RESET_FILTERS, () => {
            //     this.similarityScoreSelection = 0;
            //     this.isResubmissionRequiredChecked = true;
            //     this.isMarkingRequiredChecked = true;
            //     this.isReviewedChecked = true;
            //     this.onSimilarityScoreChange();
            //     this.onResubmissionRequiredChange();
            //     this.onMarkingRequiredChange();
            //     this.onReviewedChange();
            // });
        },

        /**
         * Show any rows that contain the selected campus
         */
        onCampusChange () {
            let campus = '';
            if (this.selectedFilters.campusId !== '-1') {
                const selectedCampus = this.campuses.find((campus) => campus.id === this.selectedFilters.campusId);
                campus = selectedCampus.name;
            }
            EventBus.emit(`vue-tables.filter::${FilterEvents.CAMPUS}`, campus); //picked up by user-bulk-modify.vue 
        },

        /**
         * Show any rows that contain the selected role
         */
        onRoleChange () {
            let role = '';
            if (this.selectedFilters.roleId !== '-1') {
                const selectedRole = this.roles.find((role) => role.id === this.selectedFilters.roleId);
                role = selectedRole.name;
            }
            EventBus.emit(`vue-tables.filter::${FilterEvents.ROLE}`, role); //picked up by user-bulk-modify.vue 
        },

        /**
         * Show any rows that contain the selected ePortfolio
         */
        onEportfolioChange () {
            // const filterParam = this.isMarkingRequiredChecked ? '' : 'pending'; 
            // EventBus.emit(`vue-tables.filter::${FilterEvents.MARKING_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
            // this.$nextTick(() => document.dispatchEvent(new CustomEvent(FilterEvents.MARKING_REQUIRED))); //picked up by similarity-report.vue 
        },

        /**
         * Show any rows that contain the selected year
         */
        onYearChange () {
            let year; 
            if (this.selectedFilters.yearLevelId === '-1') {
                year = '';
            } else if (this.selectedFilters.yearLevelId === '') {
                year = 'Not set';
            } else {
                const selectedYear = this.yearLevels.find((yearLevel) => yearLevel.id === this.selectedFilters.yearLevelId);
                year = selectedYear.name;
            }
            EventBus.emit(`vue-tables.filter::${FilterEvents.YEAR}`, year); //picked up by user-bulk-modify.vue 
            // EventBus.emit(`vue-tables.filter::${FilterEvents.YEAR}`, event.currentTarget[event.currentTarget.selectedIndex].innerText); //picked up by user-bulk-modify.vue 
        },

        /**
         * Show any rows that contain the selected token
         */
        onTokenChange () {
            // const filterParam = this.isReviewedChecked ? '' : 'reviewed'; 
            // EventBus.emit(`vue-tables.filter::${FilterEvents.REVIEWED}`, filterParam); //picked up by similarity-report.vue 
            // this.$nextTick(() => document.dispatchEvent(new CustomEvent(FilterEvents.REVIEWED))); //picked up by similarity-report.vue 
        },

    }

}
</script>

<template>
    <fieldset class="content">
        <div class="small-12 columns">
            <p>
                Search for users with the criteria listed below, and apply changes to selected users.
            </p>
        </div>
        <div class="small-12 medium-6 large-3 columns">
            <label>
                Campus
                <select @change="onCampusChange" v-model="selectedFilters.campusId">
                    <option value="-1">Any</option>
                    <option v-for="campus in campuses" :value="campus.id" :key="campus.id">
                        {{ campus.name }}
                    </option>
                </select>
            </label>
        </div>
        <div class="small-12 medium-6 large-3 columns">
            <label>
                Role
                <select @change="onRoleChange" v-model="selectedFilters.roleId">
                    <option value="-1">Any</option>
                    <option v-for="role in roles" :value="role.id" :key="role.id">
                        {{ role.name }}
                    </option>
                </select>
            </label>
        </div>
        <div v-if="eportfolioEnabled" class="small-12 medium-6 large-2 columns">
            <label>
                ePortfolio
                <select @change="onEportfolioChange" v-model="selectedFilters.ePortfolio">
                    <option value="-1">Any</option>
                    <option value="1">Enabled</option>
                    <option value="0">Disabled</option>
                </select>
            </label>
        </div>
        <div class="small-12 medium-6 large-2 columns">
            <label>
                Year
                <select @change="onYearChange" v-model="selectedFilters.yearLevelId">
                    <option value="-1">Any</option>
                    <option value="">Not set</option>
                    <option v-for="yearLevel in yearLevels" :value="yearLevel.id" :key="yearLevel.id">
                        {{ yearLevel.name }}
                    </option>
                </select>
            </label>
        </div>
        <div class="small-12 medium-6 large-2 columns">
            <label>
                Token
                <select @change="onTokenChange" v-model="selectedFilters.token">
                    <option value="-1">Any</option>
                    <option value="1">Set</option>
                    <option value="0">Not set</option>
                </select>
            </label>
        </div>
    </fieldset>
    <!-- <div class="filters">
        <div class="filters__column">
            <label>Similarity score</label>
            <select v-model="similarityScoreSelection" @change="onSimilarityScoreChange()">
                <option value="0">-</option>
                <option value="75">Above 75%</option>
                <option value="50">Above 50%</option>
                <option value="25">Above 25%</option>
                <option value="1">Above 1%</option>
            </select>
        </div>
        <div class="filters__column">
            <div class="checklist">
                <label>Status</label>
                <ul class="option-list">
                    <li>
                        <input type="checkbox" id="resubmission-required" v-model="isResubmissionRequiredChecked" @change="onResubmissionRequiredChange()">
                        <label for="resubmission-required">Resubmission required</label>
                    </li>
                    <li>
                        <input type="checkbox" id="marking-required" v-model="isMarkingRequiredChecked" @change="onMarkingRequiredChange()">
                        <label for="marking-required">Marking required</label>
                    </li>
                    <li>
                        <input type="checkbox" id="reviewed" v-model="isReviewedChecked" @change="onReviewedChange()">
                        <label for="reviewed">Reviewed</label>
                    </li>
                </ul>
            </div>
        </div>
    </div> -->
</template>

<style lang="scss" scoped>

// @import './similarity-report';

// .filters {
//     background: white;
//     display: flex;
//     padding: 1rem 0;
//     @media (max-width: $tableToSingleColumn) {
//         flex-direction: column;
//     }
// }

//     .filters__column { 
//         flex: 1 1 0; //keep both sides equal
//         margin: 0 1rem;
//     }

// legend { 
//     margin-bottom: 0.5rem; //extra gap between it and the form element below
// }

// .option-list li {
//     display: inline-block; //rather than block, to line up with similarity score filter better
//     margin-right: 2rem;
//     @media (max-width: $tableToSingleColumn) {
//         display: block;
//     }
// }

</style>