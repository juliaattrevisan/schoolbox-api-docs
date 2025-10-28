<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    adjustment: AdjustmentType[] | null;
    options: AdjustmentType[];
}>();

const selected = window.Vue.ref<AdjustmentType[]>([]);
</script>

<template>
    <div class="l-pad-s">
        <search-select
            v-model:selected="selected"
            :default-results="props.options"
            label="Search"
            label-id="students"
            endpoint="/ajax/user.php?roleTypeID=student&"
            :is-single-select="false"
            placeholder="Search for categories..."
        />
        <!-- <select :value="props.adjustment?.id || ''">
        <option value="" disabled>Select an option</option>
        <option v-for="option in props.options" :key="option.id" :value="option.id">
            {{ option.name }}
        </option>
    </select> -->
    </div>
</template>

<style scoped></style>
