/**
 * This is primarily a copy of the original LinkImage plugin from CKEditor 5
 * modified to display a browse button that opens our resource modal to select
 * a resource to link to
 *
 * Changes:
 *  - Update all imports to "@ckeditor/" equivalents
 *  - Change LinkImageUI import to our own plugin
 */

// Ignore this file for TypeScript and ESLint checks for now (SA-18139)
/* eslint-disable */
/* eslint-enable vue/no-v-html */

/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */

/**
 * @module link/linkimage
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import LinkImageEditing from "@ckeditor/ckeditor5-link/src/linkimageediting";
import LinkImageUI from "@js/editor/plugin/link/LinkImageUI";

import "@ckeditor/ckeditor5-link/theme/linkimage.css";

/**
 * The `LinkImage` plugin.
 *
 * This is a "glue" plugin that loads the {@link module:link/linkimageediting~LinkImageEditing link image editing feature}
 * and {@link module:link/linkimageui~LinkImageUI link image UI feature}.
 */
export default class LinkImage extends Plugin {
    /**
     * @inheritDoc
     */
    public static get requires() {
        return [LinkImageEditing, LinkImageUI] as const;
    }

    /**
     * @inheritDoc
     */
    public static get pluginName() {
        return "LinkImage" as const;
    }
}
