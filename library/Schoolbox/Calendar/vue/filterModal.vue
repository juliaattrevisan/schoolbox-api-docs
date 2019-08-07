<script>
    import RevealModal from "@schoolbox/reveal-modal";

    const FILTER_CHANGED = 'filterChanged';

    export default {
        name: "filter-modal",
        props: [
            'selectedFilters',
            'sources',
            'eventTypes',
            'groups',
            'campuses',
        ],
        components: {RevealModal},
        methods: {
            open() {
                this.$refs.modal.open();
            },
            applyFilters() {
                // I guess this posts a msg back to the fullcalendar component,
                // with the selected filters.
                // The events are already loaded regardless of whether the
                // filters exclude them: the event's visibility just needs to be
                // updated.

                // Hopefully selectedFilters isn't passed by reference:
                // I don't want to apply filters till I click this button.
                // (Turns out they are: I'll have to think of something else)
                this.$emit(FILTER_CHANGED, this.selectedFilters);
                this.$refs.modal.close();
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <form @submit.prevent="applyFilters" action="#">
            <section>
                <fieldset class="content filter-list">
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist sources">
                            <legend>Sources</legend>
                            <!-- TODO get the select-alls working -->
                            <input type="checkbox" class="select_all" id="sourceSelectAll" />
                            <label for="sourceSelectAll">Select all</label>

                            <template v-for="source in sources">
                                <input type="checkbox"
                                       name="source"
                                       :id="'source' + source.ids.join(',')"
                                       :value="source.ids"
                                       v-model="selectedFilters.source"
                                       class="applyFilters" />
                                <label :for="'source' + source.ids.join(',')">{{ source.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist groups">
                            <legend>Groups</legend>
                            <input type="checkbox" class="select_all" id="groupsSelectAll" />
                            <label for="groupsSelectAll">Select all</label>

                            <template v-for="group in groups">
                                <input type="checkbox"
                                       name="group"
                                       :id="'group' + String(group.id)"
                                       :value="group.id"
                                       v-model="selectedFilters.group"
                                       class="applyFilters" />
                                <label :for="'group' + String(group.id)">{{ group.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist campus">
                            <legend>Campus</legend>
                            <input type="checkbox" class="select_all"  id="campusSelectAll" />
                            <label for="campusSelectAll">Select all</label>

                            <template v-for="campus in campuses">
                                <input type="checkbox"
                                       name="group"
                                       :id="'campus' + String(campus.id)"
                                       :value="campus.id"
                                       v-model="selectedFilters.campus"
                                       class="applyFilters" />
                                <label :for="'campus' + String(campus.id)">{{ campus.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist events">
                            <legend>Event</legend>
                            <input type="checkbox" id="eventTypeSelectAll" class="select_all" />
                            <label for="eventTypeSelectAll">Select all</label>

                            <template v-for="eventType in eventTypes">
                                <input type="checkbox"
                                       name="group"
                                       :id="'eventType' + String(eventType.id)"
                                       :value="eventType.id"
                                       v-model="selectedFilters.eventType"
                                       class="applyFilters" />
                                <label :for="'eventType' + String(eventType.id)"
                                       :style="{backgroundColor: eventType.color}">{{ eventType.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                </fieldset>
            </section>
            <input type="submit" value="Apply Filters" />
        </form>
    </reveal-modal>
</template>

<style scoped>

</style>
