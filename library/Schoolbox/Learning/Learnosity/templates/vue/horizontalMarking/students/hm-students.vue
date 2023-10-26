<script setup lang="ts">
/**
 * The list of students to display
 */

import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsToggle from "./hm-students-toggle.vue";
import HmStudentsStudent from "./hm-students-student.vue";

const allStudentsAttempted = window.Vue.computed(() => {
    return useItemsStore().hideMarkedAttempts;
});

const studentsStore = useStudentsStore();
</script>

<template>
    <div class="l-flex-col l-gap-s">
        <div class="l-flex-row l-flex--justify-space l-flex--align-end f-no-margins">
            <h2 class="subheader">Students</h2>
            <hm-students-toggle />
        </div>
        <hm-students-student
            v-for="student in studentsStore.students"
            :key="student.id"
            :student="student"
        ></hm-students-student>
    </div>
</template>

<style scoped></style>
