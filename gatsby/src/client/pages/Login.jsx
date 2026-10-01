import { useState } from 'react'
import { api, setToken } from '../api.js'
import { ForgotPassword, ResetPassword } from './PasswordReset.jsx'
import AuthShell from '../components/AuthShell.jsx'

export default function Login({ onLogin, resetMode, onShowForgot, onShowLogin }) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [err, setErr] = useState('')
  const [busy, setBusy] = useState(false)

  /* A completed reset can sign you straight in. */
  const [justReset, setJustReset] = useState(false)

  if (resetMode === 'forgot') {
    return (
      <ForgotPassword
        onBack={onShowLogin}
        onDone={(r) => {
          onShowLogin()
          if (r) {
            setToken(r.token)
            onLogin(r.user)
          } else {
            setJustReset(true)
          }
        }}
      />
    )
  }

  if (resetMode === 'reset') {
    return (
      <ResetPassword
        resetKey={resetMode.resetKey}
        login={resetMode.login}
        onDone={(r) => {
          onShowLogin()
          if (r) {
            setToken(r.token)
            onLogin(r.user)
          } else {
            setJustReset(true)
          }
        }}
      />
    )
  }

  async function submit(e) {
    e.preventDefault()
    setBusy(true); setErr('')
    try {
      const r = await api.post('/api/auth/login', { username, password })
      setToken(r.token)
      onLogin(r.user)
    } catch (x) { setErr(x.message) } finally { setBusy(false) }
  }

  return (
    <AuthShell>
      <h2 className="auth-title">Sign in</h2>
      <p className="auth-sub">Welcome back. Manage your estate from one place.</p>

      <form onSubmit={submit}>
        {err && <div className="error-banner" role="alert">{err}</div>}
        {justReset && (
          <div className="success-banner" role="status">
            Your password has been changed. Sign in with it below.
          </div>
        )}

        <div className="field">
          <label>Username</label>
          <input value={username} onChange={(e) => setUsername(e.target.value)} autoFocus autoComplete="username" />
        </div>
        <div className="field">
          <label>Password</label>
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" />
        </div>

        <div className="auth-form-row">
          <button type="button" className="link-btn" onClick={onShowForgot}>
            Forgot password?
          </button>
        </div>

        <button className="btn btn-block" disabled={busy || !username || !password}>
          {busy ? 'Signing in…' : 'Sign in'}
        </button>

        <p className="muted small login-hint">
          Use your WordPress username and password. An{' '}
          <strong>Application Password</strong> works here too — create one in
          WordPress under <em>Users → Profile → Application Passwords</em>.
        </p>
      </form>
    </AuthShell>
  )
}