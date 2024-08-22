/**
 * This is primarily a copy of the original Link plugin from CKEditor 5
 * modified to display a browse button that opens our resource modal to select
 * a resource to link to
 *
 * Changes:
 *  - Update all imports to "@ckeditor/" equivalents
 *  - Change LinkUI import to our own plugin
 *  - Import ResourceLinkEditing and add into requires()
 */

// Ignore this file for TypeScript and ESLint checks for now (SA-18139)
/* eslint-disable */
/* eslint-enable vue/no-v-html */

/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */

/**
 * @module link/link
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import LinkEditing from "@ckeditor/ckeditor5-link/src/linkediting";
import ResourceLinkEditing from "@js/editor/plugin/resourceLink/ResourceLinkEditing";
import LinkUI from "@js/editor/plugin/link/LinkUI";
import AutoLink from "@ckeditor/ckeditor5-link/src/autolink";

/**
 * The link plugin.
 *
 * This is a "glue" plugin that loads the {@link module:link/linkediting~LinkEditing link editing feature}
 * and {@link module:link/linkui~LinkUI link UI feature}.
 */
export default class Link extends Plugin {
    /**
     * @inheritDoc
     */
    public static get requires() {
        return [LinkEditing, ResourceLinkEditing, LinkUI, AutoLink] as const;
    }

    /**
     * @inheritDoc
     */
    public static get pluginName() {
        return "Link" as const;
    }
}
