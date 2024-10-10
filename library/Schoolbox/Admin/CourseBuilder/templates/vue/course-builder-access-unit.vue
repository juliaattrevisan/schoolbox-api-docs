<script setup lang="ts">
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import type { Role } from "./courseBuilderTypes";

interface Props {
    roles: Role[];
}

const props = defineProps<Props>();

const store = useCourseBuilderStore();
</script>

<template>
    <fieldset class="content">
        <div class="columns">
            <input
                id="unitInherit"
                v-model="store.unitInherit"
                type="checkbox"
                name="unitInherit"
                value="1"
                data-checkbox-unit-inherit
            />
            <label for="unitInherit" class="checkbox-label">Inherit permissions from the unit's parent folder</label>
        </div>
    </fieldset>
    <table v-if="!store.unitInherit">
        <thead>
            <tr>
                <th>Role</th>
                <th>None</th>
                <th>Read</th>
                <th>Write</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="role in props.roles" :key="role.id">
                <td>{{ role.name }}</td>
                <td>
                    <input
                        :id="`unitGroupNone[${role.id}]`"
                        type="radio"
                        value="0"
                        :name="`unit_group[${role.id}]`"
                        :checked="store.unitPermissions[role.id] == 0"
                        @change="store.unitPermissions[role.id] = 0"
                    />
                    <label class="radio-label" :for="`unitGroupNone[${role.id}]`"></label>
                </td>
                <td>
                    <input
                        :id="`unitGroupRead[${role.id}]`"
                        type="radio"
                        value="1"
                        :name="`unit_group[${role.id}]`"
                        :checked="store.unitPermissions[role.id] == 1"
                        @change="store.unitPermissions[role.id] = 1"
                    />
                    <label class="radio-label" :for="`unitGroupRead[${role.id}]`"></label>
                </td>
                <td>
                    <input
                        :id="`unitGroupWrite[${role.id}]`"
                        type="radio"
                        value="2"
                        :name="`unit_group[${role.id}]`"
                        :checked="store.unitPermissions[role.id] == 2"
                        @change="store.unitPermissions[role.id] = 2"
                    />
                    <label class="radio-label" :for="`unitGroupWrite[${role.id}]`"></label>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped></style>
