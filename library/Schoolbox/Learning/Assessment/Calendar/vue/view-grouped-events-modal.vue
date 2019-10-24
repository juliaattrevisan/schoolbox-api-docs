<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import MetaList    from '@schoolbox/meta/list'
    import MetaItem    from '@schoolbox/meta/item'

    const EVENT_VIEW_WEEK = 'eventViewWeek';

    export default {
        name: "view-grouped-events-modal",
        props: {
            toMoment: {},
            taskTypeId: {},
            eventModifyFormUrl: {},
            eventModifyFormActionUrl: {},
            eventUpdateUrl: {},
            attendanceListUrl: {},
            attendanceToggleUrl: {},
            formatDisplayDate: {},
            formatDisplayTime: {},
            formatDisplayDatetime: {},
            returnTo: {
                default: 'calendar',
            }
        },
        data() {
            return {
                event: {},
            }
        },
        components: {
            MetaItem,
            MetaList,
            RevealModal
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
                let event      = this.event,
                    data       = event?.extendedProps?.data,
                    metadata   = data?.meta;

                return {
                    start:     event?.start,
                    title:     metadata?.folderName || metadata?.eventTypeName,
                    dateSpan:  this.determineDateSpan(event?.start, event?.end, event?.allDay),
                    subEvents: data?.subEvents || [],
                };
            }
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
                    return '';
                }
                let format      = allDay ? this.formatDisplayDate : this.formatDisplayDatetime,
                    momentStart = this.toMoment(startDate),
                    start       = momentStart.format(format),
                    momentEnd   = null,
                    end         = null;

                if (endDate != null) {
                    momentEnd = this.toMoment(endDate);
                    if (allDay) {
                        // Event end dates in FullCalendar are exclusive.
                        // This means that all-day events from the event source
                        // have had their end date incremented by one day:
                        // roll it back for rendering the user-visible end.
                        end = momentEnd.subtract(1, 'd').format(format);
                    } else {
                        end = momentEnd.format(format);
                    }
                }

                if (momentEnd && momentEnd.isValid() && !momentStart.isSame(momentEnd)) {
                    // Start and end aren't the same - stitch them together.
                    if (!allDay && momentStart.isSame(momentEnd, 'day')) {
                        // Between two times on the same day - show the timespan but only one date
                        return start + " – " + momentEnd.format(this.formatDisplayTime);
                    } else {
                        // Between two different days - show the whole timespan
                        return start + " – " + end;
                    }
                }

                // Otherwise start and end are the same - just show the start
                return start;
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
    }
</script>

<template>
    <reveal-modal ref="modal">
        <h2>{{ eventExtendedProps.title }}: {{ eventExtendedProps.dateSpan }}</h2>

        <meta-list>
            <meta-item class="icon-calendar">{{ eventExtendedProps.subEvents.length }} {{ eventExtendedProps.subEvents.length === 1 ? 'event' : 'events'}}</meta-item>
        </meta-list>

        <section>
            <ul class="information-list">
                <li v-for="subEvent in eventExtendedProps.subEvents" class="actions-small-0">
                    <div class="list-item">
                        <div class="card small-12">
                            <template v-if="subEvent.data && subEvent.data.links && subEvent.data.links.path">
                                <a :href="subEvent.data.links.path">
                                    <h3>{{ subEvent.title }}</h3>
                                </a>
                            </template>
                            <template v-else>
                                <h3>{{ subEvent.title }}</h3>
                            </template>
                            <meta-list>
                                <meta-item>{{ determineDateSpan(subEvent.start, subEvent.end, subEvent.allDay) }}</meta-item>
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
