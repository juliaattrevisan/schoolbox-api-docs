<script setup lang="ts">
/**
 * This web component is used purely to help style new components without our existing styles interfering.
 * Once the new component is styled, it can be brought into SBX to also add any temporary overrides.
 */
</script>

<template>
    <div class="c-form-group">
        <label for="news_default_expiry-field" class="c-label c-label--required">Default News Archiving Period</label>
        <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
        <input id="news_default_expiry-field" class="c-input-text" type="text" required />
    </div>
</template>

<style lang="scss" scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

:root {
    --error-colour: #fa4a47;
    --hint-color: #8d8d8d;
}
.c-form-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-s);
}

.c-hint {
    color: var(--hint-color);
    font-size: 0.875rem;
    font-style: italic;
    line-height: 1.25rem;
}

.c-label {
    display: block;
    font-size: 1rem;
    font-weight: bold;
    &--required {
        &::after {
            content: " *";
            color: var(--error-colour);
        }
    }
}

.c-input-text {
    background-color: var(--foreground-colour);
    border: 0.0625rem solid var(--form-border-color);
    border-radius: var(--roundness-inputs);
    font-size: 1rem;
    font-size: max(16px, 1rem);
    font-family: inherit;
    height: 2.25rem;
    line-height: 1;
    padding: var(--spacing-xs) var(--spacing-s);
    width: 100%;
    -webkit-font-smoothing: antialiased;
    &:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }
    &:focus {
        outline: 3px solid var(--form-border-color);
        outline-offset: 0.125rem;
    }
}
</style>
