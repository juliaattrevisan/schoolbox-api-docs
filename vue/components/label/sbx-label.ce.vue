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
     * The icon color of the label
     */
    iconColor?: string;
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
    /**
     * Whether the label should fill the available parent container width
     */
    isFullWidth?: boolean;
    /**
     * The size of the label
     */
    size?: "m" | "l";
}

const props = withDefaults(defineProps<Props>(), {
    title: "",
    sentiment: "neutral",
    backgroundColor: undefined,
    textColor: undefined,
    icon: undefined,
    iconColor: undefined,
    href: undefined,
    text: undefined,
    isButton: false,
    isFullWidth: false,
    size: "m",
});

const emit = defineEmits<{
    select: [{ href: string | undefined; title: string | undefined }];
}>();

/**
 * Compute the classes to apply to the label based on the props
 */
const labelClasses = window.Vue.computed(() => [
    "sbx-label",
    `sbx-label--${props.sentiment}`,
    `sbx-label--${props.size}`,
    { "sbx-label--full-width": props.isFullWidth },
]);
</script>

<template>
    <button
        v-if="props.isButton"
        type="button"
        :class="['sbx-label-button', { 'sbx-label-button--full-width': props.isFullWidth }]"
        :title="props.title"
        @click="emit('select', { href: props.href, title: props.title })"
    >
        <span
            :class="labelClasses"
            :title="props.title"
            :style="{
                backgroundColor: props.backgroundColor || undefined,
                color: props.textColor || undefined,
            }"
        >
            <sbx-icon
                v-if="props.icon"
                :name="props.icon"
                class="sbx-label__icon"
                :color="props.iconColor || undefined"
                :size="props.size"
            />
            {{ props.text }}
        </span>
    </button>
    <a
        v-else-if="props.href"
        :href="props.href"
        :class="['sbx-label-link', { 'sbx-label-link--full-width': props.isFullWidth }]"
        :title="props.title"
    >
        <span
            :class="labelClasses"
            :title="props.title"
            :style="{
                backgroundColor: props.backgroundColor || undefined,
                color: props.textColor || undefined,
            }"
        >
            <sbx-icon
                v-if="props.icon"
                :name="props.icon"
                class="sbx-label__icon"
                :color="props.iconColor || undefined"
                :size="props.size"
            />
            {{ props.text }}
        </span>
    </a>
    <span
        v-else
        :class="labelClasses"
        :title="props.title"
        :style="{
            backgroundColor: props.backgroundColor || undefined,
            color: props.textColor || undefined,
        }"
    >
        <sbx-icon
            v-if="props.icon"
            :name="props.icon"
            class="sbx-label__icon"
            :color="props.iconColor || undefined"
            :size="props.size"
        />
        {{ props.text }}
    </span>
</template>

<style scoped>
.sbx-label {
    align-items: center;
    box-sizing: border-box;
    border-radius: var(--sbx-config-field-corner-radius, 0);
    color: white;
    display: inline-flex;
    gap: var(--sbx-size-xs, 0.5rem);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.sbx-label--m {
    font-size: var(--sbx-font-size-xs, 0.875rem);
    padding: var(--spacing-xs) var(--spacing-s);
}

.sbx-label--l {
    font-size: var(--sbx-font-size-s, 0.9375rem);
    padding: var(--spacing-s) var(--spacing-m);
}

.sbx-label--full-width {
    width: 100%;
}

/* Lean into the span styles instead, so remove the default button styles */
.sbx-label-button {
    background: none;
    border: none;
    cursor: pointer;
    font: inherit;
    padding: 0;
}

.sbx-label-button--full-width,
.sbx-label-link--full-width {
    display: inline-flex;
    width: 100%;
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
