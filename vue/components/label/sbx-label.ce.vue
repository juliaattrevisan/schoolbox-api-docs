<script setup lang="ts">
/**
 * To display a label
 */

interface Props {
    /**
     * The title of the label to show the helper text when set
     */
    title: string;
    /**
     * The sentiment of the label, which impacts the color of the label
     */
    sentiment?: "success" | "warning" | "alert" | "neutral" | undefined;
    /**
     * The background color of the label
     */
    backgroundColor?: string | undefined;
    /**
     * The text color of the label
     */
    textColor?: string | undefined;
    /**
     * The icon of the label
     */
    icon?: string | undefined;

    /**
     * The href of the label
     */
    href?: string | undefined;

    /**
     * The text of the label
     */
    text?: string | undefined;
}

const props = withDefaults(defineProps<Props>(), {
    title: "",
    sentiment: "neutral",
    backgroundColor: undefined,
    textColor: undefined,
    icon: undefined,
    href: undefined,
    text: undefined,
});
</script>

<template>
    <component
        :is="props.href ? 'a' : 'span'"
        :href="props.href"
        :class="`sbx-label sbx-label--${props.sentiment}`"
        :style="{
            backgroundColor: props.backgroundColor || undefined,
            color: props.textColor || undefined,
        }"
        :title="props.title"
    >
        <sbx-icon v-if="props.icon" :name="props.icon" class="sbx-label__icon" />
        {{ props.text }}
    </component>
</template>

<style scoped>
/*
For now these colors are just predefined based on what's in the system currently.
Long term they will reference the design colour tokens
*/
.sbx-label {
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: var(--spacing-xs) var(--spacing-s);
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}

.sbx-label--success {
    background-color: #4fa74f;
}

.sbx-label--warning {
    background-color: #ff7d00;
}

.sbx-label--alert {
    background-color: #fa4a47;
}

.sbx-label--neutral {
    background-color: #dddddd;
    color: #474747;
}
</style>
