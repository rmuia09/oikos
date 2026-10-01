import { useState } from 'react'
import { useResource } from '../api.js'
import { ksh, fmtDate } from '../format.js'
import ErrorBanner from '../components/ErrorBanner.jsx'

export default function Dashboard({ onNavigate }) {
  const { data, loading, error, refresh } = useResource('/api/dashboard')
  const [payFilter, setPayFilter] = useState('')
  const [renewFilter, setRenewFilter] = useState('')

  if (loading) return <div className="loading">Loading dashboard…</div>
  if (error) return (
    <div className="card">
      <ErrorBanner error={error} onRetry={refresh} busy={loading} />
      <p className="muted">Make sure the server is running on port 4000.</p>
    </div>
  )

  const s = data
  const monthPct = s.expectedMonthly ? Math.round((s.collectedMonth / s.expectedMonthly) * 100) : 0

  const recentPayments = s.recentPayments.filter((p) => {
    if (!payFilter) return true
    const q = payFilter.toLowerCase()
    return `${p.unit_number} ${p.tenant_name || ''} ${p.property_name} ${p.method} ${p.payment_date}`.toLowerCase().includes(q)
  })
  const expiringSoon = s.expiringSoon.filter((l) => {
    if (!renewFilter) return true
    const q = renewFilter.toLowerCase()
    return `${l.unit_number} ${l.tenant_name} ${l.property_name} ${l.end_date}`.toLowerCase().includes(q)
  })

  return (
    <div className="dashboard-page">
      <div className="page-head dashboard-head">
        <span className="dashboard-head-icon" aria-hidden="true">📊</span>
        <div>
          <h2>Dashboard</h2>
          <p className="dashboard-head-sub">Estate management system</p>
        </div>
      </div>

      {/* Six fixed cards, always present, so the row is a clean matrix rather
          than an auto-fit grid that strands the last card on its own line. */}
      <div className="stats kpi">
        <div className="stat">
          <div className="label">Total units</div>
          <div className="value">{s.totalUnits}</div>
          <div className="sub">across the estate</div>
        </div>
        <div className="stat good">
          <div className="label">Occupied</div>
          <div className="value">{s.occupiedUnits}</div>
          <div className="sub">{s.vacantUnits} vacant</div>
        </div>
        <div className="stat accent">
          <div className="label">Expected rent / mo</div>
          <div className="value">{ksh(s.expectedMonthly)}</div>
          <div className="sub">from active leases</div>
        </div>
        <div className="stat">
          <div className="label">Collected</div>
          <div className="value">{ksh(s.collectedMonth)}</div>
          <div className="sub">{monthPct}% of expected</div>
        </div>
        <div className={s.monthOutstanding > 0 ? 'stat warn' : 'stat'}>
          <div className="label">Outstanding</div>
          <div className="value">{ksh(s.monthOutstanding)}</div>
          <div className="sub">
            {s.monthOutstanding > 0 ? 'rent not yet paid' : 'all settled'}
          </div>
        </div>
        <div className="stat caution">
          <div className="label">Pending utilities</div>
          <div className="value">{ksh(s.pendingUtility)}</div>
          <div className="sub">water &amp; electricity</div>
        </div>
      </div>

      <div className="dashboard-split">
        <div className="card">
          <div className="card-title">
            <h3>Recent payments</h3>
            <input className="inp" placeholder="Filter payments…" value={payFilter} onChange={(e) => setPayFilter(e.target.value)} />
            <button className="btn small tinted" onClick={() => onNavigate('payments')}>View all</button>
          </div>
          <div className="tbl-wrap">
            <table>
              <thead><tr><th>Date</th><th>Property</th><th>Unit</th><th>Tenant</th><th>Method</th><th className="num">Amount</th></tr></thead>
              <tbody>
                {recentPayments.map((p) => (
                  <tr key={p.id}>
                    <td data-label="Date">{fmtDate(p.payment_date)}</td>
                    <td data-label="Property">{p.property_name}</td>
                    <td data-label="Unit">{p.unit_number}</td>
                    <td data-label="Tenant">{p.tenant_name || '—'}</td>
                    <td data-label="Method">{p.method}</td>
                    <td data-label="Amount" className="num">{ksh(p.amount)}</td>
                  </tr>
                ))}
              {recentPayments.length === 0 && <tr><td data-label="Date" colSpan="6" className="muted">No recent payments match.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card">
          <div className="card-title">
            <h3>Lease renewals due</h3>
            <input className="inp" placeholder="Filter renewals…" value={renewFilter} onChange={(e) => setRenewFilter(e.target.value)} />
            <button className="btn small secondary" onClick={() => onNavigate('renewals')}>Manage</button>
          </div>
          {expiringSoon.length === 0 ? (
            <p className="muted">{renewFilter ? 'No renewals match your filter.' : 'No leases expiring in the next 60 days.'}</p>
          ) : (
            expiringSoon.map((l) => {
              /* Guard the number so a missing value can never render as
                 "undefinedd left". A zero is not "0d left" — it is due today,
                 which is the distinction that matters to a landlord. */
              const days = Number(l.days_left)
              const known = Number.isFinite(days)
              let tone = 'neutral'
              let text = 'Due'
              if (known && days < 0) { tone = 'danger'; text = 'Overdue' }
              else if (known && days === 0) { tone = 'warn'; text = 'Due today' }
              else if (known && days <= 30) { tone = 'warn'; text = `${days}d left` }
              else if (known) { tone = 'neutral'; text = `${days}d left` }
              return (
                <div className="renewal-line" key={l.id}>
                  <div style={{ flex: 1 }}>
                    <div><strong>{l.unit_number}</strong> <span className="muted">· {l.tenant_name}</span></div>
                    <div className="small muted">Ends {fmtDate(l.end_date)} · {l.property_name}</div>
                  </div>
                  <span className={`badge ${tone}`}>{text}</span>
                  {!l.notice_sent && <span className="badge danger">notice</span>}
                </div>
              )
            })
          )}
        </div>
      </div>
    </div>
  )
}