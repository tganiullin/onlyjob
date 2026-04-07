<?php

namespace App\Http\Controllers;

use App\Models\Interview;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InterviewPdfController extends Controller
{
    public function __invoke(Interview $record): Response
    {
        $record->load([
            'position',
            'interviewQuestions' => static fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id'),
            'integrityEvents.interviewQuestion',
        ]);

        $pdf = Pdf::loadView('pdf.interview', ['interview' => $record])
            ->setPaper('a4');

        $filename = sprintf('interview-%d-%s-%s.pdf',
            $record->id,
            mb_strtolower($record->first_name ?? 'unknown'),
            mb_strtolower($record->last_name ?? ''),
        );

        return $pdf->download($filename);
    }
}
