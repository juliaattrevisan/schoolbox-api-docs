<script>

/**
 * To record a learning moment photo, audio, or video
 *
 */

import RecordMedia from '@schoolbox/videojs-record/record-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import axios from 'axios';


export default {

    components: {
        RecordMedia, //the media element
        OverMediaModal //to slot in upload modal
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
            canUpload: false, //to upload the file
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            isFilenameModalActive: false, //prompt the filename change modal
            newFilename: '', //name the file
            isUploading: false, //to notify of update
            hasUploadError: false, //to notify of upload error, try again?
            hasUploaded: false, //notify UI of upload complete
            targetHeight: '0px', //to set the height based on the aspect ratio of the video
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
         *
         */
        newFilename (newValue) {
            this.newFilename = newValue.replace(/[\\/:"*?<>|]+/g, "");
        }

    },

    created () {
        this.recordMediaType = this.getMomentTypeToRecordMediaType();
    },

    mounted () {
        this.initKeyboardEvents();
        this.updateContainerHeight();
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
        updateContainerHeight () {
            this.targetHeight = (this.$refs.container.getBoundingClientRect().width * 0.5625) + 'px'; //so there's no height jumps on switching videos
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
        onRecordedMediaDeleted () {
            this.canUpload = false; //hide the upload button
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

        /**
         * present the save file name modal to name the file
         *
         */
        onUploadSelected () {
            this.isFilenameModalActive = true;
        },

        /**
         * the user has set a file name and selected save
         *
         */
        async onSaveFilenameSelected () {
            this.isFilenameModalActive = false; //close the save modal to show the upload modal
            try {
                const responseData = await this.uploadMediaAndReturnResponse();
                this.addHiddenInputs(responseData);
                this.displayUploadSuccessful();
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
            document.getElementById('evidence-filename').value = this.newFilenameWithExtension;
            document.getElementById('file').value = `${responseData.hash}:${this.newFilenameWithExtension}`;
        },

        /**
         * To show the upload successful note
         *
         */
        displayUploadSuccessful () {
            this.hasUploaded = true; //to display
            this.canUpload = false; //hide the upload button
            setTimeout(() => { 
                this.hasUploaded = false; //and hide again
            }, 2000);
        },

        /**
         * close the filename modal
         *
         */
        onCloseFilenameModalSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * close the filename modal, cancel has been selected rather than save
         *
         */
        onFilenameModalClosed () {
            this.isOverMediaModalClosing = false;
            this.isFilenameModalActive = false;
        }

    }

}
</script>

<template>

    <div>
            
        <div class="record-media-container" ref="container" :style="{ height: targetHeight }">

            <record-media
                :record-media-type="recordMediaType"
                :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
                @on-recorded-media-ready="onRecordedMediaReady"
                @on-recorded-media-deleted="onRecordedMediaDeleted"
            />

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

        <div class="upload-button-container">
            <transition name="slide">
                <a v-if="showUploadButton" @click.prevent="onUploadSelected" role="button" class="button upload-button" title="Upload" href="#upload" key="upload">Upload</a>
            </transition>
        </div>	

    </div>

</template>

<style lang="scss" scoped>

.record-media-container {
    position: relative;
    width: 100%;
    max-width: 800px;
    height: 300px; //updated on mounted()
    overflow: hidden;
    background: black;
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

.upload-button-container {
    position: relative;
    width: 100%;
    max-width: 800px;
    height: 50px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.upload-button {
    position: absolute;
    width: 100%;
}

.slide-enter-active {
    transition: transform 0.3s ease-out;
}
.slide-leave-active {
    transition: transform 0.3s ease-in;
}
.slide-enter-active {
    transform: translateY(-100%);
}
.slide-enter-to {
    transform: translateY(0%);
}
.slide-leave-to {
    transform: translateY(-100%);
}

</style>
