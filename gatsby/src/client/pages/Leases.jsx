import { useEffect, useMemo, useState } from 'react'
import { api } from '../api.js'
import { ksh, fmtDate } from '../format.js'
import ErrorBanner from '../components/ErrorBanner.jsx'
import RowMenu from '../components/RowMenu.jsx'
import VacateModal from '../components/VacateModal.jsx'

/* ============================================================
   Leases — the estate's lease history.

   Every lease ever created, across all years: active, expired,
   renewed, terminated. Renewing creates a new lease row, so a
   unit's tenancies read as a timeline year by year rather than a
   single record that silently overwrites the last.
   ============================================================ */

const TONE = {
  active: 'ok',
  expired: 'danger',
  renewed: 'neutral',
  terminated: 'neutral'
}

export default function Leases({ params }) {
  const [rows, setRows] = useState(null)
  const [props, setProps] = useState([])
  const [year, setYear] = useState('')
  const [propertyId, setPropertyId] = useState('')
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState(params?.search || '')
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState('')
  const [msg, setMsg] = useState('')
  const [vacate, setVacate] = useState(null)
  const [busy, setBusy] = useState(false)

  async function load() {
    setLoading(true)
    setErr('')
    try {
      const p = new URLSearchParams()
      if (year) p.set('year', year)
      if (propertyId) p.set('property_id', propertyId)
      if (status) p.set('status', status)
      setRows(await api.get(`/api/leases/history?${p}`))
    } catch (x) {
      setErr(x.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { api.get('/api/properties').then(setProps).catch(() => {}) }, [])
  useEffect(() => { load() }, [year, propertyId, status])

  /* A dashboard row can target this window at a specific unit or tenant. */
  useEffect(() => {
    if (params && 'search' in params) setSearch(params.search || '')
  }, [params])

  const shown = useMemo(() => {
    if (!search) return rows || []
    const q = search.toLowerCase()
    return (rows || []).filter((l) =>
      `${l.unit_number} ${l.tenant_name || ''} ${l.property_name || ''}`.toLowerCase().includes(q)
    )
  }, [rows, search])

  const years = useMemo(() => {
    const set = new Set([String(new Date().getFullYear())])
    ;(rows || []).forEach((r) => {
      if (r.start_date) set.add(String(r.start_date).slice(0, 4))
      if (r.end_date) set.add(String(r.end_date).slice(0, 4))
    })
    return [...set].sort().reverse()
  }, [rows])

  const summary = useMemo(() => {
    const out = { active: 0, expired: 0 }
    ;(rows || []).forEach((r) => {
      if (r.effective_status === 'active') out.active++
      if (r.effective_status === 'expired') out.expired++
    })
    return out
  }, [rows])

  async function renew(l) {
    setBusy(true)
    setErr('')
    try {
      const r = await api.post(`/api/leases/${l.id}/renew`, {})
      setMsg(`Renewed ${l.unit_number} at ${ksh(r.new_rent)}/mo from ${fmtDate(r.start_date)}`)
      await load()
    } catch (x) {
      setErr(x.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h2>Leases</h2>
          <p>Every lease on record, across all years — active, expired, renewed and terminated</p>
        </div>
      </div>

      {msg && <div className="success-banner">{msg}</div>}
      {err && <ErrorBanner error={err} onRetry={load} busy={loading} />}

      <div className="card">
        <div className="filters" style={{ marginBottom: 4 }}>
          <div className="field">
            <label>Year</label>
            <select value={year} onChange={(e) => setYear(e.target.value)}>
              <option value="">All years</option>
              {years.map((y) => <option key={y} value={y}>{y}</option>)}
            </select>
          </div>
          <div className="field">
            <label>Property</label>
            <select value={propertyId} onChange={(e) => setPropertyId(e.target.value)}>
              <option value="">All properties</option>
              {props.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
          <div className="field">
            <label>Status</label>
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">Any status</option>
              <option value="active">Active</option>
              <option value="renewed">Renewed</option>
              <option value="terminated">Terminated</option>
            </select>
          </div>
          <div className="field">
            <label>Search</label>
            <input
              className="inp"
              placeholder="Unit, tenant or property…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <span className="muted small" style={{ marginLeft: 'auto' }}>
            {shown ? `${shown.length} lease(s)` : ''}
            {summary.expired > 0 ? ` · ${summary.expired} expired` : ''}
          </span>
        </div>
      </div>

      {loading && <div className="loading">Loading leases…</div>}

      {!loading && rows && (
        <div className="card">
          <div className="tbl-wrap">
            <table>
              <thead>
                <tr>
                  <th>Unit</th><th>Property</th><th>Tenant</th>
                  <th>From</th><th>To</th>
                  <th className="num">Rent / mo</th><th>Status</th><th></th>
                </tr>
              </thead>
              <tbody>
                {shown.map((l) => (
                  <tr key={l.id}>
                    <td data-label="Unit"><strong>{l.unit_number}</strong></td>
                    <td data-label="Property" className="muted">{l.property_name}</td>
                    <td data-label="Tenant">{l.tenant_name}</td>
                    <td data-label="From" className="small">{fmtDate(l.start_date)}</td>
                    <td data-label="To" className="small">{fmtDate(l.end_date)}</td>
                    <td data-label="Rent / mo" className="num">{ksh(l.monthly_rent)}</td>
                    <td data-label="Status">
                      <span className={`badge ${TONE[l.effective_status] || 'neutral'}`}>
                        {l.effective_status}
                      </span>
                    </td>
                    <td data-label="" className="row-actions">
                      <RowMenu label={`Actions for lease ${l.id}`} items={[
                        (l.effective_status === 'active' || l.effective_status === 'expired') && {
                          label: 'Renew +10%', icon: '🔄', disabled: busy, onClick: () => renew(l)
                        },
                        l.effective_status === 'active' && {
                          label: 'Vacate', icon: '🔑', tone: 'warn', onClick: () => setVacate(l)
                        }
                      ]} />
                    </td>
                  </tr>
                ))}
                {shown.length === 0 && (
                  <tr><td colSpan="8" className="muted">No leases match these filters.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {vacate && (
        <VacateModal
          unit={{
            id: vacate.unit_id,
            unit_number: vacate.unit_number,
            property_name: vacate.property_name,
            tenant_name: vacate.tenant_name,
            lease_end: vacate.end_date,
            deposit_amount: vacate.deposit_amount,
            early_termination_fee: vacate.early_termination_fee,
            notice_period_days: vacate.notice_period_days
          }}
          onClose={() => setVacate(null)}
          onDone={() => {
            setMsg(`Unit ${vacate.unit_number} vacated`)
            setVacate(null)
            load()
          }}
        />
      )}
    </div>
  )
}