<script setup lang="ts">
import { useLessonPlannerStore } from "./store/lessonPlannerStore";

const lpStore = useLessonPlannerStore();
</script>

<template>
    <label for="lp-generate-fields"> Select the sections of your lesson plan which you want AI to help with: </label>
    <ul id="lp-generate-fields" class="option-list">
        <li v-for="field in lpStore.fields" :key="field.id">
            <input :id="`lp-generate-field-${field.id}`" v-model="field.selected" type="checkbox" />
            <label :for="`lp-generate-field-${field.id}`" class="checkbox-label">
                {{ field.title }}
                <template v-if="field.meta">
                    <br />
                    <span class="meta">{{ field.meta }}</span>
                </template>
            </label>
        </li>
    </ul>

    <label for="lp-generate-additional-context">Additional Context</label>
    <p class="meta">
        Provide any specific instructions, lesson context, or ideas to guide the AI in generating a tailored lesson
        plan.
    </p>
    <textarea id="lp-generate-additional-context" rows="5"></textarea>

    <button type="button" class="button expand icon-chat">Generate lesson with AI</button>
</template>

<style scoped></style>
