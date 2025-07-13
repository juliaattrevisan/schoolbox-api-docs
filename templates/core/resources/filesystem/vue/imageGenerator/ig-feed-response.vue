<script lang="ts" setup>
import type { ImageResponse } from "./imageGeneratorTypes";

interface Props {
    message: ImageResponse;
}

const props = defineProps<Props>();
const emit = defineEmits(["loaded"]);
</script>

<template>
    <div class="ig-feed-response">
        <img :src="props.message.src" @load="emit('loaded')" />
        <p v-if="props.message.message" class="l-mar--0-bottom l-mar-s--top">{{ props.message.message }}</p>
    </div>
</template>

<style scoped lang="scss">
.ig-feed-response {
    width: 80%;

    img {
        max-width: 200px;
        max-height: 200px;
    }
}
</style>
