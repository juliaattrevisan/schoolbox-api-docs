<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";
import MetaList from "@schoolboxVue/components/meta/list";
import MetaItem from "@schoolboxVue/components/meta/item";

const EVENT_VIEW_WEEK = "eventViewWeek";

export default {
    name: "view-grouped-events-modal",

    components: {
        MetaItem,
        MetaList,
        RevealModal,
    },

    props: {
        eventModifyFormUrl: {},
        eventModifyFormActionUrl: {},
        eventUpdateUrl: {},
        attendanceListUrl: {},
        attendanceToggleUrl: {},
        formatDisplayDate: {},
        formatDisplayTime: {},
        formatDisplayDatetime: {},
        returnUrl: {
            default: "calendar",
        },
    },

    data() {
        return {
            event: {},
        };
    },

    computed: {
        /**
         * Generate extended properties for the component's event.
         *
         * TODO simplify this format
         * (This format this is a mess necessitated by the mess that is the
         *  events API.)
         *
         * @return {Object}
         */
        eventExtendedProps() {
            // Also need to render the event's buttons
            let event = this.event,
                data = event?.extendedProps?.data,
                metadata = data?.meta;

            return {
                start: event?.start,
                title: metadata?.folderName || metadata?.eventTypeName,
                dateSpan: this.determineDateSpan(event?.start, event?.end, event?.allDay),
                subEvents: data?.subEvents || [],
            };
        },
    },

    methods: {
        /**
         * Open this modal.
         */
        open(event) {
            this.event = event;
            this.$refs.modal.open();
        },

        /**
         * Determine how to render the start and end date as date range.
         *
         * @param startDate
         * @param endDate
         * @param allDay
         * @return {string}
         */
        determineDateSpan(startDate, endDate, allDay) {
            if (!startDate) {
                return "";
            }
            const format = allDay ? this.formatDisplayDate : this.formatDisplayDatetime;
            const startDateFormatted = dayjs(startDate).format(format);
            let endDateFormatted = null;

            if (endDate != null) {
                if (allDay) {
                    // Event end dates in FullCalendar are exclusive.
                    // This means that all-day events from the event source
                    // have had their end date incremented by one day:
                    // roll it back for rendering the user-visible end.
                    endDateFormatted = dayjs(endDate).subtract(1, "d").format(format);
                } else {
                    endDateFormatted = dayjs(endDate).format(format);
                }
            }

            if (
                dayjs(endDate) &&
                dayjs(endDate).isValid() &&
                !dayjs(startDate).isSame(dayjs(endDate).subtract(1, "d"))
            ) {
                // Start and end aren't the same - stitch them together.
                if (!allDay && dayjs(startDate).isSame(dayjs(endDate), "day")) {
                    // Between two times on the same day - show the timespan but only one date
                    return startDateFormatted + " – " + dayjs(endDate).format(this.formatDisplayTime);
                } else {
                    // Between two different days - show the whole timespan
                    return startDateFormatted + " – " + endDateFormatted;
                }
            }

            // Otherwise start and end are the same - just show the start
            return startDateFormatted;
        },

        /**
         * Event listener for clicking the "view week" button.
         *
         * Closes the modal and emits a signal for the calendar to switch to
         * the current week.
         *
         * @param date
         */
        onClickViewWeek(date) {
            this.$refs.modal.close();
            this.$emit(EVENT_VIEW_WEEK, date);
        },
    },
};
</script>

<template>
    <reveal-modal ref="modal">
        <h2>{{ eventExtendedProps.title }}: {{ eventExtendedProps.dateSpan }}</h2>

        <meta-list>
            <meta-item class="icon-calendar"
                >{{ eventExtendedProps.subEvents.length }}
                {{ eventExtendedProps.subEvents.length === 1 ? "event" : "events" }}</meta-item
            >
        </meta-list>

        <section>
            <ul class="information-list">
                <li v-for="subEvent in eventExtendedProps.subEvents" class="actions-small-0">
                    <div class="list-item">
                        <div class="card small-12">
                            <template v-if="subEvent.data && subEvent.data.links && subEvent.data.links.view">
                                <a :href="subEvent.data.links.view">
                                    <h3>{{ subEvent.title }}</h3>
                                </a>
                            </template>
                            <template v-else>
                                <h3>{{ subEvent.title }}</h3>
                            </template>
                            <meta-list>
                                <meta-item v-if="subEvent.type"
                                    >{{ subEvent.type }} due on
                                    {{ determineDateSpan(subEvent.start, subEvent.end, subEvent.allDay) }}</meta-item
                                >
                                <meta-item v-else>{{
                                    determineDateSpan(subEvent.start, subEvent.end, subEvent.allDay)
                                }}</meta-item>
                            </meta-list>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <div class="component-action" v-if="eventExtendedProps.start">
            <section>
                <a class="button" @click.prevent="onClickViewWeek(eventExtendedProps.start)">View week</a>
            </section>
        </div>
    </reveal-modal>
</template>
