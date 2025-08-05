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
    <a v-if="props.href" :href="props.href" class="sbx-label-link" :title="props.title">
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
    color: white;
    display: inline-flex;
    font-size: 0.75rem;
    font-weight: 700;
    gap: 0.5rem;
    padding: var(--spacing-xs) var(--spacing-s);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.sbx-label-link {
    text-decoration: none;
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

.sbx-label__icon {
    align-items: center;
    display: inline-flex;
    justify-content: center;
}
</style>
