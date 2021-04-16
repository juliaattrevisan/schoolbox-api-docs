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
            currentMediaType: '' //the currently selected media recording
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
            this.MEDIA_TYPE_VIDEO = mediaTypes.VIDEO;
            this.MEDIA_TYPE_AUDIO = mediaTypes.AUDIO;
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
        <div>
            <button @click="onMediaSelected(MEDIA_TYPE_VIDEO)">Video</button>
            <button @click="onMediaSelected(MEDIA_TYPE_AUDIO)">Audio</button>
        </div>
        <div>
            <transition name="fade">
                <record-video v-if="currentMediaType === MEDIA_TYPE_VIDEO" />
                <record-audio v-else-if="currentMediaType === MEDIA_TYPE_AUDIO" />
            </transition>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.fade-enter-active, .fade-leave-active {
    transition: opacity .5s;
}
.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
    opacity: 0;
}

</style>