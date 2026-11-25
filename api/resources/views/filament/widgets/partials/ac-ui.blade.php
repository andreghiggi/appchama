@once
    <style>
        .ac-ui { --ac-border: rgba(128,128,128,.22); --ac-soft: rgba(128,128,128,.07); --ac-muted: rgba(128,128,128,.95); --ac-radius: 14px; }
        .ac-ui * { box-sizing: border-box; }
        .ac-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .ac-kpi { border: 1px solid var(--ac-border); background: var(--ac-soft); border-radius: var(--ac-radius); padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
        .ac-kpi-n { font-size: 28px; font-weight: 700; line-height: 1; }
        .ac-kpi-l { font-size: 12px; color: var(--ac-muted); text-transform: uppercase; letter-spacing: .04em; }
        .ac-kpi.is-on { border-color: rgba(15,158,141,.55); background: rgba(15,158,141,.10); }
        .ac-kpi.is-on .ac-kpi-n { color: #0f9e8d; }
        .ac-kpi.is-warn { border-color: rgba(245,158,11,.55); background: rgba(245,158,11,.10); }
        .ac-kpi.is-warn .ac-kpi-n { color: #f59e0b; }
        .ac-kpi.is-bad { border-color: rgba(220,38,38,.55); background: rgba(220,38,38,.10); }
        .ac-kpi.is-bad .ac-kpi-n { color: #ef4444; }
        .ac-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; align-items: start; }
        .ac-col-head { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; font-size: 14px; font-weight: 600; }
        .ac-count { font-size: 12px; font-weight: 600; padding: 1px 9px; border-radius: 999px; background: var(--ac-soft); border: 1px solid var(--ac-border); color: var(--ac-muted); }
        .ac-list { display: flex; flex-direction: column; gap: 10px; }
        .ac-card { position: relative; border: 1px solid var(--ac-border); border-left: 4px solid var(--ac-accent, #0f9e8d); background: var(--ac-soft); border-radius: 12px; padding: 12px 14px; transition: border-color .15s, background .15s; }
        .ac-card:hover { border-color: var(--ac-accent, #0f9e8d); }
        .ac-card.is-teal { --ac-accent: #0f9e8d; }
        .ac-card.is-amber { --ac-accent: #f59e0b; }
        .ac-card.is-red { --ac-accent: #ef4444; }
        .ac-card-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
        .ac-title { font-size: 15px; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ac-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: var(--ac-muted); }
        .ac-pill { flex: none; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
        .ac-pill.is-teal { background: rgba(15,158,141,.16); color: #14b8a6; }
        .ac-pill.is-amber { background: rgba(245,158,11,.16); color: #f59e0b; }
        .ac-pill.is-red { background: rgba(220,38,38,.16); color: #f87171; }
        .ac-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px 16px; }
        .ac-f { display: flex; flex-direction: column; min-width: 0; }
        .ac-f-l { font-size: 11px; color: var(--ac-muted); text-transform: uppercase; letter-spacing: .04em; }
        .ac-f-v { font-size: 13.5px; font-weight: 500; overflow-wrap: anywhere; }
        .ac-f-v.is-money { font-weight: 700; }
        .ac-empty { display: flex; align-items: center; gap: 10px; border: 1px dashed var(--ac-border); border-radius: 12px; padding: 16px; font-size: 13px; color: var(--ac-muted); }
        .ac-empty-i { font-size: 18px; opacity: .8; }
        .ac-sep { margin-top: 24px; }
    </style>
@endonce
