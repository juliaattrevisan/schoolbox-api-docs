<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    adjustment: AdjustmentType;
}>();

const selected = window.Vue.ref<AdjustmentType[]>([]);
</script>

<template>
    <search-select
        v-model:selected="selected"
        label="Search"
        label-id="students"
        endpoint="/ajax/user.php?roleTypeID=student&"
        :is-single-select="false"
        placeholder="Search for categories..."
    />
</template>

<style scoped></style>
