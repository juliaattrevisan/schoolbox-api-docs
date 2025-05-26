<script setup lang="ts">
import { sbProps } from "@js/util/sbx/pageUtils";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import type { LessonPlanField } from "./lessonPlannerTypes";

interface Props {
    newFieldKeyword: string; // The prefix for new field IDs
    studentConst: string; // The const for student visibility
    fieldId: LessonPlanField["id"];
    fieldRef: number | string; // The reference for the field for the form, i.e. the ID if it's an existing field or {newFieldKeyword}{index} if it's a new field
    fileBrowserCategoryId: number;
}

const props = defineProps<Props>();
const lpStore = useLessonPlannerStore();

const field = window.Vue.computed(() => {
    return lpStore.fields.find((f) => f.id === props.fieldId);
});

const fieldWasGenerated = window.Vue.computed(() => {
    const currentField = field.value;
    if (!currentField) return false;

    return currentField.body.trim() !== currentField.initialBody.trim() && currentField.body.trim().length > 0;
});

const textareaEl = window.Vue.useTemplateRef<HTMLTextAreaElement>("textareaEl");

window.Vue.onMounted(async () => {
    const currentField = field.value;
    if (!currentField) throw new Error("Unable to initialise lp-field without a field");

    await new Promise((resolve) => {
        if (!textareaEl.value) throw new Error("Unable to initialise lp-field without a textarea element");
        const bodyRef = useEditor(
            currentField.body,
            textareaEl.value,
            {
                maxCharCount: window.sbProps.MAX_BYTES_MYSQL_MEDIUMTEXT,
                targetElements: [textareaEl.value],
                fileBrowserCategoryId: props.fileBrowserCategoryId,
            },
            true,
            resolve,
        );

        // When the body changes, keep the field body in sync
        window.Vue.watch(bodyRef, (newBody) => {
            currentField.body = newBody;
        });
    });

    currentField.editorId = `field-body-${props.fieldRef}`;

    // a setTimeout is needed here otherwise foundation throws a fit...
    setTimeout(() => {
        // @ts-expect-error -- To trigger the tooltip next to the field title to show up correctly
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- foundation isn't typed, see above
        $(document).foundation("tooltip", "reflow");
    }, 0);
});
</script>

<template>
    <div v-if="field" class="row">
        <div class="small-12 columns">
            <div class="lp-item-label">
                <label>
                    {{ field.title }}
                    <span
                        v-if="field.description.length"
                        data-tooltip
                        data-width="800"
                        class="has-tip hint"
                        :title="field.description.replace(/(\r\n)+/g, '<br>')"
                    >
                        ?
                    </span>
                </label>

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
                ref="textareaEl"
                v-model="field.body"
                :name="`field[${props.fieldRef}][body]`"
            >
            </textarea>
            <a
                v-if="sbProps.hasAIFeatures"
                class="icon-ai lp-field-regenerate"
                @click="() => lpStore.openModal(props.fieldId)"
            >
                {{ fieldWasGenerated ? "Regenerate" : "Generate" }} with AI
            </a>
        </div>
    </div>
</template>

<style scoped>
.lp-field-regenerate {
    float: right;
    color: var(--content-ui-foreground);
}
</style>
