<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Contains the file type filters, as they can be changed in view-all-files.vue,
 * they are maintained through the selectedFilters prop
 */

import fileTypes from "./viewAllFilesFileTypes.js";

export default {
    props: {
        selectedFilters: {
            //the list of currently selected filters passed through
            type: Array,
            required: true,
        },
    },

    computed: {
        /**
         * To reflect the selection status of filter type document
         */
        isDocumentChecked() {
            return this.selectedFilters.includes(fileTypes.DOCUMENT);
        },

        /**
         * To reflect the selection status of filter type audio
         */
        isAudioChecked() {
            return this.selectedFilters.includes(fileTypes.AUDIO);
        },

        /**
         * To reflect the selection status of filter type video
         */
        isVideoChecked() {
            return this.selectedFilters.includes(fileTypes.VIDEO);
        },

        /**
         * To reflect the selection status of filter type image
         */
        isImageChecked() {
            return this.selectedFilters.includes(fileTypes.IMAGE);
        },

        /**
         * To reflect the selection status of filter type other
         */
        isOtherChecked() {
            return this.selectedFilters.includes(fileTypes.OTHER);
        },
    },

    created() {
        this.fileTypes = fileTypes; //to be able to reference the types within the <template>
    },

    methods: {
        /**
         * Notify view-all-files.vue of the change so it can add/remove from selectedFilters
         */
        onFilterChange(fileType) {
            this.$emit("on-filter-change", fileType); //picked up by view-all-files.vue
        },
    },
};
</script>

<template>
    <div class="filters">
        <div class="checklist">
            <label class="title">Filter to:</label>

            <ul class="option-list">
                <li>
                    <input
                        type="checkbox"
                        id="document-toggle"
                        :checked="isDocumentChecked"
                        @change="onFilterChange(fileTypes.DOCUMENT)"
                    />
                    <label for="document-toggle">Document</label>
                </li>
                <li>
                    <input
                        type="checkbox"
                        id="audio-toggle"
                        :checked="isAudioChecked"
                        @change="onFilterChange(fileTypes.AUDIO)"
                    />
                    <label for="audio-toggle">Audio</label>
                </li>
                <li>
                    <input
                        type="checkbox"
                        id="video-toggle"
                        :checked="isVideoChecked"
                        @change="onFilterChange(fileTypes.VIDEO)"
                    />
                    <label for="video-toggle">Video</label>
                </li>
                <li>
                    <input
                        type="checkbox"
                        id="image-toggle"
                        :checked="isImageChecked"
                        @change="onFilterChange(fileTypes.IMAGE)"
                    />
                    <label for="image-toggle">Image</label>
                </li>
                <li>
                    <input
                        type="checkbox"
                        id="other-toggle"
                        :checked="isOtherChecked"
                        @change="onFilterChange(fileTypes.OTHER)"
                    />
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
    @media (max-width: 700px) {
        //shift things down now as horizontal space is getting tight
        display: block;
    }
}

.option-list li {
    margin-right: 2rem;
    @media (max-width: 700px) {
        //break the list down into 2 columns to display better on mobile
        display: inline-block;
        margin-right: 0;
        min-width: 110px; //takes us all the way down to 320px devices
    }
}
</style>
