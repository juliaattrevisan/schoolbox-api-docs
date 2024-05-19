<script setup lang="ts">
/**
 * Displayed when there's currently no Units linked to the Course
 */

// import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";s

interface Props {
    courseId: number;
}
const props = defineProps<Props>();
// const addUnitStore = useAddUnitStore();

/**
 * To show the add unit display
 */
// function onCreateSelected() {
    // if (!addUnitStore.isOpen) {
    //     addUnitStore.prompt();
    // }
// }
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <div class="empty-state">
                <i class="icon-unit"></i>
                <p>
                    This course currently does not have any units. To begin building this course start by
                    <a :href="`/course/modify/${props.courseId}`">creating a unit.</a>
                </p>
            </div>
        </div>
    </div>
</template>
