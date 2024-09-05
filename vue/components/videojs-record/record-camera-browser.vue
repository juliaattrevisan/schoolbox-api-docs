<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To record, playback and upload a camera recording into SB
 * It currently always sits within a parent, currently #evidence-media-recording and #record-medida-modal
 */

import "video.js/dist/video-js.css";
import "videojs-record/dist/css/videojs.record.css";
import videojs from "video.js";
import "webrtc-adapter";
import RecordRTC from "recordrtc";
import Record from "videojs-record/dist/videojs.record.js";
import TsEBMLEngine from "videojs-record/dist/plugins/videojs.record.ts-ebml.js";
import RecordDeviceOptions from "@schoolboxVue/components/videojs-record/record-device-options";
import RecordedMedia from "@schoolboxVue/components/videojs-record/recorded-media";
import OverMediaModal from "@schoolboxVue/components/videojs-record/over-media-modal";
import { getRecordCameraOptions } from "@js/videojs/videojsOptions";
import recordMediaTypes from "@js/record-media/recordMediaTypes";
import recordMediaEvents from "@js/record-media/recordMediaEvents";
import {
    isDeviceAlreadyInUseError,
    isPermissionDeniedError,
    isDeviceChangeError,
} from "@js/record-media/recordMediaErrors";
import { isOfMimeType } from "@js/util/fileUtils";
import chooseDevice from "@schoolboxVue/mixins/videojs-record/mixinChooseDevice";

export default {
    components: {
        RecordDeviceOptions,
        RecordedMedia,
        OverMediaModal,
    },

    mixins: [chooseDevice],

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
            isRecording: false, //to track whether recording
            recordedData: null, //the recorded data that will be passed through to #recored-media
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasNotSupportedError: false, //unable to fire up the media recorder, likely because a required media API is not available
            hasDeviceAlreadyInUseError: false, //can't access the requested device
            hasPermissionDeniedError: false, //didn't allow permission
            isRecordingOverMaxFileSize: false, //notify that the recording won't upload
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
            //only if no pre recording available, otherwise initiate on possible deletion
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
                this.player = videojs(this.$refs.recordingVideo, getRecordCameraOptions(), this.onVideojsReady);
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
            this.player.on("enumerateReady", this.chooseDevice_onEnumerateReady);
            this.player.on("startRecord", this.onStartRecord);
            this.player.on("finishRecord", this.onFinishRecord);
            this.player.on("error", this.onError);
            this.player.on("deviceError", this.onDeviceError);
            this.player.on("enumerateError", this.onEnumerateError);
        },

        /**
         * onDeviceReady, dispatch to anyone listening, maybe the parent
         */
        onDeviceReady() {
            const currentDeviceSettings = this.player.record().stream.getVideoTracks()[0].getSettings(); //to get width, height and the current deviceId
            this.chooseDevice_currentVideoDeviceId = currentDeviceSettings.deviceId;
            this.$emit(recordMediaEvents.DEVICE_READY, currentDeviceSettings.width, currentDeviceSettings.height);
            if (!this.chooseDevice_hasEnumeratedDevices) {
                this.player.record().enumerateDevices();
            }
        },

        /**
         * onStartRecord
         */
        onStartRecord() {
            this.isRecording = true;
            document
                .querySelectorAll(".vjs-time-control")
                .forEach((el) => (el.style.backgroundColor = "rgba(255, 0, 0, 0.25)"));
        },

        /**
         * the recorded file is ready
         */
        onFinishRecord() {
            this.isRecording = false;
            if (this.player.recordedData.type && isOfMimeType(this.player.recordedData.type, "video/webm")) {
                //if it's a webm
                this.player.on("finishConvert", this.onFinishConvert);
            } else {
                this.recordedVideoReady(this.player.recordedData);
            }
        },

        /**
         * the seekable webm file is ready
         */
        onFinishConvert() {
            this.player.off("finishConvert", this.onFinishConvert);
            this.recordedVideoReady(this.player.convertedData);
        },

        /**
         * a media has been recorded, so can now either upload it, delete and re-record or play it back
         */
        recordedVideoReady(mediaData) {
            document.querySelectorAll(".vjs-time-control").forEach((el) => (el.style.backgroundColor = "transparent"));
            this.recordedData = mediaData; // the blob object contains the recorded data
            this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * not sure what would cause this trigger yet, but it's in the videojs-record examples, probably an invalid src etc
         *
         * @params { DOMElement }
         * @params { Object }
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
            } else if (isDeviceChangeError(errorCode)) {
                this.onDeviceChangeError();
            }
        },

        /**
         * onEnumerateError - try and manage as many errors as possible
         */
        onEnumerateError() {
            console.warn("enumerate error: " + this.player.enumerateErrorCode);
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
            this.chooseDevice_hasDeviceChangeError = false;
            this.isRecordingOverMaxFileSize = false;
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

        <record-device-options
            title="Select a Camera"
            :current-device-id="chooseDevice_currentVideoDeviceId"
            :device-list="chooseDevice_videoDeviceList"
            :is-recording="isRecording"
            @on-video-device-change="chooseDevice_onVideoDeviceChange"
        />

        <recorded-media
            v-if="hasPreRecordedData || hasNewRecording"
            class="media-container media-container-playback"
            :record-media-type="recordMediaTypes.CAMERA"
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
                Permission to access the device was denied. Please retry and grant access if you wish to proceed.
            </div>
            <div v-else-if="isRecordingOverMaxFileSize" class="alert-box alert">
                Sorry, the recording has stopped as it is over the allowed file size limit. Please record a shorter
                video or request your administrator(s) to increase the allowed file size limit.
            </div>
            <div v-else-if="hasNotSupportedError" class="alert-box alert">
                Sorry, unable to record video. It's likely your browser does not support the required features. If
                possible please upgrade your browser.
            </div>
            <div v-else-if="chooseDevice_hasDeviceChangeError" class="alert-box alert">
                Sorry, the selected camera cannot be displayed. Please ensure it's functioning properly and try again.
            </div>
            <div v-else class="alert-box alert">Sorry, an unknown error has occured.</div>
            <button @click.prevent="onCloseErrorModalSelected">Close</button>
        </over-media-modal>
    </div>
</template>

<style lang="scss" scoped>
@import "@schoolboxVue/components/videojs-record/videojs-record";
</style>
