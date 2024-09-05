<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To use the devices native tools to capture a PHOTO,
 * to easier allow toggling between device cameras
 *
 */

import RecordedMedia from "@schoolboxVue/components/videojs-record/recorded-media";
import OverMediaModal from "@schoolboxVue/components/videojs-record/over-media-modal";
import recordMediaTypes from "@js/record-media/recordMediaTypes";
import recordMediaEvents from "@js/record-media/recordMediaEvents";
import { blobToDataURL } from "@js/util/fileUtils";

export default {
    components: {
        RecordedMedia,
        OverMediaModal,
    },

    props: {
        preRecordedData: {
            //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },

    data() {
        return {
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasRecordPlayerError: false, //error with the record player
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
            captureOptions: null, //to distinguish between different media types
        };
    },

    created() {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
        this.captureOptions = this.getCaptureOptions();
    },

    mounted() {
        this.initInputCaptureElement();
    },

    methods: {
        /**
         * Do we need to request the media device straight away?
         * If not wait until it's requested - by deleting the pre recorded media recording
         * Otherwise we will just be displaying the existing pre recorded media
         *
         */
        checkForPreRecordedData() {
            if (this.preRecordedData) {
                this.hasPreRecordedData = true;
            }
        },

        /**
         * get the correct options depending on the selected media type
         *
         * @returns { Object }
         */
        getCaptureOptions() {
            return {
                accept: "image/*",
                capture: "user",
                attachPrompt: "take a picture",
                iconClass: "icon-camera",
            };
        },

        /**
         * Listen for changes so can display the captured media
         *
         */
        initInputCaptureElement() {
            this.$refs.recorder.addEventListener("change", (event) => {
                this.onFileAdded(event.target.files[0]);
            });
        },

        /**
         * get the correct options depending on the selected media type
         *
         * @returns { Blob }
         */
        async onFileAdded(mediaData) {
            this.recordedData = mediaData; // the blob object contains the recorded data
            this.recordedData.src = await blobToDataURL(this.recordedData);
            this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
            this.$refs.recorder.value = ""; //prevent the input capture element showing the preview
        },

        /**
         * confirm deletion of the latest recording, setting up for another recording
         *
         */
        onRecordedMediaDeleted() {
            this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
            this.$refs.recorder.value = "";
            this.hasPreRecordedData = false; //set to false again for possible display or a re-recording
            this.hasNewRecording = false;
        },

        /**
         * Ensure the recorded video ratio is respected
         *
         * @params { Number }
         * @params { Number }
         */
        onResize(width, height) {
            this.$emit(recordMediaEvents.RESIZE, width, height);
        },
    },
};
</script>

<template>
    <div class="container">
        <div class="media-container media-container-record">
            <div class="file-input-container">
                <input
                    ref="recorder"
                    class="dzone"
                    type="file"
                    :accept="captureOptions.accept"
                    :capture="captureOptions.capture"
                />
                <span :class="'file-input-note ' + captureOptions.iconClass"
                    >Tap here to {{ captureOptions.attachPrompt }}</span
                >
            </div>
        </div>

        <recorded-media
            v-if="hasPreRecordedData || hasNewRecording"
            key="media-container-playback"
            class="media-container media-container-playback"
            :record-media-type="recordMediaTypes.PHOTO"
            :has-pre-recorded-data="hasPreRecordedData"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            :recorded-data="recordedData"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-resize="onResize"
        />
    </div>
</template>

<style lang="scss" scoped>
.container {
    position: absolute;
    width: 100%;
    height: 100%; //will be updated by the parent container, keeping it inline with the parent
    background: white;
    overflow: hidden;
}

.media-container {
    position: absolute;
    width: 100%;
    height: 100%;
    left: 0;
    top: 0;
}

.media-container-record {
    z-index: var(--sbx-depth-default);
}

.media-container-playback {
    z-index: calc(var(--sbx-depth-default) + 1);
}

.file-input-container {
    position: absolute;
    width: 100%;
    height: 100%;
    text-align: center;
}

:deep(.dzone) {
    //piggy back on the existing dropzone styles, and update as needed
    position: absolute;
    z-index: var(--sbx-depth-default);
    width: 100%;
    height: 100%;
    font-size: 0px;
    text-indent: -999px; //hide the text, just leaving the dzone like border/display
}

.file-input-note {
    display: block;
    position: relative;
    z-index: calc(var(--sbx-depth-default) + 1);
    padding: 20px;
    font-size: 0.8rem;
    pointer-events: none;
}
</style>
