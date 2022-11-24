
/**
 * Here we are setting up our eslint configuration
 * https://eslint.org/docs/latest/user-guide/configuring/configuration-files#configuration-file-formats
 *
 * We are currently using the Airbnb config with TypeScript support, and also adding the additional checks typescript-eslint provides.
 * Prettier is added to the end so eslint doesnt report on formatting issues that prettier will automatically fix.
 */
module.exports = {
    extends: [
        'airbnb-base', // https://www.npmjs.com/package/eslint-config-airbnb-base
        'airbnb-typescript/base', // https://www.npmjs.com/package/eslint-config-airbnb-typescript
        'plugin:@typescript-eslint/recommended', // https://typescript-eslint.io/docs/linting/configs#eslint-recommended
        'plugin:@typescript-eslint/recommended-requiring-type-checking', // https://typescript-eslint.io/docs/linting/configs#recommended-requiring-type-checking
        'plugin:jsdoc/recommended', // https://www.npmjs.com/package/eslint-plugin-jsdoc
        'prettier', // https://typescript-eslint.io/docs/linting/configs#prettier
    ],
    parser: '@typescript-eslint/parser', // the parser that runs through our ts files
    parserOptions: {
        project: ['./tsconfig.json'],
    },
    plugins: [
        '@typescript-eslint', // for eslint to be using typescript-eslint
        'eslint-plugin-jsdoc' // to lint jsdocs
    ],
    root: true, // limits ESLint to this folder (wont go up parent folders)
    rules: { // customised rules
        "import/prefer-default-export": "off", // Pinia especially uses a single export
        "@typescript-eslint/no-unsafe-assignment": "off", //
        "@typescript-eslint/no-unsafe-member-access": "off", //
        "@typescript-eslint/no-unsafe-call": "off", //
    },
}