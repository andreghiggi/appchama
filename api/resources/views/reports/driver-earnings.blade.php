<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de ganhos — {{ $driverName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0d0f12;
            color: #f5f5f5;
            padding: 20px 16px 40px;
        }
        .wrap { max-width: 520px; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #9a9a9a; font-size: 13px; margin-bottom: 24px; }
        .week {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-radius: 12px;
            background: #17191d;
            margin-bottom: 8px;
        }
        .week-label { font-size: 14px; font-weight: 600; }
        .week-rides { font-size: 12px; color: #9a9a9a; margin-top: 2px; }
        .week-amount { font-size: 16px; font-weight: 700; color: #ff9f1c; }
        .week-amount.zero { color: #666; }
        .total {
            margin-top: 20px;
            padding: 16px;
            border-radius: 12px;
            background: #1b2a27;
            border: 1px solid rgba(15,158,141,.35);
        }
        .total-label { font-size: 12px; color: #8fd9cc; text-transform: uppercase; letter-spacing: .05em; }
        .total-amount { font-size: 26px; font-weight: 800; color: #0f9e8d; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Relatório de ganhos</h1>
        <p class="sub">{{ $driverName }} · últimas {{ count($weeks) }} semanas</p>

        @foreach ($weeks as $week)
            <div class="week">
                <div>
                    <div class="week-label">{{ $week['week_label'] }}</div>
                    <div class="week-rides">{{ $week['rides_count'] }} corrida(s)</div>
                </div>
                <div class="week-amount @if($week['total_earnings'] == 0) zero @endif">
                    R$ {{ number_format($week['total_earnings'], 2, ',', '.') }}
                </div>
            </div>
        @endforeach

        <div class="total">
            <div class="total-label">Total no período</div>
            <div class="total-amount">
                R$ {{ number_format(collect($weeks)->sum('total_earnings'), 2, ',', '.') }}
            </div>
        </div>
    </div>
</body>
</html>
