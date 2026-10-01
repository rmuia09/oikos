/* A single place for "something went wrong" so every page says the same thing
   and offers the same way out. Retry only appears for failures that could
   plausibly succeed on a second attempt. */

export default function ErrorBanner({ error, onRetry, busy }) {
  if (!error) return null
  const message = typeof error === 'string' ? error : error.message
  /* Plain strings come from local state we can't classify, so assume retryable.
     Only a flagged non-retryable error (e.g. a 422) hides the button. */
  const retryable = typeof error === 'object' && error ? error.retryable : true

  return (
    <div className={retryable ? 'error-banner is-retryable' : 'error-banner'} role="alert">
      <span>{message}</span>
      {retryable && onRetry && (
        <button className="btn" onClick={onRetry} disabled={busy}>
          {busy ? 'Retrying…' : 'Try again'}
        </button>
      )}
    </div>
  )
}