<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import BadgeReportStudent from './badge-report-student.vue';
import AwardDetailModal from './award-detail-modal';
import mixinFilter from '../../vue/mixinFilter';

export default {

    components: {
        BadgeReportStudent,
        AwardDetailModal
    },

    mixins: [
        mixinFilter
    ],
    
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
            currentBadge: null,
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            selectedFilters: [],
        }
    },

    computed: {
        filterEndpoint() {
            return this.getStudentListsEndpoint;
        },
    },

    mounted () {
        document.addEventListener('badge-report__onFilterChange', this.onFilterChange);
    },

    methods: {

        /**
         * Open the selected award detail modal
         * 
         * @returns { Object } 
         */
        openDetailModal (award) {
            this.$refs.awardDetailModal.open(award);
        },

        /**
         * Update the list depending on the filter selection is dispatched from
         * library/Schoolbox/Learning/Badge/Award/templates/list.twig
         * 
         * @params { Event } contains the selected filters array
         */
        onFilterChange (event) {
            this.selectedFilters = event.detail.selectedFilters;
            this.showSkeletonLoader = true;
            this.filter_load(false);
            this.filter_emitChange();
        },

    }

}
</script>
<template>
    <div>
        <div class="small-12 island">
            <section class="awards">
                <ul class="action-list">
                    <badge-report-student 
                        v-for="student in data" 
                        :key="student.id" 
                        :student="student"
                    />
                </ul>
            </section>
            <award-detail-modal ref="awardDetailModal"></award-detail-modal>
        </div>
    </div>
</template>
