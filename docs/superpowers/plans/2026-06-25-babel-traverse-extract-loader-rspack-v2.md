# babel-traverse / extract-loader: Upgrade to rspack v2 with CssExtractRspackPlugin

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `extract-loader` and its Babel 6 dep tree (including the critical `babel-traverse` vulnerability) by replacing the SCSS extraction pipeline with rspack's built-in `CssExtractRspackPlugin`, and upgrade rspack from v1 to v2.

**Architecture:** The project uses rspack as its bundler with a custom SCSS pipeline: SCSS entry points → `sass-loader` → `css-loader` → `extract-loader` → `file-loader` → emitted to `www/static/css/core/`. We replace the last two loaders with `CssExtractRspackPlugin`, which handles both extraction and file emission natively. rspack v2 is then a straightforward package version bump — the plugin API is unchanged between v1 and v2, so we validate the config change on v1 first, then bump.

**Tech Stack:** rspack v2, `CssExtractRspackPlugin` (bundled in `@rspack/core`), sass-loader, css-loader, webpack-merge

## Global Constraints

- `@rspack/core` and `@rspack/cli` must both be upgraded to `~2.0.8`
- `extract-loader` and `file-loader` must be fully removed from `package.json`
- `CssExtractRspackPlugin` import must come from `@rspack/core` — no separate package install
- The four emitted CSS files must land at their existing paths under `www/static/css/core/`
- `experiments.css` must remain `false` — we are using the plugin-based pipeline, not rspack's native CSS module system
- `npm audit` must not report `babel-traverse` after the change
- `npm-shrinkwrap.json` is the lockfile — update it via `npm install` (not `npm ci`)

---

### Task 1: Replace extract-loader pipeline with CssExtractRspackPlugin

**Files:**
- Modify: `rspack.common.js` (lines 9, 306, 388–409, 414–416)

**Interfaces:**
- Produces: `CssExtractRspackPlugin` configured to emit CSS to `../../css/core/[name].css` relative to `output.path` (`www/static/js/bundle/`), resolving to `www/static/css/core/`

- [ ] **Step 1: Capture a baseline checksum of the current CSS output**

Run the current build (or use the already-built files if fresh), then save checksums:

```bash
npm run build 2>&1 | tail -5
md5sum www/static/css/core/core.css \
       www/static/css/core/sbx-core.css \
       www/static/css/core/schoolbox-ck-content.css \
       www/static/css/core/learnosity.css \
  | tee /tmp/css-baseline.md5
```

Expected: four md5 hashes printed and saved. If any file is missing, the build hasn't run — fix that before continuing.

- [ ] **Step 2: Add the CssExtractRspackPlugin import to rspack.common.js**

In `rspack.common.js`, after line 9 (`const path = require("path");`), add:

```js
const { CssExtractRspackPlugin } = require("@rspack/core");
```

The top of the file should now look like:

```js
const path = require("path");
const { CssExtractRspackPlugin } = require("@rspack/core");
```

- [ ] **Step 3: Rename the schoolboxCkStyles entry key**

In `rspack.common.js` line 306, change the entry key from `schoolboxCkStyles` to `"schoolbox-ck-content"` so the emitted filename matches the current output:

```js
// Before (line 306)
schoolboxCkStyles: "./www/static/css/core/schoolbox-ck-content.scss",

// After
"schoolbox-ck-content": "./www/static/css/core/schoolbox-ck-content.scss",
```

Context (lines 303–307 after the change):

```js
//Sass (leave at the bottom)
core: "./www/static/css/core/core.scss",
"sbx-core": "./www/static/css/core/sbx-core.scss",
"schoolbox-ck-content": "./www/static/css/core/schoolbox-ck-content.scss",
learnosity: "./www/static/css/learnosity/learnosity.scss",
```

- [ ] **Step 4: Add the plugins array to the shared config**

In `rspack.common.js`, add a `plugins` array after the closing brace of the `module` block (after line 412 `},`) and before the `experiments` block (currently line 414):

```js
    plugins: [
        new CssExtractRspackPlugin({
            filename: "../../css/core/[name].css",
        }),
    ],
    experiments: {
```

The `../../css/core/` path is relative to `output.path` (`www/static/js/bundle/`), which resolves to `www/static/css/core/`.

- [ ] **Step 5: Replace the SCSS pipeline rule**

In `rspack.common.js`, replace lines 388–409 (the non-vue SCSS pipeline) with the following. Remove the `file-loader` and `extract-loader` entries and replace them with `CssExtractRspackPlugin.loader`:

```js
{
    use: [
        CssExtractRspackPlugin.loader,
        {
            loader: "css-loader",
            options: {
                url: false, // don't validate image urls as they error, so we just rely on checking in browser
                modules: false, // disable css modules for all files: https://github.com/css-modules/css-modules
            },
        },
        {
            loader: "sass-loader", // to load the sass and export it to css
        },
    ],
},
```

The full `\.scss$/` rule after this change should look like:

```js
{
    test: /\.scss$/,
    oneOf: [
        // this allows us to only perform the below on vue files, then other scss files follow the following
        {
            resourceQuery: /^\?vue/,
            use: [
                { loader: "vue-style-loader" },
                {
                    loader: "css-loader",
                    options: {
                        url: false, // don't validate image urls as they error, so we trust rely on checking in browser
                    },
                },
                {
                    loader: "sass-loader",
                },
            ],
        },
        {
            use: [
                CssExtractRspackPlugin.loader,
                {
                    loader: "css-loader",
                    options: {
                        url: false, // don't validate image urls as they error, so we just rely on checking in browser
                        modules: false, // disable css modules for all files: https://github.com/css-modules/css-modules
                    },
                },
                {
                    loader: "sass-loader", // to load the sass and export it to css
                },
            ],
        },
    ],
},
```

- [ ] **Step 6: Update the experiments.css comment**

Line 415 currently reads:
```js
css: false, // Leave off for now long term, as our CSS/SCSS is now pretty old, and rspack plans to make this default to true in the future https://rspack.rs/guide/tech/css
```

Update it to:
```js
css: false, // Disabled — using CssExtractRspackPlugin for CSS extraction instead https://rspack.rs/guide/tech/css
```

- [ ] **Step 7: Run the build and verify output**

```bash
npm run build 2>&1 | tail -10
```

Expected: build completes with no errors. If rspack warns about unknown options or loader failures, read the error carefully — the most likely cause is a typo in the plugin config or loader name.

- [ ] **Step 8: Compare CSS output against the baseline**

```bash
md5sum www/static/css/core/core.css \
       www/static/css/core/sbx-core.css \
       www/static/css/core/schoolbox-ck-content.css \
       www/static/css/core/learnosity.css
diff <(cat /tmp/css-baseline.md5) <(md5sum \
       www/static/css/core/core.css \
       www/static/css/core/sbx-core.css \
       www/static/css/core/schoolbox-ck-content.css \
       www/static/css/core/learnosity.css)
```

Expected: `diff` produces no output (files are identical). Minor whitespace or comment differences are acceptable if you can explain them — but any structural CSS diff should be investigated before continuing.

- [ ] **Step 9: Verify empty JS stubs are suppressed**

`RemoveEmptyScriptsPlugin` (in `rspack.dev.js`) should prevent empty JS stubs from SCSS entries appearing in the bundle. Confirm:

```bash
ls www/static/js/bundle/core.js \
   www/static/js/bundle/sbx-core.js \
   www/static/js/bundle/schoolbox-ck-content.js \
   www/static/js/bundle/learnosity.js 2>&1
```

Expected: `No such file or directory` for all four. If they exist and are non-empty, something has changed in how `RemoveEmptyScriptsPlugin` interacts with the new pipeline — investigate before committing.

- [ ] **Step 10: Commit rspack.common.js**

```bash
git add rspack.common.js
git commit -m "Replace extract-loader pipeline with CssExtractRspackPlugin

Removes file-loader and extract-loader from the SCSS extraction pipeline
and replaces them with CssExtractRspackPlugin (bundled in @rspack/core).
Renames the schoolboxCkStyles entry to schoolbox-ck-content so the emitted
filename continues to match the existing output path.

babel-traverse removal follows in the next commit."
```

---

### Task 2: Upgrade rspack to v2 and remove old loader packages

**Files:**
- Modify: `package.json` (devDependencies section)
- Updated automatically: `npm-shrinkwrap.json` (regenerated by `npm install`)

**Interfaces:**
- Consumes: The updated `rspack.common.js` from Task 1 (Task 2 must not be started until Task 1 is committed)
- Produces: `@rspack/core@~2.0.8`, `@rspack/cli@~2.0.8` installed; `extract-loader` and `file-loader` absent from `node_modules`

- [ ] **Step 1: Update package.json devDependencies**

Make these four changes to the `devDependencies` object in `package.json`:

```json
"@rspack/cli": "~2.0.8",
"@rspack/core": "~2.0.8",
```

Remove these two lines entirely:

```json
"extract-loader": "~5.1.0",
"file-loader": "~6.2.0",
```

The `devDependencies` section around these entries should look like (before → after):

```json
// Before
"@rspack/cli": "~1.7.6",
"@rspack/core": "~1.7.6",
...
"extract-loader": "~5.1.0",
"file-loader": "~6.2.0",

// After
"@rspack/cli": "~2.0.8",
"@rspack/core": "~2.0.8",
// (extract-loader and file-loader lines deleted)
```

- [ ] **Step 2: Install updated packages**

```bash
npm install
```

Expected: npm resolves and installs `@rspack/core@2.0.x` and `@rspack/cli@2.0.x`, and removes `extract-loader`, `file-loader`, and their Babel 6 dep tree (`babel-traverse`, `babel-core`, `babel-preset-env`, etc.) from `node_modules`. `npm-shrinkwrap.json` is updated automatically.

If npm warns about peer dependency conflicts around `@rspack/dev-server`, that is expected — the project does not use the rspack dev server, so this warning can be ignored.

- [ ] **Step 3: Run the build on rspack v2**

```bash
npm run build 2>&1 | tail -15
```

Expected: build completes with no errors. rspack v2 may emit additional warnings about deprecated config options — read each one and address if it refers to a config key in `rspack.common.js`, `rspack.dev.js`, or `rspack.prod.js`.

- [ ] **Step 4: Compare CSS output against the Task 1 baseline**

```bash
diff <(cat /tmp/css-baseline.md5) <(md5sum \
       www/static/css/core/core.css \
       www/static/css/core/sbx-core.css \
       www/static/css/core/schoolbox-ck-content.css \
       www/static/css/core/learnosity.css)
```

Expected: no diff output. The CSS files should be identical to what rspack v1 + `CssExtractRspackPlugin` produced in Task 1.

- [ ] **Step 5: Confirm babel-traverse is gone from npm audit**

```bash
npm audit 2>&1 | grep -i "babel-traverse" || echo "babel-traverse: not found (good)"
```

Expected: `babel-traverse: not found (good)`.

If `babel-traverse` still appears, run `npm ls babel-traverse` to find what is still pulling it in and investigate.

- [ ] **Step 6: Run the production build**

```bash
npm run publish 2>&1 | tail -10
```

Expected: production build completes with no errors. Minified CSS files are emitted to `www/static/css/core/`.

- [ ] **Step 7: Commit package.json and npm-shrinkwrap.json**

```bash
git add package.json npm-shrinkwrap.json
git commit -m "Upgrade rspack to v2, remove extract-loader and file-loader

Upgrades @rspack/core and @rspack/cli from ~1.7.6 to ~2.0.8.
Removes extract-loader (~5.1.0) and file-loader (~6.2.0) which are no
longer needed now that CssExtractRspackPlugin handles CSS extraction.

This eliminates the babel-traverse@6.26.0 critical vulnerability
(GHSA-67hx-6x53-jw92) and the entire Babel 6 transitive dep tree."
```

---

## Post-implementation checklist

After both tasks are committed:

- [ ] `npm audit` shows no `babel-traverse` finding
- [ ] All four CSS files exist at `www/static/css/core/` with content matching (or explained diffs from) the baseline
- [ ] No empty JS stubs (`core.js`, `sbx-core.js`, `schoolbox-ck-content.js`, `learnosity.js`) in `www/static/js/bundle/`
- [ ] Browser smoke test: load a page that uses core styles and confirm no visual regressions
- [ ] Browser smoke test: load a page with the CK editor and confirm editor styles render correctly
