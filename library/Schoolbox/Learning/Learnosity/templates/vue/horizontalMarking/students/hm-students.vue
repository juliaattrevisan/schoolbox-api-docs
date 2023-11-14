<script setup lang="ts">
/**
 * Display the latest attempt of any student who has submitted
 */

import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsToggle from "./hm-students-toggle.vue";
import HmStudentsShowMarkedAttempts from "./hm-students-show-marked-attempts.vue";
import HmStudentsAllMarked from "./hm-students-all-marked.vue";
import HmStudentsStudent from "./hm-students-student.vue";

const studentsStore = useStudentsStore();
</script>

<template>
    <div class="l-flex-col l-gap-s f-no-margins">
        <h2 class="subheader f-no-margins">Students</h2>
        <div class="l-flex-row l-flex--align-center l-gap-m">
            <hm-students-toggle />
            <hm-students-show-marked-attempts />
        </div>
        <hm-students-all-marked />
        <div class="l-flex-col l-gap-m">
            <hm-students-student
                v-for="student in studentsStore.students"
                :key="student.id"
                :student="student"
            ></hm-students-student>
        </div>
    </div>
</template>

<style scoped></style>
