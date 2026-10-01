import { useEffect, useRef, useState } from 'react'

/* ============================================================
   Row actions menu.

   Tables with 3-5 buttons per row turn into a wall of repeated
   chrome. This collapses them behind a single "…" trigger and a
   popover, so the data stays the loudest thing in the row.
   ============================================================ */

export default function RowMenu({ label = 'Row actions', items }) {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    if (!open) return
    const onDown = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false)
    }
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('pointerdown', onDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('pointerdown', onDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  const usable = items.filter(Boolean)

  return (
    <div className="rowmenu" ref={ref}>
      <button
        className={open ? 'rowmenu-trigger is-open' : 'rowmenu-trigger'}
        onClick={() => setOpen((v) => !v)}
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={open}
        title="More actions"
      >
        <span aria-hidden="true">⋯</span>
      </button>

      {open && (
        <div className="rowmenu-pop" role="menu">
          {usable.map((item) => (
            <button
              key={item.label}
              className={item.tone ? `rowmenu-item tone-${item.tone}` : 'rowmenu-item'}
              role="menuitem"
              disabled={item.disabled}
              title={item.title || ''}
              onClick={() => {
                setOpen(false)
                item.onClick()
              }}
            >
              <span className="rowmenu-item-icon" aria-hidden="true">{item.icon}</span>
              {item.label}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}