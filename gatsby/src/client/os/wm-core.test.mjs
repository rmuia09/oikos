#!/usr/bin/env node
/* Unit tests for the pure window-manager core.
 *
 * Node treats a bare .js file as CommonJS, but wm-core.js is ESM (webpack
 * handles it fine). So we mirror it to a .mjs file in a temp dir and import
 * that. Run with: npm run test:os
 */
import { readFileSync, writeFileSync, mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const dir = mkdtempSync(join(tmpdir(), 'wm-'))
const shim = join(dir, 'wm-core.mjs')
writeFileSync(shim, readFileSync(join(here, 'wm-core.js')))

const { reducer, initialState, snapRect, detectSnap, SNAP } = await import(pathToFileURL(shim).href)

let pass = 0
let fail = 0
const ok = (name, cond, extra = '') => {
  if (cond) pass++
  else {
    fail++
    console.log('FAIL:', name, extra)
  }
}

const bounds = { w: 1440, h: 900 }
let s = { ...initialState, bounds }
const app = (id) => ({ type: 'open', appId: id, title: id, icon: 'x' })

/* ---- open ---- */
s = reducer(s, app('a'))
ok('open creates 1 window', s.windows.length === 1)
ok('open focuses window', s.focusedId === s.windows[0].id)
ok('open assigns z', s.windows[0].z === 11)

const r = s.windows[0].rect
ok('defaultRect w within bounds', r.w <= bounds.w, JSON.stringify(r))
ok('defaultRect h within bounds', r.h <= bounds.h, JSON.stringify(r))
ok('defaultRect on canvas', r.x >= 0 && r.y >= 0 && r.x + r.w <= bounds.w && r.y + r.h <= bounds.h, JSON.stringify(r))

/* ---- cascade ---- */
s = reducer(s, app('b'))
ok(
  'cascade offsets new window',
  s.windows[1].rect.x !== s.windows[0].rect.x || s.windows[1].rect.y !== s.windows[0].rect.y
)

/* ---- focus / z-order ---- */
const firstId = s.windows[0].id
const zBefore = s.windows[0].z
s = reducer(s, { type: 'focus', id: firstId })
ok('focus raises z', s.windows[0].z > zBefore, `${zBefore} -> ${s.windows[0].z}`)
ok('focus sets focusedId', s.focusedId === firstId)

/* ---- minimize / restore ---- */
const topId = s.windows[1].id
s = reducer(s, { type: 'minimize', id: topId })
ok('minimize marks minimized', s.windows.find((w) => w.id === topId).minimized === true)
ok('minimize refocuses survivor', s.focusedId === firstId)

s = reducer(s, { type: 'restore', id: topId })
ok('restore clears minimized', s.windows.find((w) => w.id === topId).minimized === false)
ok('restore focuses', s.focusedId === topId)

/* ---- cycle ---- */
s = reducer(s, { type: 'cycle' })
ok('cycle moves focus', s.focusedId === firstId, s.focusedId)

/* ---- snap + unsnap round-trip ---- */
const origRect = s.windows.find((w) => w.id === firstId).rect
s = reducer(s, { type: 'applySnap', id: firstId, snap: SNAP.maximize })
let w0 = s.windows.find((w) => w.id === firstId)
ok('snap maximize fills desktop', w0.rect.w === bounds.w && w0.rect.h === bounds.h, JSON.stringify(w0.rect))
ok('snap stores prevRect', !!w0.prevRect)
ok('snap keeps original rect', JSON.stringify(w0.prevRect) === JSON.stringify(origRect))

s = reducer(s, { type: 'unsnap', id: firstId })
w0 = s.windows.find((w) => w.id === firstId)
ok('unsnap restores rect', JSON.stringify(w0.rect) === JSON.stringify(origRect), JSON.stringify(w0.rect))
ok('unsnap clears snap flag', w0.snap === null)

/* ---- snap rect geometry ---- */
const L = snapRect(SNAP.left, bounds)
const R = snapRect(SNAP.right, bounds)
ok('left half geometry', L.x === 0 && L.w === 720)
ok('right half geometry', R.x === 720 && R.w === 720)
ok('halves tile the width', L.w + R.w === bounds.w)
const q = snapRect(SNAP.topLeft, bounds)
ok('quadrant geometry', q.w === 720 && q.h === 450)

/* ---- snap detection ---- */
ok('top centre maximizes', detectSnap(720, 5, bounds) === SNAP.maximize)
ok('top-left corner', detectSnap(20, 5, bounds) === SNAP.topLeft)
ok('top-right corner', detectSnap(1420, 5, bounds) === SNAP.topRight)
ok('left edge', detectSnap(3, 400, bounds) === SNAP.left)
ok('right edge', detectSnap(1437, 400, bounds) === SNAP.right)
ok('bottom-left corner', detectSnap(3, 895, bounds) === SNAP.bottomLeft)
ok('bottom-right corner', detectSnap(1437, 895, bounds) === SNAP.bottomRight)
ok('canvas centre has no snap', detectSnap(700, 400, bounds) === null)

/* ---- minimizeAll toggles ---- */
s = reducer(s, { type: 'minimizeAll' })
ok('minimizeAll hides all', s.windows.every((w) => w.minimized === true))
s = reducer(s, { type: 'minimizeAll' })
ok('minimizeAll toggles back', s.windows.every((w) => w.minimized === false))

/* ---- close ---- */
const n = s.windows.length
s = reducer(s, { type: 'close', id: s.windows[0].id })
ok('close removes one', s.windows.length === n - 1)

s = reducer(s, { type: 'closeAll' })
ok('closeAll empties desktop', s.windows.length === 0 && s.focusedId === null)

/* ---- edge cases ---- */
const same = s
ok('unknown action is a no-op', reducer(s, { type: 'nope' }) === same)

s = reducer(s, app('x'))
s = reducer(s, app('x'))
ok('same app can open twice', s.windows.length === 2)
ok('window ids are unique', s.windows[0].id !== s.windows[1].id)

/* ---- chromeless locked page (dashboard) ---- */
let c = { ...initialState, bounds }
c = reducer(c, {
  type: 'open',
  appId: 'dashboard',
  title: 'Dashboard',
  icon: 'x',
  chromeless: true,
  snap: SNAP.maximize
})
const cw = c.windows[0]
ok('chromeless flag is stored', cw.chromeless === true)
ok('chromeless opens maximised', cw.snap === SNAP.maximize)
ok(
  'chromeless fills the canvas',
  cw.rect.w === bounds.w && cw.rect.h === bounds.h,
  JSON.stringify(cw.rect)
)

let plain = { ...initialState, bounds }
plain = reducer(plain, app('units'))
ok('ordinary window is not chromeless', plain.windows[0].chromeless === false)
ok('ordinary window is not snapped', plain.windows[0].snap === null)

/* ---- snapped windows follow the canvas on resize ---- */
const bigger = { w: 1600, h: 1000 }

let rb = { ...initialState, bounds }
rb = reducer(rb, {
  type: 'open',
  appId: 'dashboard',
  title: 'Dashboard',
  icon: 'x',
  chromeless: true,
  snap: SNAP.maximize
})
rb = reducer(rb, { type: 'bounds', bounds: bigger })
ok(
  'maximised window follows a canvas resize',
  rb.windows[0].rect.w === bigger.w && rb.windows[0].rect.h === bigger.h,
  JSON.stringify(rb.windows[0].rect)
)

let rf = { ...initialState, bounds }
rf = reducer(rf, app('units'))
const before = JSON.stringify(rf.windows[0].rect)
rf = reducer(rf, { type: 'bounds', bounds: bigger })
ok('floating window is untouched by a resize', JSON.stringify(rf.windows[0].rect) === before)

console.log(`\n${pass} passed, ${fail} failed`)
process.exit(fail ? 1 : 0)