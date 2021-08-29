<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import BadgeImage from '../../vue/badge-image';
import AwardDetailModal from './award-detail-modal';
import mixinFilter from '../../vue/mixinFilter';

export default {

    components: {
        BadgeImage,
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
            selectedFilters: null,
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
         * get extra css class when award is a goal
         * 
         * @returns { String } 
         */
        getCss (award) {
            if (award.isGoal) {
                return 'goal';
            }
            return '';
        },

        /**
         * Update the list depending on the filter selection is dispatched from
         * library/Schoolbox/Learning/Badge/Award/templates/list.twig
         * 
         * @params { Event } contains the selected filters array
         */
        onFilterChange (event) {
            debugger;
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
                    <li v-for="student in data" :key="student.id" class="action-small-1 feed-item">
                        <div class="card wrap-down">
                            <a :href="`/search/user/${student.id}`">
                                <img :src="`/portrait.php?id=${student.id}&size=square64`" :alt="student.fullName">
                                {{ student.fullName }}
                            </a>
                        </div>
                        <div v-for="(award, index) in student.awards" :key="index" class="left badge-card" style="width:100px; height:120px; overflow:hidden; text-align:center">
                            <a href="#open-award-modal" @click.prevent="openDetailModal(award)" :title="award.badge.name">
                                <badge-image :badge="award.badge" :css="getCss(award)"></badge-image>
                                {{ award.badge.name }}
                            </a>
                        </div>
                    </li>
                </ul>
            </section>
            <award-detail-modal ref="awardDetailModal"></award-detail-modal>
        </div>
    </div>
</template>
