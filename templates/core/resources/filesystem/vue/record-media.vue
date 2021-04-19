<script>

/*
 * To record some media
 *
 */

import RecordVideo from './record-video';
import RecordAudio from './record-audio';
import mediaTypes from '../js/mediaTypes';

export default {
    
    components: {
        RecordVideo,
        RecordAudio
    },

    data () {
		return {
            // currentMediaType: '' //the currently selected media recording
            currentMediaType: mediaTypes.AUDIO //the currently selected media recording
        }
    },

    created () {
        this.initConsts();
    },

    methods: {

        /*
         * For consts to be available within the template
         * 
         */ 
        initConsts () {
            this.MEDIA_TYPE_AUDIO = mediaTypes.AUDIO;
            this.MEDIA_TYPE_VIDEO = mediaTypes.VIDEO;
        },

        /*
         * To toggle between the different media types
         * 
         * @params {String}
         * @returns
         */ 
        onMediaSelected (mediaType) {
            this.currentMediaType = mediaType;
        }

    }

}
</script>

<template>
    <div>
        <div class="menu">
            <button class="menu-item" @click="onMediaSelected(MEDIA_TYPE_AUDIO)">Audio only</button>
            <button class="menu-item" @click="onMediaSelected(MEDIA_TYPE_VIDEO)">Audio &amp; Video</button>
            <button class="menu-item" @click="onMediaSelected(MEDIA_TYPE_VIDEO)">Screen Capture and Audio</button>
        </div>
        <div class="current-media-container">
            <!-- <transition name="fade"> -->
                <record-audio v-if="currentMediaType === MEDIA_TYPE_AUDIO" />
                <record-video v-else-if="currentMediaType === MEDIA_TYPE_VIDEO" />
            <!-- </transition> -->
        </div>
    </div>
</template>

<style lang="scss" scoped>

.menu {
    position: relative;
    width: 100%;
    height: 100%;
    z-index: 1;
    display: flex;
    margin-bottom: 5px;
    // height: 400px;
}

.menu-item {
    width: calc(33.333333% - 2px);
    height: 100%;
    margin-right: 2px;
    &:last-of-type {
        margin-right: 0px;
    }
}

.current-media-container {
    width: calc(100% - 2px); //to allow for margin of buttons
}

.fade-enter-active, .fade-leave-active {
    transition: opacity .5s;
}
.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
    opacity: 0;
}

</style>