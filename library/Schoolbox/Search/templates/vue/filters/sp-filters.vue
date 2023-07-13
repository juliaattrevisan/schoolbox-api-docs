<script setup lang="ts">
/**
 * To customise the search results via filters
 */

import { useFiltersStore } from "./store/spFiltersStore";

const filtersStore = useFiltersStore();
</script>

<template>
    <h2 class="subheader">Filters</h2>
    <section class="content">
        <fieldset class="checklist">
            <ul class="option-list">
                <li>
                    <input
                        id="selectAllOrdinary"
                        v-model="filtersStore.activeFilters"
                        type="checkbox"
                        value="assessments"
                    />
                    <label for="selectAllOrdinary">Assessments</label>
                </li>
                <li>
                    <input id="ordinaryCheckbox1" v-model="filtersStore.activeFilters" type="checkbox" value="badges" />
                    <label for="ordinaryCheckbox1">Badges</label>
                </li>
                <li>
                    <input id="ordinaryCheckbox2" v-model="filtersStore.activeFilters" type="checkbox" value="events" />
                    <label for="ordinaryCheckbox2">Events</label>
                </li>
            </ul>
        </fieldset>
    </section>
</template>

<style scoped></style>
