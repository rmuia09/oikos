import Modal from './Modal.jsx'

/* ============================================================
   Notice to Quit — a printable template.

   Deliberately a template rather than a generated legal document:
   the correct notice period and prescribed form depend on whether
   the tenancy is residential or commercial, so the landlord fills
   in their details and has it checked.
   ============================================================ */

const fmtLong = (s) =>
  new Date(s + 'T00:00:00').toLocaleDateString('en-GB', {
    day: 'numeric', month: 'long', year: 'numeric'
  })

function addDays(dateStr, days) {
  const d = new Date(dateStr + 'T00:00:00')
  d.setDate(d.getDate() + days)
  return d.toISOString().slice(0, 10)
}

export default function NoticeToQuit({ lease, onClose }) {
  const today = new Date().toISOString().slice(0, 10)
  const days = Number(lease.notice_period_days || 30)
  const vacate = addDays(today, days)

  return (
    <Modal title="Notice to quit" onClose={onClose} size="lg">
      <div className="notice-sheet" id="notice-sheet">
        <h1>Notice to Quit</h1>
        <p className="notice-date">{fmtLong(today)}</p>

        <p>To: <strong>{lease.tenant_name || '________________________'}</strong></p>
        <p>Re: <strong>{lease.unit_number}</strong>{lease.property_name ? `, ${lease.property_name}` : ''}</p>

        <p>
          Take notice that your tenancy of the above premises is hereby terminated,
          and you are required to vacate and deliver up vacant possession on or
          before <strong>{fmtLong(vacate)}</strong> — being not less than {days} days&rsquo;
          notice from the date of this letter.
        </p>

        <p>
          Please return all keys, settle any outstanding rent and utility charges,
          and leave the premises in good order. A final account of your deposit will
          be provided once the premises have been inspected.
        </p>

        <p className="notice-sig">
          ______________________________<br />
          Landlord / authorised agent
        </p>
      </div>

      <div className="notice-actions">
        <button type="button" className="btn secondary" onClick={onClose}>Close</button>
        <button type="button" className="btn" onClick={() => window.print()}>Print</button>
      </div>

      <div className="smtp-note" style={{ marginTop: 14 }}>
        <strong>Template only.</strong> Notice periods and grounds differ by tenancy.
        The Rent Restriction Act (Cap 296) requires one month for controlled
        residential tenancies; the Landlord and Tenant Act (Cap 301) requires two
        months, in a prescribed form, for shops, hotels and catering premises. Add
        your name and address, and have a Kenyan advocate check it before serving.
      </div>
    </Modal>
  )
}