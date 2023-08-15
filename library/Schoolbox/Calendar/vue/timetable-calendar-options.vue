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

    props: {
        options: Object,
    },

    computed: {},

    beforeMount() {},

    mounted() {},

    methods: {
        /**
         * When a child student button is clicked:
         *  - Ignore the click if the button is already in the active state
         *  - Load calendar events for the newly selected child student
         *  - Update the state of the buttons and the page heading.
         * @param ev - The click event.
         */
        onClickChildStudentButton(ev) {
            console.info("Click!");
            //const anchor = ev.target;
            //
            //if (anchor.parentElement.classList.contains("active")) {
            //    // This child is already selected, so do nothing.
            //    return;
            //}
            //
            //// The ref is set as <timetable-calendar ref='timetable-calendar'></timetable-calendar> in timetableCalendar.twig.
            //window.fullCalendarVueAppRoot.$refs["timetable-calendar"].changeUserAndReloadEvents(
            //    anchor.getAttribute("data-userId")
            //);
            //
            //// Update state of buttons.
            //for (const anchorInner of document.querySelectorAll("[data-child-student-button]")) {
            //    anchorInner.parentElement.classList.remove("active");
            //}
            //anchor.parentElement.classList.add("active");
            //
            //// Update page heading.
            //const childName = anchor.getAttribute("data-childFormattedName");
            //const yearLevel = anchor.getAttribute("data-childYearLevel");
            //document.querySelector("[data-heading-user-fullname]").innerHTML = childName;
            //document.querySelector("[data-heading-user-year]").innerHTML = yearLevel == "" ? "" : yearLevel;
        },
    },
};
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <dl class="sub-nav">
                <dd
                    v-for="userOption in options['userOptions']"
                    :class="userOption.userId === options['selectedUserOption'].userId ? 'active' : ''"
                >
                    <!-- Format of data-childYearLevel must match format used below in <span data-heading-user-year>. -->
                    <a
                        data-child-student-button
                        :data-userId="userOption.userId"
                        :data-childFormattedName="userOption.formattedName"
                        :data-childYearLevel="userOption.yearLevel"
                        :title="`'Timetable for ${userOption.formattedName} in ${userOption.firstCampusName}`"
                        @click="onClickChildStudentButton"
                    >
                        {{ userOption.formattedName }} ({{ userOption.firstCampusName }})
                    </a>
                </dd>
            </dl>
        </div>
    </div>
</template>

<style lang="scss"></style>
