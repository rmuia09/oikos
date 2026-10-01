/* ============================================================
   Pure window-manager logic.

   Kept free of React and JSX so it can be reasoned about (and
   unit-tested) on its own. `window-manager.jsx` wraps this in a
   reducer + context.
   ============================================================ */

export const MIN_W = 320
export const MIN_H = 240

export const SNAP = {
  maximize: 'maximize',
  left: 'left',
  right: 'right',
  topLeft: 'top-left',
  topRight: 'top-right',
  bottomLeft: 'bottom-left',
  bottomRight: 'bottom-right'
}

let seq = 0
const nextId = () => `w${++seq}`

export function topZ(windows) {
  return windows.reduce((m, w) => Math.max(m, w.z), 10)
}

/** Cascade new windows so they never land exactly on top of each other.
 *  Windows open wide by default: these are data tables, and a narrow window
 *  strands the columns in the middle of a mostly empty desktop. */
export function defaultRect(bounds, index) {
  const w = Math.min(1180, Math.max(MIN_W, bounds.w - 72))
  const h = Math.min(760, Math.max(MIN_H, bounds.h - 84))
  const step = 28
  const slot = index % 5
  return {
    x: Math.max(8, Math.round((bounds.w - w) / 2 + slot * step)),
    y: Math.max(8, Math.round((bounds.h - h) / 2 + slot * step)),
    w,
    h
  }
}

/** Rect for a named snap within the desktop. */
export function snapRect(snap, bounds) {
  const half = Math.round(bounds.w / 2)
  const halfH = Math.round(bounds.h / 2)
  switch (snap) {
    case SNAP.maximize:
      return { x: 0, y: 0, w: bounds.w, h: bounds.h }
    case SNAP.left:
      return { x: 0, y: 0, w: half, h: bounds.h }
    case SNAP.right:
      return { x: bounds.w - half, y: 0, w: half, h: bounds.h }
    case SNAP.topLeft:
      return { x: 0, y: 0, w: half, h: halfH }
    case SNAP.topRight:
      return { x: bounds.w - half, y: 0, w: half, h: halfH }
    case SNAP.bottomLeft:
      return { x: 0, y: bounds.h - halfH, w: half, h: halfH }
    case SNAP.bottomRight:
      return { x: bounds.w - half, y: bounds.h - halfH, w: half, h: halfH }
    default:
      return null
  }
}

/** Which snap zone (if any) the pointer at (px, py) is hovering. */
export const EDGE = 12
export const CORNER = 90

export function detectSnap(px, py, bounds) {
  if (py <= EDGE) {
    if (px <= CORNER) return SNAP.topLeft
    if (px >= bounds.w - CORNER) return SNAP.topRight
    return SNAP.maximize
  }
  if (px <= EDGE) {
    if (py >= bounds.h - CORNER) return SNAP.bottomLeft
    return SNAP.left
  }
  if (px >= bounds.w - EDGE) {
    if (py >= bounds.h - CORNER) return SNAP.bottomRight
    return SNAP.right
  }
  return null
}

export const initialState = {
  windows: [],
  focusedId: null,
  snapPreview: null,
  bounds: { w: 1280, h: 800 },
  cascade: 0
}

export function reducer(state, action) {
  switch (action.type) {
    case 'bounds':
      /* Snapped windows (including the maximised, chromeless dashboard) are
         sized by the canvas, so they must re-derive their rect when it
         changes — otherwise they keep a stale size after a viewport resize. */
      return {
        ...state,
        bounds: action.bounds,
        windows: state.windows.map((w) =>
          w.snap ? { ...w, rect: snapRect(w.snap, action.bounds) } : w
        )
      }

    case 'open': {
      const { appId, title, icon, rect, chromeless, snap } = action
      const z = topZ(state.windows) + 1
      /* A chromeless app opens already snapped to the full canvas and keeps
         that state, so it behaves as a locked page rather than a panel. */
      const initialSnap = snap || null
      const win = {
        id: nextId(),
        appId,
        title,
        icon,
        chromeless: !!chromeless,
        rect:
          rect ||
          (initialSnap
            ? snapRect(initialSnap, state.bounds)
            : defaultRect(state.bounds, state.cascade)),
        prevRect: null,
        snap: initialSnap,
        z,
        minimized: false
      }
      return {
        ...state,
        windows: [...state.windows, win],
        focusedId: win.id,
        cascade: state.cascade + 1,
        snapPreview: null
      }
    }

    case 'focus': {
      const win = state.windows.find((w) => w.id === action.id)
      if (!win) return state
      if (win.z === topZ(state.windows)) return { ...state, focusedId: win.id }
      return {
        ...state,
        focusedId: action.id,
        windows: state.windows.map((w) =>
          w.id === action.id ? { ...w, z: topZ(state.windows) + 1, minimized: false } : w
        )
      }
    }

    case 'close':
      return {
        ...state,
        windows: state.windows.filter((w) => w.id !== action.id),
        focusedId: state.focusedId === action.id ? null : state.focusedId
      }

    case 'minimize': {
      const win = state.windows.find((w) => w.id === action.id)
      if (!win) return state
      const remaining = state.windows.filter((w) => w.id !== action.id)
      const nextFocus = remaining.filter((w) => !w.minimized).sort((a, b) => b.z - a.z)[0]
      return {
        ...state,
        windows: state.windows.map((w) =>
          w.id === action.id ? { ...w, minimized: true } : w
        ),
        focusedId: nextFocus ? nextFocus.id : null
      }
    }

    case 'restore':
      return {
        ...state,
        windows: state.windows.map((w) =>
          w.id === action.id ? { ...w, minimized: false, z: topZ(state.windows) + 1 } : w
        ),
        focusedId: action.id
      }

    case 'minimizeAll': {
      const anyVisible = state.windows.some((w) => !w.minimized)
      return {
        ...state,
        windows: state.windows.map((w) => ({ ...w, minimized: anyVisible })),
        focusedId: anyVisible ? null : state.focusedId
      }
    }

    case 'move':
      return {
        ...state,
        windows: state.windows.map((w) =>
          w.id === action.id ? { ...w, rect: { ...w.rect, x: action.x, y: action.y } } : w
        )
      }

    case 'resize':
      return {
        ...state,
        windows: state.windows.map((w) =>
          w.id === action.id ? { ...w, rect: { ...w.rect, ...action.rect } } : w
        )
      }

    case 'snapPreview':
      return { ...state, snapPreview: action.snap }

    case 'applySnap': {
      const target = snapRect(action.snap, state.bounds)
      if (!target) return state
      return {
        ...state,
        snapPreview: null,
        windows: state.windows.map((w) => {
          if (w.id !== action.id) return w
          return {
            ...w,
            // remember the free-floating rect so unsnapping restores it
            prevRect: w.snap ? w.prevRect : w.rect,
            rect: target,
            snap: action.snap
          }
        })
      }
    }

    case 'unsnap': {
      const win = state.windows.find((w) => w.id === action.id)
      if (!win) return state
      return {
        ...state,
        windows: state.windows.map((w) =>
          w.id === action.id ? { ...w, rect: w.prevRect || w.rect, snap: null, prevRect: null } : w
        )
      }
    }

    case 'cycle': {
      const visible = state.windows.filter((w) => !w.minimized)
      if (visible.length < 2) return state
      const ordered = visible.sort((a, b) => b.z - a.z)
      const next = ordered[ordered.length - 1]
      return {
        ...state,
        focusedId: next.id,
        windows: state.windows.map((w) =>
          w.id === next.id ? { ...w, z: topZ(state.windows) + 1 } : w
        )
      }
    }

    case 'closeAll':
      return { ...state, windows: [], focusedId: null, snapPreview: null }

    default:
      return state
  }
}