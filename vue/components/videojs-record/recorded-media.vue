<script>

/**
 * A previously or just recorded media made through either #record-media-modal or #evidence-media-recording
 *
 */

import { gsap } from 'gsap/all';
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
                return [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.SCREEN, recordMediaTypes.PHOTO].indexOf(value) !== -1 // The value must match one of these strings
            }
        },
        hasPreRecordedData: { //if not, it must be a new recording
            type: Boolean, 
            required: true,
            default: false
        },
        preRecordedData: { //to display an already existing recording on startup
            type: Object,
            required: false,
        },
        recordedData: { //new recording
            type: Blob,
            required: false,
        }
    },

    data () {
        return {
            player: null, //videojs-record instance
            isDeviceReady: false, //to toggle the device display
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasRecordPlayerError: false, //error with the record player
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
            mediaWidth: 0, //to restore to device dimensions if expanding/contracting on over-media-modal changes
            mediaHeight: 0, //to restore to device dimensions if expanding/contracting on over-media-modal changes 
        }
    },

	created () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
	},

    mounted () {
        gsap.from(this.$refs.deleteButton, 0.5, { right: '-50px', delay: 0.5 } ); //move it across to highlight
	},

    methods: {

        /**
         * Either display a previously recorded media, or something just recorded
         *
         */
        checkForPreRecordedData () {
            if (this.preRecordedData) {
                this.playbackData = {
                    src: this.getPlaybackDataSrc(),
                    type: this.getPlaybackDataType(),
                    hash: this.preRecordedData.hash
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
         * to know what the media type is
         *
         * @returns { String } 
         */
        getPlaybackDataType () {
            if (this.recordMediaType === recordMediaTypes.AUDIO_ONLY) {
                return 'audio/mpeg';
            } else {
                return 'video/mp4';
            }
        },

        /**
         * close the error modal, animating out
         *
         */
        onCloseErrorModalSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * it's closed now, so disable the errors to allow trying again
         *
         */
        onErrorModalClosed () {
            this.hasError = false;
            this.isOverMediaModalClosing = false;
            this.hasRecordPlayerError = false;
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
            this.$emit(recordMediaEvents.RESIZE, this.mediaWidth, this.mediaHeight);
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
         * Pass through the image src to display the photo
         *
         * @returns { String }
         */
        getImgSrc () {
            if (this.hasPreRecordedData) { //otherwise it's the already saved image src
                return this.playbackData.src;
            } else { 
                return this.recordedData.src; //was put in by 
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
         * @params { Number }
         * @params { Number }
         */
        onMediaDimensionsReady (width, height) {
            this.mediaWidth = width;
            this.mediaHeight = height;
            this.$emit(recordMediaEvents.RESIZE, width, height);
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
         * Ensure the recorded video ratio is respected
         *
         * @params { Number }
         * @params { Number }
         */
        onResize (width, height) {
            this.$emit(recordMediaEvents.RESIZE, width, height);
        },

    }

}
</script>

<template>
    
    <div>

        <audio-only-player
            v-if="recordMediaType === recordMediaTypes.AUDIO_ONLY"
            :options="getAudioPlayerOptions()"
            @on-dimensions-ready="onMediaDimensionsReady"
        />
        <photo-player
            v-else-if="recordMediaType === recordMediaTypes.PHOTO"
            :img-src="getImgSrc()"
            :is-new-photo="hasPreRecordedData ? false : true"
            @on-dimensions-ready="onMediaDimensionsReady"
        />
        <video-player
            v-else
            :options="getVideoPlayerOptions()"
            @on-dimensions-ready="onMediaDimensionsReady"
            @on-video-error="onRecordPlayerError"
        />

        <transition name="slide">
            <a ref="deleteButton" v-if="!hasError" @click.prevent="onDeleteRecordingSelected" class="button icon-delete delete-button" title="Delete recording"></a>
        </transition>

        <over-media-modal v-if="isDeleteRecordingModalActive" @on-resize="onResize" :is-closing="isOverMediaModalClosing" :closed-call-back="onDeleteRecordingModalClosed" key="deleteRecordingModal">
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
    @media screen and (max-width: 640px) {
        right: 5px; 
        top: 5px;
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
