<script setup lang="ts">
interface Props {
    selectedLanguages: string[];
}

defineProps<Props>();

const emit = defineEmits<{
    close: [];
    languagesSelected: [languages: string[]];
}>();

const isOpen = window.Vue.ref<boolean>(false);

function open(): void {
    isOpen.value = true;
}

function close(): void {
    isOpen.value = false;
    emit("close");
}

function onConfirm(languages: string[]): void {
    emit("languagesSelected", languages);
    close();
}

defineExpose({ open });
</script>

<template>
    <div v-if="isOpen" class="modal-overlay" @click.self="close">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Select Languages</h3>
                <button type="button" class="modal-close" aria-label="Close" @click="close">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <slot :on-confirm="onConfirm" />
            </div>
        </div>
    </div>
</template>
