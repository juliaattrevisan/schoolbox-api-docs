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
        data() {
            return {
                selected: this.selectedFilters
            };
        },
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
                this.$emit(FILTER_CHANGED, this.selected);
                this.$refs.modal.close();
            },
            allSelected(filterKey) {
                // Naivest implementation: check length
                switch (filterKey) {
                    case 'source':
                        return this.sources.length === this.selected.source.length;
                    case 'group':
                        return this.groups.length === this.selected.group.length;
                    case 'campus':
                        return this.campuses.length === this.selected.campus.length;
                    case 'eventType':
                        return this.eventTypes.length === this.selected.eventType.length;
                    default:
                        return false;
                }
                // Select-alls:
                // Initial state: selected if all are selected
                // On check: set all to selected
                // On uncheck: set all to deselected
            },
            toggleSelectAll($event, filterKey) {
                // Checked? Copy all values from allFilters to selectedFilter
                // Unchecked: Clear all values
                if ($event.target.checked) {
                    this.selected[filterKey] = this.getAllIds(filterKey);
                } else {
                    this.selected[filterKey] = [];
                }
            },
            getAllIds(filterKey) {
                switch (filterKey) {
                    case 'source':
                        return this.sources.map(v => v.ids);
                    case 'group':
                        return this.groups.map(v => v.id);
                    case 'campus':
                        return this.campuses.map(v => v.id);
                    case 'eventType':
                        return this.eventTypes.map(v => v.id);
                    default:
                        return false;
                }
            }
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
                            <input type="checkbox"
                                   id="sourceSelectAll"
                                   :checked="allSelected('source')"
                                   @change="toggleSelectAll($event, 'source')" />
                            <label for="sourceSelectAll">Select all</label>

                            <template v-for="source in sources">
                                <input type="checkbox"
                                       name="source"
                                       :id="'source' + String(source.ids)"
                                       :value="source.ids"
                                       v-model="selected.source" />
                                <label :for="'source' + String(source.ids)">{{ source.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist groups">
                            <legend>Groups</legend>
                            <input type="checkbox"
                                   id="groupsSelectAll"
                                   :checked="allSelected('group')"
                                   @change="toggleSelectAll($event, 'group')"/>
                            <label for="groupsSelectAll">Select all</label>

                            <template v-for="group in groups">
                                <input type="checkbox"
                                       name="group"
                                       :id="'group' + String(group.id)"
                                       :value="group.id"
                                       v-model="selected.group" />
                                <label :for="'group' + String(group.id)">{{ group.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist campus">
                            <legend>Campus</legend>
                            <input type="checkbox"
                                   id="campusSelectAll"
                                   :checked="allSelected('campus')"
                                   @change="toggleSelectAll($event, 'campus')"/>
                            <label for="campusSelectAll">Select all</label>

                            <template v-for="campus in campuses">
                                <input type="checkbox"
                                       name="group"
                                       :id="'campus' + String(campus.id)"
                                       :value="campus.id"
                                       v-model="selected.campus" />
                                <label :for="'campus' + String(campus.id)">{{ campus.name }}</label>
                            </template>
                        </fieldset>
                    </div>
                    <div class="small-6 large-3 columns">
                        <fieldset class="checklist events">
                            <legend>Event</legend>
                            <input type="checkbox"
                                   id="eventTypeSelectAll"
                                   :checked="allSelected('eventType')"
                                   @change="toggleSelectAll($event, 'eventType')" />
                            <label for="eventTypeSelectAll">Select all</label>

                            <template v-for="eventType in eventTypes">
                                <input type="checkbox"
                                       name="group"
                                       :id="'eventType' + String(eventType.id)"
                                       :value="eventType.id"
                                       v-model="selected.eventType" />
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
