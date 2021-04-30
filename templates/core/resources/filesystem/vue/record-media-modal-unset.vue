<script>

/*
 * The initial menu, given maximum presence
 *
 */

import { gsap } from 'gsap/all';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';


export default {

	created () {
		this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
	},

	mounted () {
		this.updateContainerHeight();
		this.animateOn();
	},

	methods: {

		/*
		 * To set up appropriate initial height on container, keeping the same size as a 4/3 video element
		 *
		 */
		updateContainerHeight () {
			const targetHeight = this.$refs.pseudoVideo.getBoundingClientRect().width * 0.5625; //so there's no height jumps on moving to selected record method
			this.$refs.pseudoVideo.style.height = targetHeight + 'px';
		},

		/*
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
				} );
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

		/*
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
	<div class="pseudo-video" ref="pseudoVideo">
		<button class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.AUDIO_ONLY)">
			<div>
				<span class='icon-mic'></span>
				<span>Record audio</span>
				<aside>Say something with an audio only recording.</aside>
			</div>
		</button>
		<button class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.CAMERA)">
			<div>
				<div class='icon-video'></div>
				<span>Record a video</span>
				<aside>Use a computer camera to create a video recording.</aside>
			</div>
		</button>
		<button class="menu-item" @click="recordMediaTypeSelected(recordMediaTypes.SCREEN)">
			<div>
				<div class='icon-screen-capture'></div>
				<span>Record your screen</span>
				<aside>Choose a computer screen to create a screen recording.</aside>
			</div>
		</button>
	</div>
</template>

<style lang="scss" scoped>

@import '@css/utils/variables';

$font-family-icon: 'schoolbox';

.pseudo-video {
	width: 100%;
	height: 300px; //will be updated at mounted()
	display: flex;
	background: white;
	overflow: hidden;
}

.menu-item {
	background: #FAFAFA;
	border: 1px solid #EDEDED;
	width: calc(33.4%);
	margin-right: 1rem;
	margin-top: 1rem;
	&:last-child {
		margin-right: 0;
	}
	span {
		display: block;
		transition: padding-bottom 0.2s;
	}
	aside {
		padding: 10px 30px;
		font-size: 0.8rem;
		font-style:  italic;
		opacity: 0;
		transition: opacity 0.2s;
	}
	&:hover {
		background: white;
		span {
			padding-bottom: 20px;
		}
		aside {
			opacity: 1;
		}
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

@media screen and (max-width: 640px) {
}

</style>
