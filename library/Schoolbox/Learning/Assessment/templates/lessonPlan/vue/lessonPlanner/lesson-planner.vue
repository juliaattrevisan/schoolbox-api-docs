<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import type { LessonPlanField } from "./lessonPlannerTypes";
import LpModalGenerate from "./lp-modal-generate.vue";
import LpModalReview from "./lp-modal-review.vue";

interface Props {
    newFieldKeyword: string; // The prefix for new field IDs
    studentConst: string; // The const for student visibility
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
            Generate lesson with AI
        </button>
    </fieldset>

    <fieldset>
        <div v-for="(field, i) in lpStore.fields" :key="field.id" class="row">
            <div class="small-12 columns">
                <div class="lp-item-label">
                    <label>{{ field.title }}</label>

                    <p v-if="props.studentConst !== field.visibility" class="meta">
                        <span class="icon-view-hollow"></span>This field is not visible to students
                    </p>
                </div>

                <!-- The Lesson Plan Template field which is related to this Lesson Plan Field
                    Because of the swamp you need to know both since the same function fulfils
                    create and update phases in mapperProxy->save() -->
                <template v-if="field.lessonPlanTemplateField !== undefined">
                    <!-- We are modifying an existing field -->
                    <input
                        type="hidden"
                        :name="`field[${field.id}][template]`"
                        :value="field.lessonPlanTemplateField"
                    />
                    <textarea
                        :id="`field-body-${field.id}`"
                        v-model="field.body"
                        :name="`field[${field.id}][body]`"
                        data-ckeditor
                    >
                    </textarea>
                </template>
                <template v-else>
                    <!-- We are creating a new field -->
                    <input
                        type="hidden"
                        :name="`field[${props.newFieldKeyword}${i + 1}][template]`"
                        :value="field.id"
                    />
                    <textarea
                        :id="`field-body-${props.newFieldKeyword}${i + 1}`"
                        v-model="field.body"
                        :name="`field[${props.newFieldKeyword}${i + 1}][body]`"
                        data-ckeditor
                    >
                    </textarea>
                </template>
            </div>
        </div>
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
