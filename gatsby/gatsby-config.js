/**
 * Gatsby config.
 *
 * Oikos is a client-side React app (WordPress is the data source),
 * so no data graph is needed at build time — GATSBY_WP_URL is read in the
 * browser by src/client/api.js. See .env.example.
 */
module.exports = {
  siteMetadata: {
    title: 'Oikos',
    description: 'Rental, lease and utility management, powered by WordPress',
    siteUrl: process.env.GATSBY_SITE_URL || 'http://localhost:8001',
  },
  /* Serve the app from a subpath (e.g. /oikos) when it is bundled inside a
     WordPress plugin on the same domain. Build with --prefix-paths. */
  pathPrefix: process.env.GATSBY_PATH_PREFIX || '',
  plugins: [],
}