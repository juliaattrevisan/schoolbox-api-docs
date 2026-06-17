<script setup lang="ts">
import { getGradient } from "@js/util/cssUtils";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookClassImpl } from "./types/payload";

interface Props {
    classImpl: MarkbookClassImpl;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();
</script>

<template>
    <tr
        v-if="mbStore.data !== null && props.classImpl.stats !== null"
        class="average-headers"
        :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }"
    >
        <th class="fixed-left white profile-cell average-expander">
            <a
                href="#"
                :class="mbStore.showAverages ? 'icon-up-arrow' : 'icon-down-arrow'"
                title="Show additional metrics"
                data-test="markbook-class-average-expander"
                @click="mbStore.showAverages = !mbStore.showAverages"
            ></a>
            Class Average
        </th>

        <template v-if="mbStore.showWeightedAverageColumn">
            <th
                v-if="mbStore.showDrafts"
                data-test="class-average-mark-book-page-draft"
                class="fixed-left student-grade-cell"
                :class="getGradient(props.classImpl.stats.draftNormalisedMean, 100, true)"
            >
                {{ props.classImpl.stats.draftWeightedMean }}
            </th>
            <th
                v-else
                data-test="class-average-mark-book-page"
                class="fixed-left student-grade-cell"
                :class="getGradient(props.classImpl.stats.normalisedMean, 100, true)"
            >
                {{ props.classImpl.stats.weightedMean }}
            </th>
        </template>

        <th v-if="mbStore.showRankColumn" class="student-grade-cell white">-</th>
        <th
            v-for="{ activity, aImpl } in mbStore.orderedClassImplActivities(props.classImpl)"
            :key="activity.id"
            class="student-grade-cell white"
        >
            <span v-if="aImpl === null" class="na"></span>
            <span v-else-if="mbStore.showDrafts && mbStore.aImplHasDraftMarks(aImpl)" class="meta">
                {{ aImpl.stats.draftMean ?? "–" }}
            </span>
            <span v-else>
                {{ aImpl.stats.mean ?? "–" }}
            </span>
        </th>
    </tr>
</template>

<style scoped></style>
