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
    <tr v-if="mbStore.data !== null && props.classImpl.stats !== null" class="average-headers">
        <th class="fixed-left white profile-cell average-expander">
            <a
                href="#"
                :class="mbStore.showAverages ? 'icon-up-arrow' : 'icon-down-arrow'"
                title="Show additional metrics"
                @click="mbStore.showAverages = !mbStore.showAverages"
            ></a>
            Class Average
        </th>

        <th
            v-if="mbStore.showDrafts"
            class="fixed-left student-grade-cell"
            :class="getGradient(props.classImpl.stats.draftNormalisedMean, 100, true)"
        >
            {{ props.classImpl.stats.draftWeightedMean }}
        </th>
        <th
            v-else
            class="fixed-left student-grade-cell"
            :class="getGradient(props.classImpl.stats.normalisedMean, 100, true)"
        >
            {{ props.classImpl.stats.weightedMean }}
        </th>

        <th v-if="mbStore.data.rank !== null">-</th>
        <th v-for="assessment in mbStore.orderedActivities" :key="assessment.id" class="student-grade-cell white">
            <span v-if="props.classImpl.assessments[assessment.id] === undefined" class="na"></span>
            <span
                v-else-if="
                    mbStore.showDrafts &&
                    props.classImpl.assessments[assessment.id].stats.draftMean &&
                    Object.keys(props.classImpl.assessments[assessment.id].hasDraft).length > 0
                "
                class="meta"
            >
                {{ props.classImpl.assessments[assessment.id].stats.draftMean ?? "-" }}
            </span>
            <span v-else>
                {{ props.classImpl.assessments[assessment.id].stats.mean ?? "–" }}
            </span>
        </th>
    </tr>
</template>

<style scoped></style>
