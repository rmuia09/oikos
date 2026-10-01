import { useEffect } from 'react'

export default function Modal({ title, onClose, children, wide, size }) {
  /* Escape closes the dialog, matching the overlay click. */
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const cls = size ? `modal modal-${size}` : 'modal'
  return (
    <div className="overlay" onClick={onClose}>
      <div
        className={cls}
        style={wide ? { maxWidth: 680 } : undefined}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h3>{title}</h3>
          <button className="icon-btn" onClick={onClose} aria-label="Close">×</button>
        </div>
        <div className="modal-body">{children}</div>
      </div>
    </div>
  )
}