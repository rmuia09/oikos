import { useState } from 'react'
import { api } from '../api.js'
import AuthShell from '../components/AuthShell.jsx'

/* ============================================================
   Forgot password + reset.

   Both screens deliberately say the same thing whether or not an
   account exists, so this form can't be used to enumerate users.
   ============================================================ */

export const PASSWORD_RULES = [
  { label: 'At least 8 characters', test: (v) => v.length >= 8 },
  { label: 'A lowercase letter', test: (v) => /[a-z]/.test(v) },
  { label: 'An uppercase letter', test: (v) => /[A-Z]/.test(v) },
  { label: 'A number', test: (v) => /[0-9]/.test(v) }
]

/** Shared rule checklist so signup, change, and reset read identically. */
export function PasswordRules({ value }) {
  return (
    <ul className="auth-rules">
      {PASSWORD_RULES.map((rule) => {
        const ok = rule.test(value || '')
        const state = !value ? '' : ok ? 'is-ok' : 'is-bad'
        return (
          <li key={rule.label} className={state}>
            <span className="auth-rule-tick" aria-hidden="true">{ok ? '✓' : '•'}</span>
            {rule.label}
          </li>
        )
      })}
    </ul>
  )
}

export function ForgotPassword({ onBack, onDone }) {
  const [identifier, setIdentifier] = useState('')
  const [sent, setSent] = useState(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setErr('')
    try {
      const r = await api.post('/api/auth/forgot-password', { identifier })
      setSent(r)
    } catch (x) {
      setErr(x.message)
    } finally {
      setBusy(false)
    }
  }

  /* SMS is the default channel, so the natural next step is entering the code
     that just arrived — not hunting for an email. */
  if (sent && sent.channel !== 'email') {
    return <ResetWithCode identifier={identifier} sent={sent} onBack={onBack} onDone={onDone} />
  }

  if (sent) {
    return (
      <Shell title="Check your email" onBack={onBack}>
        <p className="auth-note">{sent.message}</p>

        {/* Local dev has no mail server, so the API hands back the link. */}
        {sent.dev_reset_link && (
          <div className="auth-devlink">
            <strong>Local development</strong>
            <p>No mail server here, so use this link directly:</p>
            <a href={sent.dev_reset_link}>{sent.dev_reset_link}</a>
          </div>
        )}

        <p className="auth-note muted">
          Nothing arrived? Check that email sending is configured, or ask an
          administrator to reset it for you.
        </p>
        <div className="auth-actions">
          <button className="btn secondary" onClick={onBack}>Back to sign in</button>
          <button className="btn" onClick={() => { setSent(null); setIdentifier('') }}>
            Send again
          </button>
        </div>
      </Shell>
    )
  }

  return (
    <Shell title="Forgot your password?" onBack={onBack}>
      <p className="auth-note">
        Enter your username or phone number and we&rsquo;ll text you a 6-digit
        code to choose a new password.
      </p>

      <form onSubmit={submit}>
        {err && <div className="error-banner" role="alert">{err}</div>}
        <div className="field">
          <label>Username or phone number</label>
          <input
            value={identifier}
            onChange={(e) => setIdentifier(e.target.value)}
            autoFocus
            autoComplete="username"
          />
        </div>
        <div className="auth-actions">
          <button type="button" className="btn secondary" onClick={onBack}>Cancel</button>
          <button className="btn" disabled={busy || !identifier.trim()}>
            {busy ? 'Sending…' : 'Send reset code'}
          </button>
        </div>
      </form>
    </Shell>
  )
}

/* SMS path: enter the 6-digit code and a new password together. */
export function ResetWithCode({ identifier, sent, onBack, onDone }) {
  const [code, setCode] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  const mismatch = confirm.length > 0 && password !== confirm
  const codeReady = code.replace(/\D/g, '').length === 6
  const canSubmit =
    codeReady && PASSWORD_RULES.every((r) => r.test(password)) && password === confirm && !busy

  async function submit(e) {
    e.preventDefault()
    if (mismatch) { setErr('Those two passwords do not match.'); return }
    setBusy(true)
    setErr('')
    try {
      const r = await api.post('/api/auth/reset-password', {
        identifier,
        code: code.replace(/\D/g, ''),
        password
      })
      onDone(r.token && r.user ? r : null)
    } catch (x) {
      setErr(x.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Shell title="Enter your code" onBack={onBack}>
      <p className="auth-note">
        We sent a 6-digit code to the phone number on{' '}
        <strong>{identifier}</strong>. It expires in 10 minutes.
      </p>

      <form onSubmit={submit}>
        {err && <div className="error-banner" role="alert">{err}</div>}

        {/* Shown only in local dev, where no SMS gateway is configured. */}
        {sent.dev_code && (
          <div className="auth-devlink">
            <strong>Local development</strong>
            <p>
              No SMS gateway here, so your code is{' '}
              <span className="auth-devcode">{sent.dev_code}</span>
              {sent.dev_phone ? <> (would go to {sent.dev_phone})</> : null}.
            </p>
          </div>
        )}

        <div className="field">
          <label>6-digit code</label>
          <input
            value={code}
            onChange={(e) => setCode(e.target.value)}
            autoFocus
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={6}
            placeholder="123456"
            className="auth-code"
          />
        </div>

        <div className="field">
          <label>New password</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="new-password"
          />
        </div>

        <div className="field">
          <label>Confirm new password</label>
          <input
            type="password"
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            autoComplete="new-password"
          />
        </div>

        <PasswordRules value={password} />
        {mismatch && <p className="auth-mismatch">Those two passwords do not match.</p>}

        <div className="auth-actions">
          <button type="button" className="btn secondary" onClick={onBack}>
            Back
          </button>
          <button className="btn" disabled={!canSubmit}>
            {busy ? 'Saving…' : 'Change password'}
          </button>
        </div>
      </form>
    </Shell>
  )
}

export function ResetPassword({ resetKey, login, onDone }) {
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  const mismatch = confirm.length > 0 && password !== confirm
  const canSubmit =
    PASSWORD_RULES.every((r) => r.test(password)) && password === confirm && !busy

  async function submit(e) {
    e.preventDefault()
    if (mismatch) { setErr('Those two passwords do not match.'); return }
    setBusy(true)
    setErr('')
    try {
      const r = await api.post('/api/auth/reset-password', {
        reset_key: resetKey,
        login,
        password
      })
      /* A token comes back when we could start a session immediately,
         otherwise fall back to the sign-in screen. */
      onDone(r.token && r.user ? r : null)
    } catch (x) {
      setErr(x.message)
    } finally {
      setBusy(false)
    }
  }

  if (!resetKey || !login) {
    return (
      <Shell title="Reset link invalid" onBack={() => onDone(null)}>
        <p className="auth-note">
          This link is missing its details. Request a new one and try again.
        </p>
      </Shell>
    )
  }

  return (
    <Shell title="Choose a new password" onBack={() => onDone(null)}>
      <form onSubmit={submit}>
        {err && <div className="error-banner" role="alert">{err}</div>}

        <div className="field">
          <label>New password</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoFocus
            autoComplete="new-password"
          />
        </div>

        <div className="field">
          <label>Confirm new password</label>
          <input
            type="password"
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            autoComplete="new-password"
          />
        </div>

        <PasswordRules value={password} />
        {mismatch && <p className="auth-mismatch">Those two passwords do not match.</p>}

        <div className="auth-actions">
          <button type="button" className="btn secondary" onClick={() => onDone(null)}>
            Cancel
          </button>
          <button className="btn" disabled={!canSubmit}>
            {busy ? 'Saving…' : 'Change password'}
          </button>
        </div>
      </form>
    </Shell>
  )
}

/* Shared chrome so all three auth screens look like one flow. */
function Shell({ title, onBack, children }) {
  return (
    <AuthShell>
      <h2 className="auth-title">{title}</h2>
      {children}
      {onBack && (
        <p className="muted small login-hint">
          <button className="link-btn" onClick={onBack}>← Back to sign in</button>
        </p>
      )}
    </AuthShell>
  )
}