/* ============================================================
   Desktop watermark.

   A faint engraved emblem sitting in the canvas whitespace, behind
   the icons and every window. Pure inline SVG, so there is no binary
   asset to ship and it inherits the palette through currentColor.
   ============================================================ */

export default function DesktopWatermark() {
  return (
    <div className="os-watermark" aria-hidden="true">
      <svg viewBox="0 0 360 430" fill="none" xmlns="http://www.w3.org/2000/svg">
        {/* Badge */}
        <rect
          x="30" y="20" width="300" height="300" rx="34"
          stroke="currentColor" strokeWidth="8"
        />

        {/* Roofline */}
        <path
          d="M80 176 L180 92 L280 176"
          stroke="currentColor" strokeWidth="8"
          strokeLinejoin="round" strokeLinecap="round"
        />

        {/* Building */}
        <rect x="100" y="176" width="160" height="114" stroke="currentColor" strokeWidth="8" />

        {/* Door */}
        <rect x="158" y="220" width="44" height="70" stroke="currentColor" strokeWidth="8" />

        {/* Windows */}
        <rect x="118" y="200" width="26" height="26" stroke="currentColor" strokeWidth="7" />
        <rect x="216" y="200" width="26" height="26" stroke="currentColor" strokeWidth="7" />

        {/* Ground line */}
        <path d="M66 290 H294" stroke="currentColor" strokeWidth="8" strokeLinecap="round" />

        {/* Wordmark */}
        <text
          x="180" y="392" textAnchor="middle"
          fontFamily="Cinzel, Georgia, 'Times New Roman', serif"
          fontSize="46" fontWeight="700" letterSpacing="6"
          fill="currentColor"
        >
          OIKOS
        </text>
        <text
          x="180" y="420" textAnchor="middle"
          fontFamily="ui-monospace, SFMono-Regular, Menlo, monospace"
          fontSize="11" letterSpacing="6"
          fill="currentColor"
        >
          HOUSEHOLD MANAGEMENT
        </text>
      </svg>
    </div>
  )
}