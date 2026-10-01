import { useEffect, useMemo, useRef, useState } from 'react'
import APPS from './apps.js'

/* ⌘K launcher — fuzzy-ish search over the app registry. */

function score(app, q) {
  if (!q) return 1
  const hay = `${app.title} ${app.id} ${app.icon}`.toLowerCase()
  const needle = q.toLowerCase()
  if (app.title.toLowerCase().startsWith(needle)) return 100
  if (hay.includes(needle)) return 50
  // subsequence match, e.g. "rentpay" -> "Rent Payments"
  let i = 0
  for (const ch of hay) {
    if (ch === needle[i]) i++
    if (i === needle.length) return 10
  }
  return 0
}

export default function Launcher({ onPick, onClose }) {
  const [q, setQ] = useState('')
  const [active, setActive] = useState(0)
  const inputRef = useRef(null)

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  const results = useMemo(() => {
    return APPS.map((a) => ({ a, s: score(a, q) }))
      .filter((r) => r.s > 0)
      .sort((x, y) => y.s - x.s)
      .map((r) => r.a)
  }, [q])

  useEffect(() => {
    setActive(0)
  }, [q])

  const commit = (app) => {
    if (app) onPick(app.id)
  }

  const onKeyDown = (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActive((i) => (results.length ? (i + 1) % results.length : 0))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive((i) => (results.length ? (i - 1 + results.length) % results.length : 0))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      commit(results[active])
    }
  }

  return (
    <div className="os-launcher-overlay" onClick={onClose}>
      <div className="os-launcher" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Applications">
        <input
          ref={inputRef}
          className="os-launcher-input"
          placeholder="Search applications…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={onKeyDown}
        />
        <ul className="os-launcher-list">
          {results.length === 0 && <li className="empty">No applications match “{q}”</li>}
          {results.map((app, i) => (
            <li key={app.id}>
              <button
                className={i === active ? 'os-launcher-item is-active' : 'os-launcher-item'}
                onMouseEnter={() => setActive(i)}
                onClick={() => commit(app)}
              >
                <span className="os-launcher-emoji" aria-hidden="true">{app.icon}</span>
                <span className="os-launcher-title">{app.title}</span>
                {app.adminOnly && <span className="badge neutral">admin</span>}
              </button>
            </li>
          ))}
        </ul>
        <div className="os-launcher-foot">
          <span><kbd>↑</kbd><kbd>↓</kbd> move</span>
          <span><kbd>↵</kbd> open</span>
          <span><kbd>esc</kbd> close</span>
        </div>
      </div>
    </div>
  )
}