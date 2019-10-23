<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import MetaList    from '@schoolbox/meta/list'
    import MetaItem    from '@schoolbox/meta/item'
    import axios       from 'axios';
    import qs          from 'qs';

    const EFFICACY_SELF = 'self';
    const EFFICACY_SERIES = 'series';

    const EVENT_MODIFIED = 'eventModified';
    const EVENT_DELETED = 'eventDeleted';

    const ATTENDANCE_ACTION_ADD = 'add';
    const ATTENDANCE_ACTION_REMOVE = 'remove';

    export default {
        name: "view-grouped-events-modal",
        props: {
            event: {},
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
        components: {
            MetaItem,
            MetaList,
            RevealModal
        },
        computed: {
            window: () => window,

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
                    metadata   = data?.meta,
                    modifyLink = null,
                    deleteLink = null;

                // // Modify/delete links
                // if (metadata?.editable) {
                //     if (data?.custom?.modifyLink) {
                //         modifyLink = data?.custom?.modifyLink;
                //     } else if (metadata?.eventId) {
                //         modifyLink = this.eventModifyFormUrl + '?eventID=' + metadata?.eventId + '&returnTo=' + this.returnTo;
                //         deleteLink = this.eventModifyFormActionUrl + '?action=Delete&id=' + metadata?.eventId + '&returnTo=' + this.returnTo;
                //     }
                // }

                return {
                    // eventId: metadata?.eventId,
                    // seriesId: metadata?.seriesId,
                    // editable: metadata?.editable,
                    // detail: metadata?.detail,
                    // location: metadata?.location,
                    // author: {
                    //     id: Math.max(0, metadata?.authorId || 0),
                    //     name: metadata?.author,
                    // },
                    // reviewer: {
                    //     id: Math.max(0, metadata?.reviewerId || 0),
                    //     name: metadata?.reviewer,
                    // },
                    // folder: {
                    //     id: Math.max(0, data?.links?.category?.id || 0),
                    // },
                    title:     metadata?.folderName || metadata?.eventTypeName,
                    dateSpan:  this.determineDateSpan(event?.start, event?.end, event?.allDay),
                    subEvents: data?.subEvents || [],
                    // booking: {
                    //     isBooking: (data?.booking?.status || '').length,
                    //     status: data?.booking?.status,
                    //     related: data?.booking?.related || [],
                    //     actions: (data?.booking?.buttons || []).filter(button => button?.href),
                    // },
                    // attendance: {
                    //     // Massage attendance data into something resembling usability
                    //     isAttendable: data?.attendance?.icon,
                    //     status: data?.attendance?.status,
                    //     actions: {
                    //         toggle: {
                    //             isAvailable: Boolean(data?.attendance?.requestButton),
                    //             href: this.attendanceToggleUrl,
                    //             text: data?.attendance?.nextAction === ATTENDANCE_ACTION_ADD ? 'RSVP' : 'Unattend',
                    //         },
                    //         list: {
                    //             isAvailable: Boolean(data?.attendance?.listButton),
                    //             href: this.attendanceListUrl + String(metadata?.eventId),
                    //         },
                    //     },
                    // },
                    // classAttendance: {
                    //     url: data?.classAttendance?.url,
                    // },
                    // task: {
                    //     isTask: Number(metadata?.eventTypeId) === Number(this.taskTypeId),
                    //     completed: metadata?.completed,
                    // },
                    // links: {
                    //     view: data?.custom?.moreDetailsLink,
                    //     modify: modifyLink,
                    //     delete: deleteLink,
                    //     path: data?.links?.path
                    // },
                };
            }
        },
        methods: {
            /**
             * Open this modal.
             */
            open() {
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
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <h2>{{ eventExtendedProps.title }}: {{ eventExtendedProps.dateSpan }}</h2>

        <h3>{{ eventExtendedProps.subEvents.length }} {{ eventExtendedProps.subEvents.length === 1 ? 'event' : 'events'}}</h3>

        <ul>
            <li v-for="subEvent in eventExtendedProps.subEvents">
                {{ subEvent.title }}: {{ determineDateSpan(subEvent.start, subEvent.end, subEvent.allDay) }}
            </li>
        </ul>

        <!-- Go to the week view -->
    </reveal-modal>
</template>
