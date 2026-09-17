/** HTML dos marcadores de embarque/destino — estilo Uber. */



export function originMarkerHtml(): string {

  return `

    <div style="display:flex;flex-direction:column;align-items:center;width:24px;height:24px">

      <div style="width:16px;height:16px;background:#111;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center">

        <div style="width:5px;height:5px;background:#fff;border-radius:50%"></div>

      </div>

    </div>`;

}



/** Ponto do motorista — sem ícone de veículo. */
export function driverMarkerHtml(): string {
  return `
    <div style="display:flex;flex-direction:column;align-items:center;width:20px;height:20px">
      <div style="width:14px;height:14px;background:#1A73E8;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35)"></div>
    </div>`;
}

export function destinationMarkerHtml(etaMinutes?: number, label = 'Chegada'): string {

  const etaBlock =

    etaMinutes != null && Number.isFinite(etaMinutes)

      ? `<div style="background:#fff;border-radius:10px;padding:8px 14px;box-shadow:0 4px 16px rgba(0,0,0,.18);text-align:center;min-width:72px;margin-bottom:6px">

          <div style="font-size:11px;font-weight:600;color:#6B7280;line-height:1.2">${label}</div>

          <div style="font-size:20px;font-weight:800;color:#111;line-height:1.15;margin-top:2px">${Math.round(etaMinutes)} min</div>

        </div>`

      : '';



  return `

    <div style="display:flex;flex-direction:column;align-items:center">

      ${etaBlock}

      <div style="width:16px;height:16px;background:#111;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center">

        <div style="width:5px;height:5px;background:#fff;border-radius:50%"></div>

      </div>

    </div>`;

}


