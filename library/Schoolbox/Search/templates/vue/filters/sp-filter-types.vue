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
        <div class="checklist">
            <ul class="option-list t-no-list-style l-flex-col f-no-margins">
                <sp-filter-types-type
                    v-for="typeItem in filterTypesStore.allTypes"
                    :key="typeItem.value"
                    :type="typeItem"
                />
            </ul>
        </div>
    </div>
</template>

<style scoped>
.checklist {
    border: 1px solid var(--sbx-config-color-accent, var(--form-border-color));
    border-radius: var(--sbx-config-field-corner-radius, 0);
    max-height: 300px;
    overflow-y: auto;
    padding: var(--spacing-s);
}
</style>
