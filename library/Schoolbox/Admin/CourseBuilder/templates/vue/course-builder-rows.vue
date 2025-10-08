<script setup lang="ts">
import { slideDown, slideUp } from "@js/util/animateUtils";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import type Sortable from "sortablejs";
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import type { PageTemplate, CourseTemplate } from "./courseBuilderTypes";

interface Props {
    availableTemplates: PageTemplate[];
}

const props = defineProps<Props>();

const store = useCourseBuilderStore();

const sortableOptions = window.Vue.computed(() => ({
    // Link the selected fields sortable to the available fields sortable so that we can drag between them
    group: {
        name: `fields-courseBuilder-frameworks`,
        pull: false, // can't drag out of the selected fields
        put: false, // can't drag into the selected fields
    },
    // We use this to force the html5 drag and drop logic to be ignored and use the fallback so we can style it
    forceFallback: true,
    // The class to apply to the clone when dragging
    fallbackClass: "dragged-item",
    // How long the animation should take when items shift
    animation: 200,
    // The handle to use to drag the field around
    handle: "[data-pe-field-drag-handle]",
}));

/**
 * When the user resequences the fields
 * @param event The event that triggered the resequence
 */
async function onResequenceField(event: Sortable.SortableEvent): Promise<void> {
    if (event.oldIndex === event.newIndex) return;
    if (event.oldIndex === undefined || event.newIndex === undefined) return;

    await store.resequenceField(event.oldIndex, event.newIndex);
}

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
    <sortable-wrapper
        tag="ul"
        class="information-list sortable-card ui-sortable"
        :list="store.courseTemplates"
        :item-key="(item) => item.id"
        :options="sortableOptions"
        :transition-enter="onRowEnter"
        :transition-leave="onRowLeave"
        @end="onResequenceField"
    >
        <template #item="{ element }">
            <li class="list-item-container">
                <div class="actions-small-1">
                    <div class="list-item">
                        <div class="small-12 medium-1 column">
                            <div class="grip compact" data-pe-field-drag-handle />
                        </div>
                        <div class="small-12 medium-1 column">
                            <strong class="hide-for-medium-up">Template ID:</strong>
                            {{ element.id }}
                        </div>
                        <div class="small-12 medium-4 column">
                            <strong class="hide-for-medium-up">Template Name:</strong>
                            <input
                                v-model="element.name"
                                maxlength="50"
                                required
                                @input="clearNameValidationError(element)"
                            />
                            <label v-if="element.nameHasValidationError" class="error"> This field is required. </label>
                        </div>
                        <div class="small-12 medium-3 column">
                            <strong class="hide-for-medium-up">Course Page Template:</strong>
                            <div class="select-wrapper">
                                <select v-model="element.courseTemplate" required>
                                    <template v-for="availableTemplate in props.availableTemplates">
                                        <option
                                            v-if="
                                                !availableTemplate.deleted ||
                                                element.courseTemplate?.id === availableTemplate.id
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
                                <select v-model="element.unitTemplate" required>
                                    <template v-for="availableTemplate in props.availableTemplates">
                                        <option
                                            v-if="
                                                !availableTemplate.deleted ||
                                                element.unitTemplate?.id === availableTemplate.id
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
                            :class="{ disabled: existingCourseTemplateCount === 1 && element.id >= 0 }"
                            title="Delete Template"
                            @click.prevent="onDeleteClicked(element)"
                        ></a>
                    </nav>
                </div>
            </li>
        </template>
    </sortable-wrapper>
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
