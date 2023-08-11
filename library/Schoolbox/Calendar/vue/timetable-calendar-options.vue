<script>
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import CustomisedCalendarEvent from "@schoolboxVue/components/fullcalendar/customised-calendar-event";

export default {
    name: "timetable-calendar-options",

    components: {},

    mixins: [],

    data() {
        return {};
    },

    props: {},

    computed: {},

    beforeMount() {},

    mounted() {
        this.$nextTick().then(() => {
            // TODO: Do this the Vue way somehow.
            //            const anchors = document.querySelectorAll("[data-child-student-button]");
            //
            //            for (const anchor of anchors) {
            //                anchor.addEventListener("click", onClickChildStudentButton);
            //            }
        });
    },

    beforeDestroy() {
        //        window.removeEventListener("resize", this.onResize);
    },

    methods: {
        /**
         * When a child student button is clicked:
         *  - Ignore the click if the button is already in the active state
         *  - Load calendar events for the newly selected child student
         *  - Update the state of the buttons and the page heading.
         */
        onClickChildStudentButton() {
            // TODO: Do this the Vue way somehow.
            //const anchor = ev.target;
            //if (anchor.parentElement.classList.contains("active")) {
            //    // This child is already selected, so do nothing.
            //    return;
            //}
            //// The ref is set as <timetable-calendar ref='timetable-calendar'></timetable-calendar> in timetableCalendar.twig.
            //window.fullCalendarVueAppRoot.$refs["timetable-calendar"].changeUserAndReloadEvents(
            //    anchor.getAttribute("data-userId")
            //);
            //// Update state of buttons.
            //for (const anchorInner of document.querySelectorAll("[data-child-student-button]")) {
            //    anchorInner.parentElement.classList.remove("active");
            //}
            //anchor.parentElement.classList.add("active");
            //// Update page heading.
            //const childName = anchor.getAttribute("data-childFormattedName");
            //const yearLevel = anchor.getAttribute("data-childYearLevel");
            //document.querySelector("[data-heading-user-fullname]").innerHTML = childName;
            //document.querySelector("[data-heading-user-year]").innerHTML = yearLevel === "" ? "" : yearLevel;
        },
    },
};
</script>

<template>
    <div>Hello! I'm a Vue component.</div>
</template>

<style lang="scss"></style>
