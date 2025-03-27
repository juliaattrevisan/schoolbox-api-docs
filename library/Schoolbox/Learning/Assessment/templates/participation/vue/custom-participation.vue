<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import type { Participant } from "../js/types/customParticipationTypes";

interface Props {
    allParticipants: Participant[];
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="participant in props.allParticipants" :key="participant.id">
                    <td>{{ participant.user.fullName }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped></style>
