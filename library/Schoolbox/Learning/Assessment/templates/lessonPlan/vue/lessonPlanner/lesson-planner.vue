<script setup lang="ts">
import { sbProps } from "@js/util/sbx/pageUtils";
import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import { LessonPlannerModalStep, type LessonPlanField } from "./lessonPlannerTypes";
import LpField from "./lp-field.vue";
import LpModalGenerate from "./lp-modal-generate.vue";
import LpModalGenerating from "./lp-modal-generating.vue";
import LpModalReview from "./lp-modal-review.vue";

interface Props {
    newFieldKeyword: string; // The prefix for new field IDs
    studentConst: string; // The const for student visibility
    fileBrowserCategoryId: number;
    fields: LessonPlanField[];
    lessonPlanDuration: string; // The duration of the lesson plan, only available in the AI menu
    contextName: string; // The course name/unit name/folder name/etc that this is being created for, to assist the AI
    generatePlanRoute: string | null; // The route to generate the lesson plan with AI, if available
}

const props = defineProps<Props>();
const lpStore = useLessonPlannerStore();

window.Vue.onMounted(() => {
    lpStore.init(props.fields, props.contextName, props.lessonPlanDuration, props.generatePlanRoute);
});
</script>

<template>
    <fieldset>
        <slot name="title"></slot>
        <slot name="date"></slot>
        <slot name="curriculum-codes"></slot>

        <!-- Hidden field outside of the modal to ensure the form gets it -->
        <input v-model="lpStore.lessonPlanDuration" type="hidden" name="duration" />

        <div class="row l-pad-m--top">
            <div class="small-12 columns">
                <button
                    v-if="sbProps.hasAIFeatures"
                    type="button"
                    class="button expand icon-ai"
                    @click="() => lpStore.openModal()"
                >
                    {{ lpStore.hasLessonContent ? "Regenerate" : "Generate" }}
                    {{ locale.lessonPlan.singular() }}
                    with AI
                </button>
            </div>
        </div>
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
            <lp-modal-generating v-else-if="lpStore.modalStep === LessonPlannerModalStep.GENERATING" />
            <lp-modal-review v-else-if="lpStore.modalStep === LessonPlannerModalStep.REVIEW" />
        </section>
    </basic-modal>
</template>

<style scoped></style>
