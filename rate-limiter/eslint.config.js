'use strict';

const js = require('@eslint/js');

module.exports = [
  js.configs.recommended,
  {
    ignores: ['coverage/**', 'node_modules/**', 'load-test/**'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: require('globals').node,
      sourceType: 'commonjs',
    },
    rules: {
      'no-console': ['warn', { allow: ['warn', 'error'] }],
    },
  },
];
