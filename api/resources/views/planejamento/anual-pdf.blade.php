<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Planejamento {{ $anual->ano }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 8px; color: #444; }
        .meta { margin-bottom: 16px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-weight: bold; }
        .status { font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>Planejamento pastoral {{ $anual->ano }}</h1>
    <div class="meta">
        {{ $igreja?->nome ?? 'Igreja' }}
        · Status: {{ $anual->status }}
        @if($mesNome)
            · Mês: {{ $mesNome }}
        @endif
    </div>

    <h2>Eventos</h2>
    @if($eventos->isEmpty())
        <p>Nenhum evento cadastrado.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Pastoral</th>
                    <th>Título</th>
                    <th>Local</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($eventos as $evento)
                    @php
                        $data = $evento->data_inicio?->format('d/m/Y');
                        if ($evento->data_fim && $evento->data_fim->format('Y-m-d') !== $evento->data_inicio?->format('Y-m-d')) {
                            $data .= ' – '.$evento->data_fim->format('d/m/Y');
                        }
                        $horaIni = $evento->hora_inicio;
                        $horaFim = $evento->hora_fim;
                        if ($horaIni instanceof \DateTimeInterface) { $horaIni = $horaIni->format('H:i'); }
                        if ($horaFim instanceof \DateTimeInterface) { $horaFim = $horaFim->format('H:i'); }
                        if (is_string($horaIni) && preg_match('/^(\d{2}:\d{2})/', $horaIni, $m)) { $horaIni = $m[1]; }
                        if (is_string($horaFim) && preg_match('/^(\d{2}:\d{2})/', $horaFim, $m)) { $horaFim = $m[1]; }
                        $horario = trim(($horaIni ?? '').($horaFim ? ' – '.$horaFim : ''));
                        $locais = $evento->locais->pluck('nome')->implode(', ');
                        if ($evento->local_texto) {
                            $locais = $locais !== '' ? $locais.'; '.$evento->local_texto : $evento->local_texto;
                        }
                    @endphp
                    <tr>
                        <td>{{ $data }}</td>
                        <td>{{ $horario !== '' ? $horario : '—' }}</td>
                        <td>{{ $evento->pastoral?->nome }}</td>
                        <td>{{ $evento->titulo }}</td>
                        <td>{{ $locais !== '' ? $locais : '—' }}</td>
                        <td class="status">{{ $evento->status_solicitacao }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
