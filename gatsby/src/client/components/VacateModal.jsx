import { useState } from 'react'
import Modal from './Modal.jsx'
import { api } from '../api.js'
import { ksh } from '../format.js'

/* ============================================================
   Vacate a unit.

   Ending a lease early is a different act from a lease simply
   expiring, so the exit type is explicit. The final account
   (deposit less any early-termination charge and deductions) is
   shown before anything is committed, and terminating an unexpired
   lease without notice demands an explicit override.
   ============================================================ */

const VACATE_TYPES = [
  { value: 'expired', label: 'Expired — reached the lease end date' },
  { value: 'early_termination', label: 'Early termination — tenant left before the end date' },
  { value: 'eviction', label: 'Eviction' },
  { value: 'mutual', label: 'Mutual surrender — both parties agreed' }
]

const today = () => new Date().toISOString().slice(0, 10)

export default function VacateModal({ unit, onClose, onDone }) {
  const [vacateType, setVacateType] = useState('expired')
  const [effective, setEffective] = useState(today())
  const [noticeServed, setNoticeServed] = useState(false)
  const [override, setOverride] = useState(false)
  const [deductions, setDeductions] = useState('0')
  const [notes, setNotes] = useState('')
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  const deposit = Number(unit.deposit_amount || 0)
  const early = vacateType === 'early_termination'
  const fee = early ? Number(unit.early_termination_fee || 0) : 0
  const deduct = Number(deductions || 0)
  const refund = deposit - fee - deduct

  const blocked = early && !noticeServed && !override
  const canSubmit = reason.trim() && !blocked && !busy

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setErr('')
    try {
      await api.post(`/api/units/${unit.id}/vacate`, {
        reason: reason.trim(),
        vacate_type: vacateType,
        effective_date: effective,
        notice_served: noticeServed,
        override,
        deductions: deduct,
        notes
      })
      onDone()
    } catch (x) {
      setErr(x.message)
      setBusy(false)
    }
  }

  return (
    <Modal title={`Vacate ${unit.unit_number}`} onClose={onClose} size="lg">
      <form onSubmit={submit}>
        {err && <div className="error-banner" role="alert">{err}</div>}

        <p className="auth-note">
          <strong>{unit.property_name}</strong>
          {unit.tenant_name ? <> · {unit.tenant_name}</> : null}
          {unit.lease_end ? <> · lease ends {unit.lease_end}</> : null}
        </p>

        <div className="form-grid">
          <div className="field" style={{ gridColumn: '1 / -1' }}>
            <label>How is the lease ending?</label>
            <select value={vacateType} onChange={(e) => setVacateType(e.target.value)}>
              {VACATE_TYPES.map((t) => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>

          <div className="field">
            <label>Effective date</label>
            <input type="date" value={effective} onChange={(e) => setEffective(e.target.value)} />
          </div>
          <div className="field">
            <label>Deductions (amount)</label>
            <input
              type="number" min="0" step="any" inputMode="decimal"
              value={deductions}
              onChange={(e) => setDeductions(e.target.value)}
              placeholder="0"
            />
          </div>
        </div>

        {early && (
          <div className={blocked ? 'exit-warning is-blocked' : 'exit-warning'}>
            <strong>Ending before the lease end date.</strong>
            <p>
              Kenyan law sets notice periods, not penalties — any early-termination
              charge here is contractual. Confirm notice was served, or override.
            </p>
            <label className="exit-check">
              <input type="checkbox" checked={noticeServed} onChange={(e) => setNoticeServed(e.target.checked)} />
              Notice was served ({unit.notice_period_days || 30} days required)
            </label>
            <label className="exit-check">
              <input type="checkbox" checked={override} onChange={(e) => setOverride(e.target.checked)} />
              Override — terminate early without notice (recorded in the audit trail)
            </label>
          </div>
        )}

        <div className="field" style={{ marginTop: 12 }}>
          <label>Deduction notes (optional)</label>
          <input
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="e.g. damaged door, unpaid water bill"
          />
        </div>

        <div className="field" style={{ marginTop: 12 }}>
          <label>Reason for vacating *</label>
          <textarea
            rows={2}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="e.g. tenant relocating, lease expired, eviction for arrears"
            required
          />
        </div>

        <div className="settlement">
          <div className="settlement-row">
            <span>Deposit held</span><span>{ksh(deposit)}</span>
          </div>
          {fee > 0 && (
            <div className="settlement-row is-deduct">
              <span>Early-termination charge</span><span>− {ksh(fee)}</span>
            </div>
          )}
          {deduct > 0 && (
            <div className="settlement-row is-deduct">
              <span>Deductions</span><span>− {ksh(deduct)}</span>
            </div>
          )}
          <div className={refund >= 0 ? 'settlement-row is-total' : 'settlement-row is-total is-owed'}>
            <span>{refund >= 0 ? 'Refund to tenant' : 'Balance due from tenant'}</span>
            <span>{ksh(Math.abs(refund))}</span>
          </div>
        </div>

        <div className="modal-foot" style={{ padding: '16px 0 0', border: 0, background: 'transparent' }}>
          <button type="button" className="btn secondary" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn danger" disabled={!canSubmit}>
            {busy ? 'Vacating…' : early ? 'Terminate lease & vacate' : 'End lease & vacate'}
          </button>
        </div>
      </form>
    </Modal>
  )
}