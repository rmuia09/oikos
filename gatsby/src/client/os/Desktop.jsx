import { useEffect, useState, useMemo } from 'react'
import { useWindowManager, snapRect, SNAP } from './window-manager.jsx'
import AppWindow from './AppWindow.jsx'
import Dock from './Dock.jsx'
import Launcher from './Launcher.jsx'
import Explorer from './Explorer.jsx'
import DesktopIcons from './DesktopIcons.jsx'
import DesktopWatermark from './DesktopWatermark.jsx'
import APPS from './apps.js'
import Modal from '../components/Modal.jsx'

/* ============================================================
   The desktop: canvas that owns the window layer, the top menu
   bar and the bottom dock.
   ============================================================ */

export default function Desktop({ user, onLogout, children }) {
  const wm = useWindowManager()
  const [launcherOpen, setLauncherOpen] = useState(false)
  const [confirmCloseAll, setConfirmCloseAll] = useState(false)

  // Track the canvas size so snapping and clamping stay correct.
  useEffect(() => {
    const measure = () => {
      const el = document.getElementById('desktop')
      if (!el) return
      wm.setBounds({ w: el.clientWidth, h: el.clientHeight })
    }
    measure()
    window.addEventListener('resize', measure)
    return () => window.removeEventListener('resize', measure)
  }, [wm])

  const appsById = useMemo(() => {
    const map = {}
    for (const a of APPS) map[a.id] = a
    return map
  }, [])

  const openExplorer = () => {
    const existing = wm.state.windows.find((w) => w.appId === 'explorer')
    if (existing) {
      if (existing.minimized) wm.restore(existing.id)
      else wm.focus(existing.id)
    } else {
      wm.open({ id: 'explorer', title: 'Files — Oikos', icon: '🗂' })
    }
  }

  /* Create a new window for an app. Reserved for the one case where a second
     copy is genuinely wanted; every navigation path goes through surfaceApp. */
  const launch = (id) => {
    const app = appsById[id]
    /* Chromeless apps (the dashboard) open locked to the full canvas. */
    if (app) wm.open(app, app.chromeless ? { snap: SNAP.maximize } : undefined)
    setLauncherOpen(false)
  }

  /* Bring an app to the front, or open it if it isn't running. This is what
     every launcher / navigation path uses, so the same app can never be
     stacked by accident. Never minimises — that's the dock's job. */
  const surfaceApp = (id) => {
    const wins = wm.state.windows.filter((w) => w.appId === id)
    if (!wins.length) {
      launch(id)
      return
    }
    const target = wins.find((w) => !w.minimized) || wins[0]
    if (target.minimized) wm.restore(target.id)
    else wm.focus(target.id)
    setLauncherOpen(false)
  }

  /* Dock / desktop-icon behaviour: a second click on the focused app tucks it
     away, which is why this is separate from surfaceApp. */
  const toggleApp = (id) => {
    const wins = wm.state.windows.filter((w) => w.appId === id)
    if (!wins.length) {
      launch(id)
      return
    }
    const target =
      wins.find((w) => w.id === wm.state.focusedId) ||
      wins.find((w) => !w.minimized) ||
      wins[0]
    if (target.minimized) wm.restore(target.id)
    else if (target.id === wm.state.focusedId) wm.minimize(target.id)
    else wm.focus(target.id)
  }

  const windows = wm.state.windows
  const minimized = windows.filter((w) => w.minimized)
  const restoreAll = () => minimized.forEach((w) => wm.restore(w.id))

  /* ---------- keyboard shortcuts ---------- */
  useEffect(() => {
    const onKey = (e) => {
      const mod = e.metaKey || e.ctrlKey

      /* The confirm dialog is modal: it owns Escape and swallows the window
         shortcuts so ⌘W can't close something behind it. */
      if (confirmCloseAll) {
        if (e.key === 'Escape') {
          e.preventDefault()
          setConfirmCloseAll(false)
        }
        return
      }

      const focused = wm.state.focusedId
      const win = wm.state.windows.find((w) => w.id === focused)

      if (e.key === 'Escape') {
        if (launcherOpen) setLauncherOpen(false)
        return
      }
      if (mod && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        setLauncherOpen((v) => !v)
        return
      }
      /* preventDefault must fire whenever we claim the combo, even if there is
         no window to act on — otherwise the browser handles ⌘W itself and
         closes the user's tab. Claim first, then act. */
      if (mod && e.key.toLowerCase() === 'w') {
        e.preventDefault()
        if (win) wm.close(win.id)
        return
      }
      if (mod && e.key.toLowerCase() === 'm') {
        e.preventDefault()
        if (win) wm.minimize(win.id)
        return
      }
      if (e.key === '`' && !mod) {
        e.preventDefault()
        wm.cycle()
      }
    }
    const handler = (e) => onKey(e)
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [wm, launcherOpen, confirmCloseAll])

  const preview = wm.state.snapPreview ? snapRect(wm.state.snapPreview, wm.state.bounds) : null

  /* Nothing on screen but hidden windows is the worst state to be in, so the
     desktop speaks up whenever everything is minimised. */
  const allHidden = windows.length > 0 && windows.every((w) => w.minimized)

  const isAdmin = user.role === 'admin'

  return (
    <div className="os">
      <header className="os-menubar">
        <button className="os-mark" onClick={() => setLauncherOpen(true)} title="Applications (⌘K)">
          <span aria-hidden="true">🏛️</span> <span className="brand-word">Oikos</span>
        </button>

        <nav className="os-menubar-items">
          <button onClick={() => setLauncherOpen(true)}>Apps</button>
          <button onClick={openExplorer}>Files</button>
          {minimized.length > 0 && (
            <button onClick={restoreAll}>Show all ({minimized.length})</button>
          )}
        </nav>

        <div className="os-menubar-right">
          {/* Window-wide actions live on the right, which never shrinks, so
              they can't be scrolled out of view on a narrow window. */}
          <div className="os-window-actions">
            <button
              className="os-window-action os-window-action--min"
              onClick={() => wm.minimizeAll()}
              disabled={windows.length === 0}
              title="Minimise every open window"
            >
              Minimise all
            </button>
            <button
              className="os-window-action os-window-action--close"
              onClick={() => setConfirmCloseAll(true)}
              disabled={windows.length === 0}
              title="Close every open window"
            >
              Close all
            </button>
          </div>
          <span className="os-clock">{new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
          <span className={`badge ${isAdmin ? 'accent' : 'neutral'}`}>{user.role}</span>
          <button className="btn small secondary" onClick={onLogout}>Log out</button>
        </div>
      </header>

      <div className="os-canvas" id="desktop">
        {/* Wallpaper mark: behind the icons, behind every window. */}
        <DesktopWatermark />

        {preview && (
          <div
            className="os-snap-preview"
            style={{ left: preview.x, top: preview.y, width: preview.w, height: preview.h }}
            aria-hidden="true"
          />
        )}

        {/* Shortcuts sit behind every window and give the empty desktop
            something to do besides a centred welcome message. */}
        <DesktopIcons
          onOpen={toggleApp}
          running={windows}
          focusedId={wm.state.focusedId}
        />

        {windows.map((win) => {
          if (win.appId === 'explorer') {
            return (
              <AppWindow key={win.id} win={win} app={win}>
                <Explorer apps={APPS} onOpen={surfaceApp} />
              </AppWindow>
            )
          }
          const app = appsById[win.appId]
          if (!app) return null
          const restricted = app.adminOnly && !isAdmin
          return (
            <AppWindow key={win.id} win={win} app={app}>
              {restricted ? (
                <div className="empty">Admin access required for {win.title}.</div>
              ) : (
                <app.view
                  user={user}
                  onLogout={onLogout}
                  onNavigate={surfaceApp}
                  onLaunch={surfaceApp}
                  openExplorer={openExplorer}
                />
              )}
            </AppWindow>
          )
        })}

        {allHidden ? (
          <div className="os-welcome">
            <div className="os-welcome-card">
              <h1>
                {minimized.length} window{minimized.length > 1 ? 's' : ''} minimised
              </h1>
              <p className="os-welcome-sub">They&rsquo;re still open, just out of sight.</p>
              <div className="os-welcome-actions">
                <button className="btn" onClick={restoreAll}>Show all windows</button>
                <button className="btn secondary" onClick={() => setConfirmCloseAll(true)}>Close all</button>
              </div>
            </div>
          </div>
        ) : windows.length === 0 ? (
          <div className="os-welcome">
            {/* One card holds the identity, the two primary actions, and the
                shortcut legend, so the eye travels a single block instead of
                hopping between floating elements. */}
            <div className="os-welcome-card">
              <h1 className="brand-script os-welcome-logo">Oikos</h1>
              <p className="os-welcome-sub">Rentals, leases &amp; utilities</p>

              <div className="os-welcome-actions">
                <button className="btn" onClick={() => surfaceApp('dashboard')}>Open dashboard</button>
                <button className="btn secondary" onClick={openExplorer}>Browse files</button>
              </div>

              <div className="os-hint">
                <p><kbd>⌘</kbd><kbd>K</kbd> all apps · <kbd>`</kbd> cycle windows · <kbd>⌘</kbd><kbd>W</kbd> close · <kbd>⌘</kbd><kbd>M</kbd> minimise</p>
                <p className="os-hint-sub">Drag a titlebar to move · drag to an edge to snap · double-click a titlebar to fill the screen</p>
              </div>
            </div>
          </div>
        ) : null}
      </div>

      <Dock onOpen={toggleApp} onFiles={openExplorer} />
      {launcherOpen && <Launcher apps={APPS} onPick={surfaceApp} onClose={() => setLauncherOpen(false)} />}

      {confirmCloseAll && (
        <Modal title="Close all windows?" onClose={() => setConfirmCloseAll(false)}>
          <p className="confirm-lead">
            This closes {windows.length} window{windows.length > 1 ? 's' : ''} at once. Any
            unsaved form inside {windows.length > 1 ? 'them' : 'it'} will be lost, and
            reopening an app starts it fresh.
          </p>
          <p className="confirm-hint">
            To keep {windows.length > 1 ? 'them' : 'it'} out of the way without losing anything,
            use <strong>Minimise all</strong> instead.
          </p>
          <div className="modal-foot" style={{ padding: '16px 0 0', border: 0, background: 'transparent' }}>
            <button className="btn secondary" onClick={() => setConfirmCloseAll(false)}>
              Cancel
            </button>
            <button
              className="btn secondary"
              onClick={() => { setConfirmCloseAll(false); wm.minimizeAll() }}
            >
              Minimise instead
            </button>
            <button
              className="btn danger"
              onClick={() => { wm.closeAll(); setConfirmCloseAll(false) }}
            >
              Close all
            </button>
          </div>
        </Modal>
      )}
    </div>
  )
}