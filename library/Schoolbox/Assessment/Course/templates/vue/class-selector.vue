<script setup>

/**
 * Provide multiple ways to select classes for faster class importing
 */

import { ref, computed, onMounted  } from 'vue'

const classList = ref(null); //to hold the class list
const isProcessing = ref(false); //to hold the class list

const props = defineProps({
    endpoint: {
        type: String,
        required: true,
    }
});

onMounted(() => {
    getClasses();
});

/**
 * Get all the classes from the enpoint
 */
async function getClasses () {
    try {
        isProcessing.value = true;
        const response = await fetch(props.endpoint, {
            method: 'GET',
        });
        const responseData = await response.json();
        classList.value = responseData;
        // this.logData = responseData;
    } catch (error) {
        console.warn(error);
        isProcessing.value = false;
    } finally {
        // this.hasUpdated = true;
        // this.$emit('updated'); 
        isProcessing.value = false;
        // this.updateFields = Object.assign({}, this.updateFieldsOriginal); //reset to original values
    }
}

</script>

<template>
   <div>
       <input id="import_folders" type="hidden" name="import_folders" required />
   </div>
</template>

<style lang="scss" scoped>

</style>