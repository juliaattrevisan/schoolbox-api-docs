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
    <div class="c-form-element">
        <input id="rad1" type="radio" class="c-input-radio" />
        <label for="rad1" class="c-label">Radio</label>
    </div>
</template>

<style lang="scss" scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

@import "www/static/css/core/ui/forms/_input-radio.scss";
</style>
