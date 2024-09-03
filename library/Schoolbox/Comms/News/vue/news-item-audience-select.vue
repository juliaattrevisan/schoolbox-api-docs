<script>
/**
 * To select who will be able to see the news item
 * Various options are presented within a modal to
 * specifically target grades / groups / campuses etc
 *
 * ATTENTION - There's quite a few global vars from library/Schoolbox/Comms/News/templates/create.twig being referenced here
 *             When you can't find the origin of a var, just search in the create.twig file
 *
 */

export default {
    props: {
        audienceBrowserSearchUrl: String,
        audienceIsReadOnly: Boolean,
        localeNews: String,
        rejectRoute: String,
        article: Object,
        audiencePlaceholder: String,
        encodedSegments: Array,
    },

    data() {
        return {
            // Prevent the user from submitting the composite modal form if current selections would exceed this number of composite segments.
            // eg a 10 x 10 selection will create (10 * 10)*(2+1) = 300 rows in the audience segment table!
            limitSegmentsPerAddition: 300,

            // The audience groups available in the main simple-segment panel, and the dropdowns on the intersect panel
            audienceOptions: {
                base: Object.assign({}, audienceOptions),
                intersect: {
                    primary: Object.assign({}, audienceOptions),
                    secondary: Object.assign({}, audienceOptions),
                },
            },

            // Modal sections showing and hiding
            pageShow: {
                index: true,
                valueSelect: false,
                intersect: false,
            },

            // The option selected by the user from the initial modal screen
            selectedAudienceOption: {},

            // The list of options populated by ajax after the user selects an initial option
            audienceListItems: {},
            audienceListItemsIntersect: {
                primary: [],
                secondary: [],
            },

            // The checked state of the above list items
            audienceListChecked: [],

            /**
             * The set of audience bricks for this article
             * @param {Brick[]}
             */
            audienceBricks: [],

            // The audience string for form submission, generated from the list of audience bricks in this.audienceBricks
            audienceFormString: "",

            // Preloader and empty state showing/hiding for the 2nd screen
            showLoader: true,
            showEmptyState: false,

            // The checked state of the three checkboxes at the bottom of the 2nd modal screen
            includeUsers: true,
            includeParents: false,
            includeTeachers: false,

            // The selected intersect groups
            // Default to campus and role
            intersect: {
                primary: getDefaultIntersectAudience("primary", audienceOptions),
                secondary: getDefaultIntersectAudience("secondary", audienceOptions),
            },

            // The models for the checkboxes
            intersectList: {
                primary: [],
                secondary: [],
            },

            // Just a marker to check if advanced has been clicked for (need to load defaults on first click)
            hasLoadedAdvanced: false,

            // the composite brick currently selected for display in the composite details modal
            selectedCompositeBrick: {},
        };
    },

    computed: {
        showModifierSelfCheckbox() {
            return (
                this.selectedAudienceOption.availableModifiers &&
                -1 !== this.selectedAudienceOption.availableModifiers.indexOf("self")
            );
        },

        showModifierParentsCheckbox() {
            return (
                this.selectedAudienceOption.availableModifiers &&
                -1 !== this.selectedAudienceOption.availableModifiers.indexOf("parents_of")
            );
        },

        showModifierTeachersCheckbox() {
            return (
                this.selectedAudienceOption.availableModifiers &&
                -1 !== this.selectedAudienceOption.availableModifiers.indexOf("teachers_of")
            );
        },

        showModifierCheckboxes() {
            return this.showModifierParentsCheckbox || this.showModifierTeachersCheckbox;
        },

        /**
         * Disable the modal submit button until each of the checklists has something checked
         */
        submitEnable() {
            return !!(
                this.audienceListChecked.length &&
                (this.includeUsers || this.includeParents || this.includeTeachers)
            );
        },

        submitEnableAdvanced() {
            return !!(
                this.intersectList.primary.length &&
                this.intersectList.secondary.length &&
                !this.isTooManyAdvancedSelections
            );
        },

        isTooManyAdvancedSelections() {
            return (
                this.intersectList.primary.length * this.intersectList.secondary.length * 3 >
                this.limitSegmentsPerAddition
            );
        },

        selectedModifiers() {
            var modifiers = [];
            if (this.includeUsers) {
                modifiers.push("self");
            }
            if (this.includeParents) {
                modifiers.push("parents_of");
            }
            if (this.includeTeachers) {
                modifiers.push("teachers_of");
            }
            return modifiers;
        },

        // Returns the number of different audience segment entity-types available in the modal
        countModalSegmentTypes() {
            var count = 0;
            for (var i in audienceOptions) {
                if (audienceOptions.hasOwnProperty(i) && !audienceOptions[i].hideFromModal) {
                    count++;
                }
            }
            return count;
        },

        // Returns whether the user can create a complex audience
        canCreateComplex() {
            if (this.countModalSegmentTypes > 1) {
                return true;
            }
            for (var i in audienceOptions) {
                if (
                    audienceOptions.hasOwnProperty(i) &&
                    !audienceOptions[i].hideFromModal &&
                    audienceOptions[i].isMulti
                ) {
                    return true;
                }
            }
            return false;
        },

        tooltipText() {
            var availableMods = [];
            var tooltipPrefixText = "";
            var tooltipSufixText = "";

            for (var i in this.selectedAudienceOption.availableModifiers) {
                if (this.selectedAudienceOption.availableModifiers.hasOwnProperty(i)) {
                    availableMods.push(this.selectedAudienceOption.availableModifiers[i]);
                }
            }

            if (availableMods.length === 1 && availableMods[0] === "self") {
                switch (this.selectedAudienceOption.name.toLowerCase()) {
                    case "campus":
                        return (
                            "Select " +
                            this.selectedAudienceOption.name.toLowerCase() +
                            " below, please note these include all members of the campus including staff, parents and students."
                        );
                        break;

                    default:
                        return false;
                }
            }

            if (availableMods.length > 1) {
                var selectedMods = [];
                availableMods.forEach((element) => {
                    selectedMods.push(tooltipTextLabelMap[element]);
                });

                tooltipPrefixText =
                    "Select " +
                    this.selectedAudienceOption.namePlural +
                    " below, then choose whether you would like to include the ";
                tooltipSufixText = ", or a combination.";

                return tooltipPrefixText + selectedMods.join(", ") + tooltipSufixText;
            }
        },
    },

    watch: {
        //To create an audience string to add to the form submission, linked to the audienceBricks rather than the selectize element
        //This is needed as it's possible to delete audience bricks just by tapping the backspace in the audience input field a few times...
        //Which would result in the bricks displayed in the custom UI not matching what's actually going to be submitted
        audienceBricks: {
            handler() {
                const encodedAudienceSegments = [];
                this.audienceBricks.forEach((brick) => {
                    //loop through each brick
                    brick.segments.forEach((segment) => {
                        //and then each segment within
                        encodedAudienceSegments.push(codec.encode(segment)); //to add each into the array
                    });
                });
                this.audienceFormString = encodedAudienceSegments.join(","); //then create a comma delimited segment string
            },
            deep: true, //we need to watch 'deeply', as there can be changes in the 'segments' within each 'audienceBrick'
        },
    },

    mounted() {
        var vue = this;
        brickManager = new BrickManager(codec, entityTypeLabelMap);

        $audienceSelectize = $("#audience-search");

        // Audience Selectize
        // A blend of Vue and jQuery, what a world
        audienceSelectize = $audienceSelectize
            .selectizeSelector({
                ajax: {
                    url: this.audienceBrowserSearchUrl,
                    type: "GET",
                    dataType: "json",
                },
                selectize: {
                    valueField: "key",
                    labelField: "name",
                    searchField: "name",
                    render: {
                        /**
                         * Called when ajax call returns to render each item into dropdown list
                         *
                         * @param {Object} item     - The selected item from the AJAX set
                         * @param {function} escape - A function that replaces [&<>"] chars with HTML character entities
                         * @return {string}
                         */
                        option: function (item, escape) {
                            if (!audienceIsReadOnly) {
                                var segment = codec.decode(item.key),
                                    entityType = segment.getEntityType(),
                                    modifier = segment.getModifier();

                                var $option = $audienceSelectizeItemTemplate.clone();
                                $option.find("[data-name]").html(escape(item.name));
                                $option.find("[data-entityType]").html("(" + audienceOptions[entityType].name + ")");
                                if ("self" !== modifier) {
                                    $option.find("[data-modifier]").html("[" + modifierLabelMap[modifier] + "]");
                                } else {
                                    $option.find("[data-modifier]").remove();
                                }
                                return "<div>" + $option.html() + "</div>";
                            }
                        },
                    },
                    /**
                     * Called when an item is added to audienceSelectize. (either due to initial value, option selection, or manual addition)
                     *
                     * We want to add the simple/composite segment to our bricks in Vue's data model, Vue will then update the Brick DOM for us.
                     *
                     * @param {string} key - The audience segment identifier, eg 'folder:Mzgx:parents_of'
                     * @param {jQuery} $item - The jQuery wrapper of the DOM element that is displayed in default audienceSelectize
                     */
                    onItemAdd: function (key, $item) {
                        var selectize = this;

                        // Selectize doesn't give us all the data of the selected item, find the item in the full list -_-
                        var selectedItem = this.options[key];

                        brickManager.onAddSegment(
                            audienceSelectize,
                            vue.audienceBricks,
                            key,
                            selectedItem.name,
                            $item,
                            selectedItem.labels,
                        );

                        if (!audienceIsReadOnly) {
                            // Hide this item from the audienceSelectize list
                            $item.addClass("hide");

                            // Selectize hides the placeholder once an item is added- we want to keep it, quite hacky
                            $("#audience-search-selectized")
                                .attr("placeholder", vue.audiencePlaceholder)
                                .css("width", "100%");

                            // The audienceSelectize dropdown doesn't position correctly for some reason with this setup so force reposition again after a very short timeout
                            setTimeout(function () {
                                audienceSelectize.positionDropdown();

                                // Trigger a blur on the selectize so that validation updates
                                // This has to happen on a slight delay as well for some reason
                                selectize.$input.blur();
                            }, 1);
                        }
                    },
                    /**
                     * Called when we remove an item from audienceSelectize. (either because we clicked X icon on audienceSelectize item, or programmatically)
                     * @param {string} key    - eg 'composite:508:campus:509:IjIi:self,role:510:IjYi:self:self'
                     * @param {jQuery} $item - Wrapper of audienceSelectize item
                     */
                    onItemRemove: function (key, $item) {
                        brickManager.onRemoveSegment(key, $item);
                        // Selectize hides the placeholder once an item is removed- we want to keep it, quite hacky
                        $("#audience-search-selectized")
                            .attr("placeholder", vue.audiencePlaceholder)
                            .css("width", "100%");
                    },
                },
                value: this.encodedSegments,
                multiple: true,
            })
            .data("selectize");
    },

    methods: {
        /**
         * Opening the 'browse...' modal
         * If only one audience type is available, just shows that. Otherwise displays an index page.
         */
        openBrowseModal() {
            this.resetModal();

            if (1 === this.countModalSegmentTypes) {
                // If only one audience type available, jump straight to that
                var audienceOption = null;
                for (var type in this.audienceOptions.base) {
                    if (this.audienceOptions.base.hasOwnProperty(type)) {
                        audienceOption = this.audienceOptions.base[type];
                        break;
                    }
                }
                this.changePage(this.audienceOptions.base[audienceOption.entityType]);
            } else {
                // Reset everythang
                this.showPage("index");
            }

            // Open the modal
            $("#audience-browse").foundation("reveal", "open");
        },

        /**
         * Selecting one of the initial options
         * @param {AudienceOption} audienceOption
         */
        changePage(audienceOption) {
            // Reset everything
            this.resetModal();

            // Show the page
            this.showPage("valueSelect");

            // Update the selected option
            this.selectedAudienceOption = audienceOption;

            // Not yet loaded?
            if (audienceOption.isLoaded) {
                this.populateAudienceList(audienceOption);
            } else {
                var callback = () => {
                    this.populateAudienceList(audienceOption);
                };
                this.loadAudienceOptions(audienceOption, callback);
            }
        },

        /**
         * Loads available options for the specific audience option, then calls the specified callback
         *
         * @param {AudienceOption} audienceOption
         * @param {function} callback
         */
        async loadAudienceOptions(audienceOption, callback) {
            // Request a list of options with the uri listed in the object above
            fetch(audienceOption.ajax)
                .then((response) => response.json())
                .then((data) => {
                    // Ensure we received expected payload
                    if (
                        undefined === data.availableModifiers ||
                        undefined === data.canTargetNull ||
                        undefined === data.valueLabels
                    ) {
                        throw new Error("Unable to process AJAX payload: " + data);
                    }
                    // Cache the options
                    audienceOption.availableModifiers = data.availableModifiers;
                    audienceOption.isLoaded = true;

                    audienceOption.canTargetNull = data.canTargetNull;
                    audienceOption.valueLabels = data.valueLabels;

                    callback();
                })
                .catch((error) => {
                    console.log(error);
                });
        },

        /**
         * Show the list of items, or show the empty state
         * @param {AudienceOption} audienceOption
         */
        populateAudienceList(audienceOption) {
            // Hide the loader
            this.showLoader = false;

            if (Object.keys(audienceOption.valueLabels).length) {
                // Populate the list with cached version
                this.audienceListItems = audienceOption.valueLabels;
            } else {
                // Show empty state
                this.showEmptyState = true;
            }
        },

        /**
         * Submitting the simple-segment modal form
         */
        addAudienceSimple() {
            let entityType = this.selectedAudienceOption.entityType;

            // Add to audienceSelectize
            this.audienceListChecked.forEach((encodedValue) => {
                this.injectSimpleSegment(this.selectedAudienceOption, entityType, encodedValue, this.selectedModifiers);
            });

            brickManager.onAddSetSimpleSegmentsViaUi(audienceSelectize, this.audienceBricks, entityType);

            audienceSelectize.refreshItems();

            // Close modal
            $("#audience-browse").foundation("reveal", "close");
        },

        // Inject a simple segment item into audienceSelectize
        injectSimpleSegment(audienceOption, entityType, encodedValue, modifiers) {
            var entityTypeHR = entityTypeLabelMap[entityType];
            var label = audienceOption.valueLabels[encodedValue];
            var labels = {};
            labels[entityType] = {};
            labels[entityType][encodedValue] = label;

            modifiers.forEach((modifier) => {
                var option = {
                    key: codec.encodeSimpleAttributes(entityType, encodedValue, modifier),
                    name: "[" + entityTypeHR + ":" + label + "]^" + modifier,
                    labels: labels,
                };
                audienceSelectize.addOption(option);
                audienceSelectize.addItem(option.key);
            });
        },

        // Submitting the composite-segment modal form
        addAudienceIntersect() {
            if (this.isTooManyAdvancedSelections) {
                throw new Error("Too many selections");
            }

            // TODO move this to BrickManager method. Pass in reference to Vue data

            let entityTypePrimary = this.intersect.primary.entityType;
            let entityTypePrimaryHR = entityTypeLabelMap[entityTypePrimary];
            let entityTypeSecondary = this.intersect.secondary.entityType;
            let entityTypeSecondaryHR = entityTypeLabelMap[entityTypeSecondary];
            let simplifiedValues = [];

            // Go through both lists, and if any is used in both, create simplified segment.
            // Since any intersection with a simple segment is a subset, don't bother adding the subset.
            if (entityTypePrimary === entityTypeSecondary) {
                this.intersectList.primary.forEach((primaryValue) => {
                    this.intersectList.secondary.forEach((secondaryValue) => {
                        if (primaryValue === secondaryValue) {
                            this.injectSimpleSegment(this.intersect.primary, entityTypePrimary, primaryValue, ["self"]);
                            simplifiedValues.push(primaryValue);
                        }
                    });
                });
            }

            // Each checkbox in first dimension
            this.intersectList.primary.forEach((primaryValue) => {
                if (-1 !== simplifiedValues.indexOf(primaryValue)) {
                    return true;
                }

                var labelPrimary = this.intersect.primary.valueLabels[primaryValue];

                // Each checkbox in second dimension
                this.intersectList.secondary.forEach((secondaryValue) => {
                    if (-1 !== simplifiedValues.indexOf(secondaryValue)) {
                        return true;
                    }

                    var labelSecondary = this.intersect.secondary.valueLabels[secondaryValue];

                    // Create a audienceSelectize item. Adding it will cause audienceSelectize to inject a Segment into the BrickManager
                    var labels = {};
                    labels[entityTypePrimary] = {};
                    labels[entityTypePrimary][primaryValue] = labelPrimary;
                    if (entityTypePrimary !== entityTypeSecondary) {
                        labels[entityTypeSecondary] = {};
                    }
                    labels[entityTypeSecondary][secondaryValue] = labelSecondary;

                    this.selectedModifiers.forEach((modifier) => {
                        // composite children are hard-coded to 'self' for now
                        var ss1 = new Segment(entityTypePrimary, primaryValue, "self", null),
                            ss2 = new Segment(entityTypeSecondary, secondaryValue, "self", null),
                            cs = new Segment("composite", null, modifier, [ss1, ss2]);

                        // Composite names are not displayed to user, but helpful for debugging.
                        var name1 = "[" + entityTypePrimaryHR + ":" + labelPrimary + "]^S",
                            name2 = "[" + entityTypeSecondaryHR + ":" + labelSecondary + "]^S",
                            option = {
                                key: codec.encode(cs),
                                name: "[" + name1 + " ∩ " + name2 + "]^" + modifier,
                                labels: labels,
                            };

                        audienceSelectize.addOption(option);
                        audienceSelectize.addItem(option.key);
                    });
                });
            });

            if (simplifiedValues.length) {
                brickManager.onAddSetSimpleSegmentsViaUi(audienceSelectize, this.audienceBricks, entityType);
            }

            // Close modal
            $("#audience-browse").foundation("reveal", "close");
            this.intersectList.primary = [];
            this.intersectList.secondary = [];

            audienceSelectize.refreshItems();
        },

        /**
         * Show the page specified, hiding the other
         * @param page
         */
        showPage(page) {
            // Reset everything so that when we submit, we only submit details from the selected page.
            // If we do not do this, selecting an audience and then selecting a composite audience before submitting will result in an AJAX error.
            this.resetModal();

            // Set all pages to false to start
            Object.keys(this.pageShow).forEach((page) => {
                this.pageShow[page] = false;
            });

            // Set the specified page to true
            this.pageShow[page] = true;
        },

        /**
         * Reset the second modal section back to a default state
         */
        resetModal() {
            this.audienceListItems = {};
            this.audienceListChecked = [];
            this.includeUsers = true;
            this.includeParents = false;
            this.includeTeachers = false;
            this.showLoader = true;
            this.showEmptyState = false;
        },

        /**
         * Remove an audience item
         * TODO ask the brick manager to do this, and pass it selecize
         */
        removeAudienceBrick(brick) {
            brickManager.removeBrick(audienceSelectize, this.audienceBricks, brick);
            audienceSelectize.refreshItems();
        },

        /**
         * Clicking the 'advanced' tab
         */
        advancedToggle() {
            if (!this.hasLoadedAdvanced) {
                // Load the defaults
                this.updateIntersectList("primary");
                this.updateIntersectList("secondary");
                // Update the flag
                this.hasLoadedAdvanced = true;
            }

            // Show the page always
            this.showPage("intersect");
        },

        /**
         * Update an intersect list with possible options
         * @param {string} list 'primary' or 'secondary'
         */
        updateIntersectList(list) {
            var audienceOption = this.intersect[list];

            // Reset checked item model
            this.intersectList[list] = [];

            // Urgh
            var otherList = list === "primary" ? "secondary" : "primary";

            // Remove this option from the other dropdown if it is not a 1:N relationship
            this.audienceOptions.intersect[otherList] = Object.assign({}, audienceOptions);
            if (!audienceOption.isMulti) {
                delete this.audienceOptions.intersect[otherList][audienceOption.entityType];
            }

            // Try for cached list
            if (audienceOption.isLoaded) {
                this.audienceListItemsIntersect[list] = audienceOption.valueLabels;
            } else {
                // Nope, ajax time

                var callback = () => {
                    this.audienceListItemsIntersect[list] = audienceOption.valueLabels;
                };

                this.loadAudienceOptions(audienceOption, callback);
            }
        },

        /**
         * Populate and show a modal with info on a composite audience segment
         * @param {Brick} audienceBrick
         */
        showCompositeInfo(audienceBrick) {
            this.selectedCompositeBrick = audienceBrick;
            // Show modal
            $("#audience-composite").foundation("reveal", "open");
        },
    },
};
</script>

<template>
    <div v-if="countModalSegmentTypes">
        <div class="small-12 columns">
            <label class="required">Audience</label>
            <p class="meta">
                <template v-if="!audienceIsReadOnly">
                    Target your article to a specific audience. Use the input box below to search, or use the 'Browse'
                    button to select from a list of available options.
                </template>
                <template v-else>
                    This audience can no longer be updated because the article is published. If you wish to update the
                    audience,
                    <template v-if="rejectRoute !== ''">
                        <a id="unpublish-article" :href="rejectRoute" :data-reject="article.title"
                            >unpublish this {{ localeNews }}.</a
                        >
                    </template>
                    <template v-else> unpublish this {{ localeNews }}. </template>
                </template>
            </p>
            <ul
                class="grid small-block-grid-1 medium-block-grid-2 large-block-grid-4 audience-grid"
                v-show="audienceBricks.length"
            >
                <li v-for="(audienceBrick, index) in audienceBricks" :key="index">
                    <div class="actions-small-1">
                        <div class="card small-12">
                            <h4 :title="audienceBrick.nameLabel">
                                {{ audienceBrick.nameLabel }}
                            </h4>
                            <p class="meta">
                                {{ audienceBrick.typeLabel }}
                                <a
                                    href="#"
                                    v-if="audienceBrick.isComposite()"
                                    @click.prevent="showCompositeInfo(audienceBrick)"
                                    >(view details)</a
                                >
                            </p>
                            <p class="meta" :title="audienceBrick.modifier">
                                <a
                                    href="#"
                                    v-if="audienceBrick.isComposite()"
                                    @click.prevent="showCompositeInfo(audienceBrick)"
                                    >{{ audienceBrick.modifierLabel }}</a
                                >
                                <template v-if="audienceBrick.isSimple()">{{ audienceBrick.modifierLabel }}</template>
                            </p>
                        </div>
                        <template v-if="!audienceIsReadOnly">
                            <nav>
                                <a
                                    class="icon-close size-8"
                                    :title="'Remove ' + audienceBrick.nameLabel + ' from audience'"
                                    @click="removeAudienceBrick(audienceBrick)"
                                ></a>
                            </nav>
                        </template>
                    </div>
                </li>
            </ul>
            <div class="input-group" id="audience-selectize-group">
                <input
                    type="text"
                    id="audience-search"
                    name="audience-search"
                    :placeholder="audiencePlaceholder"
                    required
                />
                <button class="button icon-drag-drop" @click.prevent="openBrowseModal">Browse&hellip;</button>
            </div>
            <input type="hidden" name="audience" :value="audienceFormString" />
        </div>

        <template v-if="!audienceIsReadOnly">
            <div data-reveal id="audience-browse" class="reveal-modal no-pad">
                <template v-if="canCreateComplex">
                    <dl class="tabs text-center" role="list">
                        <dd :class="{ active: pageShow.index || pageShow.valueSelect }">
                            <a @click="showPage('index')">Select Audience</a>
                        </dd>
                        <dd :class="{ active: pageShow.intersect }">
                            <a @click="advancedToggle()">Create Composite Audience</a>
                        </dd>
                    </dl>
                </template>

                <a class="close-reveal-modal" aria-label="Close">×</a>

                <div v-show="pageShow.index" class="reveal-modal-tab-content">
                    <div
                        class="small-12 medium-4 columns left"
                        v-for="(audienceOption, index) in audienceOptions.base"
                        :key="index"
                    >
                        <button
                            v-if="!audienceOption.hideFromModal"
                            class="button-block"
                            @click="changePage(audienceOption)"
                        >
                            <i :class="'icon-' + audienceOption.icon"></i>
                            {{ audienceOption.namePlural }}
                        </button>
                    </div>
                </div>

                <div v-show="pageShow.valueSelect" class="reveal-modal-tab-content">
                    <div class="row">
                        <div class="small-12 columns">
                            <ul class="breadcrumb" v-show="countModalSegmentTypes > 1">
                                <li><a href="#" @click.prevent="showPage('index')">Audience Types</a></li>
                                <li class="active">
                                    <a>{{ selectedAudienceOption.namePlural }}</a>
                                </li>
                            </ul>
                            <template v-if="tooltipText">
                                <p class="meta">{{ tooltipText }}</p>
                            </template>
                            <div class="empty-state" v-show="showEmptyState">
                                <p>No {{ selectedAudienceOption.namePlural }} found.</p>
                            </div>
                            <div class="checklist checklist-container" v-show="!showEmptyState">
                                <div class="loader" v-show="showLoader"></div>
                                <div
                                    class="checklist-wrapper"
                                    v-for="(audienceListItem, index) in audienceListItems"
                                    :key="index"
                                >
                                    <input
                                        type="checkbox"
                                        :id="'audience-list-input-' + index"
                                        :value="index"
                                        v-model="audienceListChecked"
                                    />
                                    <label :for="'audience-list-input-' + index">{{ audienceListItem }}</label>
                                </div>
                            </div>
                            <fieldset class="checklist border-none" v-show="showModifierCheckboxes">
                                <ul class="option-list">
                                    <li v-if="showModifierSelfCheckbox">
                                        <input type="checkbox" id="audience-select-users" v-model="includeUsers" />
                                        <label for="audience-select-users">{{
                                            selectedAudienceOption.selfModifier
                                        }}</label>
                                    </li>
                                    <li v-if="showModifierParentsCheckbox">
                                        <input type="checkbox" id="audience-select-parents" v-model="includeParents" />
                                        <label for="audience-select-parents">Parents of students</label>
                                    </li>
                                    <li v-if="showModifierTeachersCheckbox">
                                        <input
                                            type="checkbox"
                                            id="audience-select-teachers"
                                            v-model="includeTeachers"
                                        />
                                        <label for="audience-select-teachers">Teachers of students</label>
                                    </li>
                                </ul>
                            </fieldset>
                            <button class="button submit" @click.prevent="addAudienceSimple" :disabled="!submitEnable">
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                <div v-show="pageShow.intersect" class="reveal-modal-tab-content">
                    <div class="row">
                        <div class="small-12 columns">
                            <p class="meta">
                                Choose audience types and select the appropriate tickboxes to create a composite
                                audience. E.g. Parents and Students only in the Senior Campus.
                            </p>
                            <div class="alert-box alert" v-if="isTooManyAdvancedSelections">
                                Too many selections. Please deselect some options.
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div
                            v-for="(item, key, index) in intersect"
                            class="small-12 medium-6 columns checklist"
                            :key="index"
                        >
                            <legend>
                                <label for="">Audience Type {{ index + 1 }}:</label>
                                <select @change="updateIntersectList(key)" v-model="intersect[key]">
                                    <template v-for="(audienceOption, index) in audienceOptions.intersect[key]">
                                        <option
                                            :value="audienceOption"
                                            v-if="!audienceOption.hideFromModal"
                                            :key="index"
                                        >
                                            {{ audienceOption.name }}
                                        </option>
                                    </template>
                                </select>
                            </legend>
                            <ul class="option-list" v-for="(item, key2) in audienceListItemsIntersect[key]" :key="key2">
                                <li>
                                    <input
                                        type="checkbox"
                                        :id="key + '-' + key2"
                                        :value="key2"
                                        v-model="intersectList[key]"
                                        :key="key2 + '-input'"
                                    />
                                    <label :for="key + '-' + key2" class="checkbox-label" :key="key2 + '-label'">{{
                                        item
                                    }}</label>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="row">
                        <div class="small-12 columns">
                            <button
                                class="button submit"
                                @click.prevent="addAudienceIntersect"
                                :disabled="!submitEnableAdvanced"
                            >
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div data-reveal id="audience-composite" class="reveal-modal">
            <a class="close-reveal-modal" aria-label="Close">×</a>
            <p v-if="selectedCompositeBrick.modalData">
                This portion of the audience consists of users who are in <strong>both</strong> one or more of these
                <strong>{{ selectedCompositeBrick.modalData[0].typeLabel.plural.toLowerCase() }}</strong> and one or
                more of these <strong>{{ selectedCompositeBrick.modalData[1].typeLabel.plural.toLowerCase() }}</strong
                >.
            </p>
            <hr />
            <div class="row">
                <div
                    class="small-12 medium-6 columns no-pad audience-composite-row"
                    v-for="(dimensionData, index) in selectedCompositeBrick.modalData"
                    :key="index"
                >
                    <p>
                        <strong>{{ dimensionData.typeLabel.plural }}</strong>
                    </p>
                    <ul>
                        <li v-for="(label, index) in dimensionData.valueLabels" :key="index">{{ label }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
