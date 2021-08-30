<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import axios from 'axios';
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
            studentData: [],
            isDataLoaded: false,
            hasDataLoadError: false,
            selectedFilters: [],
        }
    },

    mounted () {
        document.addEventListener('badge-report__onFilterChange', this.getStudentData);
    },

    methods: {

        /**
         * Load the list current student studentData
         */
        async getStudentData (event) {
            try {
                const config = {
                    params: {
                        filters: event.detail.selectedFilters
                    },
                    paramsSerializer: params => stringify(params, { arrayFormat: 'brackets' } ),
                }
                const response = await axios.get(this.getStudentListsEndpoint, config);
                this.studentData = response.data.data;
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
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

    <div>

        <div v-if="isDataLoaded" class="small-12 island">
            <section class="awards">
                <ul class="action-list">
                    <badge-report-student 
                        v-for="student in studentData" 
                        :key="student.id" 
                        :student="student"
                        @on-award-selected="onAwardSelected"
                    />
                </ul>
            </section>
            <award-detail-modal ref="awardDetailModal"></award-detail-modal>
        </div>

        <div v-else-if="hasDataLoadError">
            <h2>Unable to load the list data</h2>
        </div>

        <div v-else>
            <h2>Loading please wait</h2>
        </div>

    </div>

</template>
