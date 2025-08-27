<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import { toRef } from "vue";
import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";
import { useStore } from "./store/pastoralReportStore";
import { useMaxScrollableHeight } from "./composable/useMaxScrollableHeight";
import { useLoadRecords } from "./composable/useLoadRecords";

const props = defineProps<{
    results: PastoralRecord[];
}>();

const store = useStore();
const { maxScrollableHeight } = useMaxScrollableHeight();
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(
    toRef(() => store.selectedTab?._links.view || ""),
);
</script>

<template>
    <div class="scrollable" :style="{ maxHeight: maxScrollableHeight }" data-test="pr-list">
        <div v-if="props.results.length > 0" class="l-flex-col l-gap-s">
            <div v-for="record in props.results" :key="record.id" class="c-island">
                <ul class="information-list threaded">
                    <pr-record :record="record" />
                </ul>
            </div>
        </div>
        <pr-empty v-else />
    </div>
</template>

<style scoped></style>
