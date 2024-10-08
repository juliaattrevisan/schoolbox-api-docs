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
                id="courseInherit"
                v-model="store.courseInherit"
                type="checkbox"
                name="courseInherit"
                value="1"
                data-checkbox-course-inherit
            />
            <label for="courseInherit" class="checkbox-label"
                >Inherit permissions from the course's parent folder</label
            >
        </div>
    </fieldset>
    <table v-if="!store.courseInherit">
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
                        :id="`courseGroupNone[${role.id}]`"
                        type="radio"
                        value="0"
                        :name="`course_group[${role.id}]`"
                        :checked="store.coursePermissions[role.id] == 0"
                        @change="store.coursePermissions[role.id] = 0"
                    />
                    <label class="radio-label" :for="`courseGroupNone[${role.id}]`"></label>
                </td>
                <td>
                    <input
                        :id="`courseGroupRead[${role.id}]`"
                        type="radio"
                        value="1"
                        :name="`course_group[${role.id}]`"
                        :checked="store.coursePermissions[role.id] == 1"
                        @change="store.coursePermissions[role.id] = 1"
                    />
                    <label class="radio-label" :for="`courseGroupRead[${role.id}]`"></label>
                </td>
                <td>
                    <input
                        :id="`courseGroupWrite[${role.id}]`"
                        type="radio"
                        value="2"
                        :name="`course_group[${role.id}]`"
                        :checked="store.coursePermissions[role.id] == 2"
                        @change="store.coursePermissions[role.id] = 2"
                    />
                    <label class="radio-label" :for="`courseGroupWrite[${role.id}]`"></label>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped></style>
