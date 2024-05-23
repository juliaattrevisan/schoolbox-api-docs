<script setup lang="ts">
/**
 * Displayed when there's currently no Units linked to the Course
 * NOTE: We initially had planned to create a new unit directly from the unit planner,
 * but for now this functionality has been removed as we would just be duplicating
 * functionality provided elsewhere without also provided the ability to rename/deleting units as well.
 * The relevant code has been commented out for now as it may be added in the future however.
 */
import { getReturnUrl } from "@js/util/sbx/urlUtils";
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
                    <a :href="`/course/modify/${props.courseId}?${getReturnUrl()}`">creating a unit.</a>
                </p>
            </div>
        </div>
    </div>
</template>
