@extends('layouts.app')

@section('title', 'Employee Pairs')

@php
    $warnings ??= [];
    $skippedRows ??= count($warnings);
    $pair ??= null;
    $fileName ??= null;
    $error ??= null;
@endphp

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Employee pairs</h1>
        <p class="mt-2 text-slate-600">
            Find the pair of employees who worked together on common projects for the longest time.
        </p>
    </header>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('employees.analyze') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label for="file" class="block text-sm font-medium text-slate-900">CSV file</label>
                <p class="mt-1 text-sm text-slate-600">
                    One row per assignment: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">EmpID, ProjectID, DateFrom, DateTo</code>.
                    The header row is optional, <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">NULL</code> as DateTo means today,
                    and most date formats are accepted.
                </p>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        id="file"
                        name="file"
                        type="file"
                        accept=".csv,.txt,text/csv,text/plain"
                        required
                        data-auto-submit
                        data-max-bytes="{{ \App\Http\Requests\UploadCsvRequest::MAX_KILOBYTES * 1024 }}"
                        aria-describedby="file-error"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-800 hover:file:bg-slate-200"
                    >
                    <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Find pair
                    </button>
                </div>

                <p id="file-error" class="mt-2 text-sm text-red-600" data-file-error @if (! $errors->has('file')) hidden @endif>
                    {{ $errors->first('file') }}
                </p>
            </div>
        </form>
    </section>

    @if ($error)
        <div role="alert" class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            @if ($fileName)
                <p class="font-medium">Could not analyse “{{ $fileName }}”.</p>
            @endif
            <p>{{ $error }}</p>
        </div>
    @endif

    @if ($fileName && ! $error)
        <section class="mt-8" aria-labelledby="result-heading">
            <h2 id="result-heading" class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Result for “{{ $fileName }}”
            </h2>

            @if ($pair)
                <p class="mt-2 text-lg text-slate-900" data-testid="summary">
                    Employees <strong>{{ $pair->empId1 }}</strong> and <strong>{{ $pair->empId2 }}</strong>
                    worked together for <strong>{{ number_format($pair->totalDays) }}</strong>
                    {{ \Illuminate\Support\Str::plural('day', $pair->totalDays) }} in total.
                </p>

                @if ($pair->otherPairsWithSameTotal > 0)
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $pair->otherPairsWithSameTotal }} other {{ \Illuminate\Support\Str::plural('pair', $pair->otherPairsWithSameTotal) }}
                        also worked together for {{ number_format($pair->totalDays) }} days; the pair with the lowest employee IDs is shown.
                    </p>
                @endif

                @include('employees.partials.datagrid', ['pair' => $pair])
            @else
                <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    No pair of employees worked together on a common project in this file.
                </div>
            @endif
        </section>
    @endif

    @if ($warnings !== [])
        <details class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" @if (count($warnings) <= 5) open @endif>
            <summary class="cursor-pointer font-medium">
                {{ number_format($skippedRows) }} {{ \Illuminate\Support\Str::plural('row', $skippedRows) }} skipped because of invalid data
            </summary>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </details>
    @endif
@endsection
