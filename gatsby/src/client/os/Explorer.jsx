import { useEffect, useState } from 'react'
import { api, useResource } from '../api.js'
import APPS from './apps.js'

/* ============================================================
   Files — a two-pane explorer over the estate.

   Left: properties (folders). Right: that property's units.
   Clicking a unit opens the matching app window, which is what
   makes the OS metaphor do real work rather than decoration.
   ============================================================ */

export default function Explorer({ onOpen }) {
  const { data: props, loading, error } = useResource('/api/properties')
  const [sel, setSel] = useState(null)
  const [units, setUnits] = useState(null)
  const [unitsLoading, setUnitsLoading] = useState(false)

  useEffect(() => {
    if (!props || !props.length) return
    if (sel && props.some((p) => String(p.id) === String(sel))) return
    setSel(props[0].id)
  }, [props, sel])

  useEffect(() => {
    if (!sel) return
    let live = true
    setUnitsLoading(true)
    api
      .get('/api/units')
      .then((d) => {
        if (!live) return
        const all = Array.isArray(d) ? d : d?.items || []
        // /units has no property filter server-side, so narrow it here.
        setUnits(all.filter((u) => String(u.property_id) === String(sel)))
      })
      .catch(() => {
        if (live) setUnits([])
      })
      .finally(() => {
        if (live) setUnitsLoading(false)
      })
    return () => {
      live = false
    }
  }, [sel])

  const selected = (props || []).find((p) => String(p.id) === String(sel))
  const occupied = (units || []).filter((u) => u.status === 'occupied' || u.occupied).length

  if (error) return <div className="empty">{error}</div>
  if (loading) return <div className="loading">Reading estate…</div>

  return (
    <div className="os-explorer">
      <aside className="os-explorer-side">
        <div className="os-explorer-head">
          <span>Properties</span>
          <span className="mono muted">{(props || []).length}</span>
        </div>
        <ul className="os-explorer-list">
          {(props || []).map((p) => (
            <li key={p.id}>
              <button
                className={String(p.id) === String(sel) ? 'os-explorer-row is-sel' : 'os-explorer-row'}
                onClick={() => setSel(p.id)}
              >
                <span aria-hidden="true">📁</span>
                <span className="os-explorer-name">{p.name}</span>
                <span className="mono muted small">{p.occupied_count}/{p.unit_count}</span>
              </button>
            </li>
          ))}
          {!(props || []).length && <li className="empty">No properties yet.</li>}
        </ul>

        <div className="os-explorer-head os-explorer-head-sub">
          <span>Open an app</span>
        </div>
        <ul className="os-explorer-list">
          {APPS.slice(0, 8).map((a) => (
            <li key={a.id}>
              <button className="os-explorer-row" onClick={() => onOpen(a.id)}>
                <span aria-hidden="true">{a.icon}</span>
                <span className="os-explorer-name">{a.title}</span>
              </button>
            </li>
          ))}
        </ul>
      </aside>

      <section className="os-explorer-main">
        {!selected ? (
          <div className="empty">Select a property</div>
        ) : (
          <>
            <header className="os-explorer-title">
              <div>
                <h3>{selected.name}</h3>
                <p className="muted small">{selected.location || 'No location set'}</p>
              </div>
              <div className="os-explorer-meta">
                <span className="badge accent">{occupied} occupied</span>
                <span className="badge neutral">{(units || []).length} units</span>
              </div>
            </header>

            {unitsLoading ? (
              <div className="loading">Opening…</div>
            ) : (
              <ul className="os-file-grid">
                {(units || []).map((u) => {
                  const occ = u.status === 'occupied' || u.occupied
                  return (
                    <li key={u.id}>
                      <button
                        className="os-file"
                        onClick={() => onOpen(u.status === 'occupied' || u.occupied ? 'tenants' : 'units')}
                      >
                        <span className="os-file-icon" aria-hidden="true">{occ ? '📄' : '🗋'}</span>
                        <span className="os-file-name">
                          {u.unit_number || u.label || `Unit ${u.id}`}
                        </span>
                        <span className={occ ? 'badge ok' : 'badge warn'}>
                          {occ ? (u.tenant_name || 'Occupied') : 'Vacant'}
                        </span>
                      </button>
                    </li>
                  )
                })}
                {!(units || []).length && <li className="empty">No units in this property.</li>}
              </ul>
            )}
          </>
        )}
      </section>
    </div>
  )
}