import Vue from "vue";
import CalendarComponent from "./calendar-component.vue";

const calendars = document.querySelectorAll('[id^="calendar-"]');

calendars.forEach((calendar) => {
    let identifier = `#${calendar.id}`;

    /* Only spawn one vue component per container
     * Every calendar component executes this file for every other component
     *
     * So if you have three components, you'll have 3x3 = 9 executions here
     *
     * To curb this insanity, we only initialise components that we haven't
     * already encountered by checking the data-loaded attribute and using
     * that as a flag
     *
     * TODO jiggles to replace this with a functioning vanillajs equivalent
     *  We originally tried that, but it didn't work for unknown reasons
     *  jquery ftw
     */
    if (!$(identifier).attr('data-loaded')) {
        new Vue({
            el: identifier,
            components: {
                'calendar-component': CalendarComponent
            }
        });
    }

    $(identifier).attr('data-loaded', true);
});