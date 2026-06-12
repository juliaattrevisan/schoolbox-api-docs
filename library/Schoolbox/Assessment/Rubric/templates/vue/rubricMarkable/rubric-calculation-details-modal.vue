<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { RUBRIC_OPEN_CALCULATION_DETAILS_MODAL } from "../rubricEvent";
import { useRubricMarks } from "./composable/useRubricMarks";
import type { RubricGrid, RubricMarkedGrid } from "./types";
import { applyMarkScheme, deriveRubricMark } from "./rubricUtils";

interface Props {
    rubrics: {
        rubric: RubricGrid;
        markedGrid: RubricMarkedGrid;
    }[];
}
const props = defineProps<Props>();

const { derivedRubricMarks } = useRubricMarks(
    // The derived total mark should always be a percentage in the context of this modal
    { mode: MarkTypeSlug.PERCENT },
    window.Vue.toRef(props.rubrics),
);

const rubricInfo = window.Vue.computed(() =>
    props.rubrics.map(({ rubric, markedGrid }) => {
        const derivedMark = derivedRubricMarks.value.find((m) => m.rubricId === rubric.id)!;

        // Always display using the total mark scheme in this modal
        const scaledMark = deriveRubricMark(markedGrid);
        const { humanReadableMark } = applyMarkScheme(scaledMark, rubric.derivedMaxValue, {
            mode: MarkTypeSlug.TOTAL,
            conf: { totalMark: rubric.derivedMaxValue },
        });

        return {
            title: rubric.title,
            scaledWeight: derivedMark.scaledWeightRounded,
            humanReadableMark,
            normalisedMark: markedGrid.normalisedMark,
        };
    }),
);

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

        <div v-for="(rubric, index) in rubricInfo" :key="index">
            <strong>
                {{ rubric.title.length ? rubric.title : `Rubric ${index + 1}` }}
                -
                {{ rubric.scaledWeight }}% Weighting
            </strong>

            <p>({{ rubric.humanReadableMark }}) × 100 = {{ rubric.normalisedMark }}%</p>
        </div>

        <strong>Combined</strong>
        <p>TODO</p>
    </basic-modal>
</template>
