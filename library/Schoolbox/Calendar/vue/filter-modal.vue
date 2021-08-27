<script>
import RevealModal from "@vue/components/reveal-modal";

const FILTER_CHANGED = "filterChanged";

export default {
  name: "filter-modal",

  components: { RevealModal },

  props: ["selectedFilters", "sources", "eventTypes", "groups", "campuses"],

  data() {
    return {
      selected: this.selectedFilters
    };
  },

  methods: {
    /**
     * Open the modal.
     */
    open() {
      this.$refs.modal.open();
    },

    /**
     * Apply the selected filters, and close the modal.
     */
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

    /**
     * Are all the options for the given filter key selected?
     * If so, the "select all" checkbox will be checked.
     *
     * @param filterKey
     * @return {boolean}
     */
    allSelected(filterKey) {
      // Naivest implementation: check length
      switch (filterKey) {
        case "source":
          return this.sources.length === (this.selected?.source || []).length;
        case "group":
          return this.groups.length === (this.selected?.group || []).length;
        case "campus":
          return this.campuses.length === (this.selected?.campus || []).length;
        case "eventType":
          return this.eventTypes.length === (this.selected?.eventType || []).length;
        default:
          return false;
      }
    },

    /**
     * On-check callback for select all checkboxes: sets all options
     * for the filter key on/off.
     *
     * @param $event
     * @param filterKey
     */
    toggleSelectAll($event, filterKey) {
      if ($event.target.checked) {
        this.selected[filterKey] = this.getAllIds(filterKey);
      } else {
        this.selected[filterKey] = [];
      }
    },

    /**
     * Get all IDs available for the given filter key.
     *
     * @param filterKey
     * @return {Array}
     */
    getAllIds(filterKey) {
      switch (filterKey) {
        case "source":
          return this.sources.map(v => v.ids);
        case "group":
          return this.groups.map(v => v.id);
        case "campus":
          return this.campuses.map(v => v.id);
        case "eventType":
          return this.eventTypes.map(v => v.id);
        default:
          return [];
      }
    }
  }
};
</script>

<template>
  <reveal-modal ref="modal" size="medium">
    <form @submit.prevent="applyFilters" action="#">
      <section>
        <fieldset class="content option-list">
          <div class="small-6 large-3 columns">
            <fieldset class="checklist sources">
              <legend>Sources</legend>
              <ul class="option-list">
                <li>
                  <input
                    type="checkbox"
                    id="sourceSelectAll"
                    :checked="allSelected('source')"
                    @change="toggleSelectAll($event, 'source')"
                  />
                  <label for="sourceSelectAll">Select all</label>
                </li>
                  <li v-for="source in sources" :key="'input-' + source.id">
                    <input
                      type="checkbox"
                      name="source"
                      :id="'source' + String(source.ids)"
                      :key="'input-' + source.id"
                      :value="source.ids"
                      v-model="selected.source"
                    />
                    <label :for="'source' + String(source.ids)" :key="'label' + source.id">{{ source.name }}</label>
                  </li>
              </ul>
            </fieldset>
          </div>
          <div class="small-6 large-3 columns">
            <fieldset class="checklist groups">
              <legend>Groups</legend>
              <ul class="option-list">
                <li>
                  <input
                    type="checkbox"
                    id="groupsSelectAll"
                    :checked="allSelected('group')"
                    @change="toggleSelectAll($event, 'group')"
                  />
                  <label for="groupsSelectAll">Select all</label>
                </li>
                <li v-for="group in groups" :key="'input-' + group.id">
                    <input
                      type="checkbox"
                      name="group"
                      :id="'group' + String(group.id)"
                      :key="'input-' + group.id"
                      :value="group.id"
                      v-model="selected.group"
                    />
                    <label :for="'group' + String(group.id)" :key="'label-' + group.id">{{ group.name }}</label>
                </li>
              </ul>
            </fieldset>
          </div>
          <div class="small-6 large-3 columns">
            <fieldset class="checklist campus">
              <legend>Campus</legend>
              <ul class="option-list">
                <li>
                  <input
                    type="checkbox"
                    id="campusSelectAll"
                    :checked="allSelected('campus')"
                    @change="toggleSelectAll($event, 'campus')"
                  />
                  <label for="campusSelectAll">Select all</label>
                </li>
                <li v-for="campus in campuses" :key="'input-' + campus.id">
                    <input
                        type="checkbox"
                        name="group"
                        :id="'campus' + String(campus.id)"
                        :key="'input-' + campus.id"
                        :value="campus.id"
                        v-model="selected.campus"
                    />
                    <label :for="'campus' + String(campus.id)" :key="'label-' + campus.id">{{ campus.name }}</label>
                </li>
              </ul>
            </fieldset>
          </div>
          <div class="small-6 large-3 columns">
            <fieldset class="checklist events">
              <legend>Event</legend>
              <ul class="option-list">
                <li>
                  <input
                    type="checkbox"
                    id="eventTypeSelectAll"
                    :checked="allSelected('eventType')"
                    @change="toggleSelectAll($event, 'eventType')"
                  />
                  <label for="eventTypeSelectAll">Select all</label>
                </li>
                <li v-for="eventType in eventTypes" :key="'input-' + eventType.id">
                    <input
                      type="checkbox"
                      name="group"
                      :id="'eventType' + String(eventType.id)"
                      :key="'input-' + eventType.id"
                      :value="eventType.id"
                      v-model="selected.eventType"
                    />
                    <label
                      :for="'eventType' + String(eventType.id)"
                      :style="{ backgroundColor: eventType.color }"
                      :key="'label-' + eventType.id"
                      >{{ eventType.name }}</label
                    >
                </li>
              </ul>
            </fieldset>
          </div>
        </fieldset>
      </section>
      <input type="submit" value="Apply Filters" />
    </form>
  </reveal-modal>
</template>

<style lang="scss" scoped>
ul {
  margin: 0px;
  list-style: none;
}
label::v-deep {
  padding-right: 1rem !important;
}
</style>
