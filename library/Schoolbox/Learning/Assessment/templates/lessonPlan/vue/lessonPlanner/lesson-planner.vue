<script setup lang="ts">
import { sbProps } from "@js/util/sbx/pageUtils";
import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import { LessonPlannerModalStep, type LessonPlanField } from "./lessonPlannerTypes";
import LpField from "./lp-field.vue";
import LpModalGenerate from "./lp-modal-generate.vue";
import LpModalReview from "./lp-modal-review.vue";

interface Props {
    newFieldKeyword: string; // The prefix for new field IDs
    studentConst: string; // The const for student visibility
    fileBrowserCategoryId: number;
    fields: LessonPlanField[];
}

const props = defineProps<Props>();
const lpStore = useLessonPlannerStore();

window.Vue.onMounted(() => {
    lpStore.init(props.fields);
});
</script>

<template>
    <fieldset>
        <slot name="title"></slot>
        <slot name="date"></slot>

        <button
            v-if="sbProps.hasAIFeatures"
            type="button"
            class="button expand icon-chat"
            @click="() => lpStore.openModal()"
        >
            {{ lpStore.hasLessonContent ? "Regenerate" : "Generate" }}
            {{ locale.lessonPlan.singular() }}
            with AI
        </button>
    </fieldset>

    <fieldset v-for="(field, i) in lpStore.fields" :key="field.id">
        <lp-field
            :new-field-keyword="props.newFieldKeyword"
            :student-const="props.studentConst"
            :file-browser-category-id="props.fileBrowserCategoryId"
            :field-id="field.id"
            :field-ref="field.lessonPlanTemplateField ? field.id : `${props.newFieldKeyword}${i}`"
        />
    </fieldset>

    <basic-modal v-if="sbProps.hasAIFeatures" v-model:is-open="lpStore.isModalOpen">
        <section>
            <lp-modal-generate v-if="lpStore.modalStep === LessonPlannerModalStep.GENERATE" />
            <div v-else-if="lpStore.modalStep === LessonPlannerModalStep.GENERATING" class="loader"></div>
            <lp-modal-review v-else-if="lpStore.modalStep === LessonPlannerModalStep.REVIEW" />
        </section>
    </basic-modal>
</template>

<style scoped></style>
