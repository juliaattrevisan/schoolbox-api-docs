# Babel-traverse / extract-loader: Upgrade to rspack v2 with native CSS extraction

**Date:** 2026-06-25
**Branch:** npm-audit-26.1
**Author:** jleckie

## Problem

`babel-traverse@6.26.0` carries a critical vulnerability (CVSS 9.3, GHSA-67hx-6x53-jw92 — arbitrary code execution when compiling specifically crafted malicious code). It is a transitive dependency pulled in entirely by `extract-loader@5.1.0` via `babel-core@6.26.3` and `babel-preset-env@1.7.0`. While the risk is build-time only (an attacker would need to inject malicious SCSS/JS into the build pipeline), the vulnerability triggers dependency monitoring tools and represents an outdated build-time dep tree that should be modernised.

## Goal

Remove `extract-loader` and its old Babel 6 dep tree entirely by replacing the CSS extraction pipeline with rspack's built-in `CssExtractRspackPlugin`, taking the opportunity to upgrade rspack from v1 to v2 at the same time.

## Chosen Approach

**Upgrade rspack to v2 + replace extract-loader with `CssExtractRspackPlugin`.**

This was preferred over:
- Downgrading `extract-loader` to v4 (clears the audit but stays on old tooling, downgrade not upgrade)
- Replacing with `CssExtractRspackPlugin` on rspack v1 (same config change but leaves the rspack major version bump as future debt)

The code already anticipated this direction — `rspack.common.js` contains the comment: _"Leave off for now long term, as our CSS/SCSS is now pretty old, and rspack plans to make this default to true in the future."_

## Package Changes

| Package | Action | From | To |
|---|---|---|---|
| `@rspack/core` | Upgrade | `~1.7.6` | `~2.0.8` |
| `@rspack/cli` | Upgrade | `~1.7.6` | `~2.0.8` |
| `extract-loader` | Remove | `~5.1.0` | — |
| `file-loader` | Remove | `~6.2.0` | — |

`CssExtractRspackPlugin` ships inside `@rspack/core` — no new package is needed. Removing `extract-loader` eliminates the entire Babel 6 dep tree: `babel-core`, `babel-preset-env`, `babel-traverse`, and ~10 related packages.

`file-loader` is only used in the extract-loader pipeline and can be removed alongside it.

## Config Changes

### `rspack.common.js`

**1. Import `CssExtractRspackPlugin`** at the top of the file:

```js
const { CssExtractRspackPlugin } = require("@rspack/core");
```

**2. Add a `plugins` array** to the shared config (currently plugins live only in dev/prod configs):

```js
plugins: [
    new CssExtractRspackPlugin({
        filename: "../../css/core/[name].css",
    }),
],
```

The `../../css/core/` path is relative to `output.path` (`www/static/js/bundle/`), resolving to `www/static/css/core/` — matching the current output location exactly.

**3. Replace the SCSS pipeline rule** — swap `file-loader` + `extract-loader` for `CssExtractRspackPlugin.loader`:

```js
// Before
{
    loader: "file-loader",
    options: { name: "[name].css", outputPath: "../../css/core/" },
},
{ loader: "extract-loader" },

// After
CssExtractRspackPlugin.loader,
```

`css-loader` and `sass-loader` remain unchanged beneath it.

**4. Rename the `schoolboxCkStyles` entry key** to `"schoolbox-ck-content"`:

```js
// Before
schoolboxCkStyles: "./www/static/css/core/schoolbox-ck-content.scss",

// After
"schoolbox-ck-content": "./www/static/css/core/schoolbox-ck-content.scss",
```

`CssExtractRspackPlugin` uses the entry key as `[name]` in the filename template, unlike `file-loader` which used the original source filename. Without this rename the file would emit as `schoolboxCkStyles.css` instead of `schoolbox-ck-content.css`.

**5. Keep `experiments.css: false`** — this flag controls rspack's native CSS module system, which is a separate feature from `CssExtractRspackPlugin`. Leaving it off avoids any conflict and keeps the webpack-compatible CSS pipeline in place.

### `rspack.dev.js` and `rspack.prod.js`

No changes required. `CssExtractRspackPlugin` is added to the shared config; `RemoveEmptyScriptsPlugin` in both dev/prod configs continues to handle cleanup of the empty JS stubs emitted for SCSS-only entries.

## rspack v2 Migration Notes

rspack publishes a migration guide at https://rspack.rs/guide/migration/webpack. Known breaking changes to check against this config:

- `builtins.html` removed in v2 — not used here, no impact
- Resolver option defaults may change — verify aliases still resolve correctly after upgrade
- Run `npx @rspack/cli build` after upgrading and review any warnings before assuming success

## Verification

1. **Baseline snapshot** — before making any changes, capture checksums of the four emitted CSS files:
   - `www/static/css/core/core.css`
   - `www/static/css/core/sbx-core.css`
   - `www/static/css/core/schoolbox-ck-content.css`
   - `www/static/css/core/learnosity.css`

2. **Apply changes and rebuild** — install updated packages, run build, compare checksums. Any diff in CSS content should be investigated.

3. **Browser spot-check** — load a page exercising core styles and a page using CK editor styles. Visual regressions are the real success gate; the CSS pipeline has no unit test coverage.

4. **Confirm audit is clear** — run `npm audit` and confirm `babel-traverse` no longer appears.

5. **Confirm empty JS cleanup** — verify the empty JS stubs (`core.js`, `sbx-core.js`, etc.) are not appearing in `www/static/js/bundle/` after the build.

## Success Criteria

- `npm audit` no longer reports `babel-traverse` vulnerability
- The four emitted CSS files are byte-for-byte identical (or explained diffs only)
- No visual regressions in browser spot-check
- `RemoveEmptyScriptsPlugin` continues to suppress empty JS stubs for SCSS entries
