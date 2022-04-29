<script setup>

/**
 * An individual search result that matches the search query with search result
 * highlighted within. Selecting will take the user to relevant page/setting
 *
 */

const emit = defineEmits(['remove']);

const props = defineProps({
    classItem: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: 'name',
            }
        }
    },
});

/**
 * To remove from the selected list
 */
function onRemoveSelected () {
    emit('remove', props.classItem);
}


</script>

<template>
    <div class="selected-class">
        <span>{{ classItem.name }}</span>
        <button class="remove-button icon-close" :aria-label="`Remove ${classItem.name}`" @click.prevent="onRemoveSelected"></button>
    </div>
</template>

<style lang="scss" scoped>

.selected-class {
    background: var(--body-background);
    color: #000;
    display: flex;
    font-size: 0.8125rem;
    gap: 0.5rem;
    min-height: auto;
    padding: 5px 10px;
    text-align: left;
    transition: all 0.3s;
}

.remove-button {
    background: var(--body-background);
    border-radius: 1rem;
    height: 1rem;
    min-height: 0;
    margin-top: 3px;
    padding: 0;
}

</style>
