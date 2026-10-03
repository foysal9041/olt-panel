{{--
    Office ID card look (CR80: 54 × 85.6 mm), shared by the generator page and
    its print window. Kept in one <style id="idc-css">
    /* The card ignores the page's own styles (alignment, tables, small text). */
    .idc, .idc * { box-sizing: border-box; margin: 0; padding: 0; border: 0; text-align: left; text-transform: none; letter-spacing: normal; line-height: 1.2; }
    .idc {
        --d: #0a2a5e; --m: #123f86; --a: #1e88e5;
        position: relative; width: 53.98mm; height: 85.6mm; overflow: hidden; flex: none;
        border-radius: 3.18mm; background: #fff; color: #0f172a;
        font-family: 'Poppins', 'Segoe UI', Arial, sans-serif; font-size: 2.2mm;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .idc svg.idc-bg { position: absolute; inset: 0; width: 100%; height: 100%; }
    .idc .fd { fill: var(--d); } .idc .fm { fill: var(--m); } .idc .fa { fill: var(--a); }

    /* ---- Front ---- */
    .idc .idc-logo { position: absolute; left: 50%; top: 4.2mm; width: 37mm; height: auto; transform: translateX(-50%); }
    .idc .idc-photo {
        position: absolute; left: 50%; top: 22.4mm; width: 26mm; height: 26mm; transform: translateX(-50%);
        border-radius: 50%; padding: .85mm; background: #fff;
        box-shadow: 0 0 0 .55mm var(--a), 0 1.2mm 3mm rgba(0, 0, 0, .3);
    }
    .idc .idc-photo img, .idc .idc-photo .idc-ph { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block; }
    .idc .idc-photo .idc-ph { display: flex; align-items: center; justify-content: center; background: #e2e8f0; color: #64748b; font-size: 7mm; font-weight: 700; }
    .idc .idc-name {
        position: absolute; left: 2.5mm; right: 2.5mm; top: 50.9mm; text-align: center;
        color: #fff; font-size: 3.7mm; font-weight: 700; letter-spacing: .05mm; white-space: nowrap; overflow: hidden;
    }
    .idc .idc-role { position: absolute; left: 2mm; right: 2mm; top: 55.5mm; text-align: center; color: #8cc8ff; font-size: 2.45mm; font-weight: 500; white-space: nowrap; overflow: hidden; }
    .idc .idc-rule { position: absolute; left: 50%; top: 59.1mm; width: 21mm; height: .3mm; transform: translateX(-50%); background: linear-gradient(90deg, transparent, var(--a), transparent); }
    .idc .idc-info { position: absolute; left: 7.5mm; right: 3mm; top: 60.6mm; color: #fff; font-size: 2.15mm; }
    .idc .idc-row { display: flex; align-items: center; height: 2.95mm; white-space: nowrap; }
    .idc .idc-row .k { flex: none; width: 17.5mm; font-weight: 500; color: #dbeafe; }
    .idc .idc-row .c { flex: none; width: 2.6mm; color: #dbeafe; }
    .idc .idc-row .v { flex: 1; min-width: 0; font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
    .idc .idc-row.exp .v { color: #fde68a; }
    .idc .idc-web { position: absolute; left: 0; right: 0; bottom: 0; height: 5.2mm; display: flex; align-items: center; justify-content: center; gap: 1.2mm; color: #fff; font-size: 2.1mm; font-weight: 500; letter-spacing: .05mm; }
    .idc .idc-web svg { width: 2.4mm; height: 2.4mm; fill: #fff; }

    /* ---- Back ---- */
    .idc.idc-back .idc-logo { top: 5mm; width: 38mm; }
    .idc .idc-line { position: absolute; left: 5mm; right: 5mm; top: 21.6mm; height: .25mm; background: #94a3b8; }
    .idc .idc-line::after { content: ''; position: absolute; left: 50%; top: -.35mm; width: 6mm; height: .9mm; border-radius: 1mm; transform: translateX(-50%); background: var(--a); }
    /* Each contact line centred on the card, icon just before its text. */
    .idc .idc-contact { position: absolute; left: 3.5mm; right: 3.5mm; top: 24.4mm; display: flex; flex-direction: column; align-items: center; gap: 1.3mm; font-size: 2.1mm; color: #1e293b; }
    .idc .idc-contact > div { display: flex; align-items: center; justify-content: center; gap: 1.5mm; max-width: 100%; }
    .idc .idc-contact i { flex: none; display: flex; align-items: center; justify-content: center; width: 3.5mm; height: 3.5mm; border-radius: 50%; background: var(--a); }
    .idc .idc-contact i svg { width: 1.9mm; height: 1.9mm; fill: #fff; }
    .idc .idc-contact span { max-width: 38mm; line-height: 1.3; word-break: break-word; text-align: center; }
    .idc .idc-qr {
        position: absolute; left: 50%; top: 50.2mm; width: 17mm; height: 17mm; transform: translateX(-50%);
        padding: .9mm; border: .35mm solid var(--a); border-radius: 1.4mm; background: #fff;
    }
    .idc .idc-qr canvas, .idc .idc-qr img { width: 100% !important; height: 100% !important; display: block; image-rendering: pixelated; }
    .idc .idc-sign { position: absolute; right: 3.6mm; bottom: 2.8mm; width: 22mm; text-align: center; color: #fff; }
    .idc .idc-sign img { display: block; height: 6.4mm; max-width: 100%; margin: 0 auto -.3mm; object-fit: contain; }
    .idc .idc-sign .gap { height: 6.4mm; }
    .idc .idc-sign .ln { height: .25mm; background: rgba(255, 255, 255, .85); }
    .idc .idc-sign .lb { display: block; margin-top: .7mm; text-align: center; font-size: 1.55mm; font-weight: 600; letter-spacing: .12mm; color: #fff; }
    .idc .idc-note { position: absolute; left: 3.8mm; bottom: 3.1mm; width: 23mm; color: #cbd5e1; font-size: 1.42mm; line-height: 1.35; }
    .idc .idc-no { position: absolute; right: 3.6mm; top: 2.4mm; font-size: 1.4mm; color: #94a3b8; letter-spacing: .05mm; }

    /* ---- Templates: only the chosen layout's artwork shows ---- */
    .idc .bgv { display: none; }
    .idc.idc-l-wave .bgv-wave, .idc.idc-l-angle .bgv-angle, .idc.idc-l-classic .bgv-classic { display: inline; }
    .idc .idc-brand { display: none; }

    /* Diagonal: light body, photo on a slanted band, dark text */
    .idc.idc-l-angle.idc-front .idc-photo { top: 20mm; width: 25mm; height: 25mm; border-radius: 3.4mm; padding: .8mm; box-shadow: 0 0 0 .5mm var(--a), 0 1.2mm 3mm rgba(0, 0, 0, .28); }
    .idc.idc-l-angle.idc-front .idc-photo img, .idc.idc-l-angle.idc-front .idc-photo .idc-ph { border-radius: 2.7mm; }
    .idc.idc-l-angle .idc-name { top: 47.4mm; color: var(--d); }
    .idc.idc-l-angle .idc-role { top: 52mm; color: var(--m); }
    .idc.idc-l-angle .idc-rule { top: 55.5mm; }
    .idc.idc-l-angle .idc-info { top: 57mm; color: #0f172a; }
    .idc.idc-l-angle .idc-row { height: 2.8mm; }
    .idc.idc-l-angle .idc-row .k, .idc.idc-l-angle .idc-row .c { color: #64748b; }
    .idc.idc-l-angle .idc-row.exp .v { color: #b91c1c; }

    /* Classic: dark header with the company name, photo on its edge */
    .idc.idc-l-classic.idc-front .idc-logo { display: none; }
    .idc.idc-l-classic .idc-brand { position: absolute; left: 0; right: 0; top: 4.2mm; display: flex; align-items: center; justify-content: center; gap: 1.8mm; color: #fff; }
    .idc.idc-l-classic .idc-brand img { width: 9mm; height: 9mm; }
    .idc.idc-l-classic .idc-brand b { display: block; font-size: 4.1mm; font-weight: 700; letter-spacing: .9mm; line-height: 1; }
    .idc.idc-l-classic .idc-brand span { display: block; margin-top: .5mm; font-size: 2.15mm; font-weight: 500; letter-spacing: .55mm; color: var(--a); line-height: 1; }
    .idc.idc-l-classic.idc-front .idc-photo { top: 17.2mm; width: 25mm; height: 25mm; }
    .idc.idc-l-classic .idc-name { top: 44.6mm; color: var(--d); }
    .idc.idc-l-classic .idc-role { top: 49.2mm; color: var(--m); }
    .idc.idc-l-classic .idc-rule { top: 52.8mm; }
    .idc.idc-l-classic .idc-info { top: 54.6mm; color: #0f172a; }
    .idc.idc-l-classic .idc-row .k, .idc.idc-l-classic .idc-row .c { color: #64748b; }
    .idc.idc-l-classic .idc-row.exp .v { color: #b91c1c; }
    .idc.idc-l-classic .idc-web { color: #fff; }
</style>
