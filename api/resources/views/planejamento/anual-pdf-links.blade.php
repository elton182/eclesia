<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Links das pastorais {{ $anual->ano }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { margin-bottom: 16px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px 12px; text-align: left; vertical-align: middle; }
        th { background: #f3f3f3; font-weight: bold; }
        .pastoral { font-size: 13px; font-weight: bold; color: #4E1220; }
        .qr { width: 88px; height: 88px; }
        .link-url {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8px;
            word-break: break-all;
            color: #333;
            margin-top: 6px;
        }
        .empty { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <h1>Links públicos das pastorais — {{ $anual->ano }}</h1>
    <div class="meta">
        {{ $igreja?->nome ?? 'Igreja' }}
        · Status: {{ $anual->status }}
        · Use o QR ou o link para propor datas sem login
    </div>

    @if(count($linhas) === 0)
        <p class="empty">Nenhuma pastoral ativa cadastrada.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 32%">Pastoral</th>
                    <th>Link / QR Code</th>
                </tr>
            </thead>
            <tbody>
                @foreach($linhas as $linha)
                    <tr>
                        <td class="pastoral">{{ $linha['pastoral']->nome }}</td>
                        <td>
                            <img class="qr" src="{{ $linha['qr_data_uri'] }}" alt="QR Code">
                            <div class="link-url">{{ $linha['link'] }}</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
