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
		 * don't apply these customisations to list views
		 * 
		 * @returns { Boolean }
		 */
		isEventInListComponent () {
			return this.$el.parentElement.className.includes('list');
		},

		/**
		 * don't apply these customisations to list views
		 * 
		 * @returns { String }
		 */
		eventBackgroundColor () {
			return this.$el.parentElement.parentElement.style.backgroundColor;
		},

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
		this.checkBackgroundColor();
	},

	methods: {

		/**
		 * Ensure the text colour is readable on the background, which uses the user assigned folder colour
		 */
		updateTextColor () {
			if (!this.isEventInListComponent) { //don't do for list view, only within month/week views
				const readableTextColor = tinycolor.mostReadable(this.eventBackgroundColor, ['black', 'white']).toRgbString();
				if (this.content.timeText !== '') { //not always available
					this.$refs.timeText.style.color = readableTextColor;
				}
				this.$refs.titleText.style.color = readableTextColor;
			}
		},

		/**
		 * If it's white, add a border
		 */
		checkBackgroundColor () {
			if (!this.isEventInListComponent) { //don't do for list view, only within month/week views
				if (this.eventBackgroundColor === 'rgb(255, 255, 255)') {
					this.$refs.eventContainer.style.border = '1px solid black';
				}
			}
		},


	}

}
</script>

<template>
	<div class="event-container" ref="eventContainer">
		<b v-if="content.timeText" ref="timeText">{{ content.timeText }}</b>
		<span ref="titleText" v-html="lineBreakedTitleText"></span>
	</div>
</template>

<style lang="scss" scoped>

.event-container {
	padding: 3px;
}

</style>