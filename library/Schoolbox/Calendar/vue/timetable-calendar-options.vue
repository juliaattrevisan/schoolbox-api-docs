<script>
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import CustomisedCalendarEvent from "@schoolboxVue/components/fullcalendar/customised-calendar-event";

export default {
    name: "timetable-calendar-options",

    components: {},

    emits: ["optionSelected"],

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
            const anchor = ev.target;

            if (anchor.parentElement.classList.contains("active")) {
                // This child is already selected, so do nothing.
                console.info("Already selected so nothing to do.");
                return;
            }

            this.$emit("optionSelected", {
                selectedUserId: anchor.getAttribute("data-userId"),
                selectedUserName: anchor.getAttribute("data-childFormattedName"),
                selectedYearLevel: anchor.getAttribute("data-childYearLevel"),
            });

            // TODO: Update state of buttons the Vue way somehow.
            //for (const anchorInner of document.querySelectorAll("[data-child-student-button]")) {
            //    anchorInner.parentElement.classList.remove("active");
            //}
            //anchor.parentElement.classList.add("active");
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
