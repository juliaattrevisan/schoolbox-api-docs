<script setup lang="ts">
/**
 * TTo filter the results by types
 */

import { useFilterTypesStore } from "./store/spFilterTypesStore";

const filterTypesStore = useFilterTypesStore();
</script>

<template>
    <h2 class="subheader">Filters</h2>
    <section class="content">
        <fieldset class="checklist">
            <ul class="option-list">
                <li v-for="filter in filterTypesStore.allTypes" :key="filter.value">
                    <input
                        :id="filter.value"
                        v-model="filterTypesStore.selectedTypes"
                        type="checkbox"
                        :value="filter.value"
                    />
                    <label :for="filter.value">{{ filter.title }}</label>
                </li>
            </ul>
        </fieldset>
    </section>
</template>

<style scoped></style>
