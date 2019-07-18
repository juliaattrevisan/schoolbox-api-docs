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
            window: function () { return window; }
        }
    }

</script>

<template>
    <div>
        <FullCalendar
            default-view="dayGridMonth"
            :views="{
                timeGridDay: {
                    // dateDisplayFormats.momentJsDateLong: figure out how to inject this
                    titleFormat: window.dateDisplayFormats.momentJsDateLong
                },
                timeGridWeek: {
                    columnFormat: 'ddd D/M'
                }
            }"
            event-time-format="h(:mm)a"
            :custom-buttons="{
                customExport: {
                    text: 'export',
                    click: function () {
                        window.$('#calendar-export').foundation('reveal', 'open');
                    }
                },
                customFilters: {
                    text: 'filters',
                    click: function () {
                        window.$('#calendar-controls').foundation('reveal', 'open');
                    }
                },
            }"
            :header="{
                left: 'prev,today,next',
                center: 'title',
                right: 'customFilters,dayGridMonth,timeGridWeek,timeGridDay,customExport'
            }"
            :plugins="calendarPlugins"
        />
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
</style>
