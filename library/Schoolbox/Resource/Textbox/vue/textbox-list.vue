<script>

import axios from 'axios';
import Toast from '@schoolbox/toast';

const EVENT_ADD_TEXTBOX = 'textbox-list-add';

export default {
    name: 'textbox-list',
    components: {Toast},
    props: {
        /**
         * Endpoint for fetching textbox usages.
         */
        textboxUsageEndpoint: {
            type: String,
            required: true,
        },

        /**
         * Endpoint for creating textboxes.
         */
        createTextboxEndpoint: {
            type: String,
            required: true,
        },

        /**
         * Endpoint for posting textbox changes.
         */
        postChangesEndpoint: {
            type: String,
            required: true,
        },

        /**
         * Which folder's textboxes are being shown in this component?
         */
        folderId: {
            type: Number,
            required: true,
        },

        /**
         * Where will the user be redirected after the list is saved?
         */
        returnUrl: {
            type: String,
            required: true,
        }
    },
    data() {
        return {
            textboxes: [],
            loading: true,
            window: window,
            showSkeletonLoader: true,
            validator: null,
            saveInProgress: false,
        }
    },
    mounted() {
        axios
            .get(this.textboxUsageEndpoint)
            .then(response => this.textboxes = response.data)
            .finally(() => this.showSkeletonLoader = false);

        // Add event so that a new textbox may be added from outside of this
        // component
        this.window.document.addEventListener(EVENT_ADD_TEXTBOX, this.addTextbox);
    },
    destroyed() {
        // Remove the add textbox event
        this.window.document.removeEventListener(EVENT_ADD_TEXTBOX, this.addTextbox)
    },
    updated() {
        this.setupValidation();
        this.setupEditors();
        this.setupCollapsers();
    },
    methods: {
        /**
         * Adds a textbox to the list.
         */
        addTextbox() {
            this.textboxes.push({
                textbox: {
                    id: null,
                    heading: '',
                    body: '',
                },
                folders: [],
                dashboards: [],
            });
        },

        /**
         * Deletes a textbox from the list of textboxes.
         *
         * Currently this only hides the textbox from view, it does not remove
         * it because `$index` might be reused by another textbox.
         */
        deleteTextbox(index) {
            this.$set(this.textboxes[index], 'deleted', true);
        },

        /**
         * Does this textbox usage record contain a usage for this component's
         * folder?
         *
         * Show it as "this folder" in the list of pages instead.
         *
         * @param {Object} usage
         * @returns {Boolean}
         */
        inUseOnThisFolder(usage) {
            return usage.folders.some((folder) => folder.id === this.folderId);
        },

        /**
         * The folders other than the this component which this textbox is
         * being used on.
         *
         * @param {Object} usage
         * @returns {Array}
         */
        otherFolders(usage) {
            return usage.folders.filter((folder) => folder.id !== this.folderId);
        },

        /**
         * Post changes to the list of textboxes.
         */
        postChanges() {
            let requests = [];

            // DOM updates: disable button, add toast
            this.saveInProgress = true;
            this.$refs.toast.activate('Saving textboxes...');

            // Create a new set of textbox data to post
            this.syncEditorChangesToModel();
            this.textboxes.forEach(usage => {
                let textbox = usage.textbox,
                    deleted = usage.deleted ?? false;
                if (deleted) {
                    // Check if this textbox has a delete link.
                    // If it doesn't it's new, so just don't create it.
                    let deleteEndpoint = textbox?._links?.delete;
                    if (deleteEndpoint) {
                        // add to the list of promises
                        requests.push(axios.delete(deleteEndpoint));
                    }
                } else {
                    // Check if this textbox has an update link.
                    // If it doesn't it's new, so create it: otherwise update.
                    let updateEndpoint = textbox?._links?.update;
                    if (updateEndpoint) {
                        requests.push(axios.put(updateEndpoint, textbox));
                    } else {
                        requests.push(axios.post(this.createTextboxEndpoint, textbox));
                    }
                }
            });

            Promise.all(requests)
                .then(() => {
                    this.$refs.toast.timeout('Saved', true);
                    this.window.location.href = this.returnUrl;
                })
                .catch(() => {
                    this.$refs.toast.timeout('Could not save all textboxes', false);
                    this.saveInProgress = false
                });
        },

        /**
         * Set up the validator, destroying and recreating it if it's already
         * been set up.
         */
        setupValidation() {
            let $form = jQuery(this.$refs.form);

            if (this.validator) {
                this.validator.destroy();
            }

            // Apply the validator to the form
            this.validator = $form.alaressValidate({
                submitHandler: (form, event) => {
                    event.preventDefault();
                    this.postChanges();
                }
            });

            // Apply the validation rule to each input
            $form.find('input[type=text]').each(function(index, element) {
                $(element).rules('add', {
                    requiredNotBlank: true
                });
            });
        },

        /**
         * Set up editor instances on textareas within the form which don't
         * already have them.
         */
        setupEditors() {
            let $form = jQuery(this.$refs.form);
            $form.find('textarea').each(function () {
                if (!(this.id in window.CKEDITOR.instances)) {
                    jQuery.fn.alaressEditor({
                        targetElements: $(this)
                    });
                }
            });
        },

        /**
         * Set up collapsers for each textbox.
         */
        setupCollapsers() {
            // Apply the collapser
            this.$nextTick(() => {
                let $collapsers = jQuery(this.$el).find('[data-collapser^="collapser-textbox"]');
                $collapsers.destroyCollapsable();
                $collapsers.collapsable();
            })
        },

        /**
         * Sync changes made to a CKEditor instance to the data model.
         *
         * Required because changes made to the v-model linked textarea the
         * CKEditor is created from aren't propagated to the model.
         */
        syncEditorChangesToModel() {
            this.textboxes.forEach((usage, index) => {
                let editor = this.window.CKEDITOR.instances['textbox-' + String(index) + '-body'];
                this.textboxes[index].textbox.body = editor.getData();
            });
        }
    },
    computed: {
        /**
         * All textboxes which are not marked as deleted.
         *
         * @returns {*[]}
         */
        undeletedTextboxes() {
            return this.textboxes.filter(v => v?.deleted !== true)
        }
    },
}

</script>
<template>
    <form method="post" ref="form" :action="postChangesEndpoint">
        <section>
            <ul class="information-list" v-if="showSkeletonLoader">
                <li class="row" v-for="n in 3" :key="n">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </li>
            </ul>
            <template v-else>
                <ul class="information-list unsortable expandable-card">
                    <template v-for="(usage, index) in textboxes">
                        <li v-if="usage.deleted !== true">
                            <div class="actions-small-1">
                                <div :class="{'expander': true, 'icon-up-arrow': Boolean(usage.textbox.id)}"
                                     :data-collapser="'collapser-textbox-' + String(index)"></div>
                                <div class="list-item">
                                    <div class="small-12 column">
                                        <label>
                                            <span class="hide-for-medium-up">Textbox</span>
                                            <input :name="'textbox[' + String(index) + '][heading]'" type="text" v-model="usage.textbox.heading">
                                        </label>

                                        <p class="meta" v-if="usage.folders.length > 0">
                                            Pages:
                                            <template v-if="inUseOnThisFolder(usage)">
                                                this page<template v-if="otherFolders(usage).length > 0">, </template>
                                            </template>
                                            <template v-for="(folder, index) in otherFolders(usage)">
                                                <a :href="folder._links.view">{{ folder.name }}</a><template v-if="(index + 1) < otherFolders(usage).length">, </template>
                                            </template>
                                        </p>

                                        <p class="meta" v-if="usage.dashboards.length > 0">
                                            Dashboards: {{ usage.dashboards.map((v) => v.name).join(', ') }}
                                        </p>
                                    </div>
                                </div>
                                <nav>
                                    <a class="icon-delete" href="#" @click.prevent="deleteTextbox(index)" title="Delete"></a>
                                </nav>
                            </div>
                            <div :class="{'actions-small-1': true, 'hide': Boolean(usage.textbox.id)}"
                                 :data-collapse-state="usage.textbox.id ? 'collapsed' : 'expanded'"
                                 :data-collapsable="'collapser-textbox-' + String(index)">
                            <textarea :name="'textbox[' + String(index) + '][body]'"
                                      :id="'textbox-' + String(index) + '-body'"
                                      v-model="usage.textbox.body">
                            </textarea>
                            </div>
                        </li>
                    </template>
                </ul>

                <div class="empty-state" v-if="undeletedTextboxes.length === 0">
                    <i class="icon-textbox"></i>
                    <p>No textboxes currently exist on this page.</p>
                </div>
            </template>
        </section>
        <input type="submit" ref="saveButton" value="Save all" :disabled="saveInProgress" />
        <toast ref="toast"></toast>
    </form>
</template>
