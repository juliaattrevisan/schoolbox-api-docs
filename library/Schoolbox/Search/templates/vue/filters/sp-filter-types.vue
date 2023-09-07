<script setup lang="ts">
/**
 * To filter the results by types
 */

import { useFilterTypesStore } from "./store/spFilterTypesStore";
import SpFilterTypesType from "./sp-filter-types-type.vue";

const filterTypesStore = useFilterTypesStore();
</script>

<template>
    <div>
        <label>Types</label>
        <fieldset class="checklist">
            <ul class="option-list t-no-list-style">
                <sp-filter-types-type
                    v-for="typeItem in filterTypesStore.allTypes"
                    :key="typeItem.value"
                    :type="typeItem"
                />
            </ul>
        </fieldset>
    </div>
</template>

<style scoped></style>
