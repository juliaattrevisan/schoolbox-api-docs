<script>

import mixinFilter from '@schoolboxVue/mixins/filter';

export default {
    name: "pastoralClassReport",
    mixins: [
        mixinFilter
    ],
    props: {
        // The route via which the report data is retrieved
        getReportDataRoute: {
            type: String,
            default: '',
            required: true,
        },
    },
    data () {
        return {
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },
    created () {
        // assign endpoint for the mixin filter
        this.filter_endpoint = this.getReportDataRoute;
    },
    mounted () {
        document.addEventListener('pastoral-class-report__onFilterChange', this.onFilterChange);
    },
    methods: {
        /**
         * Callback for when the filters for this badge list are updated.
         *
         * Fired from outer space, currently from library/Schoolbox/Learning/Badge/templates/list.twig
         */
        onFilterChange (event) {
            this.changeFilters(event.detail.selectedFilters);
        },

        /**
         * Update the display with the selected filters
         *
         * @param {object} selectedFilters The filters that were selected
         */
        changeFilters (selectedFilters) {
            this.selectedFilters    = selectedFilters;
            this.showSkeletonLoader = true;
            this.filter_load(false);
            this.filter_emitChange();
        },
    }
}
</script>

<template>
    <div>
        <div v-if="showSkeletonLoader" class="row">
            <ul class="information-list">
                <li class="row" v-for="n in 3" :key="n">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </li>
            </ul>
        </div>
        <div v-else>
            <div v-if="data.length === 0" class="empty-state">
                <i class="icon-class-list"></i>
                <p>This class contains no students.</p>
            </div>
            <ul v-else class="information-list">
                <li class="row" v-for="studentData in data" :key="studentData.student.id">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
