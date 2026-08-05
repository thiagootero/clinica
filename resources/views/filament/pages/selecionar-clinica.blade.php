<x-filament-panels::page>
    <div class="rounded-xl border bg-white p-4">
        <p class="text-sm text-gray-600">
            Você é Administrador do sistema e não está preso a uma única clínica. Escolha abaixo qual clínica deseja
            visualizar e operar. Essa escolha vale apenas para esta sessão.
        </p>

        @if ($this->clinicaAtiva)
            <p class="mt-3 text-sm font-medium">
                Clínica ativa agora: <span class="text-primary-600">{{ $this->clinicaAtiva->nome }}</span>
            </p>
        @else
            <p class="mt-3 text-sm font-medium text-danger-600">
                Nenhuma clínica selecionada. Enquanto isso, você não verá dados de pacientes, agendas ou cadastros.
            </p>
        @endif
    </div>

    <div class="mt-6 max-w-lg">
        {{ $this->form }}
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Clínica</th>
                    <th class="px-4 py-3 text-left">CNPJ</th>
                    <th class="px-4 py-3 text-left">Situação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($clinicas as $clinica)
                    <tr>
                        <td class="px-4 py-3">{{ $clinica->nome }}</td>
                        <td class="px-4 py-3">{{ $clinica->cnpj ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $clinica->ativo ? 'Ativa' : 'Inativa' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">Nenhuma clínica cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
