<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import { stringify } from 'qs';
import BadgeReportHeader from './badge-report-header';
import BadgeReportStudent from './badge-report-student';
import AwardDetailModal from './award-detail-modal';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer';


export default {

    components: {
        BadgeReportHeader,
        BadgeReportStudent,
        AwardDetailModal,
        BottomDrawer
    },
    
    props: {
        subjectTitle: {
            type: String,
            required: true
        },
        getStudentListsEndpoint: { //where to get the student data from
            type: String,
            required: true
        },
        returnUrlName: { //Along with `encodedReturnUrl` this is used when generating a link to learning moments and badges, so that the user may be redirected somewhere after those forms are submitted.
            type: String,
            required: true,
        },
        encodedReturnUrl: { // Along with `returnUrlName` this is used when generating a link to learning moments and badges, so that the user may be redirected somewhere after those forms are submitted.
            type: String,
            required: true,
        },
    },
    
    data () {
        return {
            studentsData: [], //hold all student data
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            selectedFilters: [], //to show only those badges curently filtered
            selectedStudents: [], //list of student ids that can have badges awarded to them
        }
    },

    computed: {

        /**
         * To determine whether to show the bottom-drawer to award badges to students
         * 
         * @returns { Boolean }
         */
        hasSelectedStudents () {
            return this.selectedStudents.length > 0;
        },

        /**
         * To display how many students are selected
         * 
         * @returns { Boolean }
         */
        selectedStudentsCount () {
            return this.selectedStudents.length;
        },

        /**
         * To show an 'indeterminate' state on the header checkbox, currently a dash in the box
         * 
         * @returns { Boolean }
         */
        hasSomeStudentsSelected () {
            return this.hasSelectedStudents && this.selectedStudentsCount < this.studentsData.length;
        },

        /**
         * Activate the select all when all are checked individually
         * 
         * @returns { Boolean }
         */
        areAllStudentsSelected () {
            return this.selectedStudentsCount === this.studentsData.length;
        }

    },

    mounted () {
        document.addEventListener('badge-report__onFilterChange', this.getStudentData); //the student data is ready
    },

    methods: {

        /**
         * Load the current student data
         */
        async getStudentData (event) {
            try {
                const queryString = stringify( { filters: event.detail.selectedFilters }, { arrayFormat: 'brackets' } ); //use stringify to help split open and convert array values to a query string
                const response = await fetch(this.getStudentListsEndpoint + '?' + queryString);
                const responseData = await response.json();
                this.studentsData = responseData.data;
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * To be able to award badges to multiple students
         * 
         * @params { Number } 
         * @params { Boolean } 
         */
        onSelectAllSelected (isChecked) {
            if (isChecked) {
                this.selectedStudents = this.studentsData.map((student) => student.id); //add in all student ids
            } else {
                this.selectedStudents = []; //clear all 
            }
        },

        /**
         * To be able to award badges to multiple students
         * 
         * @params { Number } 
         * @params { Boolean } 
         */
        onStudentSelected (studentID, isChecked) {
            if (isChecked) {
                this.selectedStudents = [...this.selectedStudents, studentID]; //add in
            } else {
                this.selectedStudents = this.selectedStudents.filter((selectedStudentsID) => selectedStudentsID !== studentID); //or remove
            }
        },

        /**
         * Open the selected award detail modal
         * 
         * @params { Object } 
         */
        onBadgeSelected (badge) {
            this.$refs.awardDetailModal.open(badge);
        },

        /**
         * To update a badge that was previously a goal that is now an achievement
         * This is so it goes full colour, and can't be awarded again in the award-detail-modal.vue
         * 
         * @params { Object } 
         */
        onAchievementAwarded (awardedBadge) {
            const student = this.studentsData.find((student) => student.id === awardedBadge.recipient.id);
            const badge = student.badges.find((badge) => badge.badge.id === awardedBadge.badge.id);
            badge.isGoal = false; //this will trigger the badge to update within badge-report-student.vue
        },

        /**
         * Clear the currently selected students
         */
        onClearStudentSelection () {
            this.selectedStudents = [];
        },

        /**
         * Navigate to the set goal / award achievement page with the users selected
         */
        getUrl (selection) {
            const url = new URL(window.location.href);
            url.pathname = `learning/badge/award/user/${selection}`;
            url.searchParams.append(this.returnUrlName, this.encodedReturnUrl);
            url.searchParams.append('userIds', this.selectedStudents.join(','));
            return url.toString();
        },

    }

}
</script>

<template>

    <div class="small-12 island badge-report">

        <section class="content">
            
            <div v-if="isDataLoaded">
                <badge-report-header 
                    :has-some-students-selected="hasSomeStudentsSelected"
                    :are-all-students-selected="areAllStudentsSelected"
                    @on-select-all-selected="onSelectAllSelected"
                />
                <ul class="action-list">
                    <badge-report-student 
                        v-for="student in studentsData" 
                        :key="student.id" 
                        :student="student"
                        :selected-students="selectedStudents"
                        @on-student-selected="onStudentSelected"
                        @on-badge-selected="onBadgeSelected"
                    />
                </ul>
                <award-detail-modal 
                    ref="awardDetailModal"
                    @on-achievement-awarded="onAchievementAwarded"
                />
            </div>

            <div v-else-if="hasDataLoadError" class="alert-box alert">
                Sorry, we are currently unable to load the student data.
            </div>

            <div v-else class="loader"></div>

        </section>

        <bottom-drawer :showDrawer="hasSelectedStudents">
            <h3 class="selected-count"><em>{{ selectedStudentsCount }} selected</em></h3>
            <button @click.prevent="onClearStudentSelection" @keyup.enter="onClearStudentSelection" class="button-clear">Clear selection</button>
            <div class="modal-actions">
                <a :href="getUrl('goal')" title="Set goal" class="button submit icon-goal">Set goal</a>
                <a :href="getUrl('achievement')" title="Award achievement" class="button submit icon-achievement">Award achievement</a>
            </div>
        </bottom-drawer>

    </div>

</template>

<style lang="scss" scoped>

.badge-report {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.content {
    padding: 1rem 0 0 0; //remove the padding for most
}

.action-list {
    padding: 0 1rem; //decrease the padding
}

.selected-count {
    display: inline-block;
    margin-right: 1rem;
}

.modal-actions {
	display: flex;
    margin-bottom: 1rem;
	a {
		margin: 0 2px;
	}
}

.button-clear {
    display: inline-block;
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
}

</style>