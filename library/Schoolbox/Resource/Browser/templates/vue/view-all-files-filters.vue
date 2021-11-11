<script>

/**
 * Contains the similarity report filters, score and status
 */

import { Event } from 'vue-tables-2';
import fileTypes from './viewAllFilesFileTypes';
import filterEvents from './viewAllFilesFilterEvents';

export default {

    props: {
        selectedFilters: { //the list of currently selected filters passed through
            type: Array,
            required: true,
        }
    },

    data () {
        return {
            isDocumentChecked: true,
            isAudioChecked: true,
            isVideoChecked: true,
            isImageChecked: true,
            isOtherChecked: true,
        }
    },

    created () {
        this.fileTypes = fileTypes; //to be able to reference the types within the <template>
    },

    mounted () {
        this.initFilterStatus();
        // this.initResetFiltersListener();
    },

    methods: {
        
        /**
         * To display which filters are currently selected on startup
         */
        initFilterStatus () {
            this.isDocumentChecked = this.selectedFilters.includes(fileTypes.DOCUMENT);
            this.isAudioChecked = this.selectedFilters.includes(fileTypes.AUDIO);
            this.isVideoChecked = this.selectedFilters.includes(fileTypes.VIDEO);
            this.isImageChecked = this.selectedFilters.includes(fileTypes.IMAGE);
            this.isOtherChecked = this.selectedFilters.includes(fileTypes.OTHER);

        },

        /**
         * Reset all the filters back to there base level
         */
        // initResetFiltersListener () {
        //     document.addEventListener(filterEvents.RESET_FILTERS, () => {
        //         this.similarityScoreSelection = 0;
        //         this.isResubmissionRequiredChecked = true;
        //         this.isMarkingRequiredChecked = true;
        //         this.isReviewedChecked = true;
        //         this.onSimilarityScoreChange();
        //         this.onResubmissionRequiredChange();
        //         this.onMarkingRequiredChange();
        //         this.onReviewedChange();
        //     });
        // },

        /**
         * Toggle the display of files of type document
         */
        onDocumentChange () {
            const filterParam = this.isDocumentChecked ? '' : fileTypes.DOCUMENT; 
            Event.$emit(`vue-tables.filter::${filterEvents.DOCUMENT_CHECKED}`, filterParam); //picked up by view-all-files.vue 
        },

        /**
         * Toggle the display of files of type audio
         */
        onAudioChange () {
            const filterParam = this.isAudioChecked ? '' : fileTypes.AUDIO; 
            Event.$emit(`vue-tables.filter::${filterEvents.AUDIO_CHECKED}`, filterParam); //picked up by view-all-files.vue 
        },

        /**
         * Toggle the display of files of type video
         */
        onVideoChange () {
            const filterParam = this.isVideoChecked ? '' : fileTypes.VIDEO; 
            Event.$emit(`vue-tables.filter::${filterEvents.VIDEO_CHECKED}`, filterParam); //picked up by view-all-files.vue 
        },

        /**
         * Toggle the display of files of type image
         */
        onImageChange () {
            const filterParam = this.isDocumentChecked ? '' : fileTypes.IMAGE; 
            Event.$emit(`vue-tables.filter::${filterEvents.IMAGE_CHECKED}`, filterParam); //picked up by view-all-files.vue 
        },

        /**
         * Toggle the display of files of type document
         */
        onOtherChange () {
            const filterParam = this.isOtherChecked ? '' : fileTypes.OTHER; 
            Event.$emit(`vue-tables.filter::${filterEvents.OTHER_CHECKED}`, filterParam); //picked up by view-all-files.vue 
        },

    }

}
</script>

<template>
    <div class="filters">
        <div class="checklist">
            <label class="title">Filter to:</label>
            <ul class="option-list">
                <li>
                    <input type="checkbox" id="document-toggle" v-model="isDocumentChecked" @change="onDocumentChange()">
                    <label for="document-toggle">Document</label>
                </li>
                <li>
                    <input type="checkbox" id="audio-toggle" v-model="isAudioChecked" @change="onAudioChange()">
                    <label for="audio-toggle">Audio</label>
                </li>
                <li>
                    <input type="checkbox" id="video-toggle" v-model="isVideoChecked" @change="onVideoChange()">
                    <label for="video-toggle">Video</label>
                </li>
                <li>
                    <input type="checkbox" id="image-toggle" v-model="isImageChecked" @change="onImageChange()">
                    <label for="image-toggle">Image</label>
                </li>
                <li>
                    <input type="checkbox" id="other-toggle" v-model="isOtherChecked" @change="onOtherChange()">
                    <label for="other-toggle">Other</label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.filters {
    background: white;
    display: flex;
    padding: 0 1rem;
    // @media (max-width: $tableToSingleColumn) {
    //     flex-direction: column;
    // }
}

.title,
.option-list,
.option-list li {
    display: inline-block; //rather than block, to line up with similarity score filter better
}

.title {
    margin-right: 1rem;    
}

.option-list li {
    margin-right: 2rem;
    // @media (max-width: $tableToSingleColumn) {
    //     display: block;
    // }
}

</style>