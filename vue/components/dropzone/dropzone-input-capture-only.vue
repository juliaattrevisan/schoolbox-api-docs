<script>

/*
 * To show an image that's beeen generated from videojs-record
 *
 */

import { gsap } from 'gsap/all';
import Dropzone from 'dropzone';
import AudioOnlyPlayer from '@schoolbox/videojs/audio-only-player';
import PhotoPlayer from '@schoolbox/videojs/photo-player';
import VideoPlayer from '@schoolbox/videojs/video-player';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import { getVideoPlayerOptionsWithBlob, getVideoPlayerOptionsWithSrc, getAudioPlayerOptionsWithBlob, getAudioPlayerOptionsWithSrc } from '@shared/videojs/videojsOptions';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';


export default {

    components: {
        OverMediaModal,
        AudioOnlyPlayer,
        PhotoPlayer,
        VideoPlayer
    },

    props: {
        recordMediaType: { //currently should either be AUDIO_ONLY, CAMERA or SCREEN
            type: String,
            required: true,
            validator: function (value) {
                return [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.PHOTO].indexOf(value) !== -1 // The value must match one of these strings
            }
        },
        preRecordedData: { //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },

    data () {
        return {
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasRecordPlayerError: false, //error with the record player
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
        }
    },

    computed: {

        /**
         * To know whether to show the delete recording button
         *
         * @returns { Boolean }
         */
        showDeleteRecordingButton () {
            if ((this.hasPreRecordedData || this.hasNewRecording) && !this.hasError) {
                return true;
            } else {
                return false;
            }
        },

    },

    created () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
	},

    mounted () {

        // const dropzoneOptions = getOptions();
        const dropzoneOptions = {};
        dropzoneOptions.url = '/file/post';

        const dropzone = new Dropzone(this.$refs.dropzone, dropzoneOptions);
        dropzone.on('addedfile', (file) => this.onFileAdded(file));

    },

    methods: {

        /**
         * Do we need to request the media device straight away? 
         * If not wait until it's requested - by deleting the pre recorded media recording
         * Otherwise we will just be displaying the existing pre recorded media
         *
         */
        checkForPreRecordedData () {
            if (this.preRecordedData) {
                this.hasPreRecordedData = true;
                this.playbackData = {
                    src: this.getPlaybackDataSrc(),
                    type: this.preRecordedData.mime
                } 
            }
        },

        /**
         * to know where to reference the saved file from
         *
         * @returns { String } 
         */
        getPlaybackDataSrc () {
            if (this.recordMediaType === recordMediaTypes.PHOTO) {
                return `/storage/image.php?hash=${this.preRecordedData.hash}`;
            } else {
                return `/cms/sendVideo.php?id=${this.preRecordedData.hash}&type=storage`;
            }
        },

        /**
         * get the correct options depending on the selected media type
         *
         * @returns { Object }
         */
        getDropzoneOptions () {
            if (this.recordMediaType === recordMediaTypes.PHOTO) {
                return {
                    uploadMultiple: false,
                    capture: 'camera',
                    acceptedFiles: 'image/*'
                }
            } else if (this.recordMediaType === recordMediaTypes.AUDIO_ONLY) {
                return {
                    uploadMultiple: false,
                    capture: 'microphone',
                    acceptedFiles: 'audio/*'
                }
            } else if (this.recordMediaType === recordMediaTypes.CAMERA) {
                return {
                    uploadMultiple: false,
                    capture: 'camcorder',
                    acceptedFiles: 'video/*'
                }
            }
        },

        /**
         * get the correct options depending on the selected media type
         *
         * @returns { Blob }
         */
        onFileAdded (mediaData) {
            this.recordedData = mediaData; // the blob object contains the recorded data 
			this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * to be able to record another media
         *
         */
        onDeleteRecordingSelected () {
            this.isDeleteRecordingModalActive = true;
        },

        /**
         * confirm deletion of the latest recording, setting up for another recording
         *
         */
        onConfirmDeleteRecordingSelected () {
            this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
            this.isDeleteRecordingModalActive = false;
            this.hasPreRecordedData = false;  //set to false again for possible display or a re-recording
            this.hasNewRecording = false;
        },

        /**
         * close the delete recording modal
         *
         */
        onCancelDeleteRecordingSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * closed the delete recording modal, cancel has been selected rather than confirm
         *
         */
        onDeleteRecordingModalClosed () {
            this.isOverMediaModalClosing = false;
            this.isDeleteRecordingModalActive = false;
        },

        /**
         * Pass through the audio options to play the latest recorded video
         *
         * @returns { Object }
         */
        getAudioPlayerOptions () {
            if (this.hasPreRecordedData) {
                return getAudioPlayerOptionsWithSrc(this.playbackData);
            } else {
                return getAudioPlayerOptionsWithBlob(this.recordedData);
            }
        },

        /**
         * Pass through the audio options to play the latest recorded video
         *
         * @returns { String }
         */
        getImgSrc () {
            if (this.hasPreRecordedData) { //otherwise its the already saved image src
                return this.playbackData.src;
            } else { //is already a Data URL that can be passed to <image-player></image-player>
                return this.recordedData; 
            }
        },

        /**
         * Pass through the video options to play the latest recorded video
         *
         * @returns { Object }
         */
        getVideoPlayerOptions () {
            if (this.hasPreRecordedData) {
                return getVideoPlayerOptionsWithSrc(this.playbackData);
            } else {
                return getVideoPlayerOptionsWithBlob(this.recordedData);
            }
        },

        /**
         * Just pass through to the parent so they can ensure the container's height is kept up to date
         *
         * @params { HTMLVideoElement }
         */
        onRecordPlayerLoaded (htmlVideoElement) {
            this.$emit(recordMediaEvents.RATIO_READY, htmlVideoElement);
        },

        /**
         * A record player has got an error
         *
         */
        onRecordPlayerError () {
            this.hasRecordPlayerError = true;
            this.hasError = true;
        },

        /**
         * it's closed now, so disable the errors to allow trying again
         *
         */
        onErrorModalClosed () {
            this.hasError = false;
            this.hasRecordPlayerError = false;
        },

    }

}
</script>

<template>

    <div class="container">

        <div class="media-container media-container-record">
            <div class="dzone icon-attach" ref="dropzone">
                <span>Click or drag here to attach a feature image</span>
            </div>
        </div>
        <div v-if="hasPreRecordedData || hasNewRecording" class="media-container media-container-playback" key="playbackMediaContainer">
            <audio-only-player
                v-if="recordMediaType === recordMediaTypes.AUDIO_ONLY"
                :options="getAudioPlayerOptions()"
            />
            <photo-player
                v-else-if="recordMediaType === recordMediaTypes.PHOTO"
                :img-src="getImgSrc()"
            />
            <video-player
                v-else
                :options="getVideoPlayerOptions()"
                @on-video-data-loaded="onRecordPlayerLoaded"
                @on-video-error="onRecordPlayerError"
            />
        </div>
        <transition name="slide">
            <a v-if="showDeleteRecordingButton" @click.prevent="onDeleteRecordingSelected" class="button icon-delete delete-button" title="Delete recording"></a>
        </transition>

        <over-media-modal v-if="isDeleteRecordingModalActive" :is-closing="isOverMediaModalClosing" :closed-call-back="onDeleteRecordingModalClosed" key="deleteRecordingModal">
            <p>Are you sure you wish to delete the current recording?</p>
            <div class="modal-actions">
                <button @click.prevent="onConfirmDeleteRecordingSelected">Confirm</button>
                <button @click.prevent="onCancelDeleteRecordingSelected">Cancel</button>
            </div>
        </over-media-modal>
        <over-media-modal v-else-if="hasRecordPlayerError" :is-closing="isOverMediaModalClosing" :closed-call-back="onErrorModalClosed" key="errorModal">
            <div class="alert-box alert">Sorry, the recorded media cannot be accessed.</div>
        </over-media-modal>

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
        z-index: 1;
    }

    .media-container-playback {
        z-index: 2;
    }

.dzone {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.delete-button {
    position: absolute;
    z-index: 3;
    right: 10px;
    top: 10px;
    width: 42px;
    height: 44px;
    background: none;
    color: white;
    border: 2px solid white;
    background: rgba(43, 51, 63, 0.7);
    transition: 0.2s opacity;
    &::before {
        text-align: center;
        font-size: 1.4rem;
    }
    &:hover {
        opacity: 0.8;
    }
}

.modal-actions {
    display: flex;
    button {
        margin: 0px 2px; 
    }
}

.slide-enter-active {
    transition: right 0.4s ease-out;
}
.slide-leave-active {
  transition: right 0.2s ease-in;
}
.slide-enter-active, 
.slide-leave-to {
  right: -50px; 
}
.slide-enter-to {
  right: 10px; 
}

</style>
