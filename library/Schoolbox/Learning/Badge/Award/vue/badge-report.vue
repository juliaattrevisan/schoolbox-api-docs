<script>

import BadgeImage from '../../vue/badge-image';
import AwardDetailModal from './award-detail-modal';
import filterMixin from '../../vue/filter-mixin';

export default {

    components: {
        BadgeImage,
        AwardDetailModal
    },

    mixins: [
        filterMixin
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
         * Update the list depending on the filter selection
         * 
         * @params { Array } 
         */
        onFilterChange (selectedFilters) {
            this.selectedFilters = selectedFilters;
            this.showSkeletonLoader = true;
            this.load(false);
            this.emitChange();
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
