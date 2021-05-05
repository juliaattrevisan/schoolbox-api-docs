<script>

/*
 * The top menu, to toggle between recording types
 *
 */

import { gsap } from 'gsap/all';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';

export default {

	props: {
		currentRecordMediaType: { //to be able to respond to the initial change from UNSET
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

		currentRecordMediaType (value) {
			if (value !== recordMediaTypes.UNSET)  { //show the top menu now
				this.animateOn();
			}
		}

	},

	created () {
		this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
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
			const toHideHeight = this.$refs.menu.offsetHeight; //to hide the menu items underneath
			allMenuItems.forEach((element) => {
				gsap.set(element, { top: toHideHeight } );
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
		recordMediaTypeSelected (selectedRecordMediaType) {
			this.$emit('on-record-media-type-selected', selectedRecordMediaType);
		},

	}

}
</script>

<template>
	<dl class="tabs menu" ref="menu">
		<dd class="menu-item" :class="[currentRecordMediaType === recordMediaTypes.AUDIO_ONLY ? 'active' : '']">
			<a @click.prevent="recordMediaTypeSelected(recordMediaTypes.AUDIO_ONLY)" href="#record-audio" title="Record audio" :disabled="isProcessActive">Record audio</a>
		</dd>
		<dd class="menu-item" :class="[currentRecordMediaType === recordMediaTypes.CAMERA ? 'active' : '']">
			<a @click.prevent="recordMediaTypeSelected(recordMediaTypes.CAMERA)" href="#record-a-video" title="Record a video" :disabled="isProcessActive">Record a video</a>
		</dd>
		<dd class="menu-item" :class="[currentRecordMediaType === recordMediaTypes.SCREEN ? 'active' : '']">
			<a @click.prevent="recordMediaTypeSelected(recordMediaTypes.SCREEN)" href="#record-your-screen" title="Record your screen" :disabled="isProcessActive">Record your screen</a>
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
}

@media screen and (max-width: 950px) {
  .tabs dd a {
    padding: .5rem;
    font-size: .8125rem;
  }
}

</style>
