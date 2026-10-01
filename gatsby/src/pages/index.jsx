import React from 'react'
import { Link } from 'gatsby'

const FEATURES = [
  { icon: '🏘️', title: 'Properties & Units', text: 'Track every house, apartment and unit with live occupancy.' },
  { icon: '👥', title: 'Tenants & Leases', text: 'One-step onboarding, lease terms and automatic renewals.' },
  { icon: '💰', title: 'Rent Collections', text: 'Record payments, chase arrears and export period reports.' },
  { icon: '💧', title: 'Utilities', text: 'Meter readings, consumption and billable utility amounts.' },
  { icon: '🔔', title: 'Reminders', text: 'SMS and email notices when a lease is close to renewal.' },
  { icon: '🧾', title: 'Audit Trail', text: 'Every change logged with who did it and why.' }
]

export default function IndexPage() {
  return (
    <div className="landing">
      <header className="landing-nav">
        <div className="brand">
          <div className="brand-logo">🏛️</div>
          <div>
            <h1 className="brand-script landing-brand">Oikos</h1>
            <p>Rentals, leases &amp; utilities</p>
          </div>
        </div>
        <Link className="btn" to="/app/">
          Open the app <span>→</span>
        </Link>
      </header>

      <main>
        <section className="landing-hero">
          <div className="hero-copy">
            <div className="hero-tag">Gatsby + WordPress</div>
            <h1>Run your rentals from one clean dashboard.</h1>
            <p>
              Oikos keeps your properties, tenants, leases, payments and utilities
              in one place — a fast Gatsby front-end backed by the WordPress you already know.
            </p>
            <div className="hero-cta">
              <Link className="btn btn-primary hero-btn" to="/app/">
                Launch the app
              </Link>
              <Link className="btn hero-btn ghost" to="/app/">
                Live demo
              </Link>
            </div>
          </div>
          <div className="hero-panel">
            <div className="mini-card">
              <span className="mini-label">Collected this month</span>
              <strong>KSh 248,000</strong>
              <small>94% of expected</small>
            </div>
            <div className="mini-card">
              <span className="mini-label">Occupancy</span>
              <strong>46 / 48</strong>
              <small>4 leases expiring</small>
            </div>
          </div>
        </section>

        <section className="landing-features">
          {FEATURES.map((f) => (
            <div className="feature-card" key={f.title}>
              <div className="feature-icon">{f.icon}</div>
              <h3>{f.title}</h3>
              <p>{f.text}</p>
            </div>
          ))}
        </section>
      </main>

      <footer className="landing-foot">
        <p>Built with Gatsby on a WordPress headless API.</p>
      </footer>
    </div>
  )
}