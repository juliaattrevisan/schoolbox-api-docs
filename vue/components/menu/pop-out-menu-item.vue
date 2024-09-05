<script setup lang="ts">
/**
 * A button contained within pop-out-menu-markbook
 */

const emit = defineEmits(["selected"]);

/**
 * Dispatch to notify of selection
 */
function onSelected(): void {
    emit("selected");
}
</script>

<template>
    <button class="c-menu-item" @click="onSelected">
        <slot></slot>
    </button>
</template>

<style lang="scss">
.c-menu-item {
    align-items: center;
    background-color: white;
    color: var(--content-ui-foreground);
    font-size: 0.8rem;
    display: flex;
    justify-content: space-between;
    margin: 0;
    padding: 0.5rem 2rem;
    -webkit-user-select: none;
    user-select: none;
    width: 100%;

    &.is-disabled {
        cursor: not-allowed;
        pointer-events: none;
        color: #777777;
        background-color: #f8f8f8;
    }

    &:hover:not(.is-disabled) {
        background-color: var(--content-ui-background);
        cursor: pointer;
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
            background-color: var(--content-ui-foreground);
            border-radius: 50%;
            height: 0.25rem;
            width: 0.25rem;
        }
    }
}
</style>
