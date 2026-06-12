<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { RUBRIC_OPEN_CALCULATION_DETAILS_MODAL } from "../rubricEvent";
import type { RubricGrid, RubricMarkedGrid } from "./types";

interface Props {
    rubrics: {
        rubric: RubricGrid;
        markedGrid: RubricMarkedGrid;
    }[];
}
const props = defineProps<Props>();

const open = window.Vue.ref(false);

/**
 * When the RUBRIC__OPEN_CALCULATION_DETAILS_MODAL event is emitted
 */
function openCalculationDetailsModal(): void {
    open.value = true;
}

window.Vue.onMounted(() => {
    /**
     * listen for the event to open the modal, this has to be an event since the button is
     * currently outside of the Vue component and rendered after the component is mounted, so
     * we can't teleport an element out or just render a button normally at the moment.
     *
     * If the button were to be moved inside of Vue in the future, this should be simplified.
     */
    document.addEventListener(RUBRIC_OPEN_CALCULATION_DETAILS_MODAL, openCalculationDetailsModal);
});

window.Vue.onUnmounted(() => {
    // clean up
    document.removeEventListener(RUBRIC_OPEN_CALCULATION_DETAILS_MODAL, openCalculationDetailsModal);
});
</script>

<template>
    <basic-modal v-model:is-open="open" has-background-click>
        <h2>{{ locale.grades.proper() }} Calculation</h2>
        <p>The overall percentage mark is calculated from the rubrics the {{ locale.activity.singular() }} contains.</p>
        <p>
            Each rubric utilises points to give a normalised grade for the rubric. (marks awarded / marks available) ×
            100.
        </p>

        TODO
    </basic-modal>
</template>
