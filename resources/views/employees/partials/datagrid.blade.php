{{-- Every common project of the winning pair; click a header to sort. --}}
<div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm" data-sortable>
        <thead class="bg-slate-50">
            <tr>
                @foreach (['Employee ID #1', 'Employee ID #2', 'Project ID', 'Days worked'] as $column)
                    <th scope="col" class="px-4 py-3 text-right font-semibold text-slate-900 first:text-left">
                        <button type="button" data-sort class="inline-flex items-center gap-1 hover:text-indigo-600">
                            {{ $column }}<span data-sort-icon aria-hidden="true"></span>
                        </button>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($pair->projects as $project)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2 text-left tabular-nums">{{ $project->empId1 }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $project->empId2 }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $project->projectId }}</td>
                    <td class="px-4 py-2 text-right tabular-nums" data-value="{{ $project->days }}">{{ number_format($project->days) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-slate-50 font-semibold text-slate-900">
            <tr>
                <td class="px-4 py-3 text-left" colspan="3">Total ({{ count($pair->projects) }} {{ \Illuminate\Support\Str::plural('project', count($pair->projects)) }})</td>
                <td class="px-4 py-3 text-right tabular-nums" data-testid="total">{{ number_format($pair->totalDays) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
