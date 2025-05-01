<script setup lang="ts">
/**
 * The icon that may show up next to a field's name in the profile editor
 * This represents a different way the field may show on the actual profile itself
 */

import { ProfileEditorFieldSettingKey } from "./types/profileEditorTypes";
import type { ProfileEditorActiveField, ProfileEditorAvailableField } from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorActiveField | ProfileEditorAvailableField;
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

/**
 * Is this a "click to reveal" field? If so, we'll show an icon
 */
const isClickToReveal = window.Vue.computed(() => {
    if (!("fieldSettings" in props.field)) {
        return false; // This isn't an "active" field, don't show the icon
    }
    return props.field.fieldSettings[ProfileEditorFieldSettingKey.ADDITIONAL_SETTINGS].includes("clickToReveal");
});
</script>

<template>
    <span
        v-if="props.field.icon"
        :data-tooltip="tooltipText !== undefined ? `pe-fields-icon-tip-${props.field.css}` : undefined"
        :title="tooltipText"
        class="pe-fields-icon pe-fields-icon-primary"
        :class="`${props.field.icon} ${props.field.css || ''}`"
    ></span>
    <span
        v-if="isClickToReveal"
        data-tooltip
        title="Content stays hidden until revealed by a click."
        class="pe-fields-icon icon-view-hollow"
    ></span>
</template>

<style scoped lang="scss">
.pe-fields-icon {
    cursor: help;

    // For now, if "alert" is a specific class that may be applied,
    // in that scenario, we want to change the color to the alert color
    &.alert {
        color: var(--color-alert);
    }
}

.pe-fields-icon-primary {
    color: var(--content-ui-foreground);
}
</style>
