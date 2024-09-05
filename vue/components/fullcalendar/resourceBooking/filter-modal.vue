<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";

const FILTER_CHANGED = "filterChanged";

export default {
    name: "booking-filter-modal",

    components: { RevealModal },

    props: ["selectedFilters"],

    data() {
        return {
            selected: this.selectedFilters,
            options: {
                1: "Pending",
                2: "Approved",
                3: "Rejected",
            },
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
            this.$emit(FILTER_CHANGED, this.selected);
            this.$refs.modal.close();
        },

        select(event) {
            var filterValue = Number(event.target.value);

            if (event.target.checked) {
                this.selected.push(filterValue);
            } else {
                this.selected.splice(this.selected.indexOf(filterValue), 1);
            }
        },
    },
};
</script>

<template>
    <reveal-modal ref="modal">
        <form @submit.prevent="applyFilters" action="#">
            <div class="checklist option-list">
                <h2>Status</h2>
                <ul class="option-list">
                    <li v-for="(option, index) in options" :key="index">
                        <input
                            type="checkbox"
                            :id="option.toLowerCase().trim()"
                            :key="'input-' + index"
                            @click="select"
                            :value="index"
                            v-model="selected"
                        />
                        <label :for="option.toLowerCase().trim()" :key="'label-' + index">{{ option }}</label>
                    </li>
                </ul>
            </div>

            <input type="submit" value="Apply Filters" />
        </form>
    </reveal-modal>
</template>
