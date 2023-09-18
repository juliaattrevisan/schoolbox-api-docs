<script setup lang="ts">
import { slideDown, slideUp } from "@js/util/animateUtils";
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import { PageTemplate } from "./courseBuilderTypes";

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
                        <input v-model="courseTemplate.name" />
                    </div>
                    <div class="small-12 medium-3 column">
                        <strong class="hide-for-medium-up">Selected Course Page Template:</strong>
                        <div class="select-wrapper">
                            <select v-model="courseTemplate.courseTemplate">
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
                        <strong class="hide-for-medium-up">Selected Unit Page Template:</strong>
                        <div class="select-wrapper">
                            <select v-model="courseTemplate.unitTemplate">
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
                        title="Delete Row"
                        @click.prevent="store.deleteCourseTemplate(courseTemplate)"
                    ></a>
                </nav>
            </div>
        </li>
    </transition-group>
</template>

<style scoped></style>
