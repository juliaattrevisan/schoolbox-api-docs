<script>

import axios from 'axios'
import Toast from "@schoolbox/toast";

export default {
    components: {
        Toast,
    },
    data() {
        return {
            gradeGroups:    [],
            saveInProgress: false,
        }
    },
    created() {
        this.gradeGroups = this.config.gradeGroups;
    },
    computed: {
        config: () => window.adminGradesConfig,
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
            // Post the grades

            // Try to summon a toast as well
            this.saveInProgress = true;
            this.$refs.toast.activate('Saving...');
            axios
                .post(this.config.postUrl, this.gradeGroups)
                .then(response => {
                    this.gradeGroups = response.data
                    this.$refs.toast.timeout('Saved', true, 2000);
                })
                .catch(() => this.$refs.toast.timeout('Save failed', false, 5000))
                .finally(() => this.saveInProgress = false)
        }
    }
}
</script>

<template>
    <form method="POST" :action="config.postUrl">
        <section v-for="(group, groupIndex) in gradeGroups">
            <div class="content">
                <label :for="'groupName-' + String(groupIndex)">
                    {{ config.terms.grade.properSingular }} Group
                </label>
                <div class="actions-small-1">
                    <div class="list-item">
                        <input type="text"
                               required
                               :disabled="saveInProgress"
                               :id="'groupName-' + String(groupIndex)"
                               v-model="group.name"
                               :placeholder="config.terms.grade.properSingular + ' Group'"
                        />
                    </div>
                    <nav>
                        <a v-if="group.id === null"
                           href="#"
                           title="Remove Grouping"
                           :class="{'icon-delete': true, disabled: saveInProgress}"
                           @click.prevent="removeGroup(groupIndex)"></a>
                    </nav>
                </div>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Lower Bound</th>
                    <th>Normalised {{ config.terms.grade.properSingular }}</th>
                    <th>Caption</th>
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
                               :disabled="saveInProgress"
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
                               :disabled="saveInProgress"
                               v-model="level.rawGrade"
                               placeholder="Normalised Grade"
                        />
                    </td>
                    <td>
                        <div class="actions-small-1">
                            <div class="list-item">
                                <input type="text"
                                       required
                                       :disabled="saveInProgress"
                                       v-model="level.caption"
                                />
                            </div>
                            <nav>
                                <a v-if="level.id === null"
                                   title="Remove Level"
                                   href="#"
                                   :class="{'icon-delete': true, disabled: saveInProgress}"
                                   @click.prevent="removeLevel(groupIndex, levelIndex)"></a>
                            </nav>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <ul class="flex-list buttons">
                            <li>
                                <a href="#"
                                   :class="{button: true, disabled: saveInProgress}"
                                   @click.prevent="addLevel(groupIndex)">Add Level</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <ul class="flex-list buttons">
            <li>
                <a href="#"
                   :class="{button: true, disabled: saveInProgress}"
                   @click.prevent="addGroup()">Add Grouping</a>
            </li>
            <li>
                <input type="submit"
                       :disabled="saveInProgress"
                       @click.prevent="persist()"
                       value="Save changes" />
            </li>
        </ul>

        <toast ref="toast"></toast>
    </form>
</template>
