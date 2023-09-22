<script setup lang="ts">
import { slideDown, slideUp } from "@js/util/animateUtils";
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import { PageTemplate, CourseTemplate } from "./courseBuilderTypes";

interface Props {
    availableTemplates: PageTemplate[];
}

const props = defineProps<Props>();

const store = useCourseBuilderStore();

/**
 * How the element animates on
 * @param {Element} element - the element that's animating
 * @param {Function} done - to be called once animation is complete
 */
async function onRowEnter(element: Element, done: () => void) {
    await slideDown(element);
    done();
}

/**
 * How the element animates off
 * @param {Element} element - the element that's animating
 * @param {Function} done - to be called once animation is complete
 */
async function onRowLeave(element: Element, done: () => void) {
    await slideUp(element);
    done();
}

/**
 * Trigger the deletetion process for a course template
 * @param {CourseTemplate} courseTemplate - the course template to delete
 */
async function onDeleteClicked(courseTemplate: CourseTemplate) {
    if (courseTemplate.id >= 0) {
        store.courseTemplateToDelete = courseTemplate;
    } else {
        await store.deleteCourseTemplate(courseTemplate);
    }
}

const existingCourseTemplateCount = window.Vue.computed(() => {
    return store.courseTemplates.filter((courseTemplate) => courseTemplate.id >= 0).length;
});
</script>

<template>
    <transition-group tag="ul" class="information-list" data-list @enter="onRowEnter" @leave="onRowLeave">
        <li v-for="courseTemplate in store.courseTemplates" :key="courseTemplate.id" class="list-item-container">
            <div class="actions-small-1">
                <div class="list-item">
                    <div class="small-12 medium-1 column">
                        <strong class="hide-for-medium-up">ID:</strong>
                        {{ courseTemplate.id }}
                    </div>
                    <div class="small-12 medium-5 column">
                        <strong class="hide-for-medium-up">Template Name:</strong>
                        <input v-model="courseTemplate.name" required />
                    </div>
                    <div class="small-12 medium-3 column">
                        <strong class="hide-for-medium-up">Course Page Template:</strong>
                        <div class="select-wrapper">
                            <select v-model="courseTemplate.courseTemplate" required>
                                <option
                                    v-for="availableTemplate in props.availableTemplates"
                                    :key="availableTemplate.id"
                                    :value="availableTemplate"
                                >
                                    {{ availableTemplate.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="small-12 medium-3 column">
                        <strong class="hide-for-medium-up">Unit Page Template:</strong>
                        <div class="select-wrapper">
                            <select v-model="courseTemplate.unitTemplate" required>
                                <option
                                    v-for="availableTemplate in props.availableTemplates"
                                    :key="availableTemplate.id"
                                    :value="availableTemplate"
                                >
                                    {{ availableTemplate.name }}
                                </option>
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
    margin: 0px 8px 0px 0px;
}
</style>
