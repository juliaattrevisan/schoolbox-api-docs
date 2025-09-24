<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import { LessonPlannerModalStep } from "./lessonPlannerTypes";

const lpStore = useLessonPlannerStore();

const selectedFields = window.Vue.computed(() => {
    return lpStore.fields.filter((field) => field.selected);
});

window.Vue.onMounted(() => {
    // @ts-expect-error -- To trigger the tooltip next to the field title to show up correctly
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- foundation isn't typed, see above
    $(document).foundation("tooltip", "reflow");
});
</script>

<template>
    <h2>{{ locale.lessonPlan.proper() }} generated with AI</h2>

    <template v-for="(field, i) in selectedFields" :key="field.id">
        <hr v-if="i > 0" />

        <label :for="`lp-review-field-${field.id}`">
            {{ field.title }}
            <span
                v-if="field.description.length"
                data-tooltip
                data-width="800"
                class="has-tip hint"
                :title="field.description"
            >
                ?
            </span>
        </label>
        <!-- eslint-disable-next-line vue/no-v-html -- This is sanitized by the backend already -->
        <div v-if="field.suggestedBody" :id="`lp-review-field-${field.id}`" v-html="field.suggestedBody" />
        <p v-else>No suggestions available for this section.</p>
    </template>

    <div class="clearfix"></div>
    <span class="meta"
        >Schoolbox allows interaction with AI. AI can make mistakes. Please check generated content.</span
    >
    <div class="l-flex-row l-flex--no-wrap">
        <button class="l-flex__item--1 button" @click="lpStore.modalStep = LessonPlannerModalStep.GENERATE">
            Back
        </button>
        <button class="l-flex__item--1 button submit" @click="lpStore.applySuggestions">
            Add {{ selectedFields.length > 1 ? "all" : "" }} to {{ locale.lessonPlan.proper() }}
        </button>
    </div>
</template>

<style scoped>
.lp-field-description {
    white-space: pre-wrap;
}
</style>
