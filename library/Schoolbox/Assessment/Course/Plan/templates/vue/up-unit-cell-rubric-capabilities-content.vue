<script setup lang="ts">
/**
 * A component that displays a list of rubric capabilities and their indicators
 */
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import type { RubricCapability } from "./unitPlannerTypes";

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
            <tr :class="{ 'hide-for-print': courseRubric.length === 0 }">
                <th>Course Rubrics</th>
            </tr>
            <template v-for="capability in courseRubric" :key="capability.id">
                <tr>
                    <th class="l-flex markdown border-none">
                        <span
                            v-if="props.usedCapabilities.includes(capability.id)"
                            class="icon-submissions"
                            title="Used in a Learning activity"
                        ></span>
                        <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                        <div v-html="capability.name"></div>
                    </th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id">
                    <td class="l-flex border-none">
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
                <tr v-if="props.manageCourseRoute" class="hide-for-print">
                    <td>
                        <p>
                            No rubric
                            {{ locale.rubricGroup.plural() }}
                            have been added to this course yet, start by adding rubric
                            {{ locale.rubricGroup.plural() }}
                            to the
                            <a :href="getReturnUrl(`${props.manageCourseRoute}`)"> course.</a>
                        </p>
                    </td>
                </tr>
                <tr v-else class="hide-for-print">
                    <td>
                        <p>
                            No rubric
                            {{ locale.rubricGroup.plural() }}
                            have been added to this course.
                        </p>
                    </td>
                </tr>
            </template>
            <tr :class="{ 'hide-for-print': unitRubric.length === 0 }">
                <th>Unit Rubrics</th>
            </tr>
            <template v-for="capability in unitRubric" :key="capability.id">
                <tr>
                    <th class="l-flex markdown border-none">
                        <span
                            v-if="props.usedCapabilities.includes(capability.id)"
                            class="icon-submissions"
                            title="Used in a Learning activity"
                        ></span>
                        <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                        <div v-html="capability.name"></div>
                    </th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id">
                    <td class="l-flex border-none">
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
                <tr v-if="props.manageUnitRoute" class="hide-for-print">
                    <td>
                        <p>
                            No rubric
                            {{ locale.rubricGroup.plural() }}
                            have been added to this
                            {{ locale.unit.singular() }}
                            yet, start by adding rubric
                            {{ locale.rubricGroup.plural() }}
                            to the
                            <a :href="getReturnUrl(`${props.manageUnitRoute}`)"> {{ locale.unit.singular() }}. </a>
                        </p>
                    </td>
                </tr>
                <tr v-else class="hide-for-print">
                    <td>
                        <p>
                            No rubric
                            {{ locale.rubricGroup.plural() }}
                            have been added to this
                            {{ locale.unit.singular() }}.
                        </p>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

<style lang="scss" scoped>
table.rb {
    margin-bottom: 5px;
    padding-bottom: 1rem;
    a {
        line-height: inherit; /* to remove the large line-height on the link */
    }
    p {
        padding: 0.625rem 0;
    }
    th:first-of-type {
        border-right: none; /* remove the 2px right border on the first th */
    }
    th p {
        font-weight: bold;
    }
    td {
        border-bottom: none;
        border-left: none;
    }
}

/* Styles for printing */
@media print {
    table td,
    th {
        border: none !important;
    }
}
</style>
