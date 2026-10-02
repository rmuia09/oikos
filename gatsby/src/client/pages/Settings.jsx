import { useEffect, useState } from 'react'
import { api } from '../api.js'
import ErrorBanner from '../components/ErrorBanner.jsx'

export default function Settings() {
  const [ready, setReady] = useState(false)
  const [reminder, setReminder] = useState(2)
  const [rates, setRates] = useState({ water: 0, electricity: 0 })
  const [countryCode, setCountryCode] = useState('254')
  const [tenancy, setTenancy] = useState({
    notice_days: 30,
    deposit_months: 1,
    fee_months: 0,
    require_notice: true
  })
  const [sms, setSms] = useState({
    enabled: false, provider: 'africas_talking',
    username: '', api_key: '', from: '', sandbox: true, api_key_set: false,
    device_id: '', base_url: '', generic_url: '', generic_auth: '', generic_body: ''
  })
  const [email, setEmail] = useState({ enabled: false, host: '', port: 587, user: '', pass: '', from: '', pass_set: false, app_url: '' })
  const [test, setTest] = useState({ channel: 'sms', recipient: '', result: null })
  const [msg, setMsg] = useState('')
  const [err, setErr] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api.get('/api/settings').then((s) => {
      setReminder(s.renewal_reminder_months || 2)
      setCountryCode(s.phone_country_code || '254')
      setRates(s.rates || { water: 0, electricity: 0 })
      if (s.tenancy) {
        setTenancy({
          notice_days: s.tenancy.default_notice_period_days ?? 30,
          deposit_months: s.tenancy.default_deposit_months ?? 1,
          fee_months: s.tenancy.default_early_termination_fee_months ?? 0,
          require_notice: s.tenancy.require_notice_before_vacate !== false
        })
      }
      setSms({
        enabled: !!s.sms.enabled,
        provider: s.sms.provider || 'africas_talking',
        username: s.sms.username || '',
        api_key: '',
        from: s.sms.from || '',
        sandbox: !!s.sms.sandbox,
        api_key_set: !!s.sms.api_key_set,
        device_id: s.sms.device_id || '',
        base_url: s.sms.base_url || '',
        generic_url: s.sms.generic_url || '',
        generic_auth: s.sms.generic_auth || '',
        generic_body: s.sms.generic_body || ''
      })
      setEmail({ enabled: !!s.email.enabled, host: s.email.host || '', port: s.email.port || 587, user: s.email.user || '', pass: '', from: s.email.from || '', pass_set: !!s.email.pass_set, app_url: s.email.app_url || '' })
      setReady(true)
    }).catch((e) => setErr(e.message))
  }, [])

  async function save() {
    setBusy(true); setErr('')
    try {
      await api.put('/api/settings', {
        renewal_reminder_months: Number(reminder),
        phone_country_code: countryCode,
        tenancy: {
          default_notice_period_days: Number(tenancy.notice_days),
          default_deposit_months: Number(tenancy.deposit_months),
          default_early_termination_fee_months: Number(tenancy.fee_months),
          require_notice_before_vacate: tenancy.require_notice
        },
        rates: { water: Number(rates.water), electricity: Number(rates.electricity) },
        sms: {
          enabled: sms.enabled, provider: sms.provider, username: sms.username,
          from: sms.from, sandbox: sms.sandbox,
          api_key: sms.api_key, // empty = keep existing
          device_id: sms.device_id, base_url: sms.base_url,
          generic_url: sms.generic_url, generic_auth: sms.generic_auth, generic_body: sms.generic_body
        },
        email: {
          enabled: email.enabled, host: email.host, port: Number(email.port), user: email.user,
          from: email.from, pass: email.pass, // empty = keep existing
          app_url: email.app_url
        }
      })
      setMsg('Settings saved')
      setTest({ ...test, result: null })
      setTimeout(() => setMsg(''), 3000)
    } catch (x) { setErr(x.message) } finally { setBusy(false) }
  }

  async function sendTest(e) {
    e.preventDefault()
    setBusy(true); setErr('')
    try {
      const r = await api.post('/api/integrations/test', { channel: test.channel, recipient: test.recipient.trim() })
      setTest({ ...test, result: r })
    } catch (x) { setErr(x.message) } finally { setBusy(false) }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h2>Settings &amp; integrations</h2>
          <p>Renewal timing, utility rates and notification delivery (SMS / email)</p>
        </div>
        <button className="btn" disabled={!ready || busy} onClick={save}>{busy ? 'Saving…' : 'Save settings'}</button>
      </div>

      {msg && <div className="success-banner">{msg}</div>}
      {err && <ErrorBanner error={err} />}

      {!ready && <div className="loading">Loading settings…</div>}

      {ready && (
        <div className="settings-grid">
          <div className="section-card">
            <h3>Lease renewals</h3>
            <label className="field">
              <span>Send expiry reminders this many months ahead</span>
              <input type="number" min="1" value={reminder} onChange={(e) => setReminder(e.target.value)} />
            </label>
            <p className="muted small">Automated reminders run on server start and every 6 hours, and manually from the Notifications page.</p>
          </div>

          <div className="section-card">
            <h3>Tenancy exit policy</h3>
            <p className="muted small" style={{ marginBottom: 12 }}>
              Defaults copied onto every new lease. Each lease can override them.
            </p>
            <div className="form-grid">
              <div className="field">
                <label>Notice period (days)</label>
                <input
                  type="number" min="0"
                  value={tenancy.notice_days}
                  onChange={(e) => setTenancy({ ...tenancy, notice_days: e.target.value })}
                />
                <span className="muted small">30 days matches Cap 296 for controlled residential; 60 for commercial (Cap 301).</span>
              </div>
              <div className="field">
                <label>Deposit (months of rent)</label>
                <input
                  type="number" min="0" step="0.5"
                  value={tenancy.deposit_months}
                  onChange={(e) => setTenancy({ ...tenancy, deposit_months: e.target.value })}
                />
              </div>
              <div className="field">
                <label>Early-termination charge (months of rent)</label>
                <input
                  type="number" min="0" step="0.5"
                  value={tenancy.fee_months}
                  onChange={(e) => setTenancy({ ...tenancy, fee_months: e.target.value })}
                />
                <span className="muted small">Charged only when a tenant ends the lease before its end date.</span>
              </div>
            </div>
            <div className="switch-row" style={{ marginTop: 12 }}>
              <label className="switch">
                <input
                  type="checkbox"
                  checked={tenancy.require_notice}
                  onChange={(e) => setTenancy({ ...tenancy, require_notice: e.target.checked })}
                />
                <span className="slider" />
              </label>
              <span className="small">Require notice, or an explicit override, before vacating an unexpired lease</span>
            </div>
            <div className="smtp-note" style={{ marginTop: 14 }}>
              <strong>Not legal advice.</strong> Kenyan law sets notice periods, not penalties.
              The Rent Restriction Act (Cap 296) requires one month for controlled residential
              tenancies; the Landlord and Tenant Act (Cap 301) requires two months for shops,
              hotels and catering premises. Any early-termination charge must be a genuine
              pre-estimate of loss written into the lease, or a court may strike it out as a
              penalty. Have a Kenyan advocate review your lease template.
            </div>
          </div>

          <div className="section-card">
            <h3>Utility rates (KES per unit)</h3>
            <div className="form-grid">
              <div className="field">
                <label>Water</label>
                <input type="number" min="0" step="any" value={rates.water} onChange={(e) => setRates({ ...rates, water: e.target.value })} />
              </div>
              <div className="field">
                <label>Electricity</label>
                <input type="number" min="0" step="any" value={rates.electricity} onChange={(e) => setRates({ ...rates, electricity: e.target.value })} />
              </div>
            </div>
          </div>

          <div className="section-card">
            <h3>SMS</h3>
            <div className="switch-row">
              <label className="switch">
                <input type="checkbox" checked={sms.enabled} onChange={(e) => setSms({ ...sms, enabled: e.target.checked })} />
                <span className="slider" />
              </label>
              <span className="small">Send SMS notifications</span>
            </div>
            <div className="form-grid">
              <div className="field">
                <label>Gateway</label>
                <select value={sms.provider} onChange={(e) => setSms({ ...sms, provider: e.target.value })}>
                  <option value="africas_talking">Africa&rsquo;s Talking (hosted)</option>
                  <option value="sms_gate">SMS Gate for Android</option>
                  <option value="textbee">textbee (Android)</option>
                  <option value="generic">Generic HTTP</option>
                </select>
              </div>
              <div className="field">
                <label>Default country code</label>
                <input
                  value={countryCode}
                  onChange={(e) => setCountryCode(e.target.value)}
                  placeholder="254"
                  inputMode="numeric"
                />
                <span className="muted small">
                  Added to phone numbers typed without one (254 = Kenya).
                </span>
              </div>

              {sms.provider === 'africas_talking' && (
                <>
                  <div className="field">
                    <label>Username</label>
                    <input value={sms.username} onChange={(e) => setSms({ ...sms, username: e.target.value })} placeholder="sandbox / AT username" />
                  </div>
                  <div className="field">
                    <label>API key</label>
                    <input type="password" value={sms.api_key} onChange={(e) => setSms({ ...sms, api_key: e.target.value })} placeholder={sms.api_key_set ? '•••••••• (leave blank to keep)' : 'Your API key'} />
                  </div>
                  <div className="field">
                    <label>Sender ID</label>
                    <input value={sms.from} onChange={(e) => setSms({ ...sms, from: e.target.value })} placeholder="optional (e.g. EstateManager)" />
                  </div>
                  <div className="field">
                    <label>Sandbox mode</label>
                    <select value={sms.sandbox ? '1' : '0'} onChange={(e) => setSms({ ...sms, sandbox: e.target.value === '1' })}>
                      <option value="1">Sandbox (test, free)</option>
                      <option value="0">Live</option>
                    </select>
                  </div>
                </>
              )}

              {sms.provider === 'sms_gate' && (
                <>
                  <div className="field">
                    <label>Server URL</label>
                    <input value={sms.base_url} onChange={(e) => setSms({ ...sms, base_url: e.target.value })} placeholder="https://api.sms-gate.app" />
                    <span className="muted small">Leave blank for the cloud, or your private server URL.</span>
                  </div>
                  <div className="field">
                    <label>Username</label>
                    <input value={sms.username} onChange={(e) => setSms({ ...sms, username: e.target.value })} placeholder="From the Android app" />
                  </div>
                  <div className="field">
                    <label>Password</label>
                    <input type="password" value={sms.api_key} onChange={(e) => setSms({ ...sms, api_key: e.target.value })} placeholder={sms.api_key_set ? '•••••••• (leave blank to keep)' : 'From the Android app'} />
                  </div>
                </>
              )}

              {sms.provider === 'textbee' && (
                <>
                  <div className="field">
                    <label>API base URL</label>
                    <input value={sms.base_url} onChange={(e) => setSms({ ...sms, base_url: e.target.value })} placeholder="https://api.textbee.dev" />
                    <span className="muted small">Leave blank for the cloud, or your self-hosted URL.</span>
                  </div>
                  <div className="field">
                    <label>Device ID</label>
                    <input value={sms.device_id} onChange={(e) => setSms({ ...sms, device_id: e.target.value })} placeholder="From the textbee dashboard" />
                  </div>
                  <div className="field">
                    <label>API key</label>
                    <input type="password" value={sms.api_key} onChange={(e) => setSms({ ...sms, api_key: e.target.value })} placeholder={sms.api_key_set ? '•••••••• (leave blank to keep)' : 'From the textbee dashboard'} />
                  </div>
                </>
              )}

              {sms.provider === 'generic' && (
                <>
                  <div className="field" style={{ gridColumn: '1 / -1' }}>
                    <label>Endpoint URL</label>
                    <input value={sms.generic_url} onChange={(e) => setSms({ ...sms, generic_url: e.target.value })} placeholder="https://gateway.example.com/send" />
                  </div>
                  <div className="field" style={{ gridColumn: '1 / -1' }}>
                    <label>Authorization header</label>
                    <input value={sms.generic_auth} onChange={(e) => setSms({ ...sms, generic_auth: e.target.value })} placeholder="Bearer YOUR_TOKEN (optional)" />
                  </div>
                  <div className="field" style={{ gridColumn: '1 / -1' }}>
                    <label>JSON body template</label>
                    <input value={sms.generic_body} onChange={(e) => setSms({ ...sms, generic_body: e.target.value })} placeholder='{"to":"{{to}}","message":"{{message}}"}' />
                    <span className="muted small">Use <code>{'{{to}}'}</code> and <code>{'{{message}}'}</code> placeholders.</span>
                  </div>
                </>
              )}
            </div>
            <p className="muted small">
              Without credentials, messages are marked <em>simulated</em> and delivery is still recorded.
            </p>
            {(sms.provider === 'sms_gate' || sms.provider === 'textbee') && (
              <div className="smtp-note">
                <strong>Android gateway.</strong> Install the gateway app on an Android phone with a SIM,
                keep it powered on and online, and copy its credentials here. Messages go out through
                your own SIM, so there is no per-message fee — but delivery depends on that phone being
                up. Check <strong>Activity</strong> for <code>reset_sms_undelivered</code> if codes stop arriving.
              </div>
            )}
          </div>

          <div className="section-card">
            <h3>Email — SMTP</h3>
            <div className="switch-row">
              <label className="switch">
                <input type="checkbox" checked={email.enabled} onChange={(e) => setEmail({ ...email, enabled: e.target.checked })} />
                <span className="slider" />
              </label>
              <span className="small">Send email notifications</span>
              <button
                type="button"
                className="btn small secondary"
                style={{ marginLeft: 'auto' }}
                onClick={() => setEmail({ ...email, host: 'smtp.gmail.com', port: 587 })}
              >
                Use Gmail
              </button>
            </div>
            <div className="form-grid">
              <div className="field">
                <label>SMTP host</label>
                <input value={email.host} onChange={(e) => setEmail({ ...email, host: e.target.value })} placeholder="smtp.example.com" />
              </div>
              <div className="field">
                <label>Port</label>
                <input type="number" value={email.port} onChange={(e) => setEmail({ ...email, port: e.target.value })} placeholder="587 / 465" />
              </div>
              <div className="field">
                <label>Username</label>
                <input value={email.user} onChange={(e) => setEmail({ ...email, user: e.target.value })} />
              </div>
              <div className="field">
                <label>Password</label>
                <input type="password" value={email.pass} onChange={(e) => setEmail({ ...email, pass: e.target.value })} placeholder={email.pass_set ? '•••••••• (leave blank to keep)' : 'Password'} />
              </div>
              <div className="field" style={{ gridColumn: '1 / -1' }}>
                <label>From address</label>
                <input value={email.from} onChange={(e) => setEmail({ ...email, from: e.target.value })} placeholder="Oikos <no-reply@example.com>" />
              </div>
              <div className="field" style={{ gridColumn: '1 / -1' }}>
                <label>Application URL</label>
                <input value={email.app_url} onChange={(e) => setEmail({ ...email, app_url: e.target.value })} placeholder="https://app.example.com" />
                <span className="muted small">
                  Where password-reset links point. Must be the address people
                  open this app at, not the WordPress address.
                </span>
              </div>
            </div>

            <div className="smtp-note">
              <strong>Using Gmail?</strong> Google won&rsquo;t accept your normal
              password. Turn on 2-Step Verification, then create a 16-character{' '}
              <em>App Password</em> (Google Account → Security → App passwords)
              and paste that into the password field. The <em>From</em> address
              must be the same Gmail account you sign in with.
            </div>
          </div>

          <div className="section-card">
            <h3>Send a test message</h3>
            <form onSubmit={sendTest} className="form-grid">
              <div className="field">
                <label>Channel</label>
                <select value={test.channel} onChange={(e) => setTest({ ...test, channel: e.target.value })}>
                  <option value="sms">SMS</option>
                  <option value="email">Email</option>
                </select>
              </div>
              <div className="field">
                <label>Recipient {test.channel === 'sms' ? '(07xxx…)' : '(email address)'}</label>
                <input value={test.recipient} onChange={(e) => setTest({ ...test, recipient: e.target.value, result: null })} required placeholder={test.channel === 'sms' ? '0712345678' : 'you@example.com'} />
              </div>
              <div style={{ gridColumn: '1 / -1' }}>
                <button className="btn secondary" disabled={busy}>Send test</button>
              </div>
            </form>
            {test.result && (
              <p className="small" style={{ marginTop: 10 }}>
                {test.result.ok
                  ? <span className="badge ok">{test.result.simulated ? 'Simulated ' : 'Sent '}✓</span>
                  : <span className="badge danger">Failed ✗</span>}
                {test.result.error && <span className="muted" style={{ marginLeft: 6 }}>{test.result.error}</span>}
              </p>
            )}
            <p className="muted small" style={{ marginTop: 8 }}>Test messages are recorded in Notifications → Activity trail.</p>
          </div>
        </div>
      )}
    </div>
  )
}