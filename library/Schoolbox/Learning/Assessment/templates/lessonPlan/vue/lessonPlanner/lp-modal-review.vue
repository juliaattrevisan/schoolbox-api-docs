<script setup lang="ts">
import { useLessonPlannerStore } from "./store/lessonPlannerStore";

const lpStore = useLessonPlannerStore();

const selectedFields = window.Vue.computed(() => {
    return lpStore.fields.filter((field) => field.selected);
});
</script>

<template>
    <h2>Lesson Plan generated with AI</h2>

    <template v-for="(field, i) in selectedFields" :key="field.id">
        <hr v-if="i > 0" />

        <label :for="`lp-review-field-${field.id}`">{{ field.title }}</label>
        <p v-if="field.meta" class="meta">{{ field.meta }}</p>
        <textarea
            v-if="field.suggestedBody"
            :id="`lp-review-field-${field.id}`"
            v-model="field.suggestedBody"
            rows="2"
        ></textarea>
        <p v-else>No suggestions available for this section.</p>
    </template>

    <ul class="flex-list buttons">
        <li><button class="button">Back</button></li>
        <li><button class="button submit">Add all to Lesson Plan</button></li>
    </ul>
</template>

<style scoped></style>
