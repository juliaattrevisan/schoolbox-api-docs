<script setup lang="ts">
import { useMarkbookStore } from "./store/markbookStore";
import MbTableHeadActivity from "./mb-table-head-activity.vue";
import MbClassAverageHeader from "./mb-class-average-header.vue";

interface Props {
    type: "course" | "class";
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();
</script>

<template>
    <thead v-if="mbStore.data !== null">
        <tr class="rotate-headers">
            <th class="fixed-left"><div></div></th>
            <th class="fixed-left" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
                <div><span>Student Weighted Average</span></div>
            </th>
            <th v-if="mbStore.data.rank !== null" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
                <div>
                    <span>{{ props.type === "class" ? "Class" : "Course" }} Ranking</span>
                </div>
            </th>
            <mb-table-head-activity
                v-for="activity in mbStore.orderedActivities"
                :key="activity.id"
                :activity="activity"
            />
        </tr>

        <template v-if="props.type === 'class'">
            <!-- NB; This won't look correct if there are multiple classImpls.
              -- Currently that will never happen since this is restricted to the class markbook -->
            <mb-class-average-header
                v-for="(classImpl, classImplId) in mbStore.data.classImpls"
                :key="classImplId"
                :class-impl="classImpl"
            />
        </template>

        <tr class="course-headers" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
            <th class="fixed-left transparent"></th>
            <th class="fixed-left white"></th>
            <th v-if="mbStore.data.rank !== null" class="white"></th>
            <th
                v-for="group in mbStore.activityGroups"
                :key="group.unitSpec?.id"
                class="unit-title"
                :colspan="group.activities.length"
            >
                {{ group.unitSpec?.title ?? "" }}
            </th>
        </tr>
    </thead>
</template>

<style scoped></style>
