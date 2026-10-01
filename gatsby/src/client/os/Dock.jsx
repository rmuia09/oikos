import { useWindowManager } from './window-manager.jsx'
import APPS from './apps.js'

/* Bottom dock: launchers, running windows, running indicators.
   Every running window stays listed even when minimised, so nothing
   is ever silently hidden off-canvas.

   Pinned apps get a single button that both launches and controls their
   window. Only unpinned windows (Files, Settings, Users…) get their own
   chip, which is what stopped two "Units" buttons appearing side by side. */

const PINNED = ['dashboard', 'properties', 'units', 'tenants', 'payments', 'reports']

export default function Dock({ onOpen, onFiles }) {
  const wm = useWindowManager()
  const running = wm.state.windows
  const minimized = running.filter((w) => w.minimized)

  const launchers = APPS.filter((a) => PINNED.includes(a.id))

  /* Extra chips, skipping anything already covered by a pinned launcher and
     collapsing repeats of the same app into one chip. */
  const seen = new Set()
  const looseWindows = running.filter((w) => {
    if (PINNED.includes(w.appId) || seen.has(w.appId)) return false
    seen.add(w.appId)
    return true
  })

  const winsFor = (appId) => running.filter((w) => w.appId === appId)
  const focusedApp = running.find((w) => w.id === wm.state.focusedId)?.appId

  /* Launcher click cycles: restore if hidden, minimise if focused, else focus. */
  const clickLauncher = (app) => {
    const wins = winsFor(app.id)
    if (wins.length === 0) return onOpen(app.id)
    const target = wins.find((w) => w.id === wm.state.focusedId) || wins.find((w) => !w.minimized) || wins[0]
    if (target.minimized) wm.restore(target.id)
    else if (focusedApp === app.id) wm.minimize(target.id)
    else wm.focus(target.id)
  }

  const clickWindow = (win) => {
    if (win.minimized) wm.restore(win.id)
    else if (win.id === wm.state.focusedId) wm.minimize(win.id)
    else wm.focus(win.id)
  }

  /* Restore every minimised window — the escape hatch for "where did it go?". */
  const restoreAll = () => minimized.forEach((w) => wm.restore(w.id))

  return (
    <footer className="os-dock" role="toolbar" aria-label="Dock">
      <button className="dock-item dock-files" onClick={onFiles} title="Files">
        <span className="dock-emoji" aria-hidden="true">🗂</span>
        <span className="dock-label">Files</span>
      </button>

      <span className="dock-sep" aria-hidden="true" />

      {launchers.map((app) => {
        const wins = winsFor(app.id)
        const hidden = wins.some((w) => w.minimized)
        const open = wins.length > 0
        const cls = [
          'dock-item',
          open && !hidden ? 'is-running' : '',
          hidden ? 'is-minimized' : '',
          open && !hidden && focusedApp === app.id ? 'is-focused' : ''
        ].filter(Boolean).join(' ')
        return (
          <button
            key={app.id}
            className={cls}
            onClick={() => clickLauncher(app)}
            title={hidden ? `${app.title} — minimised, click to restore` : app.title}
          >
            {open && <span className="dock-dot" aria-hidden="true" />}
            <span className="dock-emoji" aria-hidden="true">{app.icon}</span>
            <span className="dock-label">{app.title}</span>
            {hidden && (
              <span className="dock-flag" aria-label={`${app.title} is minimised`}>
                {wins.filter((w) => w.minimized).length}
              </span>
            )}
          </button>
        )
      })}

      {looseWindows.length > 0 && (
        <>
          <span className="dock-sep" aria-hidden="true" />
          <span className="dock-windows" aria-label="Open windows">
            {looseWindows.map((w) => {
              const cls = w.minimized
                ? 'dock-window is-minimized'
                : w.id === wm.state.focusedId
                  ? 'dock-window is-active'
                  : 'dock-window'
              return (
                <button
                  key={w.id}
                  className={cls}
                  onClick={() => clickWindow(w)}
                  title={w.minimized ? `${w.title} — minimised, click to restore` : w.title}
                >
                  <span className="dock-window-emoji" aria-hidden="true">{w.icon}</span>
                  <span className="dock-window-title">{w.title}</span>
                  {w.minimized && <span className="dock-flag" aria-label="minimised">•</span>}
                </button>
              )
            })}
          </span>
        </>
      )}

      {minimized.length > 0 && (
        <button className="dock-restore-all" onClick={restoreAll}>
          Show all ({minimized.length} hidden)
        </button>
      )}
    </footer>
  )
}