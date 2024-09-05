<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To record, view and upload a photo into SB
 *
 */

import "video.js/dist/video-js.css";
import "videojs-record/dist/css/videojs.record.css";
import videojs from "video.js";
import "webrtc-adapter";
import RecordRTC from "recordrtc";
import Record from "videojs-record/dist/videojs.record.js";
import RecordedMedia from "@schoolboxVue/components/videojs-record/recorded-media";
import OverMediaModal from "@schoolboxVue/components/videojs-record/over-media-modal";
import { getRecordPhotoOptions } from "@js/videojs/videojsOptions";
import recordMediaTypes from "@js/record-media/recordMediaTypes";
import recordMediaEvents from "@js/record-media/recordMediaEvents";
import { isDeviceAlreadyInUseError, isPermissionDeniedError } from "@js/record-media/recordMediaErrors";
import { dataURLtoBlob } from "@js/util/fileUtils";

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
            player: null, //videojs-record instance,
            recordedData: null, //the recorded data that will be passed through to #recored-media
            isDeviceReady: false, //to toggle the device display
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasNotSupportedError: false, //unable to fire up the media recorder, likely because a required media API is not available
            hasDeviceAlreadyInUseError: false, //can't access the requested device
            hasPermissionDeniedError: false, //didn't allow permission
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
        };
    },

    created() {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
    },

    mounted() {
        if (!this.hasPreRecordedData) {
            //only if no pre recording available
            this.initVideojsRecord();
        }
    },

    beforeUnmount() {
        this.destroyVideojs();
    },

    methods: {
        /**
         * Do we need to request the media device straight away?
         * If not wait until it's requested - by deleting the pre recorded media recording
         * Otherwise we will just be displaying the existing pre recorded media
         */
        checkForPreRecordedData() {
            if (this.preRecordedData) {
                this.hasPreRecordedData = true;
            }
        },

        /**
         * Start up the videojs-record instance, which is just videojs with record functionality wrapped around it
         */
        initVideojsRecord() {
            try {
                this.player = videojs(this.$refs.recordingVideo, getRecordPhotoOptions(), this.onVideojsReady);
            } catch (error) {
                this.hasNotSupportedError = true;
                this.hasError = true;
            }
        },

        /**
         * videojs is ready, so set up listeners and animate on
         */
        onVideojsReady() {
            this.initVideojsRecordRecordListeners();
            this.player.record().getDevice(); //request the device to avoid needing to click the activate button
        },

        /**
         * track the events videojs-record offers to manage display
         */
        initVideojsRecordRecordListeners() {
            this.player.on("deviceReady", this.onDeviceReady);
            this.player.on("startRecord", this.onStartRecord);
            this.player.on("finishRecord", this.onFinishRecord);
            this.player.on("error", this.onError);
            this.player.on("deviceError", this.onDeviceError);
        },

        /**
         * onDeviceReady, dispatch to anyone listening, maybe the parent
         */
        onDeviceReady() {
            this.isDeviceReady = true;
            const currentDeviceSettings = this.player.record().stream.getVideoTracks()[0].getSettings();
            this.$emit(recordMediaEvents.DEVICE_READY, currentDeviceSettings.width, currentDeviceSettings.height);
        },

        /**
         * onStartRecord
         */
        onStartRecord() {
            //
        },

        /**
         * the recorded file is ready
         */
        onFinishRecord() {
            this.recordedVideoReady(this.player.recordedData);
        },

        /**
         * a media has been recorded, so can now either upload it, delete and re-record or play it back
         */
        async recordedVideoReady(mediaData) {
            this.recordedData = await dataURLtoBlob(mediaData); //the recorded photo is just a DataURL, so need to set up a Blob
            this.recordedData.name = "photo.jpeg";
            this.recordedData.src = mediaData;
            this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * not sure what would cause this trigger yet, but it's in the videojs-record examples, probably an invalid src etc
         */
        onError(element, error) {
            this.hasError = true;
            console.warn(error);
        },

        /**
         * onDeviceError - try and manage as many errors as possible
         */
        onDeviceError() {
            this.hasError = true;
            const errorCode = String(this.player.deviceErrorCode);
            console.warn("errorCode = " + errorCode);
            if (isDeviceAlreadyInUseError(errorCode)) {
                this.hasDeviceAlreadyInUseError = true;
            } else if (isPermissionDeniedError(errorCode)) {
                this.hasPermissionDeniedError = true;
            }
        },

        /**
         * ensure everything reset on possible modal re-display
         */
        destroyVideojs() {
            if (this.player) {
                this.player.record().stopDevice();
                this.player.record().destroy();
            }
        },

        /**
         * close the error modal, animating out
         */
        onCloseErrorModalSelected() {
            this.isOverMediaModalClosing = true;
        },

        /**
         * it's closed now, so disable the errors to allow trying again
         */
        onErrorModalClosed() {
            this.hasError = false;
            this.isOverMediaModalClosing = false;
            this.hasDeviceAlreadyInUseError = false;
            this.hasPermissionDeniedError = false;
        },

        /**
         * confirm deletion of the latest recording, setting up for another recording
         */
        onRecordedMediaDeleted() {
            this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
            this.hasPreRecordedData = false; //set to false again for possible display or a re-recording
            this.hasNewRecording = false;
            if (this.player) {
                //relaunch the recorder if it's already set up
                this.player.record().getDevice();
            } else {
                //otherwise lets fire it up
                this.initVideojsRecord();
            }
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

        /**
         * Ensure the recorded over media modal is fully visible
         *
         * @params { Number }
         * @params { Number }
         */
        onOverMediaModalOpened(width, height) {
            this.$emit(recordMediaEvents.OVER_MEDIA_MODAL_OPENED, width, height);
        },

        /**
         * revert back to original setting
         */
        onOverMediaModalClosed() {
            this.$emit(recordMediaEvents.OVER_MEDIA_MODAL_CLOSED);
        },
    },
};
</script>

<template>
    <div class="container">
        <div class="media-container media-container-record">
            <video ref="recordingVideo" playsinline class="video-js vjs-record-skin"></video>
        </div>

        <recorded-media
            v-if="hasPreRecordedData || hasNewRecording"
            class="media-container media-container-playback"
            :record-media-type="recordMediaTypes.PHOTO"
            :has-pre-recorded-data="hasPreRecordedData"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            :recorded-data="recordedData"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-resize="onResize"
            @on-over-media-modal-opened="onOverMediaModalOpened"
            @on-over-media-modal-closed="onOverMediaModalClosed"
        />

        <over-media-modal
            v-if="hasError"
            :is-closing="isOverMediaModalClosing"
            :closed-call-back="onErrorModalClosed"
            @on-over-media-modal-opened="onOverMediaModalOpened"
            @on-over-media-modal-closed="onOverMediaModalClosed"
        >
            <div v-if="hasDeviceAlreadyInUseError" class="alert-box warning">
                The camera appears to be already in use or permission has been denied. Please close any Applications or
                Browser windows that may be using the camera and try again.
            </div>
            <div v-else-if="hasPermissionDeniedError" class="alert-box alert">
                Permission to access the camera was denied. Please retry and grant access if you wish to proceed.
            </div>
            <div v-else-if="hasNotSupportedError" class="alert-box alert">
                Sorry, unable to record a photo. It's likely your browser does not support the required features. If
                possible please upgrade your browser.
            </div>
            <div v-else class="alert-box alert">Sorry, an unknown error has occured.</div>
            <button @click.prevent="onCloseErrorModalSelected">Close</button>
        </over-media-modal>
    </div>
</template>

<style lang="scss" scoped>
@import "@schoolboxVue/components/videojs-record/videojs-record";
</style>
