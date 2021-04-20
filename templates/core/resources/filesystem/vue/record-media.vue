<script>

/*
 * To record some media
 *
 */

import RecordMediaMenu from './record-media-menu';
import RecordMediaUnset from './record-media-unset';
import RecordVideo from './record-video';
import RecordAudio from './record-audio';
import mediaTypes from '../js/mediaTypes';


export default {
    
    components: {
        RecordMediaMenu,
        RecordMediaUnset,
        RecordVideo,
        RecordAudio
    },

    data () {
		return {
            currentMediaType: mediaTypes.UNSET, //the currently selected media recording
            // currentMediaType: mediaTypes.VIDEO //the currently selected media recording
            isProcessActive: false //to be able to disable certain behavour when conversions/uploads are taking place
        }
    },

    created () {
        this.mediaTypes = mediaTypes; //to be able to move between media types
    },

    methods: {

        /**
		 * To toggle between the different media types
		 *
         * @params {String}
		 */
		onMediaTypeSelected (mediaType) {
            this.currentMediaType = mediaType;
		},

        /**
		 * to be able to disable certain behavour when conversions/uploads are taking place
		 *
		 */
		onProcessStarted () {
            this.isProcessActive = true;
		},

        /**
		 * to be able to disable certain behavour when conversions/uploads are taking place
		 *
		 */
		onProcessEnded () {
            this.isProcessActive = false;
		}

    }

}
</script>

<template>
    <div>
        <record-media-menu 
            @on-media-type-selected="onMediaTypeSelected" 
            :current-media-type="currentMediaType" 
            :is-process-active="isProcessActive" 
        />
        <div>
            <record-media-unset 
                v-if="currentMediaType === mediaTypes.UNSET" 
                @on-media-type-selected="onMediaTypeSelected" 
            />
            <record-audio 
                v-else-if="currentMediaType === mediaTypes.AUDIO" 
                @on-process-started="onProcessStarted"
                @on-process-ended="onProcessEnded" 
            />
            <record-video 
                v-else-if="currentMediaType === mediaTypes.VIDEO"
                @on-process-started="onProcessStarted"
                @on-process-ended="onProcessEnded" 
            />
        </div>
    </div>
</template>

<style lang="scss" scoped>

</style>