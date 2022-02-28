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
         * Colours are determined by turnitin
         *
         * @param { Object }
         *
         * @returns { String }
         */
        getFileColour (file) {
            const percentageRounded = Math.round(file.turnitin.percentage.overall);
            if (percentageRounded >= 75) {
                return 'turnitin-red'; 
            } else if (percentageRounded >= 50) {
                return 'turnitin-orange'; 
            } else if (percentageRounded >= 25) {
                return 'turnitin-yellow'; 
            } else if (percentageRounded >= 1) {
                return 'turnitin-green'; 
            } else if (percentageRounded === 0) {
                return 'turnitin-blue'; 
            } 
        },

    },

}
</script>

<template>
    <div class="similarity title-on-single-column" data-title="Similarity %">
        <ul class="list">
            <li v-for="file in matchedFiles" :key="file.id">
                <a class="file" :href="file._links.turnitin_report"  title="View similarity check">
                    <span :class="`percentage ${getFileColour(file)}`">{{ Math.round(file.turnitin.percentage.overall) }}%</span>
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
