/**
 * Copyright (c) 2003-2020, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */

// This file contains style definitions that can be used by CKEditor plugins.
//
// The most common use for it is the "stylescombo" plugin which shows the Styles drop-down
// list containing all styles in the editor toolbar. Other plugins, like
// the "div" plugin, use a subset of the styles for their features.
//
// If you do not have plugins that depend on this file in your editor build, you can simply
// ignore it. Otherwise it is strongly recommended to customize this file to match your
// website requirements and design properly.
//
// For more information refer to: https://ckeditor.com/docs/ckeditor4/latest/guide/dev_styles.html#style-rules


/* Enforce minimum styles as per SA-7321
 * This avoids the need to edit styles.js directly which is often a victim of blind upgrades
 */
CKEDITOR.stylesSet.add('default', [
	{ name: 'Small',			element: 'small' },
	{ name: 'Typewriter',		element: 'tt' },
	{ name: 'Computer Code',	element: 'code' },
	{
		name: 'Highlighted Note',
		element: 'span',
		attributes: {
			'class': 'highlighted-note'
		}
	}
]);
