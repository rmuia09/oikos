import APPS from './apps.js'

/* ============================================================
   Desktop shortcuts.

   Native desktops are not empty — they carry icons you can click
   without opening a launcher first. These sit on the canvas behind
   every window, so they fill the grid when nothing is open and get
   covered naturally when you work.

   Single-click opens, matching the web rather than forcing a
   double-click that most people never discover.
   ============================================================ */

const ICON_IDS = [
  'dashboard',
  'properties',
  'units',
  'tenants',
  'payments',
  'reports',
  'utilities',
  'settings'
]

export default function DesktopIcons({ onOpen, running, focusedId }) {
  const items = ICON_IDS.map((id) => APPS.find((a) => a.id === id)).filter(Boolean)

  return (
    <nav className="os-icons" aria-label="Desktop shortcuts">
      {items.map((app) => {
        const wins = running.filter((w) => w.appId === app.id)
        const isOpen = wins.length > 0
        const isFocused = wins.some((w) => w.id === focusedId)
        const isMinimized = isOpen && wins.every((w) => w.minimized)

        const cls = [
          'os-icon',
          isOpen ? 'is-open' : '',
          isFocused ? 'is-focused' : '',
          isMinimized ? 'is-minimized' : ''
        ].filter(Boolean).join(' ')

        return (
          <button
            key={app.id}
            className={cls}
            onClick={() => onOpen(app.id)}
            title={
              isMinimized
                ? `${app.title} — minimised, click to restore`
                : isOpen
                  ? `${app.title} — click to focus`
                  : `Open ${app.title}`
            }
          >
            <span className="os-icon-art" aria-hidden="true">{app.icon}</span>
            <span className="os-icon-label">{app.title}</span>
            {isOpen && <span className="os-icon-dot" aria-hidden="true" />}
          </button>
        )
      })}
    </nav>
  )
}