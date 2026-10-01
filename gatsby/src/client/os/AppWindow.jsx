import { useRef, useCallback, useEffect } from 'react'
import { useWindowManager, SNAP, MIN_W, MIN_H, detectSnap } from './window-manager.jsx'

/* ============================================================
   A single OS window.

   Titlebar drag uses pointer capture so the drag survives the
   cursor leaving the titlebar. The same pointermove stream
   drives edge-hover snap detection, so a single gesture both
   moves the window and previews a snap zone.
   ============================================================ */

export default function AppWindow({ win, app, children }) {
  const wm = useWindowManager()
  const ref = useRef(null)
  const drag = useRef(null)
  const resize = useRef(null)
  const focused = wm.state.focusedId === win.id

  /* ---------- titlebar drag + snap preview ---------- */
  const onTitlePointerDown = useCallback(
    (e) => {
      if (e.button !== 0) return
      // double-click toggles maximize
      if (win.snap === SNAP.maximize) {
        wm.unsnap(win.id)
        return
      }
      e.preventDefault()
      wm.focus(win.id)
      // A snapped window is released from its grid slot on grab, so keep the
      // cursor anchored to the point of the titlebar that was taken hold of.
      if (win.snap) {
        wm.unsnap(win.id)
        drag.current = {
          startX: e.clientX,
          startY: e.clientY,
          fromSnap: true,
          grabX: e.clientX - win.rect.x,
          grabY: e.clientY - win.rect.y,
          moved: false
        }
      } else {
        drag.current = {
          startX: e.clientX,
          startY: e.clientY,
          originX: win.rect.x,
          originY: win.rect.y,
          moved: false
        }
      }
      e.currentTarget.setPointerCapture(e.pointerId)
    },
    [win.id, win.rect.x, win.rect.y, win.snap, wm]
  )

  const onTitlePointerMove = useCallback(
    (e) => {
      const d = drag.current
      if (!d) return
      const dx = e.clientX - d.startX
      const dy = e.clientY - d.startY
      if (!d.moved && Math.abs(dx) < 3 && Math.abs(dy) < 3) return
      d.moved = true

      // dragging a snapped window frees it and keeps the cursor anchored
      const base = d.fromSnap ? { x: e.clientX - d.grabX, y: e.clientY - d.grabY } : { x: d.originX + dx, y: d.originY + dy }
      wm.move(win.id, base.x, base.y)
      wm.setSnapPreview(detectSnap(e.clientX, e.clientY, wm.state.bounds))
    },
    [win.id, wm]
  )

  const onTitlePointerUp = useCallback(
    (e) => {
      const d = drag.current
      drag.current = null
      if (e.currentTarget.hasPointerCapture?.(e.pointerId)) {
        e.currentTarget.releasePointerCapture(e.pointerId)
      }
      if (!d) return
      if (!d.moved) return
      const snap = detectSnap(e.clientX, e.clientY, wm.state.bounds)
      if (snap) wm.applySnap(win.id, snap)
      else wm.setSnapPreview(null)
    },
    [win.id, wm]
  )

  /* ---------- resize (right + bottom edges) ---------- */
  const onResizePointerDown = useCallback(
    (e) => {
      if (e.button !== 0) return
      e.preventDefault()
      e.stopPropagation()
      wm.focus(win.id)
      resize.current = {
        startX: e.clientX,
        startY: e.clientY,
        originW: win.rect.w,
        originH: win.rect.h
      }
      e.currentTarget.setPointerCapture(e.pointerId)
    },
    [win.id, win.rect.w, win.rect.h, wm]
  )

  const onResizePointerMove = useCallback(
    (e) => {
      const r = resize.current
      if (!r) return
      const w = Math.max(MIN_W, Math.min(r.originW + (e.clientX - r.startX), wm.state.bounds.w - win.rect.x))
      const h = Math.max(MIN_H, Math.min(r.originH + (e.clientY - r.startY), wm.state.bounds.h - win.rect.y))
      wm.resize(win.id, { w, h })
    },
    [win.id, win.rect.x, win.rect.y, wm]
  )

  const onResizePointerUp = useCallback((e) => {
    resize.current = null
    if (e.currentTarget.hasPointerCapture?.(e.pointerId)) {
      e.currentTarget.releasePointerCapture(e.pointerId)
    }
  }, [])

  /* keep the window on-canvas when the viewport shrinks */
  useEffect(() => {
    const { bounds } = wm.state
    if (win.rect.x + win.rect.w > bounds.w || win.rect.y + win.rect.h > bounds.h) {
      wm.resize(win.id, {
        x: Math.max(0, Math.min(win.rect.x, bounds.w - MIN_W)),
        y: Math.max(0, Math.min(win.rect.y, bounds.h - MIN_H))
      })
    }
  }, [wm.state.bounds, win.id, win.rect.x, win.rect.y, win.rect.w, win.rect.h, wm])

  if (win.minimized) return null

  const style = {
    left: win.rect.x,
    top: win.rect.y,
    width: win.rect.w,
    height: win.rect.h,
    zIndex: win.z
  }

  /* A chromeless window is a locked page: no titlebar to drag, no resize
     grip, no traffic lights — just the app filling the canvas. */
  const shellClass = [
    'oswin',
    focused ? 'is-focused' : '',
    win.chromeless ? 'is-chromeless' : ''
  ].filter(Boolean).join(' ')

  return (
    <section
      ref={ref}
      className={shellClass}
      style={style}
      onPointerDown={() => wm.focus(win.id)}
      aria-label={win.title}
    >
      {!win.chromeless && (
      <header
        className="oswin-bar"
        onPointerDown={onTitlePointerDown}
        onPointerMove={onTitlePointerMove}
        onPointerUp={onTitlePointerUp}
        onPointerCancel={onTitlePointerUp}
        onDoubleClick={() => (win.snap ? wm.unsnap(win.id) : wm.applySnap(win.id, SNAP.maximize))}
      >
        {/* Words beside the dots, so the meaning is never colour-only. */}
        <div className="oswin-lights" onPointerDown={(e) => e.stopPropagation()} onDoubleClick={(e) => e.stopPropagation()}>
          <button className="light light-close" onClick={() => wm.close(win.id)} title="Close (⌘W)">
            <span className="light-glyph" aria-hidden="true">✕</span>
            <span className="light-word">Close</span>
          </button>
          <button className="light light-min" onClick={() => wm.minimize(win.id)} title="Minimise to dock (⌘M)">
            <span className="light-glyph" aria-hidden="true">–</span>
            <span className="light-word">Minimise</span>
          </button>
          <button
            className="light light-max"
            onClick={() => (win.snap ? wm.unsnap(win.id) : wm.applySnap(win.id, SNAP.maximize))}
            title={win.snap ? 'Restore (double-click title)' : 'Maximise'}
          >
            <span className="light-glyph" aria-hidden="true">{win.snap ? '❐' : '□'}</span>
            <span className="light-word">{win.snap ? 'Restore' : 'Maximise'}</span>
          </button>
        </div>
        <span className="oswin-icon" aria-hidden="true">{win.icon}</span>
        <span className="oswin-title">{win.title}</span>
        <span className="oswin-dots" aria-hidden="true">···</span>
      </header>
      )}

      <div className="oswin-body">{children}</div>

      {!win.chromeless && (
        <span
          className="oswin-resize"
          onPointerDown={onResizePointerDown}
          onPointerMove={onResizePointerMove}
          onPointerUp={onResizePointerUp}
          onPointerCancel={onResizePointerUp}
          aria-hidden="true"
        />
      )}
    </section>
  )
}