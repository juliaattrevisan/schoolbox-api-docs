<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";

const lpStore = useLessonPlannerStore();

/**
 * only allow generation if at least one field is selected
 */
const allowGeneration = window.Vue.computed(() => {
    return lpStore.fields.some((field) => field.selected);
});
</script>

<template>
    <template v-if="lpStore.generateForField === null">
        <label for="lp-generate-fields">
            Select the sections of your {{ locale.lessonPlan.singular() }} which you want AI to help with:
        </label>
        <ul id="lp-generate-fields" class="option-list">
            <li v-for="field in lpStore.fields" :key="field.id">
                <input :id="`lp-generate-field-${field.id}`" v-model="field.selected" type="checkbox" />
                <label :for="`lp-generate-field-${field.id}`" class="checkbox-label">
                    {{ field.title }}
                    <template v-if="field.description.length">
                        <br />
                        <p class="meta lp-field-description">{{ field.description }}</p>
                    </template>
                </label>
            </li>
        </ul>
    </template>
    <template v-else>
        <h2>{{ lpStore.generateForField.title }}</h2>
    </template>

    <label for="lp-generate-additional-context">Additional Context</label>
    <p class="meta">
        Provide any specific instructions, lesson context, or ideas to guide the AI in generating a tailored
        {{ locale.lessonPlan.singular() }}.
    </p>
    <textarea id="lp-generate-additional-context" v-model="lpStore.additionalContext" rows="5"></textarea>

    <div v-if="lpStore.generateError" class="alert alert-box">
        <b>There was a problem generating the {{ locale.lessonPlan.singular() }}.</b>
        {{ lpStore.generateError }}
    </div>

    <button type="button" class="button submit expand icon-chat" :disabled="!allowGeneration" @click="lpStore.generate">
        <template v-if="lpStore.generateForField === null">
            {{ lpStore.hasLessonContent ? "Regenerate" : "Generate" }}
            {{ locale.lessonPlan.singular() }}
        </template>
        <template v-else>
            {{ lpStore.generateForField.body.trim().length > 0 ? "Regenerate" : "Generate" }}
        </template>
        with AI
    </button>
</template>

<style scoped>
.lp-field-description {
    white-space: pre-wrap;
}
</style>
