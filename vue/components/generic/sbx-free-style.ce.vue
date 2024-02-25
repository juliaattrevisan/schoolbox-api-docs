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
            <sbx-icon name="approve" color="var(--form-border-color)" />
            <sbx-icon name="close" color="var(--form-border-color)" />
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

/**
A toggle component, primarily referenced from https://kittygiraudel.com/2021/04/05/an-accessible-toggle/
*/

.c-toggle {
    align-items: center;
    cursor: pointer;
    display: flex;
    flex-wrap: wrap;
    gap: 1ch;
    margin-bottom: 1em;
    position: relative;
    &__input {
        height: 100%;
        opacity: 0;
        position: absolute;
        width: 100%;
    }
    &__display {
        --offset: 0.25em;
        --diameter: 1.8em;
        align-items: center;
        background-color: #fbe4e2;
        border: 0.1em solid rgb(0 0 0 / 0.2);
        border-radius: 100vw;
        box-sizing: content-box;
        display: inline-flex;
        height: calc(var(--diameter) + var(--offset) * 2);
        justify-content: space-around;
        position: relative;
        transition: 250ms;
        width: calc(var(--diameter) * 2 + var(--offset) * 2);
        &::before {
            background-color: white;
            border: 0.1em solid rgb(0 0 0 / 0.2);
            border-radius: 50%;
            box-sizing: border-box;
            content: "";
            height: var(--diameter);
            left: var(--offset);
            position: absolute;
            top: 50%;
            transform: translate(0, -50%);
            transition: inherit;
            width: var(--diameter);
            will-change: transform;
            z-index: 2;
        }
    }
}

button.c-toggle {
    background-color: transparent;
    border: 0;
    font: inherit;
    padding: 0;
}

.c-toggle:focus .c-toggle__display,
.c-toggle__input:focus + .c-toggle__display {
    outline: 1px dotted #212121;
    outline: 1px auto -webkit-focus-ring-color;
    outline-offset: 2px;
}

.c-toggle:focus,
.c-toggle:focus:not(:focus-visible) .c-toggle__display,
.c-toggle__input:focus:not(:focus-visible) + .c-toggle__display {
    outline: 0;
}

.c-toggle[aria-pressed="true"] .c-toggle__display,
.c-toggle__input:checked + .c-toggle__display {
    background-color: #e3f5eb;
}

.c-toggle[aria-pressed="true"] .c-toggle__display::before,
.c-toggle__input:checked + .c-toggle__display::before {
    transform: translate(100%, -50%);
}

.c-toggle[disabled] .c-toggle__display,
.c-toggle__input:disabled + .c-toggle__display {
    cursor: not-allowed;
    filter: grayscale(40%);
    opacity: 0.8;
}

[dir="rtl"] .c-toggle__display::before {
    left: auto;
    right: var(--offset);
}

[dir="rtl"] .c-toggle[aria-pressed="true"] + .c-toggle__display::before,
[dir="rtl"] .c-toggle__input:checked + .c-toggle__display::before {
    transform: translate(-100%, -50%);
}
</style>
