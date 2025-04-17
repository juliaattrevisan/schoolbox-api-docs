<script setup lang="ts">
/**
 * To display a label
 */

interface Props {
    /**
     * The title of the label
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
}

const props = withDefaults(defineProps<Props>(), {
    title: "",
    sentiment: "neutral",
    backgroundColor: undefined,
    textColor: undefined,
});
</script>

<template>
    <span
        :class="`sbx-label sbx-label--${props.sentiment}`"
        :style="{
            backgroundColor: props.backgroundColor ? props.backgroundColor : undefined,
            color: props.textColor ? props.textColor : undefined,
        }"
        >{{ props.title }}</span
    >
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
