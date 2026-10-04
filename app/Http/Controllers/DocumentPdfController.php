<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\PdfRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The team's PDF of a document (opens in the browser's PDF viewer). */
class DocumentPdfController extends Controller
{
    public function __invoke(Request $request, Document $document, PdfRenderer $pdf): Response
    {
        abort_if($document->type !== $request->route('type'), 404);
        $this->authorize('view', $document);

        return response($pdf->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($document).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
