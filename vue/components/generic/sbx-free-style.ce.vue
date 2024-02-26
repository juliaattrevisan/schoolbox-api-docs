<script setup lang="ts">
/**
 * This web component is used purely to help style new components without our existing styles interfering.
 * Once the new component is styled, it can be brought into Schoolbox to finalise and ensure the new styles are kept the same once all our current styles are applied,
 * ensuring any old styles are not affecting the new component and overriding the new styles.
 *
 * The component can be viewed at http://localhost:6006/?path=/docs/getting-started-contributing-free-style--docs after running `npm run storybook`
 */
</script>

<template>
    <label class="c-toggle" for="toggle">
        <input id="toggle" type="checkbox" name="toggle" class="c-toggle__input" checked />
        <span class="c-toggle__display" hidden>
            <sbx-icon name="approve" color="var(--color-foreground-primary)" />
            <sbx-icon name="close" color="var(--color-text-primary)" />
        </span>
        Receive notifications
    </label>
</template>

<style lang="scss" scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

// @import "www/static/css/core/ui/forms/_input-toggle.scss";
</style>
