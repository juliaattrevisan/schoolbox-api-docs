<script setup lang="ts">
/**
 * Base 'pop out' that display info outside the context of the document flow.
 * Typically linked to a button that triggers the display.
 */
</script>

<template>
    <div class="o-pop-out">
        <slot></slot>
    </div>
</template>

<style lang="scss">
.o-pop-out {
    background-color: #fff;
    border-radius: 0.25rem;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.2);
    min-width: 2rem;
    min-height: 2rem;
    padding: 0.5rem 0;
    position: absolute;
    z-index: var(--depth-pop-over);

    &__divider {
        border: 1px solid rgba(255, 0, 0, 0.15);
        margin-block-end: 0;
        margin-block-start: 0.5rem;
    }
}
</style>
