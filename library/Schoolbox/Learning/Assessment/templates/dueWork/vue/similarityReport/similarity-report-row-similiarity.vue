<script>

/**
 * The similarity of files, displays the list of files in a submission
 * and links to the report for each
 */

import FilterEvents from './similarityReportFilterEvents';

export default {

    props: {
        data: { //the row data
            type: Object,
            required: true
        },
    },

    data () {
        return {
            currentSimilartyScoreSelection: 0 //to be able to show only those files above the currently selected similarity score
        }
    },

  computed: {

        /**
         * Only show those files that are above the currently selected
         * similarity score
         *
         * @returns { Array }
         */
        matchedFiles () {
            return this.data.files.filter((file) => {
                return parseInt(file.turnitin.percentage.overall) >= parseInt(this.currentSimilartyScoreSelection);
            });
        }

    },

    mounted () {
        this.initSimilartyScoreChangedListener();
    },

    methods: {

        /**
         * To update the files shown
         */
        initSimilartyScoreChangedListener () {
            document.addEventListener(FilterEvents.SIMILARITY_SCORE, (event) => {
                this.currentSimilartyScoreSelection = parseInt(event.detail.score);
            });
        },

        /**
         * Add a colour to represent the similarity score
         * A low score starting with green to high score red is used
         *
         * @param { Object }
         *
         * @returns { String }
         */
        getFileColour (file) {
            const percentageRounded = Math.round(file.turnitin.percentage.overall);
            if (this.isBetween(percentageRounded, 0, 50)) {
                return '#4fa74f';
            } else if (this.isBetween(percentageRounded, 50, 59)) {
                return '#7a904d';
            } else if (this.isBetween(percentageRounded, 60, 69)) {
                return '#9f7c4b';
            } else if (this.isBetween(percentageRounded, 70, 79)) {
                return '#ba6d4a';
            } else if (this.isBetween(percentageRounded, 80, 89)) {
                return '#da5c49';
            } else if (this.isBetween(percentageRounded, 90, 100)) {
                return '#fa4a47';
            }
        },

        /**
         * Check if x is between a min and max
         *
         * @param { Number }
         * @param { Number }
         * @param { Number }
         *
         * @returns { Boolean }
         */
        isBetween (x, min, max) {
            return x >= min && x <= max;
        },

    },

}
</script>

<template>
    <div class="title-on-single-column" data-title="Similarity %">
        <ul class="list">
            <li v-for="file in matchedFiles" :key="file.id">
                <a :href="file._links.turnitin_report"  title="View similarity report">
                    <span class="percentage" :style="{ color: getFileColour(file) }">{{ Math.round(file.turnitin.percentage.overall) }}%</span>
                    {{ file.name + '.' + file.ext }}
                </a>
            </li>
        </ul>
    </div>
</template>

<style lang="scss" style="scoped">

@import './similarity-report';

.list {
    list-style-type: none;
    margin: 0;
    li {
        margin-bottom: 0.25rem;
    }
}

.percentage { //block percentages so files names line up
    display: inline-block;
    padding-right: 0.25rem;
    text-align: right;
    width: 40px;
}

</style>
