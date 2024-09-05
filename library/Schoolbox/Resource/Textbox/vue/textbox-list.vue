<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import axios from "axios";
import Toast from "@schoolboxVue/components/toast";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import { EditorEvent } from "@js/editor/event/editorEvent";

const EVENT_ADD_TEXTBOX = "textbox-list-add";
const EVENT_BEFORE_UNLOAD = "beforeunload";

export default {
    name: "textbox-list",

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        RevealModal,
        Toast,
    },

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
        },
    },
    data() {
        return {
            textboxes: [],
            loading: true,
            window: window,
            showSkeletonLoader: true,
            validator: null,
            saveInProgress: false,

            /* When you click the Delete link, remember the index of the textbox to delete
             * A singleton modal spawns and uses this as its confirmation button destination
             * Because of the way the reveal-modal works, you don't pass it an arg
             */
            textboxIndexToDelete: undefined,
            isProcessActive: false,
        };
    },
    mounted() {
        axios
            .get(this.textboxUsageEndpoint)
            .then((response) => (this.textboxes = response.data))
            .finally(() => (this.showSkeletonLoader = false));

        // Add event so that a new textbox may be added from outside of this
        // component
        this.window.addEventListener(EVENT_ADD_TEXTBOX, this.addTextbox);
        this.window.addEventListener(EVENT_BEFORE_UNLOAD, this.onBeforeUnload);
    },
    destroyed() {
        // Remove the add textbox event
        this.window.removeEventListener(EVENT_ADD_TEXTBOX, this.addTextbox);
        this.window.removeEventListener(EVENT_BEFORE_UNLOAD, this.onBeforeUnload);
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
                // The textbox's field values
                textbox: {
                    // It is not yet saved, so it has no ID
                    id: null,
                    heading: "",
                    body: "",
                },

                // Folders and dashboards the textbox is actually in use on:
                // as this is a new textbox, it's not in use yet
                folders: [],
                dashboards: [],

                // This textbox isn't deleted, because it's not yet saved
                deleted: false,

                // This textbox is dirty, because it's not yet saved
                dirty: true,
            });

            // Focus the new textbox's heading input immediately
            this.$nextTick(() => {
                jQuery(this.$el).find('input[name$="[heading]"]').last().focus();
            });
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
            this.$refs.toast.activate("Saving textboxes...");

            // Create a new set of textbox data to post
            this.syncEditorChangesToModel();
            this.textboxes.forEach((usage, index) => {
                let textbox = usage.textbox,
                    deleted = usage.deleted ?? false;
                // If this textbox is marked as deleted, ignore it:
                // * if it existed, it's already been deleted
                // * if it didn't exist, it doesn't need to be deleted again
                if (!deleted) {
                    // Check if this textbox has an update link.
                    // If it doesn't it's new, so create it: otherwise update.
                    let updateEndpoint = textbox?._links?.update;
                    if (updateEndpoint) {
                        requests.push(axios.put(updateEndpoint, textbox).then(() => this.markAsClean(index)));
                    } else {
                        requests.push(
                            axios.post(this.createTextboxEndpoint, textbox).then(() => this.markAsClean(index)),
                        );
                    }
                }
            });

            Promise.all(requests)
                .then(() => {
                    this.$refs.toast.timeout("Saved", true);
                    this.window.location.href = this.returnUrl;
                })
                .catch(() => {
                    this.$refs.toast.timeout("Could not save all textboxes", false);
                    this.saveInProgress = false;
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
                },
            });

            // Apply the validation rule to each input (textbox headings only, don't target ckeditor internals)
            $form.find("input[type=text][data-textbox-heading]").each(function (index, element) {
                $(element).rules("add", {
                    requiredNotBlank: true,
                });
            });
        },

        /**
         * Set up editor instances on textareas within the form which don't
         * already have them.
         */
        setupEditors() {
            let $form = jQuery(this.$refs.form),
                self = this;
            $form.find("textarea").each(function () {
                if (!(this.id in window.sbProps.editors)) {
                    document.getElementById(this.id).addEventListener(EditorEvent.EDITOR__CREATED, () => {
                        window.sbProps.editors[this.id].model.document.on("change:data", () => {
                            // When this editor's content changes, mark it as dirty
                            let index = self.retrieveIndexForEditorId(this.id);
                            if (index !== null) {
                                self.markAsDirty(index);
                            }
                        });
                    });
                    document.dispatchEvent(
                        new CustomEvent(EditorEvent.EDITOR__CREATE, {
                            detail: {
                                maxCharCount: window.sbProps.MAX_BYTES_MYSQL_MEDIUMTEXT,
                                targetElements: [`#${this.id}`],
                                fileBrowserCategoryId: self.folderId,
                            },
                        }),
                    );
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
            });
        },

        /**
         * Sync changes made to a CKEditor instance to the data model.
         *
         * Required because changes made to the v-model linked textarea the
         * CKEditor is created from aren't propagated to the model.
         */
        syncEditorChangesToModel() {
            this.textboxes.forEach((usage, index) => {
                let editor = this.retrieveEditorForIndex(index);
                this.textboxes[index].textbox.body = editor ? editor.getData() : "";
            });
        },

        /**
         * Attempt to delete the textbox at this index from the list.
         *
         * If the textbox exists already, it raises a confirmation modal:
         * otherwise, it just removes it.
         *
         * @param {Number} index The index to remove from `this.textboxes`
         */
        attemptToDeleteTextbox(index) {
            let usage = this.textboxes[index];
            if (usage.textbox.id) {
                // Textbox has been persisted: show the modal
                this.textboxIndexToDelete = index;
                this.$refs.deleteModal.open();
            } else {
                // Textbox has not been persisted: just mark it as deleted
                this.markAsDeleted(index);
            }
        },

        /**
         * Marks a textbox as dirty (either its heading or body has changed).
         *
         * If any textbox is dirty, then a warning will be raised when
         * navigating away from the page.
         *
         * @param {Number} index The index to mark as dirty in `this.textboxes`
         */
        markAsDirty(index) {
            this.textboxes[index]["dirty"] = true;
        },

        /**
         * Marks a textbox as clean (its heading and body have been persisted).
         *
         * @param {Number} index The index to mark as clean in `this.textboxes`
         */
        markAsClean(index) {
            this.textboxes[index]["dirty"] = false;
        },

        /**
         * Marks a textbox as deleted.
         *
         * Currently this only hides the textbox from view, it does not remove
         * it because `$index` might be reused by another textbox.
         *
         * @param {Number} index The index to remove from `this.textboxes`
         */
        markAsDeleted(index) {
            this.textboxes[index]["deleted"] = true;

            // This textbox's state has been persisted, thus it is not dirty
            this.markAsClean(index);

            // Destroy the editor too
            let editor = this.retrieveEditorForIndex(index);
            if (editor) {
                editor.destroy();
            }
        },

        /**
         * Retrieves the CKEditor instance for this index.
         *
         * @param {Number} index The index to find the CKEditor for
         * @returns {CKEDITOR.editor|null} The editor, or null if not found
         */
        retrieveEditorForIndex(index) {
            return this.window.sbProps.editors["textbox-" + String(index) + "-body"] ?? null;
        },

        /**
         * Retrieves the index from this CKEditor instance ID.
         *
         * @param {String} editorId The CKEditor instance's ID
         * @returns {Number|null} The index, or null if not found
         */
        retrieveIndexForEditorId(editorId) {
            let match = editorId.match(/^textbox-(\d+)-body$/);
            if (match) {
                return Number(match[1]);
            }
            return null;
        },

        /**
         * Before-unload function: if any textbox is dirty, show an alert box.
         *
         * @param {Event} event the browser event which triggered this
         * @returns {string}
         */
        onBeforeUnload(event) {
            if (this.textboxes.some((textbox) => textbox.dirty ?? false)) {
                event.preventDefault();
                event.returnValue = true;
                return "Are you sure you wish to leave the page?";
            }
        },

        /**
         * Asynchronously access an endpoint to delete a file from the appropriate place
         */
        async sendDeleteTextboxRequest() {
            /* Set the loading flag to prevent people spamming the button
             */
            this.isProcessActive = true;

            this.$refs.toast.activate("Deleting text box...");

            try {
                /* Asynchronously access the endpoint to delete the thing
                 */
                await axios.delete(this.textboxes[this.textboxIndexToDelete].textbox._links.delete);

                /* Mark this textbox as deleted
                 */
                this.markAsDeleted(this.textboxIndexToDelete);

                /* Shut the modal
                 */
                this.$refs.deleteModal.close();

                /* Toast (though I prefer our twig toast until a better replacement comes along)
                 */
                this.$refs.toast.timeout("Text box deleted", true);
                this.isProcessActive = false;
            } catch (error) {
                /* Deal with error case
                 */
                this.$refs.toast.timeout("Unable to delete text box", false);
                this.isProcessActive = false;
            }
        },
    },
    computed: {
        /**
         * All textboxes which are not marked as deleted.
         *
         * @returns {*[]}
         */
        undeletedTextboxes() {
            return this.textboxes.filter((v) => v?.deleted !== true);
        },
    },
};
</script>
<template>
    <form method="post" ref="form" :action="postChangesEndpoint">
        <template v-if="showSkeletonLoader">
            <section>
                <ul class="information-list">
                    <li class="row" v-for="n in 3" :key="n">
                        <div class="small-12 card skeleton">
                            <div class="img"></div>
                            <h3></h3>
                            <p class="meta"></p>
                        </div>
                    </li>
                </ul>
            </section>
        </template>
        <template v-else>
            <section>
                <ul class="information-list unsortable expandable-card" data-contains-editors>
                    <template v-for="(usage, index) in textboxes">
                        <li v-if="usage.deleted !== true" :id="'textbox-' + usage.textbox.id" :key="index">
                            <div class="actions-small-1">
                                <div
                                    :class="{ expander: true, 'icon-up-arrow': !Boolean(usage.textbox.id) }"
                                    :data-collapser="'collapser-textbox-' + String(index)"
                                ></div>
                                <div class="list-item">
                                    <div class="small-12 column">
                                        <label>
                                            <span class="hide-for-medium-up">Textbox</span>
                                            <input
                                                :name="'textbox[' + String(index) + '][heading]'"
                                                type="text"
                                                maxlength="100"
                                                data-textbox-heading
                                                v-model="usage.textbox.heading"
                                                @change="markAsDirty(index)"
                                            />
                                        </label>

                                        <p class="meta" v-if="usage.folders.length > 0">
                                            Pages:
                                            <template v-if="inUseOnThisFolder(usage)">
                                                this page<template v-if="otherFolders(usage).length > 0">; </template>
                                            </template>
                                            <a
                                                v-for="(folder, index) in otherFolders(usage)"
                                                :href="folder._links.view"
                                                :key="index"
                                                >{{ folder.name
                                                }}<template v-if="index + 1 < otherFolders(usage).length"
                                                    >;
                                                </template></a
                                            >
                                        </p>

                                        <p class="meta" v-if="usage.dashboards.length > 0">
                                            Dashboards: {{ usage.dashboards.map((v) => v.name).join(", ") }}
                                        </p>
                                    </div>
                                </div>
                                <nav>
                                    <a
                                        class="icon-delete"
                                        href="#"
                                        @click.prevent="attemptToDeleteTextbox(index)"
                                        title="Delete"
                                    ></a>
                                </nav>
                            </div>
                            <div
                                :class="{ 'actions-small-1': true, hide: Boolean(usage.textbox.id) }"
                                :data-collapse-state="usage.textbox.id ? 'collapsed' : 'expanded'"
                                :data-collapsable="'collapser-textbox-' + String(index)"
                            >
                                <div class="content">
                                    <textarea
                                        :name="'textbox[' + String(index) + '][body]'"
                                        :id="'textbox-' + String(index) + '-body'"
                                        v-model="usage.textbox.body"
                                    >
                                    </textarea>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>
            <div class="empty-state" v-if="undeletedTextboxes.length === 0">
                <i class="icon-textbox"></i>
                <p>No text boxes currently exist on this page.</p>
            </div>
        </template>

        <template v-if="textboxes.length > 0">
            <input type="submit" ref="saveButton" value="Save all" :disabled="saveInProgress" />
        </template>

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this text box?</p>
            <ul class="flex-list buttons">
                <li>
                    <button
                        @click.prevent="sendDeleteTextboxRequest"
                        class="button submit"
                        id="usage-textbox-delete-button"
                        :disabled="isProcessActive"
                    >
                        Delete
                    </button>
                </li>
                <li>
                    <button
                        @click.prevent="$refs.deleteModal.close"
                        class="button"
                        id="usage-textbox-cancel-button"
                        :disabled="isProcessActive"
                    >
                        Cancel
                    </button>
                </li>
            </ul>
        </reveal-modal>
    </form>
</template>
