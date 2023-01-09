<script setup lang="ts">
/**
 *
 */
</script>

<template>
    <div class="o-pop-out">
       <slot></slot>
    </div>
</template>

<style lang="scss">
    .o-pop-out {
        background-color: #FFF;
        border-radius: 0.25rem;
        box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.2);
        min-width: 4rem;
        min-height: 4rem;
        padding: 0.5rem 0;
        position: absolute;
        z-index: 100001;

        &__divider {
            border: 1px solid rgba(255, 0, 0, 0.15);
            margin-block-end: 0;
            margin-block-start: 0.5rem;
        }
    }
</style>
