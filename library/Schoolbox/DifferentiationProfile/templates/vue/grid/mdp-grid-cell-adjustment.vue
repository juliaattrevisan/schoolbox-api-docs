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
            :show-input="false"
            :is-single-select="false"
        />
    </div>
</template>

<style scoped></style>
