<script setup lang="ts">
/**
 * To display a label
 */

interface Props {
    /**
     * The title of the label to show the helper text when set
     */
    title?: string;
    /**
     * The sentiment of the label, which impacts the color of the label
     */
    sentiment?: "success" | "warning" | "alert" | "neutral";
    /**
     * The background color of the label
     */
    backgroundColor?: string;
    /**
     * The text color of the label
     */
    textColor?: string;
    /**
     * The icon of the label
     */
    icon?: string;
    /**
     * The href of the label
     */
    href?: string;
    /**
     * The text of the label
     */
    text?: string;
    /**
     * Can be used as a button
     */
    isButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: "",
    sentiment: "neutral",
    backgroundColor: undefined,
    textColor: undefined,
    icon: undefined,
    href: undefined,
    text: undefined,
    isButton: false,
});

const emit = defineEmits<{
    select: [{ href: string | undefined; title: string | undefined }];
}>();
</script>

<template>
    <button
        v-if="props.isButton"
        type="button"
        class="sbx-label-button"
        :title="props.title"
        @click="emit('select', { href: props.href, title: props.title })"
    >
        <span
            :class="`sbx-label sbx-label--${props.sentiment}`"
            :title="props.title"
            :style="{
                backgroundColor: props.backgroundColor || undefined,
                color: props.textColor || undefined,
            }"
        >
            <sbx-icon v-if="props.icon" :name="props.icon" class="sbx-label__icon" />
            {{ props.text }}
        </span>
    </button>
    <a v-else-if="props.href" :href="props.href" class="sbx-label-link" :title="props.title">
        <span
            :class="`sbx-label sbx-label--${props.sentiment}`"
            :title="props.title"
            :style="{
                backgroundColor: props.backgroundColor || undefined,
                color: props.textColor || undefined,
            }"
        >
            <sbx-icon v-if="props.icon" :name="props.icon" class="sbx-label__icon" />
            {{ props.text }}
        </span>
    </a>
    <span
        v-else
        :class="`sbx-label sbx-label--${props.sentiment}`"
        :title="props.title"
        :style="{
            backgroundColor: props.backgroundColor || undefined,
            color: props.textColor || undefined,
        }"
    >
        <sbx-icon v-if="props.icon" :name="props.icon" class="sbx-label__icon" />
        {{ props.text }}
    </span>
</template>

<style scoped>
/*
For now these colors are just predefined based on what's in the system currently.
Long term they will reference the design colour tokens
*/
.sbx-label {
    align-items: center;
    border-radius: var(--sbx-config-field-corner-radius, 0);
    color: white;
    display: inline-flex;
    font-size: var(--sbx-font-size-xs, 0.75rem);
    gap: 0.5rem;
    padding: var(--spacing-xs) var(--spacing-s);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* Lean into the span styles instead, so remove the default button styles */
.sbx-label-button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.sbx-label-button:focus {
    border-color: var(--sbx-config-color-accent, black);
    outline: var(--sbx-border-width-normal, 1px) solid var(--sbx-config-color-accent, black);
    outline-offset: calc(var(--sbx-border-width-normal, 1px) + 1px);
}

.sbx-label-link {
    text-decoration: none;
}

.sbx-label--success {
    background-color: var(--sbx-color-success, #4fa74f);
    color: var(--sbx-color-success-text, #ffffff);
}

.sbx-label--warning {
    background-color: var(--sbx-color-warning, #ff7d00);
    color: var(--sbx-color-warning-text, #ffffff);
}

.sbx-label--alert {
    background-color: var(--sbx-color-alert, #fa4a47);
    color: var(--sbx-color-alert-text, #ffffff);
}

.sbx-label--neutral {
    background-color: var(--sbx-color-highlight, #dddddd);
    color: var(--sbx-color-highlight-text, #474747);
}

.sbx-label__icon {
    align-items: center;
    display: inline-flex;
    justify-content: center;
}
</style>
