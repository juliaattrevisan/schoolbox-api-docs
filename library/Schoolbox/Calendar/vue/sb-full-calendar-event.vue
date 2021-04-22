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
				this.$refs.titleText.style.color = readableTextColor;
			}
		}

	}

}
</script>

<template>
	<div>
		<b>{{ content.timeText }}</b>
        <i ref="titleText">{{ content.event.title }}</i>
	</div>
</template>