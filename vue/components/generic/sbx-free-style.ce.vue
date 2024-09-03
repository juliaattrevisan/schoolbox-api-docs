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
    <div class="c-select">
        <select id="standard-select">
            <option value="Option 1">Option 1</option>
            <option value="Option 2">Option 2</option>
            <option value="Option 3">Option 3</option>
            <option value="Option 4">Option 4</option>
            <option value="Option 5">Option 5</option>
            <option value="Option length">Option that has too long of a value to fit</option>
        </select>
    </div>
</template>

<style lang="scss" scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

@import "../../../stories/assets/styles/_form.scss";
</style>
