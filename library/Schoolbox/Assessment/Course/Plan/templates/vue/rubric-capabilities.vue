<script setup lang="ts">
import { RubricCapability } from "./unitPlannerTypes";

interface Props {
    rubricCapabilities: RubricCapability[];
}

const props = defineProps<Props>();
</script>

<template>
    <table ref="rubricCapabilitiesEl" class="rb rb-capabilities-indicators">
        <tbody>
            <template v-for="capability in props.rubricCapabilities" :key="capability.id">
                <tr>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <th class="markdown border-none" v-html="capability.name"></th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <td class="markdown border-none" v-html="indicator.name"></td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

<style scoped></style>
