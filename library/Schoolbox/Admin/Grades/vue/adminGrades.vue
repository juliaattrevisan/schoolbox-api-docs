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
            validator:      null,
        }
    },
    created() {
        this.gradeGroups = this.config.gradeGroups;
    },
    mounted() {
        this.applyValidator();
    },
    computed: {
        jQuery: () => window.jQuery,
        config: () => window.adminGradesConfig,
    },
    methods: {
        /**
         * Add a new group to the list of grade groups.
         */
        addGroup() {
            this.gradeGroups.push({
                id: null,
                name: '',
                levels: [],
            });
            this.applyValidator();
        },

        /**
         * Remove a given grade group from the list of grade groups.
         *
         * @param {Number} groupIndex The index of the group to remove
         */
        removeGroup(groupIndex) {
            this.gradeGroups = this.gradeGroups.filter((g, i) => i !== groupIndex);
            this.applyValidator();
        },

        /**
         * Add a new level to a given grade group.
         *
         * @param {Number} groupIndex The index of the group to add to
         */
        addLevel(groupIndex) {
            this.gradeGroups[groupIndex].levels.push({
                id: null,
                lowerBound: null,
                rawGrade: null,
                caption: '',
            });
            this.applyValidator();
        },

        /**
         * Remove a given level from a given grade group.
         *
         * @param {Number} groupIndex The index of the group to remove from
         * @param {Number} levelIndex The index of the level to remove
         */
        removeLevel(groupIndex, levelIndex) {
            this.gradeGroups[groupIndex].levels = this.gradeGroups[groupIndex].levels.filter((g, i) => i !== levelIndex);
            this.applyValidator();
        },

        /**
         * Apply the jQuery validator to the form inputs.
         *
         * This should be called every time the list of grade groups or levels
         * changes, as jQuery validator is too old to know what's going on
         * if it isn't told..
         */
        applyValidator() {
            // Is there one already? Destroy if so
            if (this?.validator?.destroy) {
                this.validator.destroy();
            }
            this.validator = this.jQuery(this.$refs.form).alaressValidate({
                submitHandler(form, event) {
                    // Don't do anything in the submit handler:
                    // allow Vue to handle saving.
                    event.preventDefault();
                }
            });
        },

        /**
         * Persist the grades in the form, only if their values are valid.
         */
        persist() {
            if (!this.validator.form()) {
                return;
            }

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
    <form method="POST" :action="config.postUrl" ref="form">
        <section v-for="(group, groupIndex) in gradeGroups">
            <div class="content">
                <label :for="'groupName-' + String(groupIndex)">
                    {{ config.terms.grade.properSingular }} Group
                </label>
                <div class="actions-small-1">
                    <div class="list-item">
                        <input :name="'group.' + String(groupIndex) + '.name'"
                               type="text"
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
                        <input :name="'group.' + String(groupIndex) + '.level.' + String(levelIndex) + '.lowerBound'"
                               type="number"
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
                        <input :name="'group.' + String(groupIndex) + '.level.' + String(levelIndex) + '.rawGrade'"
                               type="number"
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
                                <input :name="'group.' + String(groupIndex) + '.level.' + String(levelIndex) + '.caption'"
                                       type="text"
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
