<script>

/*
 * The top menu, to toggle between recording types
 *
 */

import { gsap } from 'gsap/all';
import mediaTypes from '../js/mediaTypes';

export default {
	
	props: {
		currentMediaType: { //to be able to respond to the initial change from UNSET
	 		type: String,
	  		required: true,
	  		default: ''
		},
		isProcessActive: { //to be able to disable buttons when something heavy is going on
	 		type: Boolean,
	  		required: true,
	  		default: false
		},
	},

	watch: {

		currentMediaType (value) { 
			if (value !== mediaTypes.UNSET)  { //show the top menu now
				this.animateOn();
			}      
		}

	},

	created () {
		this.mediaTypes = mediaTypes; //to be able to move between media types
	},

	mounted () {
		this.setInitialPosition();
	},

	methods: {

		/*
		 * to animate on the menu when an item within #record-media-unset is selected
		 * 
		 */ 
		setInitialPosition () {
			const allMenuItems = Array.from(this.$refs.menu.children);
			allMenuItems.forEach((element) => {
				gsap.set(element, { top: 44 } );
			});
		},

		/*
		 * to show selection options
		 * 
		 */ 
		animateOn () {
			const allMenuItems = Array.from(this.$refs.menu.children);
			allMenuItems.forEach((element, index) => {
				gsap.to(element, 0.5, { 
					top: 0, 
					ease: 'power2.inOut', 
					delay: index * 0.1 
				} );
			});
		},

		/**
		 * selected, so set and animate out
		 *
		 * @params {String}
		 */
		mediaTypeSelected (selectedMediaType) {
			this.$emit('on-media-type-selected', selectedMediaType);
		},

	}

}
</script>

<template>
	<dl class="tabs menu" ref="menu">
		<dd class="menu-item" :class="[currentMediaType === mediaTypes.AUDIO_ONLY ? 'active' : '']">
			<a @click.prevent="mediaTypeSelected(mediaTypes.AUDIO_ONLY)" href="#record-audio" title="Record audio" :disabled="isProcessActive">Record audio</a>
		</dd>
		<dd class="menu-item" :class="[currentMediaType === mediaTypes.CAMERA ? 'active' : '']">
			<a @click.prevent="mediaTypeSelected(mediaTypes.CAMERA)" href="#record-a-video" title="Record a video" :disabled="isProcessActive">Record a video</a>
		</dd>
		<dd class="menu-item" :class="[currentMediaType === mediaTypes.SCREEN ? 'active' : '']">
			<a @click.prevent="mediaTypeSelected(mediaTypes.SCREEN)" href="#record-your-screen" title="Record your screen" :disabled="isProcessActive">Record your screen</a>
		</dd>
	</dl>
</template>

<style lang="scss" scoped>

.menu {
	display: flex;
	overflow: hidden;
}

.menu-item {
	width: calc(33.333333%);
	text-align: center;
	&:first-of-type {
		border-left: none;
	}
	&:last-of-type {
		border-right: none;
	}
}

</style>