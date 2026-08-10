<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMarkbookStore } from "./store/markbookStore";
import { MarkbookType } from "./types/types";
import MbTableHeadActivity from "./mb-table-head-activity.vue";
import MbClassAverageHeader from "./mb-class-average-header.vue";
import { assertUnreachable } from "@js/util/errorUtils";
import type { MarkbookActivity } from "./types/payload";

interface Props {
    type: MarkbookType;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const fadedGroupIds = window.Vue.computed(() => {
    // When there's no selected feedback cell, no groups should be faded
    if (!mbStore.feedbackSelectedCell) {
        return [];
    }

    // otherwise, fade any group that isn't part of the selected activity's group
    const activityId = mbStore.feedbackSelectedCell.activityId;
    return mbStore.activityGroups
        .filter((group) => !group.activities.some((activity) => activity.id === activityId))
        .map((group) => group.id);
});

/**
 * Count visible columns for an activity group.
 * @param activities The activities in the group
 * @returns The number of visible columns
 */
function getGroupColspan(activities: MarkbookActivity[]): number {
    let total = 0;

    for (const activity of activities) {
        if (activity.splitComments) {
            // using splitComments, columns depend on the visibility of each part
            const visibility = mbStore.getSplitColumnVisibility(activity);
            if (visibility.mark) total += 1;
            if (visibility.comment) total += 1;
        } else {
            // not using splitComments, so just one column for the activity
            total += 1;
        }
    }

    return total;
}
</script>

<template>
    <thead v-if="mbStore.data !== null">
        <tr class="rotate-headers">
            <th class="fixed-left"><div></div></th>
            <th v-if="mbStore.showWeightedAverageColumn">
                <div><span>Student Weighted Average</span></div>
            </th>
            <th v-if="mbStore.showRankColumn" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
                <div>
                    <span v-if="props.type === MarkbookType.CLASS">Class Ranking</span>
                    <span v-else-if="props.type === MarkbookType.COURSE">Course Ranking</span>
                    <span v-else-if="props.type === MarkbookType.REPORTING">Rank</span>
                    <template v-else-if="assertUnreachable(props.type)"></template>
                </div>
            </th>
            <mb-table-head-activity
                v-for="activity in mbStore.orderedActivities"
                :key="activity.id"
                :activity="activity"
            />
        </tr>

        <tr class="course-headers">
            <th class="fixed-left transparent"></th>
            <th v-if="mbStore.showWeightedAverageColumn" class="fixed-left white"></th>
            <th
                v-if="mbStore.showRankColumn"
                class="white"
                :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }"
            ></th>
            <template v-for="group in mbStore.activityGroups" :key="group.id">
                <th
                    v-if="group.splitComments && getGroupColspan(group.activities) > 0"
                    :colspan="getGroupColspan(group.activities)"
                    class="white"
                    :class="{ 'markbook-faded': fadedGroupIds.includes(group.id) }"
                ></th>
                <th
                    v-else-if="getGroupColspan(group.activities) > 0"
                    class="unit-title"
                    :class="{
                        'markbook-faded': fadedGroupIds.includes(group.id),
                        /* avoid wide reporting markbook solely due to these headers */
                        'unit-title--clip': props.type === MarkbookType.REPORTING,
                    }"
                    :title="
                        group.unitSpec?.title ??
                        `These ${locale.activity.plural()} are not part of a ${locale.unit.singular()}`
                    "
                    :colspan="getGroupColspan(group.activities)"
                >
                    {{ group.unitSpec?.title ?? "" }}
                </th>
            </template>
        </tr>

        <template v-if="props.type === MarkbookType.CLASS">
            <!-- NB; This won't look correct if there are multiple classImpls.
              -- Currently that will never happen since this is restricted to the class markbook -->
            <mb-class-average-header
                v-for="(classImpl, classImplId) in mbStore.data.classImpls"
                :key="classImplId"
                :class-impl="classImpl"
            />
        </template>
    </thead>
</template>

<style scoped>
.unit-title--clip {
    max-width: 4rem;
    overflow: clip;
    text-overflow: ellipsis;
}
</style>
