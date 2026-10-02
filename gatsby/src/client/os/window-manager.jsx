import { createContext, useContext, useReducer, useCallback } from 'react'
import {
  reducer,
  initialState,
  snapRect,
  MIN_W,
  MIN_H
} from './wm-core.js'

/* React binding around the pure window-manager core (wm-core.js). */

const WindowCtx = createContext(null)

export { SNAP, MIN_W, MIN_H, snapRect, detectSnap, defaultRect, EDGE, CORNER } from './wm-core.js'

export function WindowProvider({ children }) {
  const [state, dispatch] = useReducer(reducer, initialState)

  const api = {
    state,
    open: useCallback(
      (app, opts = {}) =>
        dispatch({
          type: 'open',
          appId: app.id,
          title: app.title,
          icon: app.icon,
          chromeless: !!app.chromeless,
          snap: opts.snap || null,
          params: opts.params || null
        }),
      []
    ),
    setParams: useCallback((id, params) => dispatch({ type: 'params', id, params }), []),
    focus: useCallback((id) => dispatch({ type: 'focus', id }), []),
    close: useCallback((id) => dispatch({ type: 'close', id }), []),
    minimize: useCallback((id) => dispatch({ type: 'minimize', id }), []),
    restore: useCallback((id) => dispatch({ type: 'restore', id }), []),
    minimizeAll: useCallback(() => dispatch({ type: 'minimizeAll' }), []),
    cycle: useCallback(() => dispatch({ type: 'cycle' }), []),
    closeAll: useCallback(() => dispatch({ type: 'closeAll' }), []),
    setBounds: useCallback((bounds) => dispatch({ type: 'bounds', bounds }), []),
    move: useCallback((id, x, y) => dispatch({ type: 'move', id, x, y }), []),
    resize: useCallback((id, rect) => dispatch({ type: 'resize', id, rect }), []),
    setSnapPreview: useCallback((snap) => dispatch({ type: 'snapPreview', snap }), []),
    applySnap: useCallback((id, snap) => dispatch({ type: 'applySnap', id, snap }), []),
    unsnap: useCallback((id) => dispatch({ type: 'unsnap', id }), [])
  }

  return <WindowCtx.Provider value={api}>{children}</WindowCtx.Provider>
}

export function useWindowManager() {
  const ctx = useContext(WindowCtx)
  if (!ctx) throw new Error('useWindowManager must be used inside <WindowProvider>')
  return ctx
}