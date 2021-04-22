<script>

/**
 * To customise the display of the event, ensuring the background/text colour is readable
 *
 */

import tinycolor from 'tinycolor2';

export default {
	
	props: {
		content: {
			type: Object,
			required: true
		}
	},

	computed: {
		
		/**
		 * Ensure the new lines are respected
		 */
		lineBreakedTitleText () {
			const eventTitle = this.content.event.title;
			return eventTitle.replace('\n', '<br>');
		}

	},

	mounted () {
		this.updateTextColor();
	},

	methods: {

		/**
		 * Ensure the text colour is readable on the background, which uses the user assigned folder colour
		 */
		updateTextColor () {
			if (!this.$el.parentElement.className.includes('list')) { //don't do for list view, only within calendars
				this.bkgColour = this.$el.parentElement.parentElement.style.backgroundColor;
				const readableTextColor = tinycolor.mostReadable(this.bkgColour, ['black', 'white']).toRgbString();
				if (this.content.timeText !== '') { //not always available
					this.$refs.timeText.style.color = readableTextColor;
				}
				this.$refs.titleText.style.color = readableTextColor;
			}
		},

	}

}
</script>

<template>
	<div>
		<b v-if="content.timeText" ref="timeText">{{ content.timeText }}</b>
		<i ref="titleText" v-html="lineBreakedTitleText"></i>
	</div>
</template>