/* ============================================================
   Shared shell for every unauthenticated screen — sign in,
   forgot password, reset. One brand panel, one form column, so
   the whole flow feels like a single place.
   ============================================================ */

export default function AuthShell({ children }) {
  return (
    <div className="login-wrap">
      <div className="auth-shell">
        <aside className="auth-aside">
          <div className="auth-aside-brand">
            <span className="brand-script auth-logo">Oikos</span>
            <span className="auth-tag">Household &amp; estate management</span>
          </div>

          <ul className="auth-points">
            <li>Properties &amp; units</li>
            <li>Tenants &amp; leases</li>
            <li>Rent, utilities &amp; reports</li>
          </ul>

          <p className="auth-aside-foot">Runs on WordPress</p>
        </aside>

        <main className="auth-main">
          <div className="auth-main-inner">{children}</div>
        </main>
      </div>
    </div>
  )
}