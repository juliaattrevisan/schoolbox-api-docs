<script setup lang="ts">
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import type { LessonPlanField } from "./lessonPlannerTypes";

interface Props {
    newFieldKeyword: string; // The prefix for new field IDs
    studentConst: string; // The const for student visibility
    fieldId: LessonPlanField["id"];
    fieldRef: number | string; // The reference for the field for the form, i.e. the ID if it's an existing field or {newFieldKeyword}{index} if it's a new field
}

const props = defineProps<Props>();
const lpStore = useLessonPlannerStore();

const field = window.Vue.computed(() => {
    return lpStore.fields.find((f) => f.id === props.fieldId);
});
</script>

<template>
    <div v-if="field" class="row">
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

            <!-- If field.lessonPlanTemplateField is defined, then we are modifying an existing field -->
            <input
                type="hidden"
                :name="`field[${props.fieldRef}][template]`"
                :value="field.lessonPlanTemplateField ?? field.id"
            />
            <textarea
                :id="`field-body-${props.fieldRef}`"
                v-model="field.body"
                :name="`field[${props.fieldRef}][body]`"
                data-ckeditor
            >
            </textarea>
        </div>
    </div>
</template>

<style scoped></style>
