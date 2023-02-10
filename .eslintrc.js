/**
 * Here we are setting up our eslint configuration
 * https://eslint.org/docs/latest/user-guide/configuring/configuration-files#configuration-file-formats
 *
 * We are currently using the Airbnb config with TypeScript support, and also adding the additional checks typescript-eslint provides.
 * Prettier is added to the end so eslint doesnt report on formatting issues that prettier will automatically fix.
 */
module.exports = {
    extends: [
        "airbnb-base", // https://www.npmjs.com/package/eslint-config-airbnb-base
        "airbnb-typescript/base", // https://www.npmjs.com/package/eslint-config-airbnb-typescript
        "plugin:@typescript-eslint/recommended", // https://typescript-eslint.io/docs/linting/configs#eslint-recommended
        "plugin:@typescript-eslint/recommended-requiring-type-checking", // https://typescript-eslint.io/docs/linting/configs#recommended-requiring-type-checking
        "plugin:jsdoc/recommended", // https://www.npmjs.com/package/eslint-plugin-jsdoc
        "plugin:vue/vue3-recommended", // https://eslint.vuejs.org/user-guide/
        "prettier", // https://typescript-eslint.io/docs/linting/configs#prettier
    ],
    parser: "vue-eslint-parser", // to parse .vue files: https://eslint.vuejs.org/user-guide/#how-to-use-a-custom-parser
    parserOptions: {
        extraFileExtensions: [".vue"],
        parser: "@typescript-eslint/parser", // the parser that runs through our ts files
        project: "./tsconfig.json",
        tsconfigRootDir: __dirname,
    },
    plugins: [
        "@typescript-eslint", // for eslint to be using typescript-eslint
        "eslint-plugin-jsdoc", // to lint jsdocs
    ],
    root: true, // limits ESLint to this folder (wont go up parent folders)
    rules: {
        // customised rules
        "import/prefer-default-export": "off", // as Pinia especially uses single exports
        "no-param-reassign": [2, { props: false }], // allow modifying props of parameters, especially valid when passing DOM elements, as we don"t really want to be cloning DOM elements
        "arrow-body-style": "off", // this can be at the dev discretion instead: https://eslint.org/docs/latest/rules/arrow-body-style
        "no-underscore-dangle": "off", // PHP will pass computed properties down with the _ identifier prepended: https://eslint.org/docs/latest/rules/no-underscore-dangle
    },
};
