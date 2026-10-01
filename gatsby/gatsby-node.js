/**
 * Ensure the classic JSX transform is not required so the copied client
 * components (which rely on the automatic JSX runtime) build as-is.
 */
exports.onCreateBabelConfig = ({ actions }) => {
  actions.setBabelPlugin({
    name: '@babel/plugin-transform-react-jsx',
    options: { runtime: 'automatic' },
  })
}