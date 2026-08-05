<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório do dia — {{ $entidade->nome }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #111827;
            margin: 2rem;
        }

        .cabecalho {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .cabecalho img {
            height: 9rem;
            width: auto;
        }

        h1 {
            font-size: 1.25rem;
            margin: 0 0 0.25rem;
        }

        h2 {
            font-size: 1rem;
            font-weight: 500;
            color: #4b5563;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 0.5rem 0.75rem;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.02em;
        }

        .vazio {
            color: #9ca3af;
        }

        .no-print {
            margin-bottom: 1.5rem;
        }

        .no-print button {
            font: inherit;
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: #f9fafb;
            cursor: pointer;
        }

        @media print {
            body {
                margin: 0.5cm;
            }

            .no-print {
                display: none;
            }
        }

        @page {
            size: landscape;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <div class="cabecalho">
        <img src="{{ asset('images/logo-inttegrar.png') }}" alt="Logo">
        <div>
            <h1>Relatório do dia</h1>
            <h2>
                {{ $modo === 'sala' ? 'Sala' : 'Profissional' }}: {{ $entidade->nome }}
                — {{ ucfirst($data->translatedFormat('l, j \d\e F \d\e Y')) }}
            </h2>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Horário</th>
                <th>Paciente</th>
                @if ($modo === 'sala')
                    <th>Médico</th>
                @endif
                <th>Procedimentos previstos</th>
                <th>Descrição da consulta</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($agendamentos as $agendamento)
                <tr>
                    <td>{{ $agendamento->data_hora_inicio->format('H:i') }} – {{ $agendamento->data_hora_fim->format('H:i') }}</td>
                    <td>{{ $agendamento->paciente?->nome }}</td>
                    @if ($modo === 'sala')
                        <td>{{ $agendamento->profissional?->nome }}</td>
                    @endif
                    <td>
                        @if ($agendamento->procedimentosPrevistos->isNotEmpty())
                            {{ $agendamento->procedimentosPrevistos->pluck('nome')->implode(', ') }}
                        @else
                            <span class="vazio">—</span>
                        @endif
                    </td>
                    <td>
                        @if (filled($agendamento->descricao))
                            {{ $agendamento->descricao }}
                        @else
                            <span class="vazio">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $modo === 'sala' ? 5 : 4 }}" style="text-align: center; color: #9ca3af;">
                        Nenhum agendamento neste dia.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
