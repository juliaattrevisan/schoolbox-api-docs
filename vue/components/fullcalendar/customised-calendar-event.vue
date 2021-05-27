<script>

/**
 * To customise the display of the event, ensuring the background/text colour is readable
 *
 */

import tinycolor from 'tinycolor2';
import { EVENT_VARIANTS } from '@schoolboxCalendarEvent/event-variants';
import { ATTENDANCE_STATUS } from '@schoolboxCalendarEvent/attendance-status';

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
        },

        /**
         * to display a checkbox to show complete/incomplete
         * 
         * @returns { Boolean }
         */
        isTask () {
            if (this.content.event?.extendedProps?.data?.meta?.variant === EVENT_VARIANTS.TASK) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * to display the status of the task checkbox and add a strike through the title if completed
         * 
         * @returns { Boolean }
         */
        isTaskComplete () {
            if (this.content.event?.extendedProps?.data?.meta?.completed) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * to update the display of the event depending on whether the event has attendance
         * and if so the status of that attendance
         * 
         * @returns { String }
         */
        getAttendanceClass () {
            const attendanceStatus = this.content.event?.extendedProps?.data?.attendance?.status?.current;
            if (attendanceStatus === ATTENDANCE_STATUS.DECLINED) {
                return 'declined';
            } else if (attendanceStatus === ATTENDANCE_STATUS.PENDING) {
                return 'pending';
            } else { //otherwise just return blank
                return '';
            }
        },

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
                this.$refs.eventContainer.style.color = readableTextColor; 
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
    <div class="event-container" ref="eventContainer" :class="getAttendanceClass">
        <b v-if="content.timeText" ref="timeText">{{ content.timeText }}</b>
        <input v-if="isTask" type="checkbox" class="checkbox plain" id="completd" name="completed" v-model="isTaskComplete">
        <span ref="titleText" v-html="lineBreakedTitleText" :class="{ strike : isTaskComplete }"></span>
    </div>
</template>

<style lang="scss" scoped>

.event-container {
    padding: 3px;
}

//the task checkbox can be selected, but this change wont be reflected in the view-event-modal.vue that's opened
//prevent this behaviour as it's a bit confusing - the checkbox can only be changed in the modal, not directly on the event 
//updating directly via the event might be a nice feature in future
.checkbox { 
    pointer-events: none;
}

//to show that the user has rejected the attedence
.declined {
    text-decoration: line-through;
}

//show that the event is pending
.pending {
    opacity: 0.5;
}


</style>