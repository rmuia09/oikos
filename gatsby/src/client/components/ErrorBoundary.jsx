import { Component } from 'react'

/* Catches render-time crashes in the window shell so a single bad component
   shows a recoverable message instead of a blank white screen. Class
   component because React has no hook equivalent for this. */

export default class ErrorBoundary extends Component {
  state = { error: null }

  static getDerivedStateFromError(error) {
    return { error }
  }

  componentDidCatch(error, info) {
    /* Keep the technical detail for whoever is debugging, but never show it
       as the headline the user has to act on. */
    if (typeof console !== 'undefined') console.error('UI error:', error, info)
  }

  reset = () => this.setState({ error: null })

  render() {
    const { error } = this.state
    if (!error) return this.props.children

    return (
      <div className="error-boundary">
        <div className="error-boundary-card">
          <p className="error-boundary-kicker">Something went wrong</p>
          <h2>This screen stopped responding</h2>
          <p>
            The window hit an unexpected problem and had to stop. Nothing you
            have saved is lost.
          </p>
          <div className="error-boundary-actions">
            <button className="btn" onClick={this.reset}>Try again</button>
            <button className="btn secondary" onClick={() => window.location.reload()}>
              Reload the desktop
            </button>
          </div>
          {process.env.NODE_ENV !== 'production' && (
            <details className="error-boundary-detail">
              <summary>Technical detail</summary>
              <pre>{String(error && error.message)}</pre>
            </details>
          )}
        </div>
      </div>
    )
  }
}