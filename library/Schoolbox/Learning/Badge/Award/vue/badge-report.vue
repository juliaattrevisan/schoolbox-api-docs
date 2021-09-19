<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import { stringify } from 'qs';
import BadgeReportStudent from './badge-report-student';
import AwardDetailModal from './award-detail-modal';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer'

export default {

    components: {
        BadgeReportStudent,
        AwardDetailModal,
        BottomDrawer
    },
    
    props: {
        subjectTitle: {
            type: String,
            required: true
        },
        getStudentListsEndpoint: {
            type: String,
            required: true
        },
    },
    
    data () {
        return {
            studentData: [], //hold all student data
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            selectedFilters: [], //to show only those badges curently filtered
            selectedStudents: [] //list of student ids that can have badges awarded to them
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
         * To determine whether to show the bottom-drawer to award badges to students
         * 
         * @returns { Boolean }
         */
        selectedStudentsCount () {
            return this.selectedStudents.length;
        }

    },

    mounted () {
        document.addEventListener('badge-report__onFilterChange', this.getStudentData);
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
                this.studentData = responseData.data;
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
         * @returns { Number } 
         * @returns { Boolean } 
         */
        onStudentSelected (studentID, isChecked) {
            if (isChecked) {
                this.selectedStudents = [...this.selectedStudents, studentID]; //add in
            } else {
                this.selectedStudents = this.selectedStudents.filter((selectedStudentsID) => selectedStudentsID !== studentID); //or remove
            }
        },

        /**
         * Clear the currently selected students
         */
        onClearStudentSelection () {
            this.selectedStudents = [];
        },

        /**
         * Open the selected award detail modal
         * 
         * @returns { Object } 
         */
        onAwardSelected (award) {
            this.$refs.awardDetailModal.open(award);
        },

        /**
         * Navigate to the set goal page
         */
        onSetGoalSelected () {
            
        },

        /**
         * Navigate to the award achievement page
         */
        onAwardAchievementSelected () {
            
        },

        /**
         * Open the selected award detail modal
         * 
         * @returns { Object } 
         */
        onAwardSelected (award) {
            this.$refs.awardDetailModal.open(award);
        },

    }

}
</script>

<template>

    <div class="small-12 island">

        <section class="content">
            
            <div v-if="isDataLoaded">
                <ul class="action-list">
                    <li class="awards student">
                        <div class="small-12 medium-1 column">
                            <div class="checklist">
                                <input 
                                    id="select-all"
                                    type="checkbox"
                                />
                                <label for="select-all"></label>
                            </div>
                        </div>
                        <div class="small-12 medium-1 column">
                            <h3>Name</h3>
                        </div>
                        <div class="small-12 medium-10 column">
                            <h3>Badges</h3>
                        </div>
                    </li>
                    <badge-report-student 
                        v-for="student in studentData" 
                        :key="student.id" 
                        :student="student"
                        :selected-students="selectedStudents"
                        @on-student-selected="onStudentSelected"
                        @on-award-selected="onAwardSelected"
                    />
                </ul>
                <award-detail-modal ref="awardDetailModal"></award-detail-modal>
            </div>

            <div v-else-if="hasDataLoadError" class="alert-box alert">
                Sorry, we are currently unable to load the student data.
            </div>

            <div v-else class="loader"></div>

            <bottom-drawer :showDrawer="hasSelectedStudents">
                <h3 class="selected-count"><em>{{ selectedStudentsCount }} selected</em></h3>
                <button @click.prevent="onClearStudentSelection" @keyup.enter="onClearStudentSelection" class="button-clear">Clear selection</button>
                <div class="modal-actions">
                    <button @click.prevent="onSetGoalSelected" @keyup="onSetGoalSelected" class="button submit button-delete">Set goal</button>
                    <button @click.prevent="onAwardAchievementSelected" @keyup.enter="onAwardAchievementSelected" class="button submit button-delete">Award achievement</button>
                </div>
            </bottom-drawer>

        </section>

    </div>

</template>

<style lang="scss">

// .VueTables--client {
//     padding-bottom: 100px; // allow space for bottom-drawer at the bottom
// }

.selected-count {
    display: inline-block;
    margin-right: 1rem;
}

.modal-actions {
	display: flex;
    margin-bottom: 1rem;
	button {
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