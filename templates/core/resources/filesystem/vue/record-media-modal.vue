<script>

/*
 * To record some media, sits within the SB modal window under 'Record'
 */

import RecordMediaModalMenu from './record-media-modal-menu';
import RecordAudioOnly from '@schoolboxVue/components/record/record-audio-only';
import RecordPhoto from '@schoolboxVue/components/record/record-photo';
import RecordCamera from '@schoolboxVue/components/record/record-camera';
import RecordScreen from '@schoolboxVue/components/record/record-screen';
import OverMediaModal from '@schoolboxVue/components/videojs-record/over-media-modal';
import recordMediaTypes from '@js/record-media/recordMediaTypes';
import axios from 'axios';
import filesize from 'filesize';
import { hasDeviceCapture } from '@js/util/featureDetectionUtils';
import { isOfFileType, convertFilenameExtension } from '@js/util/fileUtils';


export default {

    components: {
        RecordMediaModalMenu, //the initial big menu
        RecordAudioOnly,
        RecordPhoto,
        RecordCamera,
        RecordScreen,
        OverMediaModal //to slot in upload modal
    },

    data () {
        return {
            currentRecordMediaType: recordMediaTypes.UNSET, //the currently selected media recording
            recordMediaTypeList: [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.SCREEN],
            hasDeviceCapture: false, //to determine whether to use the devices native device capture rather then the browsers
            recordedData: null, //could be audio, video, image etc
            canUpload: false, //to upload the file
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            isFilenameModalActive: false, //prompt the filename change modal
            newFilename: '', //name the file
            isUploading: false, //to notify of update
            uploadProgressRatio: '0%', //to track the upload amount
            hasUploadError: false, //to notify of upload error, try again?
            uploadErrorMessage: 'Sorry, there was an unknown error while uploading.',
            hasUploaded: false, //notify UI of upload complete
            defaultRatio: 0, //a ratio to return to when a video is selected
            currentRatio: 0, //default to 16:9 ratio, but could change when the device is actually available
            targetHeight: '0px', //to set the height based on the aspect ratio of the video
            hasClosed: false, //to allow any active vue to cleanup on beforeUnmount,
            deviceWidth: 0, //to restore to device dimensions if expanding/contracting on over-media-modal changes
            deviceHeight: 0, //to restore to device dimensions if expanding/contracting on over-media-modal changes
        }
    },

    computed: {

        /**
         * To know whether to show the upload button
         *
         * @returns { Boolean }
         */
        showUploadButton () {
            if (this.canUpload && !this.isUploading) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * grab the new filename with the relevant extension (can be webm/mp4/mp3)
         *
         * @returns { String }
         */
        newFilenameWithExtension () {
            const extension = this.recordedData.name.split('.').pop();
            return this.newFilename + '.' + extension;
        },

    },

    watch: {

        /**
         * ensure no invalid chars are added to the user added filename
         */
        newFilename (newValue) {
            this.newFilename = newValue.replace(/[\\/:"*?<>|]+/g, "");
        },

    },

    created () {
        this.hasDeviceCapture = hasDeviceCapture();
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types

    },

    mounted () {
        this.initKeyboardListener();
        this.initOnModalClosedListener();
        this.initRatios();
        this.initContainerHeight();
    },

    methods: {

        /**
         * to be able to press enter to save filename
         */
        initKeyboardListener () {
            document.addEventListener('keyup', (event) => {
                const keyName = event.key;
                if (keyName === 'Enter' && this.isFilenameModalActive && this.newFilename.trim() !== '') { //the save modal is open, and the filename is not blank
                    this.onSaveFilenameSelected();
                }
            });
        },

        /**
         * to close up shop for possible re-display
         */
        initOnModalClosedListener () {
            document.addEventListener('record-media-modal__onModalClosed', () => {
                this.hasClosed = true;
            });
        },

        /**
         * Set to ensure the container isn't too big
         */
        initRatios () {
            this.defaultRatio = 0.5625;
            this.currentRatio = this.defaultRatio;
        },

        /**
         * To set up appropriate initial height on container, typically keeping the same size as a 16:9 video element
         * unless otherwise set by say, a mobile phone recorded video requested a larger size to fit a portrait recorded video
         */
        initContainerHeight () {
            window.addEventListener('resize', this.updateContainerHeight);
            this.updateContainerHeight();
            this.deviceWidth = this.$refs.container.getBoundingClientRect().width;
            this.deviceHeight = parseInt(this.targetHeight);
        },

        /**
         * Triggered on window resize
         */
        updateContainerHeight () {
            if (!this.hasClosed) { //won't be available when the modal has been closed
                this.targetHeight = `${this.$refs.container.getBoundingClientRect().width * this.currentRatio}px`; //so there's no height jumps on switching videos
            }
        },

        /**
         * currently to update the container ratio to match the dimensions of the recording device
         *
         * @params { Number }
         * @params { Number }
         */
        onDeviceReady (width, height) {
            this.deviceWidth = width;
            this.deviceHeight = height;
            this.updateCurrentRatio(width, height);
        },

        /**
         * devices have different aspect ratio of 16:9, 9:16, 4:3, 3:4 etc, so ensure the container is kept the same
         *
         * @params { Number } width
         * @params { Number } height
         */
        updateCurrentRatio (width, height) {
            this.currentRatio = height / width;
            this.updateContainerHeight();
        },

        /**
         * To toggle between the different media types
         *
         * @params { String }
         */
        onRecordMediaTypeSelected (mediaType) {
            this.currentRecordMediaType = mediaType;
        },

        /**
         * to upload the recorded data
         *
         * @params { Object }
         */
        onRecordedMediaReady (recordedData) {
            this.recordedData = recordedData;
            this.canUpload = true; //to show the upload button
        },

        /**
         * recorded media is no longer available
         */
        onRecordedMediaDeleted () {
            this.canUpload = false; //hide the upload button
            if (this.hasDeviceCapture) { //restore the height to a decent amount, especially if was a phone portrait capture
                this.currentRatio = this.defaultRatio;
                this.updateContainerHeight();
            }
        },

        /**
         * present the save file name modal to name the file
         */
        onUploadSelected () {
            this.isFilenameModalActive = true;
            this.$nextTick(() => {
                this.$refs.filenameInput.focus(); //so can start typing straight away
            });
        },

        /**
         * close the filename modal
         */
        onCloseFilenameModalSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * close the filename modal, cancel has been selected rather than save
         */
        onFilenameModalClosed () {
            this.isOverMediaModalClosing = false;
            this.isFilenameModalActive = false;
        },

        /**
         * the user has set a file name and selected save
         */
        async onSaveFilenameSelected () {
            this.isFilenameModalActive = false;
            this.checkRecording();
            const responseData = await this.uploadMediaAndReturnResponse();
            if (!this.hasUploadError) {
                this.postUploadedMessage(responseData);
            }
        },

        /**
         * currently to check for audio only webms
         */
        checkRecording () {
            if (this.currentRecordMediaType === recordMediaTypes.AUDIO_ONLY && isOfFileType(this.recordedData.name, 'webm')) { //convert if audio/webm to ogg to ensure SB encoder treats as audio only (rather than video)
                this.recordedData.name = convertFilenameExtension(this.recordedData.name, '.ogg');
            }
        },

        /**
         * the video is ready, so upload it up to SchoolBox
         */
        async uploadMediaAndReturnResponse () {
            try {
                this.isUploading = true;
                this.uploadProgressRatio = '0%';
                const response = await axios.post('/storage/asyncUpload.php', this.getFormData(), {
                    onUploadProgress: (progressEvent) => this.onUploadProgress(progressEvent)
                });
                this.uploadProgressRatio = '100%';
                this.isUploading = false;
                return response.data;
            } catch (error) {
                this.checkErrorMessage(error.response.data);
                this.isUploading = false;
                this.hasUploadError = true;
            }
        },

        /**
         * What to send in the upload
         *
         * @returns { FormData }
         */
        getFormData () {
            const formData = new FormData();
            formData.append('upload', this.recordedData, this.newFilenameWithExtension);
            return formData;
        },

        /**
         * To visualise the upload progress
         *
         * @params {ProgressEvent}
         */
        onUploadProgress (progressEvent) {
            if (progressEvent.lengthComputable) {
                this.uploadProgressRatio = (progressEvent.loaded / progressEvent.total) * 100;
                this.uploadProgressRatio = this.uploadProgressRatio + '%';
            }
        },

        /**
         * try and grab known error messages to better inform of the error that's occured
         *
         * @params {Object}
         */
        checkErrorMessage (errorResponseData) {
            if (errorResponseData.hash?.includes('This file exceeds the file size restriction')) {
                this.uploadErrorMessage = `Sorry, the recording of ${filesize(this.recordedData.size)} is over the allowed file size limit. Please try a shorter recording or request your administrator(s) to increase the allowed file size limit.`;
            }
            if (errorResponseData.hash?.includes('The specified filename exceeds the max length')) {
                this.uploadErrorMessage = `Sorry, the specified filename is longer than the allowed length.\nPlease shorten the filename length.`;
            }
        },

        /**
         * inform any listeners that a file was uploaded.
         * put it in the dropzone, and inform the dropzone opener
         * that a file was posted to it.
         *
         * @params {Object}
         */
        postUploadedMessage (responseData) {
            const data = {
                title:    this.newFilenameWithExtension,
                mimeType: this.recordedData.type,
                filesize: this.recordedData.size,
                hash:     responseData.hash
            };
            this.hasUploaded = true;
            this.canUpload = false;
            setTimeout(() => { //put a slight delay so user sees the upload successfull modal
                window.top.postMessage(JSON.stringify({ data: [data] }), location.origin);
                this.hasUploaded = false;
            }, 1000);
        },

        /**
         * Ensure the recorded video ratio is respected
         *
         * @params { Number }
         * @params { Number }
         */
        onResize (width, height) {
            this.updateCurrentRatio(width, height);
        },

        /**
         * close the error modal, animating out
         */
        onCloseErrorModalSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * it's closed now, so disable the errors to allow trying again
         */
        onErrorModalClosed () {
            this.hasUploadError = false;
            this.isOverMediaModalClosing = false;
        },

        /**
         * Ensure the recorded over media modal is fully visible
         *
         * @params { Number }
         * @params { Number }
         */
        onOverMediaModalOpened (width, height) {
            this.updateCurrentRatio(width, height);
        },

        /**
         * revert back to original setting
         */
        onOverMediaModalClosed () {
            this.updateCurrentRatio(this.deviceWidth, this.deviceHeight); //revert to original device size as has resize to ensure over-media-modal is fully visible
        },

    }

}
</script>

<template>

    <div>

        <div class="ab-pane">

            <div v-if="!hasClosed" class="record-media-container" ref="container" :style="{ height: targetHeight }">

                <record-media-modal-menu
                    v-if="currentRecordMediaType === recordMediaTypes.UNSET"
                    @on-record-media-type-selected="onRecordMediaTypeSelected"
                />

                <record-audio-only
                    v-if="currentRecordMediaType === recordMediaTypes.AUDIO_ONLY"
                    :record-media-type="recordMediaTypes.AUDIO_ONLY"
                    @on-recorded-media-ready="onRecordedMediaReady"
                    @on-recorded-media-deleted="onRecordedMediaDeleted"
                    @on-resize="onResize"
                    @on-over-media-modal-opened="onOverMediaModalOpened"
                    @on-over-media-modal-closed="onOverMediaModalClosed"
                />
                <record-photo
                    v-else-if="currentRecordMediaType === recordMediaTypes.PHOTO"
                    :record-media-type="recordMediaTypes.PHOTO"
                    @on-device-ready="onDeviceReady"
                    @on-recorded-media-ready="onRecordedMediaReady"
                    @on-recorded-media-deleted="onRecordedMediaDeleted"
                    @on-resize="onResize"
                    @on-over-media-modal-opened="onOverMediaModalOpened"
                    @on-over-media-modal-closed="onOverMediaModalClosed"
                />
                <record-camera
                    v-else-if="currentRecordMediaType === recordMediaTypes.CAMERA"
                    :record-media-type="recordMediaTypes.CAMERA"
                    @on-device-ready="onDeviceReady"
                    @on-recorded-media-ready="onRecordedMediaReady"
                    @on-recorded-media-deleted="onRecordedMediaDeleted"
                    @on-resize="onResize"
                    @on-over-media-modal-opened="onOverMediaModalOpened"
                    @on-over-media-modal-closed="onOverMediaModalClosed"
                />
                <record-screen
                    v-else-if="currentRecordMediaType === recordMediaTypes.SCREEN"
                    :record-media-type="recordMediaTypes.SCREEN"
                    @on-device-ready="onDeviceReady"
                    @on-recorded-media-ready="onRecordedMediaReady"
                    @on-recorded-media-deleted="onRecordedMediaDeleted"
                    @on-resize="onResize"
                    @on-over-media-modal-opened="onOverMediaModalOpened"
                    @on-over-media-modal-closed="onOverMediaModalClosed"
                />

                <over-media-modal
                    v-if="isFilenameModalActive"
                    :is-closing="isOverMediaModalClosing"
                    :closed-call-back="onFilenameModalClosed"
                    @on-over-media-modal-opened="onOverMediaModalOpened"
                    @on-over-media-modal-closed="onOverMediaModalClosed"
                    key="filenameModal"
                >
                    <h3><em>Please add a filename prior to continuing.</em></h3>
                    <label for="filename" class="required" aria-required="true">Filename</label>
                    <input type="text" id="filename" name="filename" v-model="newFilename" @input="$event.target.composing = false" ref="filenameInput">
                    <div class="modal-actions">
                        <button class="submit" @click.prevent="onSaveFilenameSelected" :disabled="newFilename.trim() === ''">Upload</button>
                        <button @click.prevent="onCloseFilenameModalSelected">Cancel</button>
                    </div>
                </over-media-modal>
                <over-media-modal v-else-if="isUploading" key="isUploadingModal">
                    <div class="progress large-12" style="margin-bottom: 1rem;"><span class="meter" :style="{ width: uploadProgressRatio }"></span></div>
                    <p><em>Uploading video, please wait...</em></p>
                </over-media-modal>
                <over-media-modal v-else-if="hasUploaded" @on-over-media-modal-closed="onOverMediaModalClosed" key="hasUploadedModal">
                    <p><em>Media successfully uploaded.</em></p>
                </over-media-modal>
                <over-media-modal
                    v-else-if="hasUploadError"
                    :is-closing="isOverMediaModalClosing"
                    :closed-call-back="onErrorModalClosed"
                    @on-over-media-modal-opened="onOverMediaModalOpened"
                    @on-over-media-modal-closed="onOverMediaModalClosed"
                    key="errorUploadModal">
                    <div class="alert-box alert">{{ uploadErrorMessage }}</div>
                    <button @click.prevent="onCloseErrorModalSelected">Close</button>
                </over-media-modal>

            </div>

        </div><!-- .ab-pane -->

        <div class="component-action" ref="actions">
            <section>
                <a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
                <transition name="fade">
                    <a v-if="showUploadButton" @click.prevent="onUploadSelected" role="button" class="button" title="Upload" href="#upload">Upload</a>
                </transition>
            </section>
        </div><!-- .component-action -->

    </div>

</template>

<style lang="scss" scoped>

.record-media-container {
    position: relative;
    width: 100%;
    height: 300px; //updated on mounted()
    background: black;
    overflow: hidden;
    transition: 0.2s height ease-out; //don't jar the height as much, provide a slight ease
    z-index: 1;
    h2, h3 {
        max-width: none;
    }
}
.modal-actions {
	display: flex;
	button {
		margin: 0 2px;
        width: 100%;
	}
}

.component-action {
    z-index: 2;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
    opacity: 0;
}

</style>
