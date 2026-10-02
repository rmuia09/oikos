import { useCallback, useEffect, useState } from 'react'

const TOKEN_KEY = 'estate_token'
/* Empty GATSBY_WP_URL means "the site we are served from" — the app and the
   REST API share an origin, which is the case when the app is bundled into a
   WordPress plugin. No CORS, no absolute host baked into the bundle. */
const WP_URL = (process.env.GATSBY_WP_URL || '').replace(/\/+$/, '')
const API_BASE = WP_URL ? `${WP_URL}/wp-json/em/v1` : '/wp-json/em/v1'

export const getToken = () => localStorage.getItem(TOKEN_KEY)
export const setToken = (t) => (t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY))

function url(path) {
  return path.startsWith('/api') ? `${API_BASE}${path.slice(4)}` : path
}

/* Turn raw failures into something a non-technical user can act on. The
   server's own message wins when it is specific; these are the fallbacks. */
const STATUS_MESSAGES = {
  400: 'That request was incomplete. Check the highlighted fields and try again.',
  401: 'Your session has expired. Please sign in again.',
  403: 'You do not have permission to do that. Ask an administrator if you need access.',
  404: 'That record no longer exists. It may have been deleted by someone else.',
  409: 'That conflicts with existing data — perhaps it is already set that way.',
  413: 'That submission is too large to save.',
  422: 'Some of the values entered are not valid. Please review and try again.',
  429: 'Too many requests just now. Wait a few seconds and try again.',
  500: 'The server ran into a problem saving that. Please try again.',
  502: 'The server is not responding properly. It may be restarting.',
  503: 'The service is temporarily unavailable. Try again in a moment.'
}

function friendly(status, serverMessage) {
  if (serverMessage && serverMessage.length > 3) return serverMessage
  return STATUS_MESSAGES[status] || `Something went wrong (HTTP ${status}). Please try again.`
}

async function req(path, opts = {}) {
  const token = getToken()
  const full = url(path)
  let res
  try {
    res = await fetch(full, {
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Basic ${token}` } : {}),
        ...(opts.headers || {})
      },
      ...opts
    })
  } catch (e) {
    const where = WP_URL || 'the server'
    const err = new Error(
      `Cannot reach ${where}. Check that it is running, then try again.`
    )
    err.status = 0
    err.retryable = true
    throw err
  }

  const data = await res.json().catch(() => null)
  if (!res.ok) {
    if (res.status === 401 && !path.endsWith('/auth/login')) {
      setToken(null)
      window.dispatchEvent(new Event('estate:unauthorized'))
    }
    const message = data && (data.message || data.error)
    const err = new Error(friendly(res.status, message))
    err.status = res.status
    /* 5xx and 0 are transient; a 422 will fail again no matter how often we retry. */
    err.retryable = res.status >= 500 || res.status === 429
    /* Field-level validation errors, when the plugin provides them. */
    if (data && data.errors && typeof data.errors === 'object') err.fields = data.errors
    throw err
  }

  /* A successful write invalidates every other window's copy of the data —
     recording a payment must move the dashboard, not just this window. */
  if ((opts.method || 'GET').toUpperCase() !== 'GET') {
    window.dispatchEvent(new Event('estate:data-changed'))
  }

  return data
}

export const api = {
  get: (p) => req(p),
  post: (p, body) => req(p, { method: 'POST', body: JSON.stringify(body) }),
  patch: (p, body) => req(p, { method: 'PATCH', body: JSON.stringify(body) }),
  put: (p, body) => req(p, { method: 'PUT', body: JSON.stringify(body) }),
  del: (p, body) => req(p, { method: 'DELETE', body: body ? JSON.stringify(body) : undefined })
}

export function useResource(path) {
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  /* `silent` refetches in the background without flashing the loading state,
     which is what automatic refreshes want. */
  const refresh = useCallback(async (silent = false) => {
    if (!silent) setLoading(true)
    try {
      setData(await api.get(path))
      setError(null)
    } catch (e) {
      setError(e.message)
    } finally {
      if (!silent) setLoading(false)
    }
  }, [path])

  useEffect(() => { refresh() }, [refresh])

  /* Stay in sync with the rest of the app: refetch when any window writes,
     and when the tab is brought back to the foreground. Debounced so a burst
     of writes causes one refetch, not one per write. */
  useEffect(() => {
    let timer = null
    const schedule = () => {
      clearTimeout(timer)
      timer = setTimeout(() => refresh(true), 250)
    }
    const onVisible = () => {
      if (document.visibilityState === 'visible') schedule()
    }
    window.addEventListener('estate:data-changed', schedule)
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      clearTimeout(timer)
      window.removeEventListener('estate:data-changed', schedule)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [refresh])

  return { data, error, loading, refresh }
}

/**
 * Wraps a mutating call so pages don't each reimplement try/catch, spinner
 * flags, and message extraction.
 *
 *   const { run, busy } = useAction();
 *   await run(() => api.post('/api/units', body), 'Unit saved');
 */
export function useAction() {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [msg, setMsg] = useState(null)

  const run = useCallback(async (fn, successMsg) => {
    setBusy(true)
    setError(null)
    try {
      const result = await fn()
      if (successMsg) setMsg(successMsg)
      return result
    } catch (e) {
      setError(e.message)
      return null
    } finally {
      setBusy(false)
    }
  }, [])

  return { run, busy, error, msg, setError, setMsg }
}