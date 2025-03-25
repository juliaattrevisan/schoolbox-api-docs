<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import { LessonPlannerModalStep } from "./lessonPlannerTypes";

const lpStore = useLessonPlannerStore();

const selectedFields = window.Vue.computed(() => {
    return lpStore.fields.filter((field) => field.selected);
});
</script>

<template>
    <h2>{{ locale.lessonPlan.proper() }} generated with AI</h2>

    <template v-for="(field, i) in selectedFields" :key="field.id">
        <hr v-if="i > 0" />

        <label :for="`lp-review-field-${field.id}`">{{ field.title }}</label>
        <p v-if="field.meta.length" class="meta">{{ field.meta }}</p>
        <textarea
            v-if="field.suggestedBody"
            :id="`lp-review-field-${field.id}`"
            v-model="field.suggestedBody"
            rows="2"
        ></textarea>
        <p v-else>No suggestions available for this section.</p>
    </template>

    <div class="l-flex-row l-flex--no-wrap">
        <button class="l-flex__item--1 button" @click="lpStore.modalStep = LessonPlannerModalStep.GENERATE">
            Back
        </button>
        <button class="l-flex__item--1 button submit" @click="lpStore.applySuggestions">
            Add {{ selectedFields.length > 1 ? "all" : "" }} to {{ locale.lessonPlan.proper() }}
        </button>
    </div>
</template>

<style scoped></style>
