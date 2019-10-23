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

    // Various URLs this calendar can make use of
    const URL_ASSESSMENT_EVENT_SOURCE = '/learning/assessment/calendar/events';
    const URL_EVENT_UPDATE = '/calendar/ajax/update';
    const URL_EVENT_MODIFY_FORM = '/calendar/modify.php';
    const URL_EVENT_MODIFY_FORM_ACTION = '/calendar/modifyForm.php';
    const URL_ATTENDANCE_LIST = '/calendar/attendance/list/';
    const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';

    const FORMAT_FORM_DATE = 'DD-MM-YYYY';
    const FORMAT_FORM_DATETIME = 'DD-MM-YYYY HH:mm';

    // TODO let display formats be configurable
    const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
    const FORMAT_DISPLAY_TIME = 'h:mma';
    const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';
    const FORMAT_DISPLAY_WEEK_DAY = 'ddd D/M';

    const VIEW_RESOURCE_TIMELINE_SEMESTER = 'resourceTimelineSemester';

    const RESOURCE_FOLDER_PREFIX = 'folder-';
    const RESOURCE_EVENT_TYPE_PREFIX = 'eventType-';

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
            this.eventTypeResources = this.buildEventTypeResourcesList();
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, resourceTimelinePlugin, momentPlugin ],
                currentEvent: {},
                newEvent: {},
                selectedFilters: null,
                eventTypeResources: [],
                folderResources: [],
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
            urls() {
                return {
                    modifyForm: URL_EVENT_MODIFY_FORM,
                    modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
                    update: URL_EVENT_UPDATE,
                    attendanceList: URL_ATTENDANCE_LIST,
                    attendanceToggle: URL_ATTENDANCE_TOGGLE,
                };
            },
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
                let self = this;

                // No filters loaded yet? Render nothing.
                // The selected filters get populated by `onFilterChange`:
                // this is called by the filter's `filterContentLoaded` trigger.
                if (self.selectedFilters === null) {
                    successCallback([]);
                    return;
                }

                // AJAX params: the same for all requests
                let ajaxParams = {
                    headers: {
                        'X-Requested-With' : 'XMLHttpRequest'
                    },
                    params: {
                        start:   Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                        end:     Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                        filters: self.selectedFilters,
                    },
                    paramsSerializer: params => stringify(params, {arrayFormat: 'brackets'}),
                };

                axios.get(URL_ASSESSMENT_EVENT_SOURCE, ajaxParams).then(
                    function (response) {
                        // Massage the data to inject placeholders
                        let newResponse = [],
                            eventGroupingCursor = ''

                        // Assign event grouping and resourceIds into every event
                        response.data = response.data.map(function(element) {
                            // Consider not allowing events to be editable on this calendar?
                            // element.editable = false;
                            // element.data.meta.editable = false;
                            // What is the resource ID?
                            // It depends on whether the event has a folder.
                            element.resourceId = self.buildResourceIdFromEvent(element);
                            if (element.resourceId) {
                                element.data.meta.eventGrouping =
                                    self.toMoment(element.start).format('YYYY')
                                    + String(self.toMoment(element.start).isoWeek()).padStart(2, 0)
                                    + element.resourceId;
                            }
                            return element;
                        });

                        response.data.forEach(function(element) {
                            // Create a place holder
                            if (self.currentViewIsSemester() && eventGroupingCursor !== element.data.meta.eventGrouping) {

                                let itemCountByGroup = response.data.filter(function(val) {
                                    return val.data.meta.eventGrouping === element.data.meta.eventGrouping;
                                }).length;

                                newResponse.push({
                                    allDay: true,
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

                            // Add new resource here too
                            if (!self.folderResources.some(el => el.id === element.data.meta?.folderId)) {
                                self.folderResources.push({
                                    id:                   element.resourceId,
                                    title:                element.data.meta.folderName,
                                    eventBackgroundColor: element.data.meta.colour || self.defaultBgColour,
                                });
                            }

                            eventGroupingCursor = element.data.meta.eventGrouping;
                            newResponse.push(element);
                        });

                        // Reload resources here too
                        self.$refs.fullCalendar.getApi().refetchResources();

                        // Filter out events which don't start
                        successCallback(newResponse.filter(v => 'start' in v));
                    }
                );
                // Fail callback: consider a message saying something failed
            },

            /**
             * Resource source.
             * @see https://fullcalendar.io/docs/resources-function
             *
             * @param fetchInfo       Unused, but contains start/end of period
             *                        to fetch resources for.
             * @param successCallback A callback that is called with the list of
             *                        resources.
             */
            resources(fetchInfo, successCallback) {
                successCallback([
                    {
                        title: 'Events',
                        children: this.eventTypeResources,
                        eventAllow: false,
                    },
                    {
                        title: 'Assessments',
                        children: this.folderResources,
                        eventAllow: false,
                    },
                ]);
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
                        element.style.width = 'auto';
                    }
                    element.setAttribute('event-group-id', eventGrouping);
                    element.setAttribute('event-id', eventRenderInfo.event.extendedProps.data.meta.eventId);
                    element.setAttribute('event-group-date', self.toMoment(eventRenderInfo.event.start).format('MMMM DD, YYYY'));
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

                let popOver = document.getElementById('popOver'),
                    popOverContent = '',
                    popOverHeader = '',
                    dateGroup = '',
                    resources = eventInfo.event.getResources();

                if (resources.length === 0) {
                    return;
                }

                let resourceId = resources[0].id,
                    eventGroupId = eventInfo.event.extendedProps.data.meta.eventGrouping,
                    eventGroup = document.querySelectorAll("[event-group-id='"+ eventGroupId +"']"),
                    resourceCol = document.querySelectorAll("[data-resource-id='"+ resourceId +"']"),
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
                let api = this.$refs.fullCalendar.getApi();
                if (this.currentViewIsSemester()) {
                    // Figure out which term I'm in: go to the next one
                    let termIndex = this.findTermIndex(this.convertDateToDateString(api.getDate()));
                    if (this.allTerms[termIndex+1]) {
                        api.gotoDate(this.allTerms[termIndex+1].start);
                        this.redrawWeekNumbers();
                        this.cleanupMonthHeaderInlineStyles();
                    }
                } else {
                    api.next();
                }
            },

            /**
             * Customise previous button behaviour for semester view. Clicking previous button while on semester view will
             * navigate into the previous available semester
             */
            clickPrev() {
                let api = this.$refs.fullCalendar.getApi();
                if (this.currentViewIsSemester()) {
                    // Figure out which term I'm in: go to the previous one
                    let termIndex = this.findTermIndex(this.convertDateToDateString(api.getDate()));
                    if (this.allTerms[termIndex-1]) {
                        api.gotoDate(this.allTerms[termIndex-1].start);
                        this.redrawWeekNumbers();
                        this.cleanupMonthHeaderInlineStyles();
                    }
                } else {
                    api.prev();
                }
            },

            /**
             * Customise the behaviour for month view button. With this in place, once the user clicked on month button,
             * the initial month view will go to the current date
             */
            clickMonthView() {
                this.switchView('dayGridMonth');
            },

            /**
             * Customise the behaviour for week view button. With this in place, once the user clicked on month button,
             * the initial month view will go to the current date
             */
            clickWeekView() {
                this.switchView('resourceTimelineWeek');
            },

            /**
             * Customise the behaviour for week view button. With this in place, once the user clicked on month button,
             * the initial month view will go to the current date
             */
            clickSemesterView() {
                this.switchView('resourceTimelineSemester');
            },

            /**
             * Custom method for switching calendar view
             * @param viewType
             */
            switchView(viewType) {
                let self = this,
                    api = self.$refs.fullCalendar.getApi();

                /**
                 * Since clicking of month and week view needs to be customised, so that initial loading of month or week
                 * view goes to the current date.
                 *
                 * We need to do this trick so that we'll be able to show the loading pop-up.
                 * And without this, you'll notice that there's a delay before the switch happens without any indicator
                 * that the calendar is still being rendered in the background.
                 *
                 * You'll be able to replicate it by just removing all the lines below except for line 519 and 522.
                 */
                new Promise(function(resolve) {
                    self.onLoading(true);
                    setTimeout(() => resolve(1), 1000);
                }).then(function(result) {
                    // Semester view, will just use the term start date that's set on page load
                    if (viewType !== VIEW_RESOURCE_TIMELINE_SEMESTER) {
                        api.gotoDate(self.convertDateToDateString(new Date()));
                    }

                    api.changeView(viewType);

                    return result;
                }).then(function(result) {
                    self.onLoading(false);

                    return result;
                });
            },

            /**
             * Customise today button behaviour for semester view.
             *
             * Clicking today button while on semester view will navigate to
             * today, only if today is in a semester.
             */
            clickToday() {
                let api = this.$refs.fullCalendar.getApi();
                if (this.currentViewIsSemester()) {
                    // Figure out if today is in a term before going to it
                    let termIndex = this.findTermIndex(this.convertDateToDateString(new Date()));
                    if (this.allTerms[termIndex]) {
                        api.today();
                        this.redrawWeekNumbers();
                        this.cleanupMonthHeaderInlineStyles();
                    }
                } else {
                    api.today();
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

            /**
             * Callback when a date is rendered (i.e. the view or viewed date
             * changes).
             *
             * This callback enables/disabled prev/next/today buttons based on
             * whether they're available.
             */
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
                    // Next:  disable if there is no next
                    // Prev:  disable if there is no prev
                    // Today: disable if today isn't within a term
                    let currentIndex    = this.findTermIndex(this.toMoment(start).format('YYYY-MM-DD'));
                    nextButton.disabled = (currentIndex === this.allTerms.length - 1);
                    prevButton.disabled = (currentIndex === 0);
                    if (!todayButton.disabled) {
                        let todayIndex       = this.findTermIndex(this.toMoment(now).format('YYYY-MM-DD'));
                        todayButton.disabled = (todayIndex === -1);
                    }
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
                let dateString = this.convertDateToDateString(currentDate),
                    termIndex  = this.findTermIndex(dateString);

                // Ensure that visible range starts on Monday (round down),
                // and ends on Monday (round up), all without using Moment
                // (Not initialized on initial calendar load)
                if (this.allTerms[termIndex]) {
                    let startDate = new Date(this.allTerms[termIndex].start),
                        endDate   = new Date(this.allTerms[termIndex].end),
                        startDay  = startDate.getDay() || 7,
                        endDay    = endDate.getDay() || 7;

                    // Round start down to previous Monday
                    if (startDay !== 1) {
                        startDate.setHours(-24 * (startDay - 1));
                    }

                    // Round end up to next Monday
                    if (endDay !== 1) {
                        endDate.setHours(24 * (8 - endDay));
                    }

                    return {
                        start: startDate,
                        end:   endDate,
                    };
                }

                return null;
            },

            /**
             * Builds the list of all terms passed into `this.config.termDates`
             * into a single array, ordered by start then end date.
             *
             * @return {Array}
             */
            buildAllTermsList() {
                let self = this;
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
                    })
                    .map(function (term) {
                        let end = new Date(term.end);
                        end.setDate(end.getDate() + 1);
                        term.end = self.convertDateToDateString(end);
                        return term;
                    });
            },


            /**
             * Convert a Date object to a `YYYY-MM-DD` format string without
             * MomentJS.
             *
             * Required because in this component's  `create` method, the
             * calendar and thus MomentJS won't yet be initialized.
             *
             * @param {Date} date
             *
             * @return {string}
             */
            convertDateToDateString(date) {
                // Need to feed the date in as UTC because toISOString outputs
                // date in UTC.
                let actualDate = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
                return actualDate.toISOString().slice(0, 10);
            },


            /**
             * Builds the list of all event type resources, based on
             * `this.config.eventTypes`.
             *
             * @return {Array}
             */
            buildEventTypeResourcesList() {
                return this.config.eventTypes.map(function (a) {
                    return {
                        id:                   RESOURCE_EVENT_TYPE_PREFIX + String(a.id),
                        title:                a.type,
                        eventBackgroundColor: a.color,
                    }
                });
            },

            /**
             * Build a resource ID out of the event.
             *
             * @param {object} event The event to be rendered
             *
             * @return {string|null} The resource ID, or null if it has none
             */
            buildResourceIdFromEvent(event) {
                if (event?.data?.meta?.folderId) {
                    return RESOURCE_FOLDER_PREFIX + String(event.data.meta.folderId);
                }
                if (event?.data?.meta?.eventTypeId) {
                    return RESOURCE_EVENT_TYPE_PREFIX + String(event.data.meta.eventTypeId);
                }
                return null;
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
                    if (dateString >= term.start && dateString < term.end) {
                        return Number(i);
                    }
                }
                return -1;
            },

            /**
             * Callback for reloading the calendar's events. May be used when
             * a series of events is deleted, as the calendar no longer knows
             * whether an event should be rendered even if it wasn't deleted.
             * @see https://fullcalendar.io/docs/Calendar-refetchEvents
             */
            reloadEvents() {
                this.$refs.fullCalendar.getApi().refetchEvents();
            },
        }
    }
</script>

<template>
    <div>
        <view-event-modal ref="event"
                          return-to="assessmentCalendar"
                          :event="currentEvent"
                          :to-moment="toMoment"
                          :event-modify-form-url="urls.modifyForm"
                          :event-modify-form-action-url="urls.modifyFormAction"
                          :event-update-url="urls.update"
                          :attendance-list-url="urls.attendanceList"
                          :attendance-toggle-url="urls.attendanceToggle"
                          :format-display-date="formats.displayDate"
                          :format-display-time="formats.displayTime"
                          :format-display-datetime="formats.displayDatetime"
                          @eventModified="reloadEvents"
                          @eventDeleted="reloadEvents"
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
                        'MMM',
                        'w'
                    ],
                    resourceAreaWidth: '15%',
                    filterResourcesWithEvents: true,
                    validRange: {
                        start: this.minDate,
                        end: this.maxDate,
                    },
                    resourceLabelText: 'Week',
                    slotWidth: '50'
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
                }
            }"
            :week-label="''"
            :event-time-format="helper.eventTimeFormat"
            :header="{
                left: 'customToday,customPrev,customNext',
                center: 'title',
                right: 'resourceTimelineSemester,customDayGridMonth,customResourceTimelineWeek'
            }"
            :buttonText="{
                today:'Today',
                resourceTimelineSemester:'Semester',
            }"
            :custom-buttons="{
                customNext: {
                    icon: 'chevron-right',
                    click: this.clickNext.bind(this),
                },
                customPrev: {
                    icon: 'chevron-left',
                    click: this.clickPrev.bind(this),
                },
                customToday: {
                    text: 'Today',
                    click: this.clickToday.bind(this),
                },
                customDayGridMonth: {
                    eventLimit: config.eventLimit,
                    text: 'Month',
                    click: this.clickMonthView.bind(this),
                },
                customResourceTimelineWeek: {
                    text: 'Week',
                    click: this.clickWeekView.bind(this),
                },
                resourceTimelineSemester: {
                    text: 'Semester',
                    click: this.clickSemesterView.bind(this),
                }
            }"
            :scroll-time="config.dateDisplayFormats.calendarStartTime"
            :event-render="eventRender"
            :events="events"
            :resources="resources"
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

    .fc-ltr .fc-timeline-event .fc-title {
         padding-left: 3px !important;
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

    .fc-timeline-event-div {
        padding-bottom: 2px !important;
    }

    .fc-resourceTimelineSemester-view .fc-popover .fc-timeline-event {
        padding: 0 !important;
        position: relative;
        width: 100% !important;
    }

    .fc-resourceTimelineSemester-view .fc-event-container > .fc-timeline-event {
        position: absolute !important;
        top: 1px !important;
        height: 31px !important;
    }

    .fc-resourceTimelineSemester-view .fc-event-container .fc-timeline-event:not(.placeholder),
    .fc-dayGridMonth-view .placeholder,
    .fc-resourceTimelineWeek-view .placeholder {
        display: none;
    }

    .fc-resourceTimelineSemester-view .fc-resource-area .fc-widget-content div {
        height: 35px !important;
    }

    .fc-resourceTimelineSemester-view .fc-time-area .fc-rows .fc-widget-content div {
        height: 35px !important;
    }

    .fc-resourceTimelineSemester-view .fc-head tr td:first-child .fc-widget-header div {
        position: absolute !important;
    }

    .fc-time {
        color: #fff;
    }

    .fc-resourceTimelineSemester-view span.fc-cell-text.fc-sticky {
        left: 0 !important;
    }

    .fc-resourceTimelineSemester-view .fc-widget-header .fc-cell-content {
        text-align: center !important;
    }

    .fc-dayGridMonth-view a.fc-timeline-event,
    .fc-resourceTimelineWeek-view a.fc-timeline-event {
        width: auto !important;
    }

    .fc-unthemed .fc-popover .fc-header .fc-close {
        margin: 0 !important;
    }

    .fc-icon-x::before {
        display: none !important;
        content: none !important;
    }

    .fc-icon-x::after {
        color: #000 !important;
        font-size: 1.5rem !important;
        top: 0 !important;
        right: 1px !important;
        line-height: 0 !important;
    }
</style>
