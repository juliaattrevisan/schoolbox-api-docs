<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To customise the display of the event, ensuring the background/text colour is readable
 *
 */
import tinycolor from "tinycolor2";
import { EVENT_VARIANTS } from "@schoolboxCalendarEvent/event-variants";
import { ATTENDANCE_STATUS } from "@schoolboxCalendarEvent/attendance-status";

export default {
    props: {
        content: {
            type: Object,
            required: true,
        },
    },

    computed: {
        /**
         * don't apply these customisations to list views
         *
         * @returns { Boolean }
         */
        isEventInListComponent() {
            return this.$el.parentElement.className.includes("fc-list-event-title");
        },

        /**
         * don't apply these customisations to list views
         *
         * @returns { String }
         */
        eventBackgroundColor() {
            return this.$el.parentElement.parentElement.style.backgroundColor;
        },

        /**
         * to display a checkbox to show complete/incomplete
         *
         * @returns { Boolean }
         */
        isTask() {
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
        isTaskComplete() {
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
        getAttendanceClass() {
            const attendanceStatus = this.content.event?.extendedProps?.data?.attendance?.status?.current;
            if (attendanceStatus === ATTENDANCE_STATUS.DECLINED) {
                return "declined";
            } else if (attendanceStatus === ATTENDANCE_STATUS.PENDING) {
                return "pending";
            } else {
                //otherwise just return blank
                return "";
            }
        },
    },

    mounted() {
        this.updateTextColor();
        this.checkBackgroundColor();
    },

    methods: {
        /**
         * Ensure the text colour is readable on the background, which uses the user assigned folder colour
         */
        updateTextColor() {
            if (!this.isEventInListComponent) {
                //don't do for list view, only within month/week views
                const readableTextColor = tinycolor
                    .mostReadable(this.eventBackgroundColor, ["black", "white"])
                    .toRgbString();
                // no need to set color, if white
                if (readableTextColor === tinycolor("white").toRgbString()) {
                    return;
                }

                if (this.content.timeText !== "") {
                    //not always available
                    this.$refs.timeText.style.color = readableTextColor;
                }
                this.$refs.titleText.style.color = readableTextColor;
                this.$refs.eventContainer.style.color = readableTextColor;
            }
        },

        /**
         * If it's white, add a border
         */
        checkBackgroundColor() {
            if (!this.isEventInListComponent) {
                //don't do for list view, only within month/week views
                if (this.eventBackgroundColor === "rgb(255, 255, 255)") {
                    this.$refs.eventContainer.style.border = "1px solid black";
                }
            }
        },
    },
};
</script>

<template>
    <div ref="eventContainer" class="event-container" :class="getAttendanceClass">
        <b v-if="content.timeText" ref="timeText" class="fc-event-title">{{ content.timeText }}</b>
        <input v-if="isTask" v-model="isTaskComplete" type="checkbox" class="checkbox plain" name="completed" />
        <span ref="titleText" class="fc-event-title" :class="{ strike: isTaskComplete }">
            {{ content.event.title }}
        </span>
    </div>
</template>

<style lang="scss" scoped>
//apply padding as .fc-event-title will lose its padding from display: inline
.event-container {
    padding: 0px 2px 2px 2px; // make top padding zero to align the text to the top of the event in calendar
}

//needs to be inline to ensure the .strike class works
.fc-event-title {
    display: inline;
    padding-right: 2px;
}

//the task checkbox can be selected, but this change wont be reflected in the view-event-modal.vue that's opened
//prevent this behaviour as it's a bit confusing - the checkbox can only be changed in the modal, not directly on the event
//updating directly via the event might be a nice feature in future
.checkbox {
    pointer-events: none;
    //Make the checkbox bottom aligned to our text instead of floating above it
    vertical-align: text-bottom;
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
