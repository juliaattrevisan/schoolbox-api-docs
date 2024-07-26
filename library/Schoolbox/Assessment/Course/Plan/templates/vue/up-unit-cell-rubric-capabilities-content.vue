<script setup lang="ts">
/**
 * A component that displays a list of rubric capabilities and their indicators
 */
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import { RubricCapability } from "./unitPlannerTypes";

interface Props {
    rubricCapabilities: RubricCapability[];
    usedIndicators: number[];
    usedCapabilities: number[];
    manageCourseRoute: string | null;
    manageUnitRoute: string | null;
}

const props = defineProps<Props>();

/**
 * Is this cell empty?
 * Used by the parent component to determine whether to show the empty state
 */
const isEmpty = window.Vue.computed(() => {
    return props.rubricCapabilities.length === 0;
});

/**
 * filter to just showing the course rubric entries
 * @returns {RubricCapability[]} - The course rubric capabilities
 */
const courseRubric = window.Vue.computed(() => {
    return props.rubricCapabilities.filter((n) => n.course);
});

/**
 * filter to just showing the unit rubric entries
 * @returns {RubricCapability[]} - The unit rubric capabilities
 */
const unitRubric = window.Vue.computed(() => {
    return props.rubricCapabilities.filter((n) => !n.course);
});

// Allow the parent component to identify if the cell is empty
defineExpose({ isEmpty });
</script>

<template>
    <table ref="rubricCapabilitiesEl" class="rb rb-capabilities-indicators">
        <tbody>
            <tr>
                <th>Course Rubrics</th>
            </tr>
            <template v-for="capability in courseRubric" :key="capability.id">
                <tr class="card small-12 compact">
                    <th class="markdown border-none">
                        <span
                            v-if="props.usedCapabilities.includes(capability.id)"
                            class="icon-submissions"
                            title="Used in a Learning activity"
                        ></span>
                        <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                        <div v-html="capability.name"></div>
                    </th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id" class="card small-12 compact">
                    <td class="markdown border-none">
                        <span
                            v-if="props.usedIndicators.includes(indicator.id)"
                            class="icon-submissions"
                            title="Used in a Learning activity"
                        ></span>
                        <!-- eslint-disable-next-line vue/no-v-html | indicator.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                        <div v-html="indicator.name"></div>
                    </td>
                </tr>
            </template>
            <template v-if="courseRubric.length === 0">
                <p v-if="props.manageCourseRoute">
                    No rubric {{ locale.rubricGroup.plural() }} have been added to this course yet, start by adding
                    rubric {{ locale.rubricGroup.plural() }} to the
                    <a :href="getReturnUrl(`${props.manageCourseRoute}`)"> course </a>
                </p>
                <p v-else>No rubric {{ locale.rubricGroup.plural() }} have been added to this course.</p>
            </template>
            <tr>
                <th>Unit Rubrics</th>
            </tr>
            <template v-for="capability in unitRubric" :key="capability.id">
                <tr class="card small-12 compact">
                    <th class="markdown border-none">
                        <span
                            v-if="props.usedCapabilities.includes(capability.id)"
                            class="icon-submissions"
                            title="Used in a Learning activity"
                        ></span>
                        <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                        <div v-html="capability.name"></div>
                    </th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id" class="card small-12 compact">
                    <td class="markdown border-none">
                        <span
                            v-if="props.usedIndicators.includes(indicator.id)"
                            class="icon-submissions"
                            title="Used in a Learning activity"
                        ></span>
                        <!-- eslint-disable-next-line vue/no-v-html | indicator.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                        <div v-html="indicator.name"></div>
                    </td>
                </tr>
            </template>
            <template v-if="unitRubric.length === 0">
                <p v-if="props.manageUnitRoute">
                    No rubric {{ locale.rubricGroup.plural() }} have been added to this
                    {{ locale.unit.singular() }} yet, start by adding rubric {{ locale.rubricGroup.plural() }} to the
                    <a :href="getReturnUrl(`${props.manageUnitRoute}`)">
                        {{ locale.unit.singular() }}
                    </a>
                </p>
                <p v-else>
                    No rubric {{ locale.rubricGroup.plural() }} have been added to this {{ locale.unit.singular() }}.
                </p>
            </template>
        </tbody>
    </table>
</template>

<style scoped>
table {
    margin-bottom: 5px;
}

table.rb a {
    line-height: inherit; /* to remove the large line-height on the link */
}

table.rb p {
    padding: 0.625rem;
}

/* Styles for printing */
@media print {
    table td,
    th {
        border: none !important;
    }
}
</style>
