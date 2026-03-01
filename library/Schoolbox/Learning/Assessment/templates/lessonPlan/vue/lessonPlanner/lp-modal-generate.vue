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

const selectAll = window.Vue.ref(true);

/**
 * When the select all checkbox is clicked, change all fields to match the checkbox
 * This is only applied on a user action, not if selectAll is changed by other means (i.e. the watcher)
 */
async function onSelectAllClick(): Promise<void> {
    await new Promise((res) => {
        setTimeout(res, 0); // wait for the click to be processed
    });
    lpStore.fields.forEach((field) => {
        field.selected = selectAll.value;
    });
}

window.Vue.watch(
    () => lpStore.fields.every((field) => field.selected),
    (newValue) => {
        selectAll.value = newValue;
    },
    { immediate: true },
);

window.Vue.onMounted(() => {
    // When a setting is added after the page is loaded, we need to tell foundation to fix the tooltips
    window.jQuery(document).foundation("tooltip", "reflow");
});
</script>

<template>
    <template v-if="lpStore.generateForField === null">
        <label for="lp-generate-fields">
            Select the sections of your {{ locale.lessonPlan.singular() }} which you want AI to help with:
        </label>
        <ul id="lp-generate-fields" class="option-list">
            <li v-if="lpStore.fields.length > 1">
                <input id="lp-generate-select-all" v-model="selectAll" type="checkbox" />
                <label for="lp-generate-select-all" class="checkbox-label" @click="onSelectAllClick">Select All</label>
            </li>
            <li v-for="field in lpStore.fields" :key="field.id">
                <input :id="`lp-generate-field-${field.id}`" v-model="field.selected" type="checkbox" />
                <label :for="`lp-generate-field-${field.id}`" class="checkbox-label">
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
            </li>
        </ul>
    </template>
    <template v-else>
        <h2>{{ lpStore.generateForField.title }}</h2>
    </template>

    <label for="lp-duration">{{ locale.lessonPlan.proper() }} Duration</label>
    <p class="meta">
        <strong>Tips for using Duration:</strong><br />
        Specify the total time (e.g. "60 minutes").<br />
        To plan for multiple sessions, indicate the number and length (e.g. "4 x 50-minute lessons").<br />
        For the AI to accurately incorporate <strong>duration</strong> into your plan, ensure your template has fields
        that specify its structure or timing.<br />
    </p>
    <input id="lp-duration" v-model.trim="lpStore.lessonPlanDuration" type="text" maxlength="255" />

    <label for="lp-generate-additional-context">Additional Context</label>
    <p class="meta">
        Provide additional details to help the AI create a high-quality, personalized plan. This might include
        information such as <strong>specific learning objectives</strong>, students' <strong>prior knowledge</strong>,
        <strong>available resources</strong>, <strong>differentiation needs</strong>, or
        <strong>activity preferences</strong>.
    </p>
    <textarea id="lp-generate-additional-context" v-model="lpStore.additionalContext" rows="5"></textarea>

    <div v-if="lpStore.generateError" class="alert alert-box">
        <b>There was a problem generating the {{ locale.lessonPlan.singular() }}.</b>
        {{ lpStore.generateError }}
    </div>

    <button type="button" class="button submit expand icon-ai" :disabled="!allowGeneration" @click="lpStore.generate">
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
