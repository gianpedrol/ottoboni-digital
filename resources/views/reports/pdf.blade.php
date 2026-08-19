<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório — Clínica Ottoboni</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 0; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .meta { color: #6b7280; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 3px 6px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-weight: normal; }
        .num { text-align: right; }
        .kpi { display: inline-block; margin-right: 24px; }
        .kpi b { font-size: 15px; }
    </style>
</head>
<body>
    @php($tz = config('painel.timezone'))

    <h1>Clínica Ottoboni — Relatório de atendimentos</h1>
    <p class="meta">
        {{ implode(' · ', $medicos) }}<br>
        Período: {{ \Carbon\Carbon::parse($relatorio['periodo']['de'])->format('d/m/Y') }}
        a {{ \Carbon\Carbon::parse($relatorio['periodo']['ate'])->format('d/m/Y') }}
        ({{ $relatorio['periodo']['dias'] }} dias)
        · Gerado em {{ \Carbon\Carbon::parse($relatorio['gerado_em'])->format('d/m/Y H:i') }}
    </p>

    <h2>1 · Volume</h2>
    <p>
        <span class="kpi"><b>{{ $relatorio['volume']['total'] }}</b> leads no período</span>
        <span class="kpi"><b>{{ $relatorio['volume']['total_periodo_anterior'] }}</b> no período anterior</span>
        <span class="kpi"><b>{{ $relatorio['volume']['variacao_pct'] !== null ? ($relatorio['volume']['variacao_pct'] >= 0 ? '+' : '') . $relatorio['volume']['variacao_pct'] . '%' : '—' }}</b> variação</span>
    </p>
    <table>
        <thead><tr><th>Dia</th><th class="num">Leads</th></tr></thead>
        <tbody>
            @foreach ($relatorio['volume']['por_dia'] as $dia => $total)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}</td>
                    <td class="num">{{ $total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>2 · Origem</h2>
    <table>
        <thead><tr><th>Origem</th><th class="num">Leads</th><th class="num">%</th></tr></thead>
        <tbody>
            @foreach ($relatorio['origem']['fatias'] as $nome => $fatia)
                <tr><td>{{ $nome }}</td><td class="num">{{ $fatia['total'] }}</td><td class="num">{{ $fatia['pct'] }}%</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>3 · Temperatura</h2>
    <table>
        <thead><tr><th>Temperatura</th><th class="num">Leads</th><th class="num">%</th></tr></thead>
        <tbody>
            @foreach ($relatorio['temperatura']['fatias'] as $nome => $fatia)
                <tr><td>{{ $nome }}</td><td class="num">{{ $fatia['total'] }}</td><td class="num">{{ $fatia['pct'] }}%</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>4 · Procedimentos</h2>
    <table>
        <thead><tr><th>Procedimento</th><th class="num">Leads</th><th class="num">%</th></tr></thead>
        <tbody>
            @foreach ($relatorio['procedimentos']['ranking'] as $nome => $fatia)
                <tr><td>{{ $nome }}</td><td class="num">{{ $fatia['total'] }}</td><td class="num">{{ $fatia['pct'] }}%</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>5 · Funil</h2>
    @foreach ($relatorio['funil']['funis'] as $funil)
        <p>
            <b>{{ $funil['nome'] }}</b> — {{ $funil['total'] }} leads ·
            {{ $funil['ganhos'] }} ganhos ({{ $funil['taxa_ganho_pct'] }}%) ·
            {{ $funil['perdidos'] }} perdidos
        </p>
        <table>
            <thead><tr><th>Etapa</th><th class="num">Estão nela</th><th class="num">Chegaram</th><th class="num">Passagem</th></tr></thead>
            <tbody>
                @foreach ($funil['etapas'] as $etapa)
                    <tr>
                        <td>{{ $etapa['nome'] }}</td>
                        <td class="num">{{ $etapa['atual'] }}</td>
                        <td class="num">{{ $etapa['chegaram'] }}</td>
                        <td class="num">{{ $etapa['taxa_passagem_pct'] !== null ? $etapa['taxa_passagem_pct'] . '%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>6 · Follow-up</h2>
    <p>
        <span class="kpi"><b>{{ $relatorio['followup']['enviados'] }}</b> enviados</span>
        <span class="kpi"><b>{{ $relatorio['followup']['taxa_entrega_pct'] !== null ? $relatorio['followup']['taxa_entrega_pct'] . '%' : '—' }}</b> entrega</span>
        <span class="kpi"><b>{{ $relatorio['followup']['taxa_resposta_pct'] !== null ? $relatorio['followup']['taxa_resposta_pct'] . '%' : '—' }}</b> resposta em 72h</span>
    </p>

    <h2>7 · Produtividade</h2>
    <table>
        <thead><tr><th>Responsável</th><th class="num">Leads</th></tr></thead>
        <tbody>
            @foreach ($relatorio['produtividade']['por_responsavel'] as $nome => $total)
                <tr><td>{{ $nome }}</td><td class="num">{{ $total }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p>
        Tempo médio até a primeira nota humana:
        {{ $relatorio['produtividade']['tempo_medio_primeira_nota_horas'] !== null
            ? $relatorio['produtividade']['tempo_medio_primeira_nota_horas'] . 'h'
            : '—' }}
    </p>
</body>
</html>
