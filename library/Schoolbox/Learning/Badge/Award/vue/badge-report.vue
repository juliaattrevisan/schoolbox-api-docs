<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import { stringify } from 'qs';
import BadgeReportStudent from './badge-report-student.vue';
import AwardDetailModal from './award-detail-modal';

export default {

    components: {
        BadgeReportStudent,
        AwardDetailModal
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
                    <badge-report-student 
                        v-for="student in studentData" 
                        :key="student.id" 
                        :student="student"
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

        </section>

    </div>

</template>
