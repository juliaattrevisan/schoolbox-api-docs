<script setup lang="ts">
interface Props {
    /**
     * To display this as a link instead of a button
     */
    link?: string;
    /**
     * Force callback over the link being followed
     */
    forceCallback?: boolean;
    /**
     * When we don't want to wrap in <a> or <button>
     */
    justText?: boolean;
}

const props = defineProps<Props>();

/**
 * A button contained within pop-out-menu-markbook
 */
const emit = defineEmits(["selected"]);

/**
 * Dispatch to notify of selection
 * @param e - the event
 */
function onSelected(e: Event): void {
    if (props.link && !props.forceCallback) {
        return;
    }

    e.preventDefault();
    emit("selected");
}
</script>

<template>
    <div v-if="props.justText" class="c-menu-item">
        <slot></slot>
    </div>
    <a v-else-if="props.link" class="c-menu-item" :href="props.link" @click="onSelected">
        <slot></slot>
    </a>
    <button v-else class="c-menu-item" @click="onSelected">
        <slot></slot>
    </button>
</template>

<style lang="scss">
.c-menu-item {
    align-items: center;
    background-color: white;
    font-size: 0.8rem;
    display: flex;
    justify-content: space-between;
    margin: 0;
    padding: 0.5rem 1rem;
    text-wrap: pretty; /* prevent text orphans on supported browsers */
    user-select: none;
    width: 100%;

    &.is-disabled {
        cursor: not-allowed;
        pointer-events: none;
        color: #777777;
        background-color: #f8f8f8;
    }

    //Override standard button styling
    &:focus {
        background-color: unset;
    }

    &__icon {
        margin-left: 0.5rem;

        .is-disabled & {
            background-color: #777777;
        }

        &--marker {
            background-color: var(--sbx-config-color-accent, var(--content-ui-foreground));
            border-radius: 50%;
            height: 0.25rem;
            width: 0.25rem;
        }
    }
}

/* We don't want these styles applying to the div v-if="props.justText", just the <a> and <button> elements */
a.c-menu-item,
button.c-menu-item {
    color: var(--sbx-config-color-accent, var(--content-ui-foreground));
    &:hover:not(.is-disabled) {
        background-color: var(--sbx-config-color-active, var(--content-ui-background));
    }
}
</style>
