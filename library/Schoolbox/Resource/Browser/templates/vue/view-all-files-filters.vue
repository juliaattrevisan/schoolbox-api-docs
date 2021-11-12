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
        this.$nextTick(() => this.updateAllFilters()); //nextTick to allow the data to be populated prior to filtering
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
         * Trigger all filters based on the current selections
         */
        updateAllFilters () {
            this.onDocumentChange();
            this.onAudioChange();
            this.onVideoChange();
            this.onImageChange();
            this.onOtherChange();
        },

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
            const filterParam = this.isImageChecked ? '' : fileTypes.IMAGE; 
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
}

.title,
.option-list,
.option-list li {
    display: inline-block; //line up along the bottom of the search field input
}

.title {
    margin-bottom: 0.5rem;
    margin-right: 1rem;    
}

.title,
.option-list {
    @media (max-width: 700px) { //shift things down now as horizontal space is getting tight
        display: block;
    }
}

.option-list li {
    margin-right: 2rem;
    @media (max-width: 700px) { //break the list down into 2 columns to display better on mobile
        display: inline-block; 
        margin-right: 0;
        min-width: 110px; //takes us all the way down to 320px devices
    }
}

</style>