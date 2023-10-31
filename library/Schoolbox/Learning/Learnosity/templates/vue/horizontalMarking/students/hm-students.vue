<script setup lang="ts">
/**
 * The list of students to display
 */

import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsToggle from "./hm-students-toggle.vue";
import HmStudentsMarked from "./hm-students-marked.vue";
import HmStudentsStudent from "./hm-students-student.vue";

// const allStudentsAttempted = window.Vue.computed(() => {
//     return useItemsStore().hideMarkedAttempts;
// });

const studentsStore = useStudentsStore();
</script>

<template>
    <div class="l-flex-col l-gap-s f-no-margins">
        <h2 class="subheader">Students</h2>
        <div class="l-flex-row l-flex--align-center l-gap-m">
            <hm-students-toggle />
            <hm-students-marked />
        </div>
        <hm-students-student
            v-for="student in studentsStore.students"
            :key="student.id"
            :student="student"
        ></hm-students-student>
    </div>
</template>

<style scoped></style>
