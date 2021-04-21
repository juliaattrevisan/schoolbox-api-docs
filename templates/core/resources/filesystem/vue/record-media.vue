<script>

/*
 * To record some media
 *
 */

import RecordMediaMenu from './record-media-menu';
import RecordMediaUnset from './record-media-unset';
import RecordVideo from './record-video';
import RecordAudioOnly from './record-audio-only';
import mediaTypes from '../js/mediaTypes';


export default {
	
	components: {
		RecordMediaMenu,
		RecordMediaUnset,
		RecordVideo,
		RecordAudioOnly
	},

	data () {
		return {
			currentMediaType: mediaTypes.UNSET, //the currently selected media recording
			isProcessActive: false //to be able to disable certain behavour when recordings/conversions/uploads are taking place
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
		onProcessStopped () {
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
			<keep-alive><!-- to maintain recordings etc https://vuejs.org/v2/api/#keep-alive -->
				<record-audio-only 
					v-if="currentMediaType === mediaTypes.AUDIO_ONLY" 
					@on-process-started="onProcessStarted"
					@on-process-stopped="onProcessStopped" 
				/>
				<record-video 
					v-else-if="currentMediaType === mediaTypes.CAMERA"
					:media-type="mediaTypes.CAMERA"
					key="camera"
					@on-process-started="onProcessStarted"
					@on-process-stopped="onProcessStopped" 
				/>
				<record-video 
					v-else-if="currentMediaType === mediaTypes.SCREEN"
					:media-type="mediaTypes.SCREEN"
					key="screen"
					@on-process-started="onProcessStarted"
					@on-process-stopped="onProcessStopped" 
				/>
			</keep-alive>    
		</div>
	</div>
</template>

<style lang="scss" scoped>

</style>