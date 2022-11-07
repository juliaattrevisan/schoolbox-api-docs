module.exports = {
    extends: [
        'airbnb-base',
        'airbnb-typescript/base',
        'plugin:@typescript-eslint/recommended', //https://typescript-eslint.io/docs/linting/configs#eslint-recommended
        'plugin:@typescript-eslint/recommended-requiring-type-checking', //https://typescript-eslint.io/docs/linting/configs#recommended-requiring-type-checking
        'prettier', //https://typescript-eslint.io/docs/linting/configs#prettier
    ],
    parser: '@typescript-eslint/parser',
    parserOptions: {
        tsconfigRootDir: __dirname,
        project: ['./tsconfig.json'],
        extraFileExtensions: ['.vue'],
    },
    plugins: ['@typescript-eslint'],
    root: true, //limits ESLint to this folder (wont go up parent folders)
}