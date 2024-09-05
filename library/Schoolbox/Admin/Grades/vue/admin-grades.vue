<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";
import Toast from "@schoolboxVue/components/toast";

export default {
    components: {
        RevealModal,
        Toast,
    },
    data() {
        return {
            currentGroupIndex: null,
            currentLevelIndex: null,
            gradeGroups: [],
            saveInProgress: false,
            validator: null,
        };
    },
    created() {
        this.gradeGroups = this.config.gradeGroups;
    },
    mounted() {
        this.applyValidator();
    },
    computed: {
        config: () => window.adminGradesConfig,
    },
    methods: {
        /**
         * Add a new group to the list of grade groups.
         */
        addGroup() {
            this.gradeGroups.push({
                id: null,
                name: "",
                levels: [],
            });
            this.applyValidator();
        },

        /**
         * Remove a given grade group from the list of grade groups.
         *
         * @param {Number} groupIndex The index of the group to remove
         */
        async deleteGradeGroup(groupIndex) {
            this.$refs.toast.activate("Deleting Grade Group...");

            let currentGradeGroup = this.gradeGroups[groupIndex];

            /**
             * not yet persisted, so removing from the UI is enough
             */
            if (currentGradeGroup.id == null) {
                this.$refs.toast.timeout(`Group ${currentGradeGroup.name} successfully removed`, true);

                this.gradeGroups = this.gradeGroups.filter((g, i) => i !== groupIndex);
                this.closeDeleteGradeGroupModal();
                this.applyValidator();

                return;
            }

            /**
             * this grade group has been persisted, so we need to remove it and any associated grade levels
             */
            const adjustedGradeGroups = this.gradeGroups.filter((v) => v.id !== currentGradeGroup.id);

            try {
                await fetch(currentGradeGroup._links.delete, {
                    method: "DELETE",
                });
                this.gradeGroups = [];
                this.$refs.toast.timeout(`Group ${currentGradeGroup.name} successfully removed`, true);
                this.$nextTick(() => {
                    this.gradeGroups = adjustedGradeGroups;
                });
            } catch (error) {
                this.$refs.toast.timeout("Failed to remove Grade Group", false);
            } finally {
                this.closeDeleteGradeGroupModal();
            }

            this.applyValidator();
        },

        /**
         * Opens a modal window for deleting this grade group
         *
         * @param {Number} groupIndex
         */
        openDeleteGradeGroupModal(groupIndex) {
            this.currentGroupIndex = groupIndex;
            this.$refs.deleteGradeGroupModal.open();
        },

        /**
         * Closes a modal window for deleting this grade group.
         */
        closeDeleteGradeGroupModal() {
            this.$refs.deleteGradeGroupModal.close();
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
                caption: "",
            });
            this.applyValidator();
        },

        /**
         * Remove a given level from a given grade group.
         *
         * @param {Number} groupIndex The index of the group to remove from
         * @param {Number} levelIndex The index of the level to remove
         */
        async deleteGradeLevel(groupIndex, levelIndex) {
            this.$refs.toast.activate("Deleting Grade...");

            let currentGradeLevel = this.gradeGroups[groupIndex].levels[levelIndex];

            /**
             * not yet persisted, so removing from the UI is enough
             */
            if (currentGradeLevel.id == null) {
                this.$refs.toast.timeout(`Group ${currentGradeLevel.caption} successfully removed`, true);

                this.gradeGroups[groupIndex].levels = this.gradeGroups[groupIndex].levels.filter(
                    (g, i) => i !== levelIndex,
                );
                this.closeDeleteGradeGroupModal();
                this.applyValidator();

                return;
            }

            /**
             * this grade level has been persisted, so we need to delete it
             */
            const adjustedGradeLevels = this.gradeGroups[groupIndex].levels.filter(
                (v) => v.id !== currentGradeLevel.id,
            );

            try {
                await fetch(currentGradeLevel._links.delete, {
                    method: "DELETE",
                });
                this.gradeGroups[groupIndex].levels = [];
                this.$refs.toast.timeout(`Group ${currentGradeLevel.caption} successfully removed`, true);
                this.$nextTick(() => {
                    this.gradeGroups[groupIndex].levels = adjustedGradeLevels;
                });
            } catch (error) {
                this.$refs.toast.timeout("Failed to remove grade level", false);
            }

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
            this.validator = $(this.$refs.form).alaressValidate({
                submitHandler(form, event) {
                    // Don't do anything in the submit handler:
                    // allow Vue to handle saving.
                    event.preventDefault();
                },
            });
        },

        /**
         * Persist the grades in the form, only if their values are valid.
         */
        async persist() {
            if (!this.validator.form()) {
                return;
            }

            this.saveInProgress = true;
            this.$refs.toast.activate("Saving...");
            try {
                const response = await fetch(this.config.postUrl, {
                    method: "POST",
                    body: JSON.stringify(this.gradeGroups),
                    headers: {
                        "Content-Type": "application/json",
                    },
                });
                this.gradeGroups = await response.json();
                this.$refs.toast.timeout("Saved", true, 2000);
            } catch (error) {
                this.$refs.toast.timeout("Save failed", false, 5000);
            } finally {
                this.saveInProgress = false;
            }
        },
    },
};
</script>

<template>
    <form method="POST" :action="config.postUrl" ref="form">
        <section v-for="(group, groupIndex) in gradeGroups" :key="groupIndex">
            <div class="content">
                <label :for="'groupName-' + String(groupIndex)"> {{ config.terms.grade.properSingular }} Group </label>
                <div class="actions-small-1">
                    <div class="list-item">
                        <input
                            :name="'group.' + String(groupIndex) + '.name'"
                            type="text"
                            maxlength="100"
                            required
                            :disabled="saveInProgress"
                            :id="'groupName-' + String(groupIndex)"
                            v-model="group.name"
                            :placeholder="config.terms.grade.properSingular + ' Group'"
                        />
                    </div>
                    <nav>
                        <a
                            href="#"
                            title="Remove Grouping"
                            :class="{ 'icon-delete': true, disabled: saveInProgress }"
                            @click.prevent="openDeleteGradeGroupModal(groupIndex)"
                        ></a>
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
                    <tr v-for="(level, levelIndex) in group.levels" :key="levelIndex">
                        <td>
                            <input
                                :name="'group.' + String(groupIndex) + '.level.' + String(levelIndex) + '.lowerBound'"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                :disabled="saveInProgress"
                                v-model="level.lowerBound"
                                placeholder="Lower Bound"
                            />
                        </td>
                        <td>
                            <input
                                :name="'group.' + String(groupIndex) + '.level.' + String(levelIndex) + '.rawGrade'"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                :disabled="saveInProgress"
                                v-model="level.rawGrade"
                                placeholder="Normalised Grade"
                            />
                        </td>
                        <td>
                            <div class="actions-small-1">
                                <div class="list-item">
                                    <input
                                        :name="
                                            'group.' + String(groupIndex) + '.level.' + String(levelIndex) + '.caption'
                                        "
                                        type="text"
                                        maxlength="64"
                                        required
                                        :disabled="saveInProgress"
                                        v-model="level.caption"
                                    />
                                </div>
                                <nav>
                                    <a
                                        title="Remove Level"
                                        href="#"
                                        :class="{ 'icon-delete': true, disabled: saveInProgress }"
                                        @click.prevent="deleteGradeLevel(groupIndex, levelIndex)"
                                    ></a>
                                </nav>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <ul class="flex-list buttons">
                                <li>
                                    <a
                                        href="#"
                                        :class="{ button: true, disabled: saveInProgress }"
                                        @click.prevent="addLevel(groupIndex)"
                                        >Add Level</a
                                    >
                                </li>
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <ul class="flex-list buttons">
            <li>
                <a href="#" :class="{ button: true, disabled: saveInProgress }" @click.prevent="addGroup()"
                    >Add Grouping</a
                >
            </li>
            <li>
                <input type="submit" :disabled="saveInProgress" @click.prevent="persist()" value="Save changes" />
            </li>
        </ul>

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteGradeGroupModal">
            <h2>Please confirm you wish to delete this grade group.</h2>
            <div data-alert class="alert-box alert">
                Deleting these grades will not impact existing grades, but you will no longer be able to use these
                grades on new assessments.
            </div>
            <ul class="flex-list buttons">
                <li>
                    <a @click.prevent="deleteGradeGroup(currentGroupIndex)" class="button submit">Confirm Deletion</a>
                </li>
                <li><a @click.prevent="closeDeleteGradeGroupModal()" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
    </form>
</template>
