<script setup lang="ts">
/**
 * TTo filter the results by types
 */

import { useFilterTypesStore } from "./store/spFilterTypesStore";

const filterTypesStore = useFilterTypesStore();
</script>

<template>
    <div class="small-12 columns">
        <label>Types</label>
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
                    <ul v-if="filter.subTypes">
                        <li v-for="subType in filter.subTypes" :key="subType.value">
                            <input
                                :id="subType.value"
                                v-model="filterTypesStore.selectedTypes"
                                type="checkbox"
                                :value="subType.value"
                            />
                            <label :for="subType.value">{{ subType.title }}</label>
                        </li>
                    </ul>
                </li>
            </ul>
        </fieldset>
    </div>
</template>

<style scoped></style>
