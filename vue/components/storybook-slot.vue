<script setup lang="ts">
/**
 *  storybook-slot.vue
 *
 *  A class used to write non-Vue components into Storybook. Storybook generally requires using a
 *  framework (React, Vue, Angular, etc) to build its stories - here we basically say to Vue 'you
 *  will render all the story contents into this generic container' and we can reuse the container
 *  across all non-Vue chunks of code for Storybook.
 */
</script>

<template>
    <slot></slot>
</template>

<style lang="scss"></style>
