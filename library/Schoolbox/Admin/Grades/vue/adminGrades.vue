<script>

export default {
    data() {
        return {
            gradeGroups: []
        }
    },
    created() {
        this.gradeGroups = this.config.gradeGroups;
    },
    computed: {
        config: () => window.adminGradesConfig,
        // urls() {
        //     return {
        //         createForm: URL_EVENT_CREATE_FORM,
        //         modifyForm: URL_EVENT_MODIFY_FORM,
        //         modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
        //         delete: URL_EVENT_DELETE,
        //         update: URL_EVENT_UPDATE,
        //         attendanceList: URL_ATTENDANCE_LIST,
        //         attendanceToggle: URL_ATTENDANCE_TOGGLE,
        //     };
        // },
    },
    methods: {
        addGroup() {
            this.gradeGroups.push({
                id: null,
                name: '',
                levels: [],
            });
        },
        removeGroup(groupIndex) {
            this.gradeGroups = this.gradeGroups.filter((g, i) => i !== groupIndex);
        },
        addLevel(groupIndex) {
            this.gradeGroups[groupIndex].levels.push({
                id: null,
                lowerBound: null,
                rawGrade: null,
                caption: '',
            });
        },
        removeLevel(groupIndex, levelIndex) {
            this.gradeGroups[groupIndex].levels = this.gradeGroups[groupIndex].levels.filter((g, i) => i !== levelIndex);
        },
        persist() {
            // do nothing yet
        }
    }
}
</script>

<template>
    <form method="POST" :action="config.postUrl">
        <section v-for="(group, groupIndex) in gradeGroups">
            <div class="content">
                <label for="groupName">
                    Grade Group
                    <input type="text" required id="groupName" v-model="group.name" placeholder="Grade Group"/>
                </label>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Lower Bound</th>
                    <th>Normalised Grade</th>
                    <th>Caption</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(level, levelIndex) in group.levels">
                    <td>
                        <input type="number"
                               min="0"
                               max="100"
                               step="0.5"
                               required
                               v-model="level.lowerBound"
                               placeholder="Lower Bound"
                        />
                    </td>
                    <td>
                        <input type="number"
                               min="0"
                               max="100"
                               step="0.5"
                               required
                               v-model="level.rawGrade"
                               placeholder="Normalised Grade"
                        />
                    </td>
                    <td>
                        <input type="text"
                               required
                               v-model="level.caption"
                        />
                    </td>
                    <td><a href="#" class="button icon-delete no-text" @click.prevent="removeLevel(groupIndex, levelIndex)"></a></td>
                </tr>
                <tr>
                    <td colspan="4">
                        <ul class="flex-list buttons">
                            <li>
                                <a href="#" class="button" @click.prevent="addLevel(groupIndex)">Add Level</a>
                            </li>
                            <li>
                                <a href="#" class="button" @click.prevent="removeGroup(groupIndex)">Remove Group</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <ul class="flex-list buttons">
            <li>
                <a href="#" class="button" @click.prevent="addGroup()">Add Group</a>
            </li>
            <li>
                <input type="submit" @click.prevent="persist()" value="Save changes"/>
            </li>
        </ul>

    </form>
</template>
