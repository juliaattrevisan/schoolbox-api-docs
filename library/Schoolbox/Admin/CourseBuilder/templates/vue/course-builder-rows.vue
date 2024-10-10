<script setup lang="ts">
import { slideDown, slideUp } from "@js/util/animateUtils";
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import type { PageTemplate, CourseTemplate } from "./courseBuilderTypes";

interface Props {
    availableTemplates: PageTemplate[];
}

const props = defineProps<Props>();

const store = useCourseBuilderStore();

/**
 * How the element animates on
 * @param element - the element that's animating
 * @param done - to be called once animation is complete
 */
async function onRowEnter(element: Element, done: () => void): Promise<void> {
    await slideDown(element as HTMLElement);
    done();
}

/**
 * How the element animates off
 * @param element - the element that's animating
 * @param done - to be called once animation is complete
 */
async function onRowLeave(element: Element, done: () => void): Promise<void> {
    await slideUp(element as HTMLElement);
    done();
}

/**
 * Trigger the deletetion process for a course template
 * @param courseTemplate - the course template to delete
 */
async function onDeleteClicked(courseTemplate: CourseTemplate): Promise<void> {
    if (courseTemplate.id >= 0) {
        store.courseTemplateToDelete = courseTemplate;
    } else {
        await store.deleteCourseTemplate(courseTemplate);
    }
}

/**
 * Clear the validation error for a course
 * @param courseTemplate - the course template to clear the validation error for
 */
function clearNameValidationError(courseTemplate: CourseTemplate): void {
    courseTemplate.nameHasValidationError = undefined;
}

const existingCourseTemplateCount = window.Vue.computed(() => {
    return store.courseTemplates.filter((courseTemplate) => courseTemplate.id >= 0).length;
});
</script>

<template>
    <transition-group
        tag="ul"
        class="information-list sortable ui-sortable"
        data-list
        @enter="onRowEnter"
        @leave="onRowLeave"
    >
        <li v-for="courseTemplate in store.courseTemplates" :key="courseTemplate.id" class="list-item-container">
            <div class="actions-small-1">
                <div class="list-item">
                    <div class="small-12 medium-6 column">
                        <strong class="hide-for-medium-up">Template Name:</strong>
                        <input
                            v-model="courseTemplate.name"
                            maxlength="50"
                            required
                            @input="clearNameValidationError(courseTemplate)"
                        />
                        <label v-if="courseTemplate.nameHasValidationError" class="error">
                            This field is required.
                        </label>
                    </div>
                    <div class="small-12 medium-3 column">
                        <strong class="hide-for-medium-up">Course Page Template:</strong>
                        <div class="select-wrapper">
                            <select v-model="courseTemplate.courseTemplate" required>
                                <template v-for="availableTemplate in props.availableTemplates">
                                    <option
                                        v-if="
                                            !availableTemplate.deleted ||
                                            courseTemplate.courseTemplate.id === availableTemplate.id
                                        "
                                        :key="availableTemplate.id"
                                        :value="availableTemplate"
                                        :disabled="availableTemplate.deleted"
                                    >
                                        {{ availableTemplate.name }}
                                        <template v-if="availableTemplate.deleted">(Deleted)</template>
                                    </option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="small-12 medium-3 column">
                        <strong class="hide-for-medium-up">Unit Page Template:</strong>
                        <div class="select-wrapper">
                            <select v-model="courseTemplate.unitTemplate" required>
                                <template v-for="availableTemplate in props.availableTemplates">
                                    <option
                                        v-if="
                                            !availableTemplate.deleted ||
                                            courseTemplate.unitTemplate.id === availableTemplate.id
                                        "
                                        :key="availableTemplate.id"
                                        :value="availableTemplate"
                                        :disabled="availableTemplate.deleted"
                                    >
                                        {{ availableTemplate.name }}
                                        <template v-if="availableTemplate.deleted">(Deleted)</template>
                                    </option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>
                <nav>
                    <a
                        class="delete-row-button icon-delete"
                        :class="{ disabled: existingCourseTemplateCount === 1 && courseTemplate.id >= 0 }"
                        title="Delete Template"
                        @click.prevent="onDeleteClicked(courseTemplate)"
                    ></a>
                </nav>
            </div>
        </li>
    </transition-group>
</template>

<style scoped>
.list-item-container {
    transition: opacity 0.3s ease-out;
}

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}
</style>
