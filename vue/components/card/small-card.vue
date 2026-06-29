<script setup lang="ts">
/**
 * Small card component that can be used like:
 * <small-card>
 *  Here's my small card content
 * </small-card>
 */
</script>

<template>
    <div class="sbx-card sbx-card--small">
        <slot></slot>
    </div>
</template>

<style lang="scss">
.sbx-card {
    padding: 0.5rem 1.5rem;
    background-color: white;

    &--small {
        display: flex;
        gap: 0.5rem;
    }

    &__details {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    &__subtext {
        color: rgba(0, 0, 0, 0.6);
        font-size: 0.75rem;
    }

    &__text,
    &__subtext {
        font-size: 0.75rem;
    }
}
</style>
