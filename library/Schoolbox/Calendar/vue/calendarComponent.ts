import CalendarComponent from "./calendar-component.vue";

const calendars = document.querySelectorAll('[id^="calendar-"]');

calendars.forEach((calendar) => {
    /* Only spawn one vue component per container
     * Every calendar component executes this file for every other component
     *
     * So if you have three components, you'll have 3x3 = 9 executions here
     *
     * To curb this insanity, we only initialise components that we haven't
     * already encountered by checking the data-loaded attribute and using
     * that as a flag
     */
    if (!calendar.hasAttribute("data-loaded")) {
        window.Vue.createApp({
            components: {
                CalendarComponent,
            },
        }).mount(`#${calendar.id}`);
    }
    calendar.setAttribute("data-loaded", "true");
});
