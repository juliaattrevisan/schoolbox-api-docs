# SA-22946 Zenda Icon Design

## Goal

Add the approved Zenda ellipsis artwork to Schoolbox's shared SVG icon component and make it visible in the Pantry, Storybook, and the administrator's top-menu icon selector.

## Icon source and naming

Use only the `Zenda Ellipsis.svg` attachment from SA-22946. The `Zenda Purple.svg` wordmark is reference material and is not part of this implementation.

Register the icon under the name `zenda` and display title `Zenda`. Preserve the ellipsis artwork's three supplied brand colours:

- pink: `#ed5395`
- blue: `#33b0ff`
- teal: `#3ddad8`

## Shared icon implementation

Add a `zenda` branch to `vue/components/icon/sbx-icon.ce.vue` after the existing `wolfram-alpha` branch.

Express the artwork as three inline `<circle>` elements using the component's standard `viewBox="0 0 32 32"`. Normalize the source SVG's 400-unit coordinates into the 32-unit coordinate system and incorporate its group translation directly into the circle coordinates. Do not retain `width`, `height`, embedded styles, metadata, or transforms from the source SVG.

The explicit circle fills override the component's normal `currentColor` fill so the Zenda brand colours remain visible wherever the icon is rendered.

Do not add standalone SVG files to `vue/components/icon`; built-in icons are embedded directly in the component.

## Catalogue registrations

Add the icon to the Feature section of `templates/core/pantry/icons.htm`, after Wolfram Alpha:

```html
<a href="#" class="button sbx-button"><sbx-icon name="zenda"></sbx-icon>Zenda (icon-zenda)</a>
```

Add the following entry to the alphabetically ordered array in `stories/icons/icons.stories.ts`, after `wolfram-alpha`:

```ts
{ name: "zenda", label: "zenda" },
```

These are manual catalogue entries; neither Pantry nor Storybook discovers component icons automatically.

## Top-menu registration

Create `updates/26.2.alpha1/zenda/02-addMenuIcon.sql`, following the existing `01-schema.sql`.

The migration must idempotently register the shared icon name in `menu_icons`:

```sql
INSERT INTO
    menu_icons (name, title)
VALUES ('zenda', 'Zenda')
ON DUPLICATE KEY UPDATE
    title = VALUES(title);
```

No controller or Twig form changes are required. The existing top-menu administration controller reads its icon choices from `menu_icons`, and both its preview and the top-menu renderer pass the stored name to `<sbx-icon>`.

## Testing and verification

Extend `vue/components/icon/tests/sbx-icon.ce.test.js` with a focused test that creates `<sbx-icon name="zenda">` and verifies that it renders three circles with the approved fill colours. Run the test before implementing the icon to confirm it fails because the icon is absent, then rerun it after implementation.

Final verification covers:

- the focused icon unit test and full Jest suite;
- Prettier and ESLint for changed frontend files;
- TypeScript type checking;
- the frontend build;
- inspection of the SQL update and changed-file diff;
- browser verification of the Zenda icon in Pantry and in the Top Menu icon selector when the local environment is available.

No error-handling changes are needed because the existing component already handles unknown icon names.
