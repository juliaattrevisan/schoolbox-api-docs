<script>
    import RevealModal from "@schoolbox/reveal-modal";

    export default {
        name: "filter-modal",
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
        <form>
            <section>
                <fieldset class="content filter-list">
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist sources">
                            <legend>Sources</legend>
                            <input type="checkbox" class="select_all" id="sourceSelectAll" checked />
                            <label for="sourceSelectAll">Select all</label>
                            <!-- BEGIN filter_source -->
                            <input type="checkbox" name="source" id="source{SOURCE_ID}" value="source{SOURCE_ID}" class="applyFilters"/>
                            <label for="source{SOURCE_ID}">{SOURCE_NAME}</label>
                            <!-- END filter_source -->
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist groups">
                            <legend>Groups</legend>
                            <input type="checkbox" class="select_all" id="groupsSelectAll" checked />
                            <label for="groupsSelectAll">Select all</label>
                            <!-- BEGIN filter_group -->
                            <input type="checkbox" name="group" id="group{GROUP_ID}" value="group{GROUP_ID}" class="applyFilters"/>
                            <label for="group{GROUP_ID}">{GROUP_NAME}</label>
                            <!-- END filter_group -->
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist campus">
                            <legend>Campus</legend>
                            <input type="checkbox" class="select_all"  id="campusSelectAll" checked />
                            <label for="campusSelectAll">Select all</label>
                            <!-- BEGIN filter_campus -->
                            <input type="checkbox" name="campus" id="campus{CAMPUS_ID}" value="campus{CAMPUS_ID}" class="applyFilters"/>
                            <label for="campus{CAMPUS_ID}">{CAMPUS_NAME}</label>
                            <!-- END filter_campus -->
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist events">
                            <legend>Event</legend>
                            <input type="checkbox" id="eventTypeSelectAll" class="select_all" checked />
                            <label for="eventTypeSelectAll">Select all</label>
                            <!-- BEGIN filter_event_type -->
                            <input type="checkbox" name="events" id="eventType{EVENT_TYPE_ID}" value="type{EVENT_TYPE_ID}" class="applyFilters"/>
                            <label for="eventType{EVENT_TYPE_ID}" style="background-color:{EVENT_TYPE_COLOUR};">{EVENT_TYPE_NAME}</label>
                            <!-- END filter_event_type -->
                        </fieldset>
                    </div>
                </fieldset>
            </section>
        </form>
        <a href="#" id="applyFilters" class="button submit">Apply Filters</a>
    </reveal-modal>
</template>

<style scoped>

</style>
