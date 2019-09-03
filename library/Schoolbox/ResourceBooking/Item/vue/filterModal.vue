<template>
    <reveal-modal ref="modal">
        <form @submit.prevent="applyFilters" action="#">
            <section>
                <fieldset class="checklist filter-list">
                    <legend>Status</legend>
                    <div class="small-12 columns">
                        <template v-for="(option, index) in options">
                            <input type="checkbox"
                                   :id="option.toLowerCase().trim()"
                                   @click="select"
                                   :value="index"
                                   v-model="selected"
                            />
                            <label :for="option.toLowerCase().trim()">{{ option }}</label>
                        </template>
                    </div>
                </fieldset>
            </section>
            <input type="submit" value="Apply Filters" />
        </form>
    </reveal-modal>
</template>

<script>
    import RevealModal from "@schoolbox/reveal-modal";

    const FILTER_CHANGED = 'filterChanged';

    export default {
        name: "filter-modal",
        props: [
            'selectedFilters',
        ],
        data() {
            return {
                selected: this.selectedFilters,
                options: {
                    1: 'Pending',
                    2: 'Approved',
                    3: 'Rejected',
                }
            };
        },
        components: {RevealModal},
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

            toLower(value) {
                return value.toLower().trim();
            },

            select(event) {
                var filterValue = Number(event.target.value);

                if (event.target.checked) {
                    this.selected.push(filterValue);
                } else {
                    this.selected.splice(this.selected.indexOf(filterValue), 1);
                }
            }
        },
    }
</script>
