<script>

    import FullCalendar      from '@fullcalendar/vue'
    import interactionPlugin from '@fullcalendar/interaction'
    import dayGridPlugin     from '@fullcalendar/daygrid'
    import timeGridPlugin    from '@fullcalendar/timegrid'
    import listPlugin        from '@fullcalendar/list'
    import momentPlugin      from '@fullcalendar/moment'
    import RevealModal       from '@schoolbox/reveal-modal'

    export default {
        components: {
            FullCalendar,
            RevealModal
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, momentPlugin ]
            }
        },
        computed: {
            window: function () { return window; },
            config: function () { return window.fullCalendarConfig; }
        },
        methods: {
            onExportClick: function () {
                this.$refs.export.open();
            },
            onFilterClick: function () {
                this.$refs.filter.open();
            },
            onLoading:  function (isLoading) {
                /* Show loading div right in the middle
                 */
                if (isLoading === true) {
                    window.$.magnificPopup.open({
                        showCloseBtn: false,
                        items: {
                            src: "<div class='preview loader'></div>",
                            type: 'inline'
                        }
                    });
                }
                /* Has finished loading
                 */
                else {
                    /* Hide loading div
                     */
                    window.$.magnificPopup.close();

                    // // update any tasks that have been completed by strikethrough-ing them
                    // $(this).find('input[type=checkbox]').each(function () {
                    //     var self = $(this);
                    //
                    //     if (self.prop('checked')) {
                    //         self.siblings('.fc-title').addClass('strike')
                    //     }
                    // });
                    //
                    // // // reapply filters
                    // // c.filters.organise(c, ["campus", "groups", "sources"], false);
                    //
                    // // update datepicker
                    // $('#datepicker').datepicker('setDate', FullCalendarMoment.toMoment(this.getDate(), this).format('DD-MM-YYYY'))
                }
            },
            events: function (fetchInfo, callback) {
                var self = this;
                window.$.ajax({
                    dataType: 'json',
                    url: '/calendar/ajax/full',
                    data: {
                        start:  Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                        end:    Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                        userId: self.config.userId
                    },
                    success: function (data) {
                        callback(data.filter(function (v) {
                            return 'start' in v;
                        }));

                        // There should be no need to preserve state change on loading events
                        // /* Save state to the db every time you change tabs
                        //  */
                        // // TODO restore this
                        // $.post('/calendar/changeState', {calendarView: fc.view.type});
                    },
                    cache: false
                });
            },
            onDateClick: function (dateClickInfo) {
                // Create a bubble here (needs to be a Vue modal)
                console.log(dateClickInfo);
            },
            onEventClick: function (eventInfo) {
                // Create a bubble here (needs to be a Vue modal)
                console.log(eventInfo);
            },
            onEventResize: function (eventResizeInfo) {
                console.log(eventResizeInfo);
            },
            onEventDrop: function (eventDropInfo) {
                console.log(eventDropInfo);
            }
        }
    }

</script>

<template>
    <div>
        <reveal-modal ref="export">
            bbb
        </reveal-modal>

        <reveal-modal ref="filter">
            ccc
        </reveal-modal>

        <full-calendar
            :plugins="calendarPlugins"
            :views="{
                timeGridDay: {
                    // dateDisplayFormats.momentJsDateLong: figure out how to inject this
                    titleFormat: config.dateDisplayFormats.momentJsDateLong
                },
                timeGridWeek: {
                    columnFormat: 'ddd D/M'
                }
            }"
            event-time-format="h(:mm)a"
            content-height="auto"
            :custom-buttons="{
                customExport: {
                    text: 'export',
                    click: onExportClick
                },
                customFilters: {
                    text: 'filters',
                    click: onFilterClick
                },
            }"
            :header="{
                left: 'prev,today,next',
                center: 'title',
                right: 'customFilters,dayGridMonth,timeGridWeek,timeGridDay,customExport'
            }"
            :default-date="config.defaultDate"
            :default-view="config.anchorView || 'dayGridMonth'"
            :scroll-time="config.dateDisplayFormats.calendarStartTime"
            :event-limit="config.eventLimit"
            :first-day="config.firstDay"
            :events="events"
            @loading="onLoading"
            @dateClick="onDateClick"
            @eventClick="onEventClick"
            @eventResize="onEventResize"
            @eventDrop="onEventDrop"
        />
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
</style>
