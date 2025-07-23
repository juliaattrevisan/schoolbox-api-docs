<script lang="ts" setup>
import type { FileDropFile } from "./fileDropTypes";

interface Props {
    file: FileDropFile;
    disabled?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(["remove"]);

const previewType = window.Vue.computed(() => {
    if (!props.file.file.type.startsWith("image/")) {
        return {
            type: "file",
            icon: `/ajax/mime/${props.file.file.type}`,
        };
    }

    const blob = new Blob([props.file.file], { type: props.file.file.type });
    return {
        type: "image",
        icon: URL.createObjectURL(blob),
    };
});

const fileSize = window.Vue.computed(() => {
    let selectedSize = 0;
    let selectedUnit = "b";

    if (props.file.file.size > 0) {
        const units = ["tb", "gb", "mb", "kb", "b"];
        const filesizeBase = 1e3;

        for (let i = 0; i < units.length; i += 1) {
            const unit = units[i];
            const cutoff = filesizeBase ** (4 - i) / 10;

            if (props.file.file.size >= cutoff) {
                selectedSize = props.file.file.size / filesizeBase ** (4 - i);
                selectedUnit = unit;
                break;
            }
        }

        selectedSize = Math.round(10 * selectedSize) / 10; // Cutting of digits
    }

    return {
        size: selectedSize,
        unit: selectedUnit.toUpperCase(),
    };
});
</script>

<template>
    <div
        class="dz-preview"
        :class="{
            'dz-file-preview': previewType.type === 'file',
            'dz-image-preview': previewType.type === 'image',
            'dz-error': props.file.error !== false,
        }"
        style="margin: 0"
    >
        <div class="dz-image">
            <img
                :class="{
                    'fd-image-square': previewType.type === 'image',
                }"
                :src="previewType.icon"
            />
        </div>

        <div class="dz-details">
            <div class="dz-size">
                <span>
                    <strong>{{ fileSize.size }}</strong> {{ fileSize.unit }}
                </span>
            </div>
            <div class="dz-filename">
                <span>{{ props.file.file.name }}</span>
            </div>
            <div v-if="props.file.error" class="dz-error-message">
                <span>{{ props.file.error }}</span>
            </div>
        </div>

        <div v-if="!props.disabled" class="dz-close">
            <a class="button dz-remove no-margin" @click="emit('remove')">×</a>
        </div>
    </div>
</template>

<style scoped lang="scss">
.fd-image-square {
    aspect-ratio: 1 / 1;
    object-fit: cover;
}
</style>
