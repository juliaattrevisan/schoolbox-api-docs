<script setup lang="ts">
/**
 * Display the latest attempt of any student who has submitted
 */

import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsToggle from "./hm-students-toggle.vue";
import HmStudentsShowReviewedAttempts from "./hm-students-show-reviewed-attempts.vue";
import HmStudentsAllReviewed from "./hm-students-all-reviewed.vue";
import HmStudentsStudent from "./hm-students-student.vue";

const studentsStore = useStudentsStore();
</script>

<template>
    <div class="u-flex-col l-gap-s f-no-margins">
        <h2 class="subheader f-no-margins">Students</h2>
        <div class="u-flex-row u-flex--align-center l-gap-m">
            <hm-students-toggle />
            <hm-students-show-reviewed-attempts />
        </div>
        <hm-students-all-reviewed />
        <div class="u-flex-col l-gap-m">
            <hm-students-student
                v-for="student in studentsStore.students"
                :key="student.id"
                :student="student"
            ></hm-students-student>
        </div>
    </div>
</template>

<style scoped></style>
