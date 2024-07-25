<script setup lang="ts">
/**
 * A component that displays a list of rubric capabilities and their indicators
 */
import { RubricCapability } from "./unitPlannerTypes";

interface Props {
    rubricCapabilities: RubricCapability[];
    usedIndicators: int[];
    usedCapabilities: int[];
    unitFolderId: number;
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
 */
const courseRubric = window.Vue.computed(() => {
  return props.rubricCapabilities.filter((n) => n.course);
});

/**
 * filter to just showing the unit rubric entries
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
        <h2 v-if="courseRubric.length != 0">Course Rubrics</h2>
            <template v-for="capability in courseRubric" :key="capability.id">
                <tr>
                    <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                    <th class="markdown border-none" :class="{usedCapability: props.usedCapabilities.includes(capability.id)}" v-html="capability.name"></th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id">
                    <!-- eslint-disable-next-line vue/no-v-html | indicator.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                    <td class="markdown border-none" :class="{usedIndicator: props.usedIndicators.includes(indicator.id)}" v-html="indicator.name"></td>
                </tr>
            </template>
        <h2 v-if="unitRubric.length != 0">Unit Rubrics</h2>
            <template v-for="capability in unitRubric" :key="capability.id">
              <tr>
                <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                <th class="markdown border-none" :class="{usedCapability: props.usedCapabilities.includes(capability.id)}" v-html="capability.name"></th>
              </tr>
              <tr v-for="indicator in capability.indicators" :key="indicator.id">
                <!-- eslint-disable-next-line vue/no-v-html | indicator.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                <td class="markdown border-none" :class="{usedIndicator: props.usedIndicators.includes(indicator.id)}" v-html="indicator.name"></td>
              </tr>
            </template>
        </tbody>
    </table>
</template>

<style scoped>
table {
    margin-bottom: 5px;
}
.usedIndicator, .usedCapability{
  background-color: cyan;
}
/* Styles for printing */
@media print {
    table td,
    th {
        border: none !important;
    }
}
</style>
