<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import { toRef } from "vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";
import { useStore } from "./store/pastoralReportStore";
import { useMaxScrollableHeight } from "./composable/useMaxScrollableHeight";
import { useLoadRecords } from "./composable/useLoadRecords";

const store = useStore();
const { maxScrollableHeight } = useMaxScrollableHeight();
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(
    toRef(() => store.selectedTab?._links.view || ""),
);
</script>

<template>
    <div class="scrollable" :style="{ maxHeight: maxScrollableHeight }" data-test="pr-list">
        <div v-if="isNewLoad && isNewLoading" class="loader"></div>
        <data-load-error v-else-if="isNewLoad && loadError" data-title="record" :further-info="loadError" />
        <template v-else-if="payload">
            <div v-if="payload && payload.data.results.length > 0" class="l-flex-col l-gap-s">
                <div v-for="record in payload.data.results" :key="record.id" class="c-island">
                    <ul class="information-list threaded">
                        <pr-record :record="record" />
                    </ul>
                </div>
            </div>
            <pr-empty v-else />
        </template>
    </div>
</template>

<style scoped></style>
