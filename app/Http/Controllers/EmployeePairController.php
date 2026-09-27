<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\CsvImportException;
use App\Http\Requests\UploadCsvRequest;
use App\Services\CsvEmployeeReader;
use App\Services\EmployeePairFinder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class EmployeePairController extends Controller
{
    public function __construct(
        private readonly CsvEmployeeReader $reader,
        private readonly EmployeePairFinder $finder,
    ) {}

    public function index(Request $request): View
    {
        return view('employees.index', [
            'error' => $request->boolean('too_large')
                ? sprintf('The file is too large. The server accepts files up to %s.', ini_get('post_max_size'))
                : null,
        ]);
    }

    /**
     * The uploaded temp file is read directly and never stored.
     */
    public function analyze(UploadCsvRequest $request): View|Response
    {
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        try {
            $csv = $this->reader->read($file->getRealPath());
        } catch (CsvImportException $e) {
            return response()->view('employees.index', [
                'fileName' => $fileName,
                'error' => $e->getMessage(),
                'warnings' => $e->warnings,
                'skippedRows' => $e->skippedRows,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return view('employees.index', [
            'fileName' => $fileName,
            'pair' => $this->finder->find($csv->records),
            'warnings' => $csv->warnings,
            'skippedRows' => $csv->skippedRows,
            'dateOrderNote' => $csv->dateOrder?->summary(),
        ]);
    }
}
