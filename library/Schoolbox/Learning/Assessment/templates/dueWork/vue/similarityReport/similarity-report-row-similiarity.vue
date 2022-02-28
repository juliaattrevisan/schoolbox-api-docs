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
            if (percentageRounded === 0) {
                return '#0c78cf'; //blue 
            } else if (this.isBetween(percentageRounded, 1, 24)) {
                return '#278829'; //green
            } else if (this.isBetween(percentageRounded, 25, 49)) {
                return '#eab42c'; //yellow
            } else if (this.isBetween(percentageRounded, 50, 74)) {
                return '#e27600'; //orange
            } else if (this.isBetween(percentageRounded, 75, 100)) {
                return '#ff0000'; //red
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
    <div class="similarity title-on-single-column" data-title="Similarity %">
        <ul class="list">
            <li v-for="file in matchedFiles" :key="file.id">
                <a class="file" :href="file._links.turnitin_report"  title="View similarity check">
                    <span class="percentage" :style="{ color: getFileColour(file) }">{{ Math.round(file.turnitin.percentage.overall) }}%</span>
                    <span>{{ file.name + '.' + file.ext }}</span>
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

.file {
    display: flex; //so when filenames are long, they don't sit under the percentage, rather +1 lines stay under the filename 'column' 
}

.percentage { //block percentages so files names line up
    flex: 0 0 40px;
    margin-right: 0.5rem;
    text-align: right;
    width: 40px;
}

</style>
