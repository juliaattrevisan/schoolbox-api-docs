<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import MetaList    from '@schoolbox/meta/list'
    import MetaItem    from '@schoolbox/meta/item'

    export default {
        name: "view-event-modal",
        props: ['event', 'toMoment',],
        components: {
            MetaItem,
            MetaList,
            RevealModal
        },
        computed: {
            eventExtendedProps: function () {
                // Also need to render the event's buttons
                let event      = this.event,
                    data       = event?.extendedProps?.data,
                    metadata   = data?.meta,
                    modifyLink = null,
                    deleteLink = null;

                // Modify/delete links
                if (event?.editable) {
                    if (data?.custom?.modifyLink) {
                        modifyLink = data?.custom?.modifyLink;
                    } else if (metadata?.eventId) {
                        modifyLink = '/calendar/modify.php?eventID=' + metadata?.eventId + '&returnTo=calendar';
                        deleteLink = '/calendar/modifyForm.php?action=Delete&id=' + metadata?.eventId + '&returnTo=calendar';
                    }
                }

                return {
                    eventId: metadata?.eventId,
                    detail: metadata?.detail,
                    location: metadata?.location,
                    author: {
                        id: Math.max(0, metadata?.authorId || 0),
                        name: metadata?.author,
                    },
                    reviewer: {
                        id: Math.max(0, metadata?.reviewerId || 0),
                        name: metadata?.reviewer,
                    },
                    folder: {
                        id: Math.max(0, data?.links?.category.id || 0),
                    },
                    dateSpan: this.determineDateSpan(event?.start, event?.end, event?.allDay),
                    booking: {
                        status: data?.booking?.status,
                        related: data?.booking?.related,
                        actions: data?.booking?.buttons,
                    },
                    attendance: data?.attendance,
                    links: {
                        view: data?.custom?.moreDetailsLink,
                        modify: modifyLink,
                        delete: deleteLink,
                    },
                };
            }
        },
        methods: {
            open() {
                this.$refs.modal.open();
            },
            determineDateSpan: function (startDate, endDate, allDay) {
                if (!startDate) {
                    return '';
                }
                let format      = allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma',
                    momentStart = this.toMoment(startDate),
                    start       = momentStart.format(format),
                    momentEnd   = null,
                    end         = null;

                if (endDate != null) {
                    momentEnd = this.toMoment(endDate);
                    if (allDay) {
                        end = momentEnd.subtract(1, 'd').format(format);
                    } else {
                        end = momentEnd.format(format);
                    }
                }

                if (momentEnd && !momentStart.isSame(momentEnd)) {
                    // Start and end aren't the same - stitch them together.
                    if (!allDay && momentStart.isSame(momentEnd, 'day')) {
                        // Between two times on the same day - show the timespan but only one date
                        return start + " – " + momentEnd.format('h:mma');
                    } else {
                        // Between two different days - show the whole timespan
                        return start + " – " + end;
                    }
                } else {
                    // Start and end are the same - just show the start
                    return start;
                }
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <div class="row">
            <div class="small-12">
                <h2 class="note">{{ event.title }}</h2>
                <meta-list>
                    <meta-item class="icon-resource-booking" v-if="eventExtendedProps.booking.status">
                        {{ eventExtendedProps.booking.status.name }}
                    </meta-item>
                    <meta-item class="icon-calendar" v-if="eventExtendedProps.dateSpan">
                        {{ eventExtendedProps.dateSpan }}
                    </meta-item>
                    <meta-item class="icon-location" v-if="eventExtendedProps.location">
                        {{ eventExtendedProps.location }}
                    </meta-item>
                    <meta-item class="icon-staff-students" v-if="eventExtendedProps.author.id">
                        Posted by
                        <a :href="'/search/user/' + String(eventExtendedProps.author.id)">
                            {{ eventExtendedProps.author.name }}
                        </a>
                    </meta-item>
                    <meta-item class="icon-staff-students" v-if="eventExtendedProps.reviewer.id">
                        Reviewed by
                        <a :href="'/search/user/' + String(eventExtendedProps.reviewer.id)">
                            {{ eventExtendedProps.reviewer.name }}
                        </a>
                    </meta-item>
                </meta-list>
                <article style="white-space: pre-line">
                    <span v-html="eventExtendedProps.detail"></span>
                </article>

                <div v-if="eventExtendedProps.booking.related">
                    <h3>Related Bookings</h3>
                    <ul>
                        <li v-for="relatedBooking in eventExtendedProps.booking.related">
                            ({{ relatedBooking.status.name }})
                            <a :href="relatedBooking.status.href">{{ relatedBooking.title }}</a>
                        </li>
                    </ul>
                </div>

                <div v-if="eventExtendedProps.attendance">
                    <dl>
                        <dd><p :class="eventExtendedProps.attendance.icon"></p></dd>
                        <dd class="rm"><p>{{ eventExtendedProps.attendance.status }}</p></dd>
                    </dl>
                </div>

                <span v-if="eventExtendedProps.folder.id">
                        <a class="button icon-home"
                           :href="'/homepage/' + String(eventExtendedProps.folder.id)">Go to homepage</a>
                    </span>
                <span v-if="eventExtendedProps.links.view">
                        <a class="button icon-due-work"
                           :href="eventExtendedProps.links.view">View More Details</a>
                    </span>
                <span v-if="eventExtendedProps.attendance && eventExtendedProps.attendance.listButton">
                        <a class="button icon-group"
                           :href="'/calendar/attendance/list/' + String(eventExtendedProps.eventId)">Attendees</a>
                    </span>
                <span v-if="eventExtendedProps.attendance && eventExtendedProps.attendance.requestButton">
                        <a class="button icon-reply"
                           @click.prevent="toggleAttendance"
                           href="#">{{ eventExtendedProps.attendance.requestButton }}</a>
                    </span>
                <span v-if="eventExtendedProps.modifyLink">
                        <a class="button icon-modify"
                           :href="eventExtendedProps.modifyLink">Modify</a>
                    </span>
                <span v-if="eventExtendedProps.deleteLink">
                        <a class="button icon-delete"
                           :href="eventExtendedProps.deleteLink">Delete</a>
                    </span>

                <div v-for="action in eventExtendedProps.booking.actions">
                    <a :href="action.href" class="button">{{ action.label }}</a>
                </div>

            </div>
        </div>
    </reveal-modal>
</template>

<style scoped>

</style>
