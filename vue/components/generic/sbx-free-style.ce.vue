<script setup lang="ts">
/**
 * This web component is used purely to help style new components without our existing styles interfering.
 * Once the new component is styled, it can be brought into Storybook to finalise and ensure the new styles are kept the same once all our current styles are applied,
 * ensuring any old styles are not affecting the new component and overriding the new styles.
 *
 * The component can be viewed at https://vagrant.dev.schoolbox.cloud/greenhouse/free-style
 * For faster styling, you may also wish to use the dev tools and first style the component in the browser, then copy the styles into the component.
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

/*
    The <select> HTML element represents a control that provides a menu of options.
    Styled primarily based from https://moderncss.dev/custom-select-styles-with-pure-css/
*/

.c-select {
    align-items: center;
    background-color: var(--foreground-colour);
    border: 1px solid var(--form-border-color);
    border-radius: var(--roundness-inputs);
    cursor: pointer;
    display: grid; // to allow for the pseudo-element ::after to be positioned correctly
    font-size: max(16px, 1rem);
    grid-template-areas: "select";
    line-height: 1.1;
    padding: var(--spacing-s);
    position: relative;
    select,
    &::after {
        grid-area: select;
    }
    select {
        appearance: none; // A reset of styles, including removing the default dropdown arrow
        background-color: transparent;
        border: none;
        cursor: inherit;
        font-family: inherit;
        font-size: inherit;
        line-height: inherit;
        margin: 0;
        outline: none; // Remove focus outline, will add on alternate element
        padding: 0 1em 0 0;
        width: 100%;
        z-index: 1; // Stack above custom arrow
    }
    &:not(.select--multiple)::after {
        // Custom arrow
        content: "";
        justify-self: end;
        width: 0.8em;
        height: 0.5em;
        background-color: var(--form-border-color);
        clip-path: polygon(100% 0%, 0 0%, 50% 100%);
    }
}

// Interim solution until :focus-within has better support
select:focus + .focus {
    position: absolute;
    top: -1px;
    left: -1px;
    right: -1px;
    bottom: -1px;
    border: 2px solid var(--form-border-color);
    border-radius: inherit;
}

select[multiple] {
    height: 6rem;
    /*
     * Safari will not reveal an option
     * unless the select height has room to
     * show all of it
     * Firefox and Chrome allow showing
     * a partial option
     */
    padding-right: 0;
    option {
        outline-color: var(--form-border-color); // Only affects Chrome
        white-space: normal;
    }
}

.c-select--disabled {
    cursor: not-allowed;
    background-color: #eee;
    background-image: linear-gradient(to top, #ddd, #eee 33%);
}
</style>
