<script setup lang="ts">
/**
 *
 */
defineProps<{
    src: {
        type: String,
        required: true,
        default: 'https://placedog.net/122/112',
    },
    size: {
        type: Number,
        required: false,
        default: 24
    },
}>();

</script>

<template>
    <div class="user-photo" v-bind:style="{ backgroundImage: `url(${src})`}" ></div>
</template>

<style scoped lang="scss">
    .user-photo{
        border-radius: 50%;
        width: 32px;
        height: 32px;
        background-color: #EEEEEE;
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;

        &--square{
            border-radius: 0.25rem;
        }

        &--small{
            height: 24px;
            width: 24px;
        }

        &--large{
            height: 48px;
            width: 48px;
        }
    }
</style>
