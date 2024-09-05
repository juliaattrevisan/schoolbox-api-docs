<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To record a learning moment photo, audio, or video
 *
 */

import RecordPhoto from "@schoolboxVue/components/record/record-photo";
import RecordCamera from "@schoolboxVue/components/record/record-camera";
import RecordAudioOnly from "@schoolboxVue/components/record/record-audio-only";
import OverMediaModal from "@schoolboxVue/components/videojs-record/over-media-modal";
import recordMediaTypes from "@js/record-media/recordMediaTypes";
import axios from "axios";
import { humanFileSize } from "@js/util/fileUtils";
import { hasDeviceCapture } from "@js/util/featureDetectionUtils";
import { isOfFileType, convertFilenameExtension } from "@js/util/fileUtils";

export default {
    components: {
        RecordPhoto,
        RecordCamera,
        RecordAudioOnly,
        OverMediaModal,
    },

    props: {
        momentType: {
            //to determine how to set up the recording/playback
            type: String,
            required: true,
            validator: function (value) {
                return ["video", "audio", "photo"].indexOf(value) !== -1; // The value must match one of these strings
            },
        },
        preRecordedData: {
            //to display an already existing recording on startup, should contain hash, size, and mime
            type: Object,
            required: false,
        },
    },

    data() {
        return {
            recordMediaType: "", //to determine display will be retrieved comparing with props.momentType
            hasDeviceCapture: false, //to determine whether to use the devices native device capture rather then the browsers
            recordedData: null, //could be audio, video, image etc
            isUploading: false, //to notify of update
            uploadProgressRatio: "0%", //to track the upload amount
            hasUploadError: false, //to notify of upload error, try again?
            uploadErrorMessage: "Sorry, there was an unknown error while uploading.",
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasUploaded: false, //notify UI of upload complete
            defaultRatio: 0, //a ratio to return to when a video is selected
            currentRatio: 0, //default to 16:9 ratio, but could change when the device is actually available or moving between the recorded media and new media
            targetHeight: "0px", //to set the height based on the aspect ratio of the video
            isFirstRecording: true, //prevent multipled recordings coming through
            deviceWidth: 0, //to restore to device dimensions if expanding/contracting on over-media-modal changes
            deviceHeight: 0, //to restore to device dimensions if expanding/contracting on over-media-modal changes
        };
    },

    created() {
        //these vars are created so they can be accessed within the template
        this.hasDeviceCapture = hasDeviceCapture();
        this.recordMediaTypes = recordMediaTypes;
        this.recordMediaType = this.getMomentTypeToBrowserMediaCaptureType();
        this.initRatios();
    },

    mounted() {
        this.initContainerHeight();
    },

    methods: {
        /**
         * to know how to display the browser-media-capture
         * object literals intead of switch: https://ultimatecourses.com/blog/deprecating-the-switch-statement-for-object-literals
         *
         * @returns { String }
         */
        getMomentTypeToBrowserMediaCaptureType() {
            const momentTypes = {
                video: recordMediaTypes.CAMERA,
                audio: recordMediaTypes.AUDIO_ONLY,
                photo: recordMediaTypes.PHOTO,
            };
            return momentTypes[this.momentType];
        },

        /**
         * Set to ensure the container isn't too big on mobile devices
         *
         */
        initRatios() {
            if (this.hasDeviceCapture && this.recordMediaType !== recordMediaTypes.AUDIO_ONLY) {
                //audio only is purely browser API, not device capture
                this.defaultRatio = 0.35;
            } else {
                this.defaultRatio = 0.5625;
            }
            this.currentRatio = this.defaultRatio;
        },

        /**
         * To set up appropriate initial height on container, keeping the same size as a 16:9 video element
         *
         */
        initContainerHeight() {
            window.addEventListener("resize", this.updateContainerHeight);
            this.updateContainerHeight();
            this.deviceWidth = this.$refs.container.getBoundingClientRect().width;
            this.deviceHeight = parseInt(this.targetHeight);
        },

        /**
         * Triggered on window resize
         *
         */
        updateContainerHeight() {
            this.targetHeight = this.$refs.container.getBoundingClientRect().width * this.currentRatio + "px"; //so there's no height jumps on switching videos
        },

        /**
         * currently to update the container ratio to match the dimensions of the recording device
         *
         * @params { Number }
         * @params { Number }
         */
        onDeviceReady(width, height) {
            this.updateCurrentRatio(width, height);
        },

        /**
         * devices have different aspect ratio of 16:9, 9:16, 4:3, 3:4 etc, so ensure the container is kept the same
         *
         * @params { Number } width
         * @params { Number } height
         */
        updateCurrentRatio(width, height) {
            this.currentRatio = height / width;
            this.updateContainerHeight();
        },

        /**
         * to upload the recorded data
         *
         */
        onRecordedMediaReady(recordedData) {
            this.recordedData = recordedData;
            document.getElementById("evidence-filename").value = this.recordedData.name; //to pass form submit validation check
            if (this.isFirstRecording) {
                this.isFirstRecording = false;
                document.addEventListener("evidence-media-recording__onFormSubmit", this.onFormSubmit); //to prevent the form from being submitted until the media is uploaded
            }
        },

        /**
         * the form button has been pressed, so start uploading the file
         *
         */
        async onFormSubmit() {
            this.checkRecording();
            const responseData = await this.uploadMediaAndReturnResponse();
            if (!this.hasUploadError) {
                this.addHiddenInputs(responseData);
                this.displayUploadSuccessful();
                document.dispatchEvent(new CustomEvent("evidence-media-recording__onUploadComplete")); //dispach to form.twig to continue form submit
            }
        },

        /**
         * currently to check for audio only webms
         *
         */
        checkRecording() {
            if (this.recordMediaType === recordMediaTypes.AUDIO_ONLY && isOfFileType(this.recordedData.name, "webm")) {
                //convert if audio/webm to ogg to ensure SB encoder treats as audio only (rather than video)
                this.recordedData.name = convertFilenameExtension(this.recordedData.name, ".ogg");
            }
        },

        /**
         * the media is ready, so upload it up to SchoolBox and return response
         *
         */
        async uploadMediaAndReturnResponse() {
            try {
                this.isUploading = true; //show the upload modal
                this.uploadProgressRatio = "0%";
                const response = await axios.post("/storage/asyncUpload.php", this.getFormData(), {
                    onUploadProgress: (progressEvent) => this.onUploadProgress(progressEvent),
                });
                this.uploadProgressRatio = "100%";
                this.isUploading = false; //hide the upload modal
                return response.data;
            } catch (error) {
                this.checkErrorMessage(error.response.data);
                this.isUploading = false; //hide the upload modal
                this.hasUploadError = true; //to show the error modal
            }
        },

        /**
         * What to send in the upload
         *
         * @returns {String}
         */
        getFormData() {
            const formData = new FormData();
            formData.append("upload", this.recordedData, this.recordedData.name);
            return formData;
        },

        /**
         * To visualise the upload progress
         *
         * @params {ProgressEvent}
         */
        onUploadProgress(progressEvent) {
            if (progressEvent.lengthComputable) {
                this.uploadProgressRatio = (progressEvent.loaded / progressEvent.total) * 100;
                this.uploadProgressRatio = this.uploadProgressRatio + "%";
            }
        },

        /**
         * try and grab known error messages to better inform of the error that's occured
         *
         * @params {Object}
         */
        checkErrorMessage(errorResponseData) {
            if (errorResponseData.hash?.includes("This file exceeds the file size restriction")) {
                this.uploadErrorMessage = `Sorry, the recording of ${humanFileSize(
                    this.recordedData.size,
                )} is over the allowed file size limit. Please try a shorter recording or request your administrator(s) to increase the allowed file size limit.`;
            }
        },

        /**
         * populate the input fields in order to validate and submit on save
         *
         * @params {Object}
         */
        addHiddenInputs(responseData) {
            document.getElementById("file").value = `${responseData.hash}:${this.recordedData.name}`; //to retrieve the file reference
        },

        /**
         * To show the upload successful note
         *
         */
        displayUploadSuccessful() {
            this.hasUploaded = true; //to display success note
            setTimeout(() => {
                this.hasUploaded = false; //and hide again
            }, 2000);
        },

        /**
         * recorded media is no longer available
         *
         */
        onRecordedMediaDeleted() {
            this.clearHiddenInputs(); //clear the form elements
            if (this.hasDeviceCapture) {
                //restore the height to a decent amount, especially if was a phone portrait capture
                this.currentRatio = this.defaultRatio;
                this.updateContainerHeight();
            }
        },

        /**
         * ensure the form cannot be processed when there's no recording available
         *
         */
        clearHiddenInputs() {
            document.getElementById("evidence-filename").value = "";
            document.getElementById("file").value = "";
        },

        /**
         * Ensure the recorded video ratio is respected
         *
         * @params { Number }
         * @params { Number }
         */
        onResize(width, height) {
            this.updateCurrentRatio(width, height);
        },

        /**
         * close the error modal, animating out
         *
         */
        onCloseErrorModalSelected() {
            this.isOverMediaModalClosing = true;
        },

        /**
         * it's closed now, so disable the errors to allow trying again
         *
         */
        onErrorModalClosed() {
            this.hasUploadError = false;
            this.isOverMediaModalClosing = false;
            document.getElementById("evidence-submit").disabled = false; //enable to submit button again for possible re-try
        },

        /**
         * Ensure the recorded over media modal is fully visible
         *
         * @params { Number }
         * @params { Number }
         */
        onOverMediaModalOpened(width, height) {
            this.updateCurrentRatio(width, height);
        },

        /**
         * revert back to original setting
         *
         */
        onOverMediaModalClosed() {
            this.updateCurrentRatio(this.deviceWidth, this.deviceHeight); //revert to original device size as has resize to ensure over-media-modal is fully visible
        },
    },
};
</script>

<template>
    <div class="capture-media-container" ref="container" :style="{ height: targetHeight }">
        <record-photo
            v-if="recordMediaType === recordMediaTypes.PHOTO"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            @on-device-ready="onDeviceReady"
            @on-recorded-media-ready="onRecordedMediaReady"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-resize="onResize"
            @on-over-media-modal-opened="onOverMediaModalOpened"
            @on-over-media-modal-closed="onOverMediaModalClosed"
        />
        <record-camera
            v-else-if="recordMediaType === recordMediaTypes.CAMERA"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            @on-device-ready="onDeviceReady"
            @on-recorded-media-ready="onRecordedMediaReady"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-resize="onResize"
            @on-over-media-modal-opened="onOverMediaModalOpened"
            @on-over-media-modal-closed="onOverMediaModalClosed"
        />
        <record-audio-only
            v-else-if="recordMediaType === recordMediaTypes.AUDIO_ONLY"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            @on-device-ready="onDeviceReady"
            @on-recorded-media-ready="onRecordedMediaReady"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-resize="onResize"
            @on-over-media-modal-opened="onOverMediaModalOpened"
            @on-over-media-modal-closed="onOverMediaModalClosed"
        />

        <over-media-modal v-if="isUploading" key="isUploadingModal">
            <div class="progress large-12" style="margin-bottom: 1rem">
                <span class="meter" :style="{ width: uploadProgressRatio }"></span>
            </div>
            <p><em>Uploading media, please wait...</em></p>
        </over-media-modal>
        <over-media-modal
            v-else-if="hasUploaded"
            @on-over-media-modal-closed="onOverMediaModalClosed"
            key="hasUploadedModal"
        >
            <p><em>Media successfully uploaded.</em></p>
        </over-media-modal>
        <over-media-modal
            v-else-if="hasUploadError"
            :is-closing="isOverMediaModalClosing"
            :closed-call-back="onErrorModalClosed"
            @on-over-media-modal-opened="onOverMediaModalOpened"
            @on-over-media-modal-closed="onOverMediaModalClosed"
            key="errorUploadModal"
        >
            <div class="alert-box alert">{{ uploadErrorMessage }}</div>
            <button @click.prevent="onCloseErrorModalSelected">Close</button>
        </over-media-modal>
    </div>
</template>

<style lang="scss" scoped>
.capture-media-container {
    position: relative;
    width: 100%;
    max-width: 700px;
    height: 200px; //updated on mounted()
    margin-bottom: 1rem;
    overflow: hidden;
    background: white;
    transition: 0.2s height ease-out; //don't jar the height as much, provide a slight ease
    h2,
    h3 {
        max-width: none;
    }
}
</style>
