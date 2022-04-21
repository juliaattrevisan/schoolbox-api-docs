<script>

/*
 * The initial menu, given maximum presence
 *
 */

import { gsap } from 'gsap/all';
import recordMediaTypes from '@js/record-media/recordMediaTypes';
import { hasDeviceCapture, supportsVideoRecording, supportsAudioRecording } from '@js/util/featureDetectionUtils.js';


export default {

    data () {
        return {
            hasChecked: false, 
            isVideoSupported: false, //Don't show the record video if no video input is supported, hard to imagine a scenarios where video is available but not microphone?
            isAudioSupported: false, //Don't show the record audio only or screen sharing if no audio input is supported, Screen sharing should not be used when audio input is not available
            hasDeviceCapture: false //If true, it's assumed this is a mobile device that won't currently support screen sharing
        }
    },

    mounted () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.hasDeviceCapture = hasDeviceCapture();
        this.displayAfterDeviceStatusCheck();
    },

    methods: {

        /*
         * Ensure the device has been checked before we display the buttons
         *
         */
        async displayAfterDeviceStatusCheck () {
            await this.checkDeviceStatus();
            this.hasChecked = true;
            this.$nextTick(() => {
                this.updateContainerHeight();
                this.animateOn();
            });
        },

        /*
         * To display only the approriate buttons based on the devices record media features
         *
         */
        async checkDeviceStatus () {
            const deviceList = await navigator.mediaDevices.enumerateDevices(); //use the device list to check for video and audio input
            if (supportsVideoRecording(deviceList) || this.hasDeviceCapture) {
                this.isVideoSupported = true;
            } 
            if (supportsAudioRecording(deviceList)) {
                this.isAudioSupported = true;
            }
        },

        /*
         * To set up appropriate initial height on container, keeping the same size as a 4/3 video element
         *
         */
        updateContainerHeight () {
            const targetHeight = this.$refs.pseudoVideo.getBoundingClientRect().width * 0.5625; //so there's no height jumps on moving to selected record method
            this.$refs.pseudoVideo.style.height = targetHeight + 'px';
        },

        /** 
         * to show selection options
         *
         */
        animateOn () {
            const allMenuItems = Array.from(this.$refs.pseudoVideo.children);
            allMenuItems.forEach((element, index) => {
                gsap.from(element, 0.6, {
                    scale: 0.6,
                    alpha: 0.0,
                    ease: 'power2.out',
                    delay: index * 0.1
                } );
                let titleContainer = element.querySelector('div');
                gsap.from(titleContainer, 0.4, {
                    marginTop: 50,
                    alpha: 0,
                    ease: 'power2.out',
                    delay: (index * 0.1) + 0.2,
                    onComplete: () => {
                        if (index === (allMenuItems.length-1)) { //enable once everything's all animated
                            this.initMouseEvents();
                        }
                    }
                } );
            });
        },

        /** 
         * to animate mouse enter / out properly
         *
         */
        initMouseEvents () {
            const allMenuItems = Array.from(this.$refs.pseudoVideo.children);
            allMenuItems.forEach((element) => {
                element.addEventListener('mouseenter', () => {
                    gsap.to(element, 0.1, {
                        backgroundColor: 'white',
                        ease: 'power2.out',
                        overwrite: true //to prevent quick action mouse enter / outs from breaking animations
                    } );
                    gsap.to(element.querySelector('span'), 0.2, {
                        paddingBottom: 20,
                        ease: 'power2.out',
                        overwrite: true
                    } );
                    gsap.to(element.querySelector('aside'), 0.3, {
                        opacity: 1,
                        ease: 'power2.out',
                        overwrite: true
                    } );
                });
                element.addEventListener('mouseleave', () => {
                    gsap.to(element, 0.1, {
                        backgroundColor: '#FAFAFA',
                        ease: 'power2.out',
                        overwrite: true
                    } );
                    gsap.to(element.querySelector('span'), 0.2, {
                        paddingBottom: 0,
                        ease: 'power2.in',
                        overwrite: true
                    } );
                    gsap.to(element.querySelector('aside'), 0.2, {
                        opacity: 0,
                        ease: 'power2.in',
                        overwrite: true
                    } );
                });
            });
        },

        /**
         * selected, so set and animate out
         *
         * @params {String}
         */
        recordMediaTypeSelected (selectedRecordMediaType) {
            this.selectedRecordMediaType = selectedRecordMediaType;
            this.animateOutMenu();
        },

        /** 
         * fade out revealing psuedo video element in the background
         *
         */
        animateOutMenu () {
            const allMenuItems = Array.from(this.$refs.pseudoVideo.children);
            allMenuItems.forEach((element, index) => {
                element.style.border = 'none';
                element.disabled = true; //prevent multiple clicks
                let titleContainer = element.querySelector('div');
                gsap.to(titleContainer, 0.2, {
                    alpha: 0,
                    marginBottom: 50,
                    ease: 'power2.in',
                    delay: index * 0.1
                } );
                gsap.to(element, 0.3, {
                    scale: 0.9,
                    alpha: 0,
                    ease: 'power2.in',
                    delay: (index * 0.1) + 0.3,
                } );
            });
            gsap.to(this.$refs.pseudoVideo, 0.3, {
                backgroundColor: '#000',
                delay: 0.5,
                ease: 'power2.in',
                onComplete: () => this.$emit('on-record-media-type-selected', this.selectedRecordMediaType)
            } );
        },

    }

}

</script>

<template>
    <div v-if="hasChecked" class="pseudo-video" ref="pseudoVideo">
        <button v-if="isAudioSupported" class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.AUDIO_ONLY)">
            <div>
                <div class='icon-mic'></div>
                <span>Audio</span>
                <aside>Say something with an audio only recording.</aside>
            </div>
        </button>
        <button v-if="isVideoSupported" class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.PHOTO)">
            <div>
                <div class='icon-photo'></div>
                <span>Photo</span>
                <aside>Use a computer camera to take a photo.</aside>
            </div>
        </button>
        <button v-if="isVideoSupported" class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.CAMERA)">
            <div>
                <div class='icon-video'></div>
                <span>Video</span>
                <aside>Use a computer camera to create a video recording.</aside>
            </div>
        </button>
        <button v-if="!hasDeviceCapture" class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.SCREEN)">
            <div>
                <div class='icon-screen-capture'></div>
                <span>Screen</span>
                <aside>Create a computer screen recording.</aside>
            </div>
        </button>
    </div>
</template>

<style lang="scss" scoped>

@import '@css/foundation/utils/variables';

$font-family-icon: 'schoolbox';

.pseudo-video {
    position: absolute;
    width: 100%;
    height: 100%; 
    display: flex;
    background: white;
    overflow: hidden;
}

.menu-item {
    flex-grow: 1;
    flex-basis: 0;
    border: 1px solid #EDEDED;
    margin-right: 1rem;
    margin-top: 1rem;
    padding: 20px;
    background: #FAFAFA;
    &:last-child {
        margin-right: 0;
    }
    span {
        display: block;
    }
    aside {
        font-size: .8125rem;
        font-style:  italic;
        opacity: 0;
    }
}

.icon {
    font-family: $font-family-icon;
    &:before {
    color: $content-ui-foreground;
    font-size: 3rem;
        padding: 0;
    }
}

.icon-mic {
    @extend .icon;
    &:before {
        content: '\e641';
    }
}

.icon-photo {
    @extend .icon;
    &:before {
        content: '\e65c';
    }
}

.icon-video {
    @extend .icon;
    &:before {
        content: '\e674';
    }
}

.icon-screen-capture {
    @extend .icon;
    &:before {
        content: '\e920';
    }
}

@media screen and (max-width: 950px) {
    .menu-item {
        padding: 0px;
    }
    .menu-item span {
        font-size: .875rem;
    }
    .icon:before {
        font-size: 2rem;
    }
}

@media screen and (max-width: 640px) {
    .menu-item {
        margin-right: 0.25rem;
        margin-top: 0.25rem;
        .icon:before {
            font-size: 1.5rem;
        }
        aside {
            display: none;
        }
    }
}

</style>
