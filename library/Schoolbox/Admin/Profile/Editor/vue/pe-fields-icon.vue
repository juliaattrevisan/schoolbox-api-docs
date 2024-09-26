<script setup lang="ts">
/**
 * The icon that may show up next to a field's name in the profile editor
 * This represents a different way the field may show on the actual profile itself
 */

import type { ProfileEditorAvailableField } from "./profileEditorTypes";

interface Props {
    field: ProfileEditorAvailableField;
}

const props = defineProps<Props>();

/**
 * The definitions for the tooltip are currently hard-coded here,
 * this links a specific class, currently "alert" and "highlight" to a specific tooltip
 * The class may also have specific styling applied to it, such as a different color, see the style section below.
 * It may be a good idea to more clearly define these in the future, or make them more dynamic...
 */
const fieldCssDefinition: Record<string, string> = {
    alert: "This field will show up as an alert on the profile page",
    highlight: "This field will show up as highlighted on the profile page",
};

const tooltipText = window.Vue.computed<string | undefined>(() => {
    if (props.field.css && fieldCssDefinition[props.field.css] !== undefined) {
        return fieldCssDefinition[props.field.css];
    }
    return undefined;
});
</script>

<template>
    <span
        v-if="props.field.icon"
        :data-tooltip="tooltipText !== undefined ? `pe-fields-icon-tip-${props.field.css}` : undefined"
        :title="tooltipText"
        class="pe-fields-icon"
        :class="`${props.field.icon} ${props.field.css || ''}`"
    ></span>
</template>

<style scoped lang="scss">
.pe-fields-icon {
    cursor: help;
    color: var(--content-ui-foreground);

    // For now, if "alert" is a specific class that may be applied,
    // in that scenario, we want to change the color to the alert color
    &.alert {
        color: var(--color-alert);
    }
}
</style>
./profileEditorTypes
