<script setup lang="ts">
/**
 * To display a users profile photo,
 * we pass through the src as well as a size
 */

type Props = {
    src?: string;
    size?: number;
};

const props = withDefaults(defineProps<Props>(), {
    src: "",
    size: 24,
});
</script>

<template>
    <div class="user-photo" :style="{ backgroundImage: `url(${props.src})` }"></div>
</template>

<style scoped lang="scss">
.user-photo {
    border-radius: 50%;
    width: 32px;
    min-width: 32px;
    height: 32px;
    min-height: 32px;
    background-color: #eeeeee;
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;

    &--square {
        border-radius: 0.25rem;
    }

    &--small {
        height: 24px;
        width: 24px;
    }

    &--large {
        height: 48px;
        width: 48px;
    }
}
</style>
