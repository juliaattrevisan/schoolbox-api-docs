<script>
    import RevealModal from '@schoolbox/reveal-modal'

    export default {
        name: "export-modal",
        components: {RevealModal},
        methods: {
            open() {
                this.$refs.modal.open();
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <form class="top" action="#" id="calendar-export-form">
            <section>
                <fieldset class="content radiolist">

                    <input type="radio" name="export" value="all" id="export-all" checked />
                    <label for="export-all">All events</label>

                    <input type="radio" name="export" value="personal" id="export-personal" />
                    <label for="export-personal">Personal events only</label>

                    <input type="radio" name="export" value="timetable" id="export-timetable" />
                    <label for="export-timetable">Timetable classes</label>

                    <input type="radio" name="export" value="timetabledays" id="export-timetableday" />
                    <label for="export-timetableday">Timetable days</label>

                    <!-- need to pass in translation table? -->
                    <input type="radio" name="export" value="duework" id="export-duework" />
                    <label for="export-duework">{TPL_LOCAL_DUEWORK_PC}</label>

                    <input type="radio" name="export" value="resources" id="export-resources" />
                    <label for="export-resources">Resource Booking</label>

                    <input type="radio" name="export" value="external" id="export-external" />
                    <label for="export-external">External events</label>

                    <!-- need to pass in translation table + EOTC status -->
                    <input type="radio" name="export" value="eotc_all" id="export-eotc-all" />
                    <label for="export-eotc-all">{TPL_LOCAL_EOTC_PC}</label>

                    <!-- need to pass in campi, groups, types -->
                    <input type="radio" name="export" value="campus" id="export-campus" />
                    <label for="export-campus">Events for campus:
                        <select name="campus">
                            <option value="">-- select --</option>
                            <!-- BEGIN export_campus -->
                            <option label="{EXPORT_CAMPUS_NAME}" value="{EXPORT_CAMPUS_ID}">{EXPORT_CAMPUS_NAME}</option>
                            <!-- END export_campus -->
                        </select>
                    </label>

                    <input type="radio" name="export" value="group" id="export-group" />
                    <label for="export-group">Events for group:
                        <select name="group">
                            <option value="">-- select --</option>
                            <!-- BEGIN export_group -->
                            <option label="{EXPORT_GROUP_NAME}" value="{EXPORT_GROUP_ID}">{EXPORT_GROUP_NAME}</option>
                            <!-- END export_group -->
                        </select>
                    </label>

                    <input type="radio" name="export" value="event_type" id="export-type" />
                    <label for="export-type">Events by type:
                        <select name="event_type">
                            <option value="">-- select --</option>
                            <!-- BEGIN export_type -->
                            <option label="{EXPORT_TYPE_NAME}" value="{EXPORT_TYPE_ID}">{EXPORT_TYPE_NAME}</option>
                            <!-- END export_type -->
                        </select>
                    </label>
                </fieldset>
            </section>

            <ul class="flex-list buttons">
                <li><button class="icon-rss" @click.prevent="$refs.feedUrlModal.open">Generate URL</button></li>
                <li><input type="submit" class="icon-download" value="Download CSV" /></li>
            </ul>
        </form>


        <reveal-modal ref="feedUrlModal">
            <h2 id="calendar-export-dialog-text">Live Feed URL</h2>
            <p class="meta">Use the following address to subscribe your favourite calendar application to a live feed.</p>
            <div id="calendar-export-dialog-url" class="alert-box"></div>
            <button id="copy-button" data-clipboard-target="calendar-export-dialog-url" class="button icon-copy">Copy to Clipboard</button>
        </reveal-modal>
    </reveal-modal>
</template>

<style scoped>

</style>
