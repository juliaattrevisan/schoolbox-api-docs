<script>

    import FullCalendar       from '@fullcalendar/vue'
    import interactionPlugin  from '@fullcalendar/interaction'
    import dayGridPlugin      from '@fullcalendar/daygrid'
    import timeGridPlugin     from '@fullcalendar/timegrid'
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

    const VIEW_RESOURCE_TIMELINE_SEMESTER = 'resourceTimelineSemester';

    export default {
        components: {
            ViewEventModal,
            FullCalendar,
            RevealModal,
        },
        created() {
            this.helper = new FullCalendarHelper(this, this.config, this.jQuery);
            this.termStartDate = this.config.termDates.current.start;
            this.termEndDate = this.config.termDates.current.end;
            this.allTerms = this.buildAllTermsList();
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, resourceTimelinePlugin, momentPlugin ],
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
                defaultBgColour: '#0066b0',
                maxDate: null,
                minDate: null,
            }
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


            contentHeight() {
                return this.helper.contentHeight();
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
                        response.data.map(function(element, index) {
                            element.resourceId = element.data.meta.folderId;
                            element.data.meta.eventGrouping = self.toMoment(element.start).format('YYYY') + String(self.toMoment(element.start).isoWeek()).padStart(2,0) + element.resourceId;
                        });

                        response.data.forEach(function(element) {
                            // Create a place holder
                            if (self.currentViewIsSemester() && eventGroupingCursor !== element.data.meta.eventGrouping) {

                                var itemCountByGroup = response.data.filter(function(val) {
                                    return val.data.meta.eventGrouping === element.data.meta.eventGrouping;
                                }).length;

                                newResponse.push({
                                    allDay: true,
                                    backgroundColor: (element.data.meta.colour) ? element.data.meta.colour : self.defaultBgColour,
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
                                            weekNo: self.toMoment(element.start).isoWeek(),
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
                    eventGrouping = eventRenderInfo.event.extendedProps.data.meta.eventGrouping,
                    backgroundColour = (eventRenderInfo.event.extendedProps.data.meta.colour) ? eventRenderInfo.event.extendedProps.data.meta.colour : self.defaultBgColour,
                    element = eventRenderInfo.el;

                if (!self.eventGroups.includes(eventGrouping)) {
                    self.eventGroups.push(eventGrouping);
                }

                if (eventRenderInfo.event.extendedProps.data.placeholder) {
                    element.setAttribute('event-group-id-placeholder', eventGrouping);
                    element.classList.add('placeholder');
                    element.classList.add('fc-more');
                } else {
                    if (self.currentViewIsSemester()) {
                        element.style.width = '95%';
                    }
                    element.setAttribute('event-group-id', eventGrouping);
                    element.setAttribute('event-id', eventRenderInfo.event.extendedProps.data.meta.eventId);
                    element.setAttribute('event-group-date', self.toMoment(eventRenderInfo.event.start).format('MMMM DD, YYYY'));
                    element.style.backgroundColor = backgroundColour;
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

                if (!self.currentViewIsSemester() ||
                    (self.currentViewIsSemester() && eventInfo.el.getAttribute('event-group-id-placeholder') === null)) {
                    // Ideally the current event would be a part of the modal,
                    // not this component, but :shrug:
                    this.currentEvent = eventInfo.event;
                    this.$refs.event.open();
                } else {
                    this.renderPopOver(eventInfo);

                    // Add event listener for our customised popover
                    document.querySelector(".fc-close").addEventListener('click', function (element) {
                        self.destroyPopOver();
                    });
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
                popOver.setAttribute('style', 'left:' + eventInfo.el.offsetLeft + 'px;top:' + resourceCol[0].offsetTop +'px;z-index:1000;padding:5px');
                popOver.className = 'fc-popover fc-more-popover';

                popOverHeader = '<div class="fc-header fc-widget-header">' +
                    '<span class="fc-title">Week '+ eventInfo.event.extendedProps.data.meta.weekNo +'</span>' +
                    '<span class="fc-close fc-icon fc-icon-x"></span></div>';

                eventGroup.forEach(function(group, index) {
                    var event = eventGroup[index];
                        event.style.removeProperty('left');
                        event.style.removeProperty('right');
                        event.style.removeProperty('top');

                    tmpEvent.push(event.outerHTML);
                });

                tmpEvent.forEach(function(event, index) {
                    var tmpPopOverEvent = $(tmpEvent[index])[0],
                        popOverContentDate = '';

                    tmpPopOverEvent.classList.remove('hide', 'fc-draggable', 'fc-resizable');

                    if (tmpPopOverEvent.getAttribute('event-group-date') !== dateGroup) {
                        dateGroup = tmpPopOverEvent.getAttribute('event-group-date');
                        popOverContentDate = '<p style="font-weight: bold; margin-bottom: 0">' + dateGroup + '</ p>';
                    }

                    popOverContent += popOverContentDate + '<div class="fc-timeline-event-div" style="padding-bottom:40px;display:flex">' + tmpPopOverEvent.outerHTML + '</div>';
                });

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

                self.destroyPopOver();

                if (self.currentViewIsSemester()) {
                    self.redrawWeekNumbers();
                    self.cleanupMonthHeaderInlineStyles();
                }

                self.maxDate = self.getMaxTermDate();
                self.minDate = self.getMinTermDate();
            },

            /**
             * Customise next button behaviour for semester view. Clicking next button while on semester view will
             * navigate into the next available semester
             */
            clickNext() {
                var self = this;

                if (self.currentViewIsSemester()) {
                    if ((self.cursor < 0) || (self.cursor === 0 && self.config.termDates.future.length) ||
                        (self.cursor > 0 && self.config.termDates.future[Math.abs(self.cursor)])) {
                        self.cursor++;
                        var startDateByCursor = self.getStartDateByCursor();

                        if (startDateByCursor !== undefined) {
                            self.$refs.fullCalendar.getApi().gotoDate(startDateByCursor);
                        }
                    }

                    self.redrawWeekNumbers();
                    self.cleanupMonthHeaderInlineStyles();
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

                if (self.currentViewIsSemester()) {
                    if ((self.cursor > 0) || (self.cursor === 0 && self.config.termDates.past.length) ||
                        (self.cursor < 0 && self.config.termDates.past.length >= Math.abs(self.cursor) + 1)) {

                        self.cursor--;

                        var startDateByCursor = self.getStartDateByCursor();

                        if (startDateByCursor !== undefined) {
                            self.$refs.fullCalendar.getApi().gotoDate(startDateByCursor);
                        }

                        self.redrawWeekNumbers();
                        self.cleanupMonthHeaderInlineStyles();
                    }
                } else {
                    self.$refs.fullCalendar.getApi().prev();
                }
            },

            clickToday() {
                var self = this;

                if (self.currentViewIsSemester()) {
                    self.$refs.fullCalendar.getApi().gotoDate(self.config.termDates.current.start);

                    self.redrawWeekNumbers();
                    self.cleanupMonthHeaderInlineStyles();
                } else {
                    self.$refs.fullCalendar.getApi().gotoDate(self.toMoment().format('YYYY-MM-DD'));
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
                var self = this,
                    termStartDates = Object.values(self.config.termDates).flat().map(function(row){
                    return new Date(row.end);
                });

                return self.toMoment(new Date(Math.max.apply(null,termStartDates))).format('YYYY-MM-DD');
            },

            /**
             * Get the earliest available term start date for fullCalendar's validRange config.
             * @see: https://fullcalendar.io/docs/validRange
             */
            getMinTermDate() {
                var self = this,
                    termStartDates = Object.values(self.config.termDates).flat().map(function(row){
                    return new Date(row.start);
                });

                return self.toMoment(new Date(Math.min.apply(null,termStartDates))).format('YYYY-MM-DD');
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

            onDateClick(dateClickInfo) {
                if (dateClickInfo.view.type === VIEW_RESOURCE_TIMELINE_SEMESTER) {
                    this.destroyPopOver();
                }
            },

            redrawWeekNumbers() {
                document.querySelectorAll('.fc-time-area .fc-content tr')[1].querySelectorAll('.fc-cell-text').forEach(function (element, index) {
                    element.innerHTML = index + 1;
                });
            },

            redrawCalendar() {
                var self = this;

                if (self.currentViewIsSemester()) {
                    this.redrawWeekNumbers();
                    this.redrawEventRows();
                }
            },

            datesRender(datesRenderInfo) {
                let todayButton = this.$el.querySelector('button.fc-customToday-button'),
                    nextButton  = this.$el.querySelector('button.fc-customNext-button'),
                    prevButton  = this.$el.querySelector('button.fc-customPrev-button'),
                    start       = datesRenderInfo.view.currentStart,
                    end         = datesRenderInfo.view.currentEnd,
                    now         = new Date();

                // Should the Today button be enabled?
                todayButton.disabled = (now >= start && now < end);

                // What about next/prev?
                if (this.currentViewIsSemester()) {
                    // Next: disable if there is no next
                    // Prev: disable if there is no prev
                    let currentIndex    = this.findTermIndex(this.toMoment(start).format('YYYY-MM-DD'));
                    nextButton.disabled = (currentIndex === this.allTerms.length - 1);
                    prevButton.disabled = (currentIndex === 0);
                } else {
                    nextButton.disabled = false;
                    prevButton.disabled = false;
                }
            },

            cleanupMonthHeaderInlineStyles() {
                document.querySelectorAll('.fc-time-area .fc-content tr')[0].querySelectorAll('div').forEach(function (element) {
                    element.removeAttribute('style');
                    element.querySelector('span').removeAttribute('style');
                });
            },

            redrawEventRows() {
                document.querySelectorAll('.fc-resourceTimelineSemester-view .fc-time-area [data-resource-id]').forEach(function (element, index) {
                    var resourceColHeight = document.querySelector('.fc-resourceTimelineSemester-view .fc-resource-area [data-resource-id="'+ element.getAttribute('data-resource-id') +'"]').offsetHeight;

                    element.querySelector('div').style.height = resourceColHeight + 'px';

                    element.querySelectorAll('div .placeholder').forEach(function (placeholders) {
                        placeholders.style.height = (resourceColHeight - 3) + 'px';
                    })
                });
            },

            /**
             * Check if the current calendar view is in semester
             * @returns {boolean}
             */
            currentViewIsSemester() {
                return this.currentView === VIEW_RESOURCE_TIMELINE_SEMESTER;
            },

            /**
             * Determine which visible range is shown on the semester view.
             *
             * @param currentDate A Date object matching the date to show
             * @return {*} An object which can be passed to FullCalendar's
             *             `visibleRange`, or null if if found nothing.
             */
            determineVisibleRange(currentDate) {
                // Find the range in the list of terms.
                // (Need to go to these lengths to create a date, as calendar
                //  and thus moment isn't initialized yet.
                //  Need to feed the date in as UTC because toISOString outputs
                //  date in UTC)
                let actualDate = new Date(Date.UTC(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate())),
                    dateString = actualDate.toISOString().slice(0, 10);

                return this.allTerms[this.findTermIndex(dateString)] || null;
            },

            /**
             * Builds the list of all terms passed into `this.config.termDates`
             * into a single array, ordered by start then end date.
             *
             * @return {Array}
             */
            buildAllTermsList() {
                return this.config.termDates.past.concat([this.config.termDates.current], this.config.termDates.future)
                    .sort(function (a, b) {
                        if (a.start < b.start) {
                            return -1;
                        }
                        if (a.start > b.start) {
                            return 1;
                        }
                        if (a.end < b.end) {
                            return -1;
                        }
                        if (a.end > b.end) {
                            return 1;
                        }
                        return 0;
                    });
            },

            /**
             * Find the index of the term within `this.termDates` which contains
             * `dateString`.
             *
             * @param dateString A date to find the index for, in "YYYY-MM-DD"
             *                   format
             * @return {Number} The index of the term within `this.termDates`,
             *                  or -1 if not found.
             */
            findTermIndex(dateString) {
                for (let i in this.allTerms) {
                    if (!this.allTerms.hasOwnProperty(i)) {
                        continue;
                    }
                    let term = this.allTerms[i];
                    // TODO end times are exclusive: account for that
                    if (dateString >= term.start && dateString <= term.end) {
                        return Number(i);
                    }
                }
                return -1;
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
            default-view="resourceTimelineSemester"
            :plugins="calendarPlugins"
            :views="{
                resourceTimelineSemester: {
                    type: 'resourceTimeline',
                    visibleRange: this.determineVisibleRange,
                    slotDuration: { weeks: 1 },
                    weekNumbers: true,
                    slotLabelFormat: [
                        'MMMM',
                        'w'
                    ],
                    resourceAreaWidth: '15%',
                    filterResourcesWithEvents: true,
                    validRange: {
                        start: this.minDate,
                        end: this.maxDate,
                    },
                    resourceLabelText: 'Week'
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
                },
                dayGridMonth: {
                    eventLimit: config.eventLimit,
                }
            }"
            :week-label="''"
            :event-time-format="helper.eventTimeFormat"
            :header="{
                left: 'customToday,customPrev,customNext',
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
                customNext: {
                    icon: 'chevron-right',
                    click: this.clickNext.bind(this),
                },
                customPrev: {
                    icon: 'chevron-left',
                    click: this.clickPrev.bind(this)
                },
                customToday: {
                    text: 'Today',
                    click: this.clickToday.bind(this)
                }
            }"
            :scroll-time="config.dateDisplayFormats.calendarStartTime"
            :event-render="eventRender"
            :events="events"
            :resources="this.newResources"
            :scheduler-license-key="config.schedulerLicenseKey"
            :first-day="1"
            :default-date="this.termStartDate"
            :view-skeleton-render="viewSkeletonRender"
            :week-number-calculation="'ISO'"
            :content-height="this.contentHeight"
            @loading="onLoading"
            @eventClick="onEventClick"
            @dateClick="onDateClick"
            @eventPositioned="redrawCalendar"
            @windowResize="redrawCalendar"
            :dates-render="datesRender"
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

    // Please forgive me...

    .fc-resourceTimelineSemester-view .fc-timeline-event .fc-title-wrap {
        text-align: center !important;
    }

    .fc-resourceTimelineSemester-view .fc-timeline-event.placeholder .fc-title-wrap {
        padding: 5px;
        font-size: .8rem;
        line-height: 1.7;
    }

    .placeholder .fc-title {
        padding-left: 0 !important;
        margin-left: 0 !important;
    }

    .fc-timeline-event, .fc-day-grid-event {
        border: 0 !important;
    }

    .fc-event .fc-title, .fc-event .fc-title:hover {
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

    .fc-resourceTimelineSemester-view .fc-event-container .fc-timeline-event:not(.placeholder),
    .fc-dayGridMonth-view .placeholder,
    .fc-resourceTimelineWeek-view .placeholder {
        display: none;
    }

    .fc-resourceTimelineSemester-view .fc-resource-area .fc-widget-content div {
        height: auto !important;
    }

    .fc-resourceTimelineSemester-view .fc-head tr td:first-child .fc-widget-header div {
        position: absolute !important;
    }

</style>
