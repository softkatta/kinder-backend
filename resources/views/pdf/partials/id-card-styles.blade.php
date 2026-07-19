<style>
    @page { margin: 8mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; }

    .pvc-card {
        width: 85.60mm;
        height: 53.98mm;
        border-radius: 3.5mm;
        overflow: hidden;
        position: relative;
        display: inline-block;
        vertical-align: top;
        page-break-inside: avoid;
    }

    .card-sheet { text-align: center; padding: 4mm 0; }
    .bulk-row { margin-bottom: 6mm; }
    .page-break { page-break-after: always; }
    .card-gap { display: inline-block; width: 6mm; }

    .card-front, .card-back { color: #fff; }
    .card-front-inner, .card-back-inner { position: relative; z-index: 1; height: 100%; }

    .card-header { display: table; width: 100%; margin-bottom: 2mm; }
    .school-logo {
        display: table-cell; vertical-align: middle; width: 10mm; height: 10mm;
        background: rgba(255,255,255,0.95); border-radius: 2mm; text-align: center;
        font-size: 7pt; font-weight: bold; color: #4F46E5;
    }
    .school-name { display: table-cell; vertical-align: middle; padding-left: 2.5mm; }
    .school-name h1 { font-size: 8.5pt; font-weight: bold; line-height: 1.1; }
    .school-name p { font-size: 5.5pt; opacity: 0.85; margin-top: 0.5mm; }

    .card-body { display: table; width: 100%; }
    .card-info { display: table-cell; vertical-align: top; width: 58%; padding-right: 2mm; }
    .card-photo-wrap { display: table-cell; vertical-align: top; width: 42%; text-align: right; }

    .role-badge {
        display: inline-block; font-size: 5pt; font-weight: bold; letter-spacing: 0.8px;
        padding: 1mm 2.5mm; border-radius: 3mm; margin-bottom: 1.5mm; text-transform: uppercase;
    }
    .holder-name { font-size: 11pt; font-weight: bold; line-height: 1.15; margin-bottom: 0.8mm; }
    .card-id { font-size: 6.5pt; font-family: DejaVu Sans Mono, monospace; opacity: 0.9; margin-bottom: 1.5mm; }
    .meta-line { font-size: 5.5pt; line-height: 1.45; opacity: 0.92; }
    .validity { font-size: 5pt; margin-top: 1.5mm; opacity: 0.8; }

    .photo-box {
        width: 22mm; height: 26mm; border-radius: 2.5mm;
        background: rgba(255,255,255,0.25); border: 1.5px solid rgba(255,255,255,0.5);
        overflow: hidden; display: inline-block; text-align: center;
    }
    .photo-box img { width: 100%; height: 100%; object-fit: cover; }
    .photo-initials { font-size: 14pt; font-weight: bold; padding-top: 8mm; color: rgba(255,255,255,0.7); }

    .card-footer {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: rgba(0,0,0,0.28); padding: 1.5mm 3.5mm; font-size: 5pt;
    }

    .card-back-inner { display: table; width: 100%; padding: 3mm 3.5mm; }
    .back-left { display: table-cell; vertical-align: middle; width: 42%; text-align: center; }
    .back-right { display: table-cell; vertical-align: middle; width: 58%; padding-left: 2mm; }

    .digital-pass-label { font-size: 5.5pt; font-weight: bold; letter-spacing: 1px; margin-bottom: 1.5mm; opacity: 0.9; }
    .qr-wrap { background: #fff; border-radius: 2mm; padding: 1.5mm; display: inline-block; }
    .qr-hint { font-size: 4.5pt; margin-top: 1.5mm; opacity: 0.75; line-height: 1.3; }
    .qr-card-num { font-size: 5pt; font-family: DejaVu Sans Mono, monospace; margin-top: 1mm; opacity: 0.85; }

    .back-detail { font-size: 5pt; line-height: 1.55; margin-bottom: 1.2mm; }
    .back-note {
        font-size: 4.5pt; line-height: 1.35; margin-top: 2mm;
        padding: 1.5mm; background: rgba(0,0,0,0.15); border-radius: 1.5mm; font-style: italic;
    }
    .dates-row { font-size: 4.8pt; margin-top: 1.5mm; opacity: 0.85; }
</style>
