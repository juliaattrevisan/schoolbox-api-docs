<script>

/*
 * To record some media, sits within the SB modal window under 'Record'
 *
 */

import RecordMediaModalMenu from './record-media-modal-menu';
import RecordMediaModalUnset from './record-media-modal-unset';
import RecordMedia from '@schoolbox/videojs-record/record-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import axios from 'axios';


export default {

    components: {
        RecordMediaModalMenu, //top menu appears after unset menu selection
        RecordMediaModalUnset, //the initial big menu
        RecordMedia, //the media element
        OverMediaModal //to slot in upload modal
    },

    data () {
        return {
            currentRecordMediaType: recordMediaTypes.UNSET, //the currently selected media recording
            recordMediaTypeList: [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.SCREEN],
            isProcessActive: false, //to be able to disable certain behavour when recordings/conversions/uploads are taking place
            recordedData: null, //could be audio, video, image etc
            canUpload: false, //to upload the file
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            isFilenameModalActive: false, //prompt the filename change modal
            newFilename: '', //name the file
            isUploading: false, //to notify of update
            hasUploadError: false, //to notify of upload error, try again?
            hasUploaded: false, //notify UI of upload complete
            currentRatio: 0.5625, //default to 16:9 ratio, but could change when the device is actually available
            targetHeight: '0px', //to set the height based on the aspect ratio of the video
        }
    },

    computed: {

        /*
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

        /*
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

        /*
         * ensure no invalid chars are added to the user added filename
         *
         */
        newFilename (newValue) {
            this.newFilename = newValue.replace(/[\\/:"*?<>|]+/g, "");
        }

    },

    created () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
    },

    mounted () {
        this.initKeyboardEvents();
        this.initContainerHeight();
    },

    methods: {
        
        /**
         * to be able to press enter to save filename
         *
         */
        initKeyboardEvents () {
            document.addEventListener('keyup', (event) => {
                const keyName = event.key;
                if (keyName === 'Enter' && this.isFilenameModalActive && this.newFilename.trim() !== '') { //the save modal is open, and the filename is not blank
                    this.onSaveFilenameSelected();
                }
            });
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
         * To toggle between the different media types
         *
         * @params {String}
         */
        onRecordMediaTypeSelected (mediaType) {
            this.currentRecordMediaType = mediaType;
        },

        /**
         * to be able to disable certain behavour when conversions/uploads are taking place
         *
         */
        onProcessStarted () {
            this.isProcessActive = true;
        },

        /**
         * to be able to re-enable certain behavour when conversions/uploads are taking place
         *
         */
        onProcessStopped () {
            this.isProcessActive = false;
        },

        /**
         * to upload the recorded data
         *
         */
        onRecordedMediaReady (recordedData) {
            this.recordedData = recordedData;
            this.canUpload = true; //show the upload button
        },

        /**
         * recorded media is no longer available
         *
         */
        onRecordedMediaDeleted (recordedData) {
            this.canUpload = false; //hide the upload button
        },

        /*
         * present the save file name modal to name the file
         *
         */
        onUploadSelected () {
            this.isFilenameModalActive = true;
        },

        /*
         * close the filename modal
         *
         */
        onCloseFilenameModalSelected () {
            this.isOverMediaModalClosing = true;
        },

        /*
         * close the filename modal, cancel has been selected rather than save
         *
         */
        onFilenameModalClosed () {
            this.isOverMediaModalClosing = false;
            this.isFilenameModalActive = false;
        },

        /*
         * the user has set a file name and selected save
         *
         */
        async onSaveFilenameSelected () {
            this.isFilenameModalActive = false;
            try {
                this.isProcessActive = true;
                const responseData = await this.uploadMedia();
                this.postUploadedMessage(responseData);
            } catch (error) {
                this.isProcessActive = false;
                console.log(error);
            }
        },

        /*
         * the video is ready, so upload it up to SchoolBox
         *
         */
        async uploadMedia () {
            try {
                this.isUploading = true;
                const response = await axios.post('/storage/asyncUpload.php', this.getFormData(), {
                    onUploadProgress: (progressEvent) => this.onUploadProgress(progressEvent)
                });
                this.isUploading = false;
                return response.data;
            } catch (error) {
                this.isUploading = false;
                this.hasUploadError = true;
                console.log(error);
            }
        },

        /*
         * What to send in the upload
         *
         * @returns { FormData }
         */
        getFormData () {
            const formData = new FormData();
            formData.append('upload', this.recordedData, this.newFilenameWithExtension);
            return formData;
        },

        /*
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

        /*
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
            setTimeout(() => { //put a slight delay so user sees the upload succesfull modal
                window.top.postMessage(JSON.stringify({ data: [data] }), location.origin);
                this.isProcessActive = false;
                this.hasUploaded = false;
            }, 1000);
        },

    }

}
</script>

<template>

    <div>
        
        <record-media-modal-menu
            @on-record-media-type-selected="onRecordMediaTypeSelected"
            :current-record-media-type="currentRecordMediaType"
            :is-process-active="isProcessActive"
        />

        <div class="ab-pane">
            
            <div class="record-media-container" ref="container" :style="{ height: targetHeight }">
            
                <record-media-modal-unset
                    v-if="currentRecordMediaType === recordMediaTypes.UNSET"
                    @on-record-media-type-selected="onRecordMediaTypeSelected"
                />

                <keep-alive><!-- to maintain recordings etc https://vuejs.org/v2/api/#keep-alive -->
                    <template v-for="recordMediaType in recordMediaTypeList">
                        <record-media
                            v-if="currentRecordMediaType === recordMediaType"
                            :record-media-type="recordMediaType"
                            :key="recordMediaType"
                            :is-keep-alive="true"
                            @on-device-ready="onDeviceReady"
                            @on-process-started="onProcessStarted"
                            @on-process-stopped="onProcessStopped"
                            @on-recorded-media-ready="onRecordedMediaReady"
                            @on-recorded-media-deleted="onRecordedMediaDeleted"
                        />
                    </template>
                </keep-alive>

                <over-media-modal v-if="isFilenameModalActive" :is-closing="isOverMediaModalClosing" :closed-call-back="onFilenameModalClosed" key="filenameModal">
                    <h3><em>Please add a filename prior to continuing.</em></h3>
                    <label for="filename" class="required" aria-required="true">Filename</label>
                    <input type="text" id="filename" name="filename" v-model="newFilename">
                    <div class="modal-actions">
                        <button @click.prevent="onSaveFilenameSelected" :disabled="newFilename.trim() === ''">Upload</button>
                        <button @click.prevent="onCloseFilenameModalSelected">Cancel</button>
                    </div>	
                </over-media-modal>
                <over-media-modal v-else-if="isUploading" key="isUploadingModal">
                    <div class="loader"></div>
                    <p><em>Uploading video, please wait...</em></p>
                </over-media-modal>
                <over-media-modal v-else-if="hasUploaded" key="hasUploadedModal">
                    <p><em>Media successfully uploaded.</em></p>
                </over-media-modal>	

            </div>	

        </div><!-- .ab-pane -->

        <div class="component-action" ref="actions">
            <section>
                <a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
                <transition name="fade">
                    <a v-if="showUploadButton" @click.prevent="onUploadSelected" role="button" class="button" title="Upload" href="#upload" key="upload">Upload</a>
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
    h2, h3 {
        max-width: none;
    }
}
.modal-actions {
	display: flex;
	button {
		margin: 0 2px;
	}
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
      opacity: 0;
}


</style>
