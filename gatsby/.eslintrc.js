/**
 * The client app uses the automatic JSX runtime (React 17+), so `React` does
 * not need to be in scope for JSX. Gatsby's bundled ESLint config still ships
 * the pre-React-17 `react-in-jsx-scope` rule, which fails the dev build with
 * ~1400 false positives.
 */
module.exports = {
  rules: {
    'react/react-in-jsx-scope': 'off',
  },
}