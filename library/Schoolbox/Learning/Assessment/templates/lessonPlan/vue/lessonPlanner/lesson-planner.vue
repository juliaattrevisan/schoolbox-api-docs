<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import type { LessonPlanField } from "./lessonPlannerTypes";
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

        <button type="button" class="button expand icon-chat" @click="lpStore.openModal">
            {{ lpStore.hasLessonContent ? "Regenerate" : "Generate" }}
            lesson with AI
        </button>
    </fieldset>

    <fieldset>
        <lp-field
            v-for="(field, i) in lpStore.fields"
            :key="field.id"
            :new-field-keyword="props.newFieldKeyword"
            :student-const="props.studentConst"
            :file-browser-category-id="props.fileBrowserCategoryId"
            :field-id="field.id"
            :field-ref="field.lessonPlanTemplateField ? field.id : `${props.newFieldKeyword}${i}`"
        />
    </fieldset>

    <basic-modal v-model:is-open="lpStore.isModalOpen">
        <section>
            <lp-modal-generate v-if="lpStore.modalStep === 'generate'" />
            <div v-else-if="lpStore.modalStep === 'generating'" class="loader"></div>
            <lp-modal-review v-else-if="lpStore.modalStep === 'review'" />
        </section>
    </basic-modal>
</template>

<style scoped></style>
