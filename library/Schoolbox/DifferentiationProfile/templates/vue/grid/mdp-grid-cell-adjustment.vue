<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

// import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    adjustment: AdjustmentType;
    adjustmentOptions: AdjustmentType[];
}>();

const selected = window.Vue.ref<AdjustmentType[]>([]);
</script>

<template>
    <!-- <search-select
        v-model:selected="selected"
        label="Search"
        label-id="students"
        endpoint="/ajax/user.php?roleTypeID=student&"
        :is-single-select="false"
        placeholder="Search for categories..."
    /> -->
    <select :value="props.adjustment?.id || ''">
        <option value="" disabled>Select an option</option>
        <option v-for="option in props.adjustmentOptions" :key="option.id" :value="option.id">
            {{ option.name }}
        </option>
    </select>
</template>

<style scoped></style>
