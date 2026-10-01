import { WindowProvider } from './os/window-manager.jsx'
import Desktop from './os/Desktop.jsx'
import Login from './pages/Login.jsx'
import ErrorBoundary from './components/ErrorBoundary.jsx'
import { api, getToken, setToken } from './api.js'
import { useEffect, useRef, useState } from 'react'

const IDLE_TIMEOUT_MS = 3 * 60 * 1000

/** Read an emailed reset link from the query string, if present. */
function readResetParams() {
  if (typeof window === 'undefined') return null
  const p = new URLSearchParams(window.location.search)
  const key = p.get('reset')
  const login = p.get('login')
  if (key && login) return { type: 'reset', resetKey: key, login }
  if (p.get('recover') === 'password') return 'forgot'
  return null
}

export default function App() {
  const [user, setUser] = useState(null)
  const [checking, setChecking] = useState(true)
  /* 'forgot' | { type: 'reset', resetKey, login } | null */
  const [resetMode, setResetMode] = useState(() => readResetParams())

  /* An emailed reset link lands on /app?reset=KEY&login=USER. Pick that up on
     mount and on back/forward, then scrub the token from the visible URL. */
  useEffect(() => {
    const sync = () => setResetMode(readResetParams())
    window.addEventListener('popstate', sync)
    return () => window.removeEventListener('popstate', sync)
  }, [])

  /* Leave the reset flow and scrub any reset token from the address bar. */
  const clearReset = () => {
    setResetMode(null)
    if (typeof window !== 'undefined' && window.history?.replaceState) {
      window.history.replaceState({}, '', window.location.pathname)
    }
  }

  /* Enter the "forgot password" screen from the sign-in form. */
  const showForgot = () => setResetMode('forgot')

  useEffect(() => {
    const token = getToken()
    if (!token) { setChecking(false); return }
    api.get('/api/auth/me')
      .then(setUser)
      .catch(() => setToken(null))
      .finally(() => setChecking(false))
  }, [])

  function logout() {
    setToken(null)
    setUser(null)
  }

  const idleRef = useRef(null)

  useEffect(() => {
    if (!user) return
    const lastActive = { at: Date.now() }
    const logoutIfIdle = () => {
      if (Date.now() - lastActive.at >= IDLE_TIMEOUT_MS) logout()
    }
    const reset = () => {
      lastActive.at = Date.now()
      clearTimeout(idleRef.current)
      idleRef.current = setTimeout(logout, IDLE_TIMEOUT_MS)
    }
    const onVisible = () => {
      if (document.visibilityState === 'visible') logoutIfIdle()
    }
    const events = ['pointerdown', 'pointermove', 'keydown', 'touchstart', 'scroll', 'wheel']
    events.forEach((e) => window.addEventListener(e, reset))
    document.addEventListener('visibilitychange', onVisible)
    reset()
    return () => {
      clearTimeout(idleRef.current)
      events.forEach((e) => window.removeEventListener(e, reset))
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [user])

  useEffect(() => {
    const onUnauthorized = () => logout()
    window.addEventListener('estate:unauthorized', onUnauthorized)
    return () => window.removeEventListener('estate:unauthorized', onUnauthorized)
  }, [])

  if (checking) {
    return <div className="login-wrap"><p className="loading">Loading…</p></div>
  }

  /* A reset link takes priority: honour it even if a stale token is present,
     since the whole point is to replace the old credential. */
  if (!user || resetMode) {
    return (
      <Login
        onLogin={setUser}
        resetMode={resetMode}
        onShowForgot={showForgot}
        onShowLogin={clearReset}
      />
    )
  }

  return (
    <WindowProvider>
      <ErrorBoundary>
        <Desktop user={user} onLogout={logout} />
      </ErrorBoundary>
    </WindowProvider>
  )
}