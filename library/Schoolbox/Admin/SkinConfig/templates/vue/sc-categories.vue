<script setup lang="ts">
/**
 * Child component for rendering visible Skin Config categories.
 */

import type { SkinConfigData } from "../js/types/skinConfigTypes";

const props = defineProps<{
    configs: SkinConfigData;
}>();

const emit = defineEmits<{
    "preview-refresh": [];
}>();

const configs = window.Vue.ref(props.configs);

window.Vue.watch(
    () => props.configs,
    (value) => {
        configs.value = value;
    },
);

const activeConfigs = window.Vue.computed(() =>
    configs.value.toggle.enabled ? configs.value.sonar : configs.value.existing,
);

// Temporarily hide roundness and drop shadow until their controls are implemented.
const visibleConfigs = window.Vue.computed(() =>
    Object.fromEntries(
        Object.entries(activeConfigs.value).filter(
            ([categoryKey]) => categoryKey !== "Roundness" && categoryKey !== "Drop Shadow",
        ),
    ),
);

/**
 * Notify the parent that the preview should be refreshed.
 */
function onPreviewRefresh(): void {
    emit("preview-refresh");
}
</script>

<template>
    <template v-for="(subcategories, categoryKey) in visibleConfigs" :key="categoryKey">
        <div class="row">
            <div class="list-item">
                <div class="small-12 island">
                    <h2 class="subheader">{{ categoryKey }}</h2>
                </div>
            </div>
        </div>
        <section>
            <fieldset
                v-for="(subcategory, index) in subcategories"
                :key="subcategory.name || `default-${index}`"
                class="content"
                :data-test="`skin-config-subcategory-${subcategory.name || `default-${index}`}`"
            >
                <div v-if="subcategory.name" class="small-12 columns">
                    <h3>{{ subcategory.name }}</h3>
                </div>
                <div
                    v-for="option in subcategory.options"
                    :key="option.id"
                    :class="[
                        option.type === 'textarea'
                            ? 'small-12 columns'
                            : 'small-12 medium-6 large-4 l-mar-m--bottom columns',
                    ]"
                >
                    <label :id="option.name" :for="`${option.name}-field`" class="inline-block">
                        <span>{{ option.title || option.name }}</span>
                        <span
                            v-if="option.description"
                            data-tooltip
                            class="has-tip tip-top hint"
                            data-allow-html="true"
                            :title="option.description"
                            >?</span
                        >
                    </label>
                    <div v-if="option.type === 'colour'">
                        <input
                            :id="`${option.name}-field`"
                            v-model="option.value"
                            type="text"
                            :name="option.name"
                            data-colour-picker
                            :disabled="!option.editable"
                            @input="onPreviewRefresh"
                            @change="onPreviewRefresh"
                        />
                    </div>
                    <div v-else-if="option.type === 'textarea'">
                        <textarea
                            :id="`${option.name}-field`"
                            v-model="option.value"
                            :name="option.name"
                            rows="8"
                            :disabled="!option.editable"
                            @input="onPreviewRefresh"
                            @change="onPreviewRefresh"
                        />
                    </div>
                </div>
                <template v-if="configs.toggle.enabled">
                    <div
                        v-if="subcategory.name === 'General' || subcategory.name === 'Component'"
                        class="small-12 columns"
                    >
                        <h4>Preview</h4>
                    </div>
                    <template v-if="subcategory.name === 'Component'">
                        <div class="small-12 columns">
                            <div class="component-titlebar">
                                <div class="actions-small-3">
                                    <div class="list-item">
                                        <h2 title="Class News">
                                            <span>Class News</span>
                                        </h2>
                                    </div>
                                    <nav>
                                        <a href="#" class="icon-settings settings edit" title="Settings"></a>
                                        <a href="#" class="icon-up-arrow collapse" title="Expand/collapse content"></a>
                                        <a href="#" class="icon-close" title="Close"></a>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="subcategory.name === 'General'">
                        <div class="small-12 medium-4 large-3 columns">
                            <label class="required" for="title">Title</label>
                            <input id="title" type="text" name="title" value="School Musical" maxlength="255" />
                        </div>
                        <div class="small-12 medium-4 large-3 columns">
                            <div class="checklist l-flex-col l-gap-s">
                                <label class="title">Filter</label>
                                <ul class="option-list l-flex-row l-gap-s">
                                    <li data-v-40e5173a="">
                                        <input id="waiting-list" type="checkbox" checked />
                                        <label for="waiting-list">Waiting List</label>
                                    </li>
                                    <li data-v-40e5173a="">
                                        <input id="admin-access" type="checkbox" />
                                        <label for="admin-access">Group Admin</label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="small-12 medium-4 large-3 columns">
                            <label for="useDualMarkTypes" class="inline-block">Use Dual Mark Types?</label>
                            <div class="switch">
                                <input id="useDualMarkTypes" name="useDualMarkTypes" type="checkbox" />
                                <label for="useDualMarkTypes" data-test="assessment-form-checkbox-dualMarkTypes">
                                    <span class="icon-approve"></span>
                                    <span class="icon-cancel"></span>
                                </label>
                            </div>
                        </div>
                        <div class="small-12 medium-4 large-3 columns">
                            <sbx-pop-out content-id="pop-out-1" button-text="Create Activity"></sbx-pop-out>
                            <ul id="pop-out-1" class="f-dropdown" data-dropdown-content>
                                <li>
                                    <a class="icon-project" href="#">Project</a>
                                </li>
                                <li>
                                    <a class="icon-due-work" href="#">Due Work</a>
                                </li>
                                <li>
                                    <a class="icon-task" href="#">Task</a>
                                </li>
                                <li>
                                    <a class="icon-quiz" href="#">Quiz</a>
                                </li>
                                <li>
                                    <a class="icon-lti" href="#">3rd Party Assessment</a>
                                </li>
                                <li>
                                    <a class="icon-lesson-plan" href="#">Lesson Plan</a>
                                </li>
                                <li>
                                    <a class="icon-dialog-agent" href="#">Discussion</a>
                                </li>
                            </ul>
                        </div>
                        <div class="small-12 columns">
                            <ul class="flex-list buttons">
                                <li>
                                    <a href="#"><sbx-icon name="previous"></sbx-icon>Cancel</a>
                                </li>
                                <li><a href="#" class="submit">Confirm</a></li>
                            </ul>
                        </div>
                        <div class="small-12 medium-4 columns">
                            <label>Filter(s) selected:</label>
                            <div id="applied_filters">
                                <div data-applied-filter-tab="22" class="applied-filter-tab">
                                    <span class="label filter">
                                        <span data-filter-name="">Category</span>
                                        <a href="#" class="close" title="Remove filter">×</a>
                                    </span>
                                    <ul data-selected-list="" class="selected-list">
                                        <li class="selected-item">Learning</li>
                                        <li class="selected-item">Personal Development</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="small-12 medium-4 columns">
                            <label for="news-topics-selectized">Topics</label>
                            <div class="selectize-control multi plugin-remove_button">
                                <div class="selectize-input items not-full has-options has-items">
                                    <div class="item" data-value="1">
                                        General Notices<a class="remove" title="Remove">×</a>
                                    </div>
                                    <div class="item" data-value="3">
                                        Community<a class="remove" title="Remove">×</a>
                                    </div>
                                    <input type="text" autofill="no" tabindex="" style="width: 4px" />
                                </div>
                            </div>
                        </div>
                        <div class="small-12 medium-4 columns">
                            <label>Attachments</label>
                            <div id="fileImage-zone" class="attachzone s64">
                                <label></label>
                                <ul data-dz-previews="" class="az-list"></ul>
                                <div id="FileImageZone" class="dzone icon-attach">
                                    <span>Click or drag here to attach files</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </template>
            </fieldset>
        </section>
    </template>
</template>

<style scoped></style>
