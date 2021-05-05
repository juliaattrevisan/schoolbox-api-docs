<script>

/**
 * To record a learning moment photo, audio, or video
 *
 */

import RecordMedia from '@schoolbox/videojs-record/record-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import axios from 'axios';
import { dataURLtoBlob } from '@shared/util/fileUtils';


export default {

    components: {
        RecordMedia, //the media element
        OverMediaModal, //to slot in upload modal
    },

    props: {
        momentType: {  //to determine how to set up the recording/playback
            type: String,
            required: true,
            validator: function (value) {
                return ['video', 'audio', 'photo'].indexOf(value) !== -1 // The value must match one of these strings
            }
        },
        preRecordedData: {  //to display an already existing recording on startup, should contain hash, size, and mime
            type: Object,
            required: false
        },
    },

    data () {
        return {
            recordMediaType: '', //to determine display will be retrieved comparing with props.momentType 
            recordedData: null, //could be audio, video, image etc
            isUploading: false, //to notify of update
            hasUploadError: false, //to notify of upload error, try again?
            hasUploaded: false, //notify UI of upload complete
            currentRatio: 0.5625, //default to 16:9 ratio, but could change when the device is actually available
            targetHeight: '0px', //to set the height based on the aspect ratio of the video
        }
    },

    created () {
        this.recordMediaType = this.getMomentTypeToRecordMediaType();
    },

    mounted () {
        this.initContainerHeight();
    },

    methods: {
        
        /**
         * to know how to display the record-media
         * object literals intead of switch: https://ultimatecourses.com/blog/deprecating-the-switch-statement-for-object-literals
         *
         * @returns { String }
         */
        getMomentTypeToRecordMediaType () {
            const momentTypes = {
                'video': recordMediaTypes.CAMERA,
                'audio': recordMediaTypes.AUDIO_ONLY,
                'photo': recordMediaTypes.PHOTO
            };
            return momentTypes[this.momentType];
        },

        /**
         * To set up appropriate initial height on container, keeping the same size as a 16:9 video element
         *
         */
        initContainerHeight () {
            window.addEventListener('resize', this.updateContainerHeight);
            this.updateContainerHeight();
        },

        /**
         * Triggered on window resize
         *
         */
        updateContainerHeight () {
            this.targetHeight = (this.$refs.container.getBoundingClientRect().width * this.currentRatio) + 'px'; //so there's no height jumps on switching videos
        },

        /**
         * currently to update the container ratio to match the dimensions of the recording device
         *
         */
        onDeviceReady (videojs) {
            this.updateCurrentRatio(videojs);
            this.updateContainerHeight();
        },

        /**
         * devices have different aspect ratio of 16:9, 4:3 etc
         *
         */
        updateCurrentRatio (videojs) {
            const currentDeviceSettings = videojs.record().stream.getVideoTracks()[0].getSettings(); //dive dive dive!
            const deviceWidth = currentDeviceSettings.width;
            const deviceHeight = currentDeviceSettings.height;
            this.currentRatio = deviceHeight / deviceWidth;
        },

        /**
         * to upload the recorded data
         *
         */
        onRecordedMediaReady (recordedData) {
            this.recordedData = recordedData;
            this.checkRecordedData();
            document.getElementById('evidence-filename').value = this.recordedData.name; //to pass form submit validation check
            document.addEventListener('evidence-media-recording__onFormSubmit', (event) => { //to prevent the form from being submitted until the media is uploaded
                this.onFormSubmit();
            }); 
        },

        /**
         * currently just convert the PHOTO to be ready for upload
         *
         */
        checkRecordedData () {
            if (this.recordMediaType === recordMediaTypes.PHOTO) {
                this.recordedData = dataURLtoBlob(this.recordedData); //the upload needs to be a Blob
                this.recordedData.name = 'photo.jpeg'; //just for passing form submit validation checks, the file is referenced through the hash, so the name is not too relevant
            }
        },

        /**
         * the form button has been pressed, so start uploading the file
         *
         */
        async onFormSubmit (event) {
            try {
                const responseData = await this.uploadMediaAndReturnResponse();
                this.addHiddenInputs(responseData);
                this.displayUploadSuccessful();
                document.dispatchEvent(new CustomEvent('evidence-media-recording__onUploadComplete')); //dispach to form.twig to continue form submit
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * the video is ready, so upload it up to SchoolBox and return response
         *
         */
        async uploadMediaAndReturnResponse () {
            try {
                this.isUploading = true; //show the upload modal
                const response = await axios.post('/storage/asyncUpload.php', this.getFormData(), {
                    onUploadProgress: (progressEvent) => this.onUploadProgress(progressEvent)
                });
                this.isUploading = false; //hide the upload modal
                return response.data;
            } catch (error) {
                this.isUploading = false; //hide the upload modal
                this.hasUploadError = true; //to show the error modal
                console.warn(error);
            }
        },

        /**
         * What to send in the upload
         *
         * @returns {String}
         */
        getFormData () {
            const formData = new FormData();
            formData.append('upload', this.recordedData, this.recordedData.name);
            return formData;
        },

        /**
         * To visualise the upload progress
         *
         * @params {ProgressEvent}
         */
        onUploadProgress (progressEvent) {
            if (progressEvent.lengthComputable) {
                const progressRatio = progressEvent.loaded / progressEvent.total;
                console.warn(progressRatio);
            }
        },

        /**
         * populate the input fields in order to validate and submit on save
         *
         * @params {Object}
         */
        addHiddenInputs (responseData) {
            document.getElementById('file').value = `${responseData.hash}:${this.recordedData.name}`; //to retrieve the file reference
        },

        /**
         * To show the upload successful note
         *
         */
        displayUploadSuccessful () {
            this.hasUploaded = true; //to display
            setTimeout(() => { 
                this.hasUploaded = false; //and hide again
            }, 2000);
        },

        /**
         * recorded media is no longer available
         *
         */
        onRecordedMediaDeleted () {
            this.clearHiddenInputs();
        },

        /**
         * ensure the form cannot be processed when there's no recording available
         *
         */
        clearHiddenInputs () {
            document.getElementById('evidence-filename').value = '';
            document.getElementById('file').value = '';
        },

    }

}
</script>

<template>

    <div>
            
        <div class="record-media-container" ref="container" :style="{ height: targetHeight }">

            <record-media
                :record-media-type="recordMediaType"
                :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
                @on-device-ready="onDeviceReady"
                @on-recorded-media-ready="onRecordedMediaReady"
                @on-recorded-media-deleted="onRecordedMediaDeleted"
            />

            <over-media-modal v-if="isUploading" key="isUploadingModal">
                <div class="loader"></div>
                <p><em>Uploading video, please wait...</em></p>
            </over-media-modal>
            <over-media-modal v-else-if="hasUploaded" key="hasUploadedModal">
                <p><em>Media successfully uploaded.</em></p>
            </over-media-modal>	

        </div>	

    </div>

</template>

<style lang="scss" scoped>

.record-media-container {
    position: relative;
    width: 100%;
    max-width: 700px;
    height: 300px; //updated on mounted()
    margin-bottom: 1rem;
    overflow: hidden;
    background: red;
    h2, h3 {
        max-width: none;
    }
}

.modal-actions {
    display: flex;
    button {
        margin: 0px 2px; 
    }
}

</style>
