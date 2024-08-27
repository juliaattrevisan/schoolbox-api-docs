/**
 * This is primarily a copy of the original LinkImageUI plugin from CKEditor 5
 * modified to display a browse button that opens our resource modal to select
 * a resource to link to
 *
 * Changes:
 *  - Update all imports to "@ckeditor/" equivalents
 *  - Change LinkUI import to our own plugin
 *  - In requires() change "ImageBlockEditing" to "ImageInlineEditing" (as we don't use ImageBlock)
 */

// Ignore this file for TypeScript and ESLint checks for now (SA-18139)
/* eslint-disable */
/* eslint-enable vue/no-v-html */

/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */

/**
 * @module link/linkimageui
 */

import { ButtonView } from "@ckeditor/ckeditor5-ui";
import { Plugin } from "@ckeditor/ckeditor5-core";
import type { DocumentSelection, Selection, ViewDocumentClickEvent } from "@ckeditor/ckeditor5-engine";

import type { ImageUtils } from "@ckeditor/ckeditor5-image";

import LinkUI from "@js/editor/plugin/link/LinkUI";
import LinkEditing from "@ckeditor/ckeditor5-link/src/linkediting";

import { LINK_KEYSTROKE } from "@ckeditor/ckeditor5-link/src/utils";

import linkIcon from "@ckeditor/ckeditor5-link/theme/icons/link.svg";

/**
 * The link image UI plugin.
 *
 * This plugin provides the `'linkImage'` button that can be displayed in the {@link module:image/imagetoolbar~ImageToolbar}.
 * It can be used to wrap images in links.
 */
export default class LinkImageUI extends Plugin {
    /**
     * @inheritDoc
     */
    public static get requires() {
        return [LinkEditing, LinkUI, "ImageInlineEditing"] as const;
    }

    /**
     * @inheritDoc
     */
    public static get pluginName() {
        return "LinkImageUI" as const;
    }

    /**
     * @inheritDoc
     */
    public init(): void {
        const { editor } = this;
        const viewDocument = editor.editing.view.document;

        this.listenTo<ViewDocumentClickEvent>(
            viewDocument,
            "click",
            (evt, data) => {
                if (this._isSelectedLinkedImage(editor.model.document.selection)) {
                    // Prevent browser navigation when clicking a linked image.
                    data.preventDefault();

                    // Block the `LinkUI` plugin when an image was clicked.
                    // In such a case, we'd like to display the image toolbar.
                    evt.stop();
                }
            },
            { priority: "high" },
        );

        this._createToolbarLinkImageButton();
    }

    /**
     * Creates a `LinkImageUI` button view.
     *
     * Clicking this button shows a {@link module:link/linkui~LinkUI#_balloon} attached to the selection.
     * When an image is already linked, the view shows {@link module:link/linkui~LinkUI#actionsView} or
     * {@link module:link/linkui~LinkUI#formView} if it is not.
     */
    private _createToolbarLinkImageButton(): void {
        const { editor } = this;

        editor.ui.componentFactory.add("linkImage", (locale) => {
            const button = new ButtonView(locale);
            const plugin = editor.plugins.get("LinkUI") as LinkUI;
            const linkCommand = editor.commands.get("link")!;

            button.set({
                isEnabled: true,
                label: editor.t("Link image"),
                icon: linkIcon,
                keystroke: LINK_KEYSTROKE,
                tooltip: true,
                isToggleable: true,
            });

            // Bind button to the command.
            button.bind("isEnabled").to(linkCommand, "isEnabled");
            button.bind("isOn").to(linkCommand, "value", (value) => !!value);

            // Show the actionsView or formView (both from LinkUI) on button click depending on whether the image is linked already.
            this.listenTo(button, "execute", () => {
                if (this._isSelectedLinkedImage(editor.model.document.selection)) {
                    plugin._addActionsView();
                } else {
                    plugin._showUI(true);
                }
            });

            return button;
        });
    }

    /**
     * Returns true if a linked image (either block or inline) is the only selected element
     * in the model document.
     * @param {DocumentSelection | Selection} selection The selection to check.
     * @returns {boolean} `true` if the selected element is a linked image, `false` otherwise.
     */
    private _isSelectedLinkedImage(selection: DocumentSelection | Selection): boolean {
        const selectedModelElement = selection.getSelectedElement();
        const imageUtils: ImageUtils = this.editor.plugins.get("ImageUtils");

        return imageUtils.isImage(selectedModelElement) && selectedModelElement.hasAttribute("linkHref");
    }
}
