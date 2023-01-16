<script setup lang="ts">

const props = defineProps({
    iconText: {
        type: String,
        required: false,
        default: '',
    },
});

/**
 * Assign the correct icon class to apply an icon. Ideally,
 * you should just load an icon font and you wouldn't have to
 * do these sorts of checks at all. Icon fonts are the modern
 * way to store a slew of icons within your website - they're
 * generally very lightweight
 */

 /* const iconClass = `c-menu-item__icon--${props.iconText}`;
const classes = {};
classes[iconClass] = props.iconText; */

const iconClass = props.iconText === 'marker' ? 'c-menu-item__icon--marker' : `icon-${props.iconText}` ;
const classes = {};
classes[iconClass] = props.iconText;

const emit = defineEmits(["selected"])

/**
 * Dispatch to notify of selection
 */
function onSelected() {
    emit("selected");
}

</script>

<template>
    <button class="c-menu-item" @click="onSelected">
        <slot></slot>
        <div
            v-if="iconText"
            class="c-menu-item__icon"
            :class="classes"
        />
    </button>
</template>

<style lang="scss">
.c-menu-item {
    align-items: center;
    background-color: white;
    color: var(--content-ui-foreground);
    font-size: 0.8rem;
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 2rem;
    -webkit-user-select: none;
    user-select: none;

    &.is-disabled {
        cursor: not-allowed;
        pointer-events: none;
        color: #777777;
        background-color: #F8F8F8;
    }

    &:hover:not(.is-disabled) {
        background-color: var(--content-ui-background);
        cursor: pointer;
    }

    &__icon{
        margin-left: 0.5rem;

        .is-disabled &{
            background-color: #777777;
        }

        &--marker{
            background-color: var(--content-ui-foreground);
            border-radius: 50%;
            height: 4px;
            width: 4px;

        }
    }
}
</style>
