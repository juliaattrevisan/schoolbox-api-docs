<script>
    import RevealModal from "@schoolbox/reveal-modal";

    export default {
        name: "filter-modal",
        props: [
            'eventTypes',
            'groups',
            'campuses',
        ],
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

                            <template v-for="group in groups">
                                <input type="checkbox"
                                       name="group"
                                       :id="'group' + String(group.id)"
                                       :value="'group' + String(group.id)"
                                       class="applyFilters" />
                                <label :for="'group' + String(group.id)">{{ group.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist campus">
                            <legend>Campus</legend>
                            <input type="checkbox" class="select_all"  id="campusSelectAll" checked />
                            <label for="campusSelectAll">Select all</label>

                            <template v-for="campus in campuses">
                                <input type="checkbox"
                                       name="group"
                                       :id="'campus' + String(campus.id)"
                                       :value="'campus' + String(campus.id)"
                                       class="applyFilters" />
                                <label :for="'campus' + String(campus.id)">{{ campus.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist events">
                            <legend>Event</legend>
                            <input type="checkbox" id="eventTypeSelectAll" class="select_all" checked />
                            <label for="eventTypeSelectAll">Select all</label>


                            <template v-for="eventType in eventTypes">
                                <input type="checkbox"
                                       name="group"
                                       :id="'eventType' + String(eventType.id)"
                                       :value="'type' + String(eventType.id)"
                                       class="applyFilters" />
                                <label :for="'eventType' + String(eventType.id)"
                                       :style="{backgroundColor: eventType.color}">{{ eventType.name }}</label>
                            </template>
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
