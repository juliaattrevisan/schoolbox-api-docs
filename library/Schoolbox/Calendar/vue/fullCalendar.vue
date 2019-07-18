<script>

    import FullCalendar      from '@fullcalendar/vue'
    import interactionPlugin from '@fullcalendar/interaction'
    import dayGridPlugin     from '@fullcalendar/daygrid'
    import timeGridPlugin    from '@fullcalendar/timegrid'
    import listPlugin        from '@fullcalendar/list'
    import momentPlugin      from '@fullcalendar/moment'

    export default {
        components: {
            FullCalendar // make the <FullCalendar> tag available
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
            customExportClick: function () {
                window.$('#calendar-export').foundation('reveal', 'open');
            },
            customFiltersClick: function () {
                window.$('#calendar-controls').foundation('reveal', 'open');
            },
            loading: function (isLoading) {
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
            }
        }
    }

</script>

<template>
    <div>
        <FullCalendar
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
            :custom-buttons="{
                customExport: {
                    text: 'export',
                    click: customExportClick
                },
                customFilters: {
                    text: 'filters',
                    click: customFiltersClick
                },
            }"
            :header="{
                left: 'prev,today,next',
                center: 'title',
                right: 'customFilters,dayGridMonth,timeGridWeek,timeGridDay,customExport'
            }"
            :defaultDate="config.defaultDate"
            :defaultView="config.anchorView || 'dayGridMonth'"
            :scrollTime="config.dateDisplayFormats.calendarStartTime"
            :eventLimit="config.eventLimit"
            :firstDay="config.firstDay"
            :events="events"
            @loading="loading"
        />
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
</style>
