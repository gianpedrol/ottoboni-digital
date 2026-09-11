@php
    $registro = $this->registro;
    $paciente = $this->getRecord();
    $tz = \App\Filament\Resources\Patients\FichaDoPaciente::tz();
    $medico = $registro->doctor?->nome ?? $paciente->doctor?->nome;
@endphp

<x-filament-panels::page>
    <style>
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar-close-overlay,
            .fi-no-print, div.bg-amber-400.text-amber-950 {
                display: none !important;
            }
            .fi-main, .fi-main-ctn, .fi-page, .fi-page-content {
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
            }
            .documento-impresso {
                box-shadow: none !important;
                border: 0 !important;
                padding: 0 !important;
            }
        }
    </style>

    <div class="fi-no-print flex items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">Versão para impressão. Use o botão ou Ctrl+P.</p>
        <x-filament::button icon="heroicon-o-printer" x-data x-on:click="window.print()">
            Imprimir
        </x-filament::button>
    </div>

    <article class="documento-impresso mx-auto w-full max-w-3xl rounded-xl bg-white p-10 text-gray-900 shadow-sm ring-1 ring-gray-200">
        <header class="flex items-start justify-between gap-6 border-b border-gray-300 pb-4">
            <div>
                <p class="text-lg font-semibold tracking-wide">Clínica Ottoboni</p>
                <p class="text-xs text-gray-500">Cirurgia plástica · Dermatologia e estética</p>
            </div>
            <div class="text-end text-xs text-gray-500">
                <p class="font-medium text-gray-700">{{ $medico ?? '—' }}</p>
                <p>Emitido em {{ now()->timezone($tz)->format('d/m/Y') }}</p>
            </div>
        </header>

        <h1 class="mt-6 text-center text-base font-semibold uppercase tracking-widest">{{ $registro->tipo->getLabel() }}</h1>

        <p class="mt-6 text-sm">
            <span class="text-gray-500">Paciente:</span>
            <strong>{{ $paciente->nome }}</strong>
            @if ($paciente->idade() !== null)
                <span class="text-gray-500">· {{ $paciente->idade() }} anos</span>
            @endif
        </p>

        <div class="mt-6">
            @include('filament.pacientes.registro-conteudo', ['registro' => $registro, 'impressao' => true])
        </div>

        <footer class="mt-16 text-center text-sm">
            <div class="mx-auto w-72 border-t border-gray-400 pt-1">{{ $medico ?? 'Médico responsável' }}</div>

            @if ($registro->estaAssinado())
                <p class="mt-3 text-xs text-gray-500">
                    Assinado no painel em {{ $registro->assinado_em->copy()->timezone($tz)->format('d/m/Y H:i') }} (protótipo).
                    No produto final a assinatura usa certificado digital ICP-Brasil.
                </p>
            @else
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-danger-600">Rascunho — registro ainda não assinado</p>
            @endif
        </footer>
    </article>
</x-filament-panels::page>
