<script>

    import FullCalendar       from '@fullcalendar/vue'
    import interactionPlugin  from '@fullcalendar/interaction'
    import dayGridPlugin      from '@fullcalendar/daygrid'
    import timeGridPlugin     from '@fullcalendar/timegrid'
    import listPlugin         from '@fullcalendar/list'
    import momentPlugin       from '@fullcalendar/moment'
    import RevealModal        from '@schoolbox/reveal-modal'
    import ViewEventModal     from '@schoolbox/fullcalendar/view-event-modal';
    import axios              from 'axios'
    import FullCalendarHelper from "@schoolbox/fullcalendar/helper";
    import {stringify}        from 'qs';
    import resourceTimelinePlugin     from '@fullcalendar/resource-timeline';

    // Various URLs that FullCalendar may make use of.
    const URL_EVENT_SOURCE = '/learning/assessment/calendar/events';

    const FORMAT_FORM_DATE = 'DD-MM-YYYY';
    const FORMAT_FORM_DATETIME = 'DD-MM-YYYY HH:mm';

    // TODO let display formats be configurable
    const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
    const FORMAT_DISPLAY_TIME = 'h:mma';
    const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';
    const FORMAT_DISPLAY_WEEK_DAY = 'ddd D/M';

    export default {
        components: {
            ViewEventModal,
            FullCalendar,
            RevealModal,
        },
        beforeMount() {
            this.helper = new FullCalendarHelper(this, this.config, this.jQuery);
            this.termStartDate = this.config.termDates.current.start;
            this.termEndDate = this.config.termDates.current.end;
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, resourceTimelinePlugin, momentPlugin ],
                currentEvent: {},
                newEvent: {},
                selectedFilters: null,
                newResources: [],
                currentView: null,
                termStartDate: null,
                termEndDate: null,
                cursor: 0,
                currentBatchCursor: null,
                eventGroups: [],
            }
        },
        mounted() {
            // document.querySelector('.fc-timeline-event').addEventListener('click', (ev)=>{
            //     console.log('test');
            // });
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
            formats() {
                return {
                    formDate: FORMAT_FORM_DATE,
                    formDatetime: FORMAT_FORM_DATETIME,
                    displayDate: FORMAT_DISPLAY_DATE,
                    displayTime: FORMAT_DISPLAY_TIME,
                    displayDatetime: FORMAT_DISPLAY_DATETIME,
                    displayWeekDay: FORMAT_DISPLAY_WEEK_DAY,
                };
            },
        },
        methods: {
            /**
             * Event loading callback: show a spinner whilst events are loading,
             * remove the spinner once they have finished.
             * @see https://fullcalendar.io/docs/loading
             *
             * @param isLoading True when events start loading,
             *                  false once they are finished.
             */
            onLoading(isLoading) {

                this.destroyPopOver();

                // TODO this method should be unnecessary
                // (for the same reason as `onLoading` in the main fullCalendar)
                this.helper.onLoading(isLoading);
            },

            /**
             * Event source.
             * @see https://fullcalendar.io/docs/events-function
             *
             * @param fetchInfo       The time span of the events to fetch
             * @param successCallback A function to call with the list of events.
             */
            events(fetchInfo, successCallback) {
                var self = this;

                // No filters loaded yet? Render nothing.
                // The selected filters get populated by `onFilterChange`:
                // this is called by the filter's `filterContentLoaded` trigger.
                if (self.selectedFilters === null) {
                    successCallback([]);
                    return;
                }
                axios.get(
                    URL_EVENT_SOURCE,
                    {
                        headers: {
                            'X-Requested-With' : 'XMLHttpRequest'
                        },
                        params: {
                            start:   Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                            end:     Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                            filters: self.selectedFilters,
                        },
                        paramsSerializer: params => stringify(params, {arrayFormat: 'brackets'}),
                    }
                ).then(
                    function (response) {
                        // Massage the data to inject placeholders
                        let newResponse = [],
                            eventGroupingCursor = '';

                        // Assign event grouping and resourceIds into every event
                        response.data.forEach(function(element, index) {
                            response.data[index].resourceId = element.data.meta.folderId;
                            response.data[index].data.meta.eventGrouping = moment(element.start).format('YYYY') + String(moment(element.start).isoWeek()).padStart(2,0) + element.resourceId;
                        });

                        response.data.forEach(function(element) {
                            // Create a place holder
                            if (self.currentView === 'resourceTimelineSemester' && eventGroupingCursor !== element.data.meta.eventGrouping) {

                                var itemCountByGroup = response.data.filter(function(val) {
                                    return val.data.meta.eventGrouping === element.data.meta.eventGrouping;
                                }).length;

                                newResponse.push({
                                    allDay: true,
                                    color: '#fffff',
                                    backgroundColor: '#0066b0', // Update this with folder color code
                                    editable: false,
                                    draggable: false,
                                    resourceId: element.resourceId,
                                    title: itemCountByGroup,
                                    start: element.start,
                                    end: null,
                                    data: {
                                        placeholder: true,
                                        meta: {
                                            folderId: element.data.meta.folderId,
                                            folderName: element.data.meta.folderName,
                                            eventGrouping: element.data.meta.eventGrouping,
                                            weekNo: moment(element.start).isoWeek(),
                                        }
                                    },
                                });
                            }

                            eventGroupingCursor = element.data.meta.eventGrouping;
                            newResponse.push(element);
                        });

                        successCallback(newResponse.filter(function (v) {
                            // Check if resource is already added
                            if (!self.newResources.some(el => el.id === v.data.meta?.folderId)) {
                                self.newResources.push({'id': v.data.meta.folderId, 'title': v.data.meta.folderName});
                            }

                            return 'start' in v;
                        }));
                    }
                );
                // Fail callback: consider a message saying something failed
            },

            /**
             * Event render callback: manually tweaks the event's rendering, and
             * can also conditionally show/hide the event.
             * @see https://fullcalendar.io/docs/eventRender
             *
             * @param eventRenderInfo
             * @return {boolean}
             */
            eventRender(eventRenderInfo) {
                var self = this,
                    eventGrouping = eventRenderInfo.event.extendedProps.data.meta.eventGrouping;

                if (!self.eventGroups.includes(eventGrouping)) {
                    self.eventGroups.push(eventGrouping);
                }

                if (self.currentView === 'resourceTimelineSemester') {
                    var element = eventRenderInfo.el;
                    if (eventRenderInfo.event.extendedProps.data.placeholder) {
                        element.setAttribute('event-group-id-placeholder', eventGrouping);
                        element.classList.add('placeholder');
                        element.classList.add('source');
                        element.classList.add('personal');
                        element.classList.add('fc-more');
                    } else {
                        element.setAttribute('event-group-id', eventGrouping);
                        element.setAttribute('event-id', eventRenderInfo.event.extendedProps.data.meta.eventId);
                        element.setAttribute('event-group-date', moment(eventRenderInfo.event.start).format('MMMM DD, YYYY'))
                        element.style.width = '95%';
                        // element.setAttribute('style', '-moz-opacity: 0; filter:alpha(opacity: 0); opacity: 0; height: 0px;');
                        element.classList.add('hide');
                    }
                }
            },

            /**
             * Click an event on the calendar callback: opens the view event
             * modal for the event.
             * @see https://fullcalendar.io/docs/eventClick
             *
             * @param eventInfo
             */
            onEventClick(eventInfo) {
                var self = this;

                if (self.currentView !== 'resourceTimelineSemester' ||
                    (self.currentView === 'resourceTimelineSemester' && eventInfo.el.getAttribute('event-group-id-placeholder') === null)) {
                    // Ideally the current event would be a part of the modal,
                    // not this component, but :shrug:
                    this.currentEvent = eventInfo.event;
                    this.$refs.event.open();
                } else {
                    this.renderPopOver(eventInfo);
                }
            },

            /**
             * Custom method to render event popover for semester view
             */
            renderPopOver(eventInfo) {
                this.destroyPopOver();

                var popOver = document.getElementById('popOver'),
                    popOverContent = '',
                    popOverHeader = '',
                    dateGroup = '',
                    eventGroupId = eventInfo.event.extendedProps.data.meta.eventGrouping,
                    folderId = eventInfo.event.extendedProps.data.meta.folderId,
                    eventGroup = document.querySelectorAll("[event-group-id='"+ eventGroupId +"']"),
                    resourceCol = document.querySelectorAll("[data-resource-id='"+ folderId +"']"),
                    popOverPlaceholder = document.querySelector('.fc-body .fc-time-area .fc-rows'),
                    tmpEvent = [];

                popOver = document.createElement('div');
                popOver.setAttribute('id', 'popOver');
                popOver.setAttribute('style', 'left:' + eventInfo.el.offsetLeft + 'px;top:' + resourceCol[0].offsetTop +'px;z-index:100000;padding:5px');
                popOver.className = 'fc-popover fc-more-popover';

                popOverHeader = '<div class="fc-header fc-widget-header">' +
                    '<span class="fc-title">Week '+ eventInfo.event.extendedProps.data.meta.weekNo +'</span>' +
                    '</div>';

                for (var i = 0; i < eventGroup.length; i++) {
                    var event = eventGroup[i];

                    if (event.style.removeProperty) {
                        event.style.removeProperty('left');
                        event.style.removeProperty('right');
                        event.style.removeProperty('top');
                    } else {
                        // Because IE < 9 doesn't support removeProperty so ¯\_(ツ)_/¯
                        event.style.removeAttribute('left');
                        event.style.removeAttribute('right');
                        event.style.removeAttribute('top');
                    }

                    tmpEvent.push(event.outerHTML);
                }

                for (var i = 0; i < tmpEvent.length; i++) {
                    var tmpPopOverEvent = $(tmpEvent[i])[0],
                        popOverContentDate = '';

                        tmpPopOverEvent.classList.remove('hide', 'fc-draggable', 'fc-resizable');

                        if (tmpPopOverEvent.getAttribute('event-group-date') !== dateGroup) {
                            dateGroup = tmpPopOverEvent.getAttribute('event-group-date');
                            popOverContentDate = '<p style="font-weight: bold; margin-bottom: 0">' + dateGroup + '</ p>';
                        }

                        popOverContent += popOverContentDate + '<div class="fc-timeline-event-div" style="padding-bottom:40px;display:flex">' + tmpPopOverEvent.outerHTML + '</div>';
                }

                popOver.innerHTML = popOverHeader + popOverContent;

                popOverPlaceholder.insertBefore(popOver, popOverPlaceholder.firstChild);

                this.registerSemViewEventClickListener();
            },

            /**
             * Listen on popover event clicks with semester view
             */
            registerSemViewEventClickListener() {
                var popOverEvents = document.querySelectorAll("#popOver .fc-timeline-event-div");

                var openModal = function() {
                    document.querySelectorAll('.fc-event-container [event-id="'+ this.firstChild.getAttribute("event-id") +'"]')[0].click();
                };

                popOverEvents.forEach(function(event, index) {
                    popOverEvents[index].addEventListener('click', openModal, false);
                });
            },

            /**
             * Helper function for converting a Date object to a Moment object.
             * @see https://fullcalendar.io/docs/moment-plugins
             *
             * @param date
             */
            toMoment(date) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading` in the main fullCalendar)
                return this.helper.toMoment(date);
            },

            /**
             * Callback for when the filters for this calendar are updated.
             *
             * Fired from outer space (i.e. from the outside window).
             *
             * @param {object} selectedFilters The filters that were selected
             */
            onFilterChange(selectedFilters) {
                this.selectedFilters = selectedFilters;
                this.$refs.fullCalendar.getApi().refetchEvents();
            },

            /**
             * Get terms duration based on start and finish date
             * @returns {*}
             */
            getTermDateDurations() {
                return this.helper.dateDiff(this.termStartDate, this.termEndDate);
            },

            /**
             * Callback for when the calendar view changes: preserve the
             * newly selected view for this user.
             * @see https://fullcalendar.io/docs/viewSkeletonRender
             *
             * @param info
             */
            viewSkeletonRender(info) {
                var self = this;

                self.currentView = info.view.type;

                this.destroyPopOver();
            },

            /**
             * Customise next button behaviour for semester view. Clicking next button while on semester view will
             * navigate into the next available semester
             */
            clickNext() {
                var self = this;

                if (self.currentView === 'resourceTimelineSemester') {
                    if ((self.cursor < 0) || (self.cursor === 0 && self.config.termDates.future.length) ||
                        (self.cursor > 0 && self.config.termDates.future[Math.abs(self.cursor)])) {
                        self.cursor++;
                        var startDateByCursor = self.getStartDateByCursor();

                        if (startDateByCursor !== undefined) {
                            self.$refs.fullCalendar.getApi().gotoDate(startDateByCursor);
                        }
                    }
                } else {
                    self.$refs.fullCalendar.getApi().next();
                }
            },

            /**
             * Customise previous button behaviour for semester view. Clicking previous button while on semester view will
             * navigate into the previous available semester
             */
            clickPrev() {
                var self = this;

                if (self.currentView === 'resourceTimelineSemester') {
                    if ((self.cursor > 0) || (self.cursor === 0 && self.config.termDates.past.length) ||
                        (self.cursor < 0 && self.config.termDates.past.length >= Math.abs(self.cursor) + 1)) {

                        self.cursor--;
                        var startDateByCursor = self.getStartDateByCursor();

                        if (startDateByCursor !== undefined) {
                            self.$refs.fullCalendar.getApi().gotoDate(startDateByCursor);
                        }
                    }
                } else {
                    self.$refs.fullCalendar.getApi().prev();
                }
            },

            /**
             * Navigate through the dates via the cursor
             */
            getStartDateByCursor() {
                var self = this;

                if (self.cursor === 0) {
                    return self.config.termDates.current?.start;
                } else if (self.cursor < 0) {
                    return self.config.termDates.past[Math.abs(self.cursor) - 1]?.start;
                } else {
                    return self.config.termDates.future[Math.abs(self.cursor) - 1]?.start;
                }
            },

            /**
             * Get the latest available term end date for fullCalendar's validRange config.
             * @see: https://fullcalendar.io/docs/validRange
             */
            getMaxTermDate() {
                var self = this;

                var termStartDates = Object.values(self.config.termDates).flat().map(function(row){
                    return new Date(row.end);
                });

                return moment(new Date(Math.max.apply(null,termStartDates))).format('YYYY-MM-DD');
            },

            /**
             * Get the earliest available term start date for fullCalendar's validRange config.
             * @see: https://fullcalendar.io/docs/validRange
             */
            getMinTermDate() {
                var self = this;

                var termStartDates = Object.values(self.config.termDates).flat().map(function(row){
                    return new Date(row.start);
                });

                return moment(new Date(Math.min.apply(null,termStartDates))).format('YYYY-MM-DD');
            },

            /**
             * As much as possible, destroy the popover holder whenever it's created on every calendar transitions.
             */
            destroyPopOver() {
                var popOver = document.getElementById('popOver');

                // Always destroy the current popover, as there should only be one.
                if (popOver) {
                    popOver.parentNode.removeChild(popOver);
                }
            },

            eventPositioned() {

            }
        }
    }
</script>

<template>
    <div>
        <view-event-modal ref="event"
                          :event="currentEvent"
                          :to-moment="toMoment"
                          :format-display-date="formats.displayDate"
                          :format-display-time="formats.displayTime"
                          :format-display-datetime="formats.displayDatetime"
        />

        <full-calendar
            ref="fullCalendar"
            :plugins="calendarPlugins"
            :views="{
                resourceTimelineSemester: {
                    type: 'resourceTimelineYear',
                    duration: { days: getTermDateDurations() },
                    slotDuration: { weeks: 1 },
                    weekNumbers: true,
                    slotLabelFormat: [
                        'MMMM',
                        'w'
                    ],
                    resourceAreaWidth: '15%',
                    filterResourcesWithEvents: true,
                    validRange: {
                        start: this.getMinTermDate(),
                        end: this.getMaxTermDate()
                    }
                },
                resourceTimelineWeek: {
                    columnHeaderFormat: formats.displayWeekDay,
                    duration: { weeks: 1 },
                    slotDuration: { days: 1 },
                    slotLabelFormat: [
                        'ddd DD',
                    ],
                    resourceAreaWidth: '15%',
                    filterResourcesWithEvents: true,
                    validRange: {
                        start: this.getMinTermDate(),
                        end: this.getMaxTermDate()
                    },
                },
                dayGridMonth: {
                    eventLimit: config.eventLimit,
                    validRange: {
                        start: this.getMinTermDate(),
                        end: this.getMaxTermDate()
                    }
                }
            }"
            :week-label="''"
            :event-time-format="helper.eventTimeFormat"
            :header="{
                left: 'today,prev,next',
                center: 'title',
                right: 'resourceTimelineSemester,dayGridMonth,resourceTimelineWeek'
            }"
            :buttonText="{
                today:'Today',
                month:'Month',
                resourceTimelineWeek:'Week',
                resourceTimelineSemester:'Semester',
            }"
            :custom-buttons="{
                next: {
                    click: this.clickNext.bind(this),
                },
                prev: {
                    click: this.clickPrev.bind(this)
                }
            }"
            default-view="resourceTimelineSemester"
            :scroll-time="config.dateDisplayFormats.calendarStartTime"
            :event-render="eventRender"
            @loading="onLoading"
            @eventClick="onEventClick"
            :events="events"
            :resources="this.newResources"
            :scheduler-license-key="config.schedulerLicenseKey"
            :first-day="1"
            :default-date="this.termStartDate"
            :view-skeleton-render="viewSkeletonRender"
            :week-number-calculation="'ISO'"
            @eventPositioned="eventPositioned"
        />
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
    @import '~@fullcalendar/timeline/main.css';
    @import '~@fullcalendar/resource-timeline/main.css';

    // Please don't hate me with these....

    .fc-resourceTimelineSemester-view .fc-timeline-event .fc-title-wrap {
        text-align: center !important;
    }

    .fc-resourceTimelineSemester-view .fc-event .fc-title, .fc-event .fc-title:hover {
        color: #FFF !important;
    }

    .fc-resourceTimelineSemester-view .fc-ltr .fc-timeline-event {
        margin-right: 0 !important;
    }

    .fc-resourceTimelineSemester-view a.fc-more {
        margin: 0 !important;
    }

    .fc-resourceTimelineSemester-view span.fc-title.fc-sticky {
        left: 0 !important;
        width: 100% !important;
    }

    .fc-resourceTimelineSemester-view .fc-popover .fc-timeline-event .fc-title-wrap {
        text-align: left !important;
    }

    .fc-resourceTimelineSemester-view .fc-popover .fc-timeline-event {
        padding: 0 !important;
    }

    .fc-resourceTimelineSemester-view .fc-timeline-event {
        position: absolute !important;
    }

</style>
