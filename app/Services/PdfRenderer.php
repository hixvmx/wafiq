<?php

namespace App\Services;

use App\Models\Document;
use App\Support\DocumentPresenter;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Arabic PDF of a quotation / invoice with TCPDF (LGPL, pure PHP: works on shared hosting).
 *
 * The font is IBM Plex Sans Arabic (OFL), converted once for TCPDF into resources/fonts/pdf.
 * TCPDF shapes Arabic itself; the font has the presentation-form glyphs it needs.
 * Layout lives in resources/views/pdf/document.blade.php (the HTML subset TCPDF understands).
 */
class PdfRenderer
{
    private const FONT = 'plexarabic';

    public function render(Document $document): string
    {
        $document->loadMissing('lines', 'client', 'company');
        $data = DocumentPresenter::full($document);
        $company = $data['company'];

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor($company['name']);
        $pdf->SetTitle($data['number']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterFont([self::FONT, '', 8]);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setRTL(true);
        // Footer: "صفحة 1 / 2".
        $pdf->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'rtl', 'a_meta_language' => 'ar', 'w_page' => __('ui.pdf.page')]);
        $pdf->setFontSubsetting(true); // embed only the glyphs used: small files

        $fonts = resource_path('fonts/pdf');
        $pdf->AddFont(self::FONT, '', "{$fonts}/ibmplexsansarabic.php");
        $pdf->AddFont(self::FONT, 'B', "{$fonts}/ibmplexsansarabicb.php");
        $pdf->SetFont(self::FONT, '', 9.5);

        $pdf->AddPage();
        $pdf->writeHTML(view('pdf.document', [
            'd' => $data,
            'company' => $company,
            'client' => $data['client'],
            'brand' => $company['brand_color'] ?: '#0d9488',
            'images' => $this->images($document),
            'approval' => $document->approved_at ? [
                'name' => $document->approved_by_name,
                'date' => $document->approved_at->locale('ar')->translatedFormat('j F Y، H:i'),
                'ip' => $document->approved_ip,
            ] : null,
        ])->render(), true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * Free text typed by the company or client (addresses, bank details, notes…) for a
     * right-to-left page. In lines that contain Arabic, left-to-right runs with spaces or
     * punctuation (an IBAN, a phone, an email) are wrapped in LRE…PDF marks so they keep their
     * order. Lines without Arabic are left alone: TCPDF already prints them correctly, and
     * marks around a whole line would print as boxes.
     */
    public static function bidi(?string $text): string
    {
        $lines = explode("\n", str_replace("\r", '', trim((string) $text)));

        return implode("\n", array_map(function (string $line) {
            if (! preg_match('/\p{Arabic}/u', $line)) {
                return $line;
            }

            return preg_replace_callback(
                '/[A-Za-z0-9+@][A-Za-z0-9 .,:\/+@#_-]*[A-Za-z0-9]/u',
                fn (array $m) => preg_match('/[ .:\/@+-]/', $m[0]) ? "\u{202A}{$m[0]}\u{202C}" : $m[0],
                $line,
            );
        }, $lines));
    }

    public function filename(Document $document): string
    {
        return $document->displayNumber().'.pdf';
    }

    /**
     * Branding images as TCPDF inline data ("@" + base64): read from private storage,
     * never fetched over HTTP. Uses the images as they are now, like the screen does.
     *
     * @return array<string, ?string>
     */
    private function images(Document $document): array
    {
        $images = [];

        foreach (['logo', 'stamp', 'signature'] as $kind) {
            $path = $document->company->getAttribute($kind);
            $images[$kind] = $path && Storage::disk('local')->exists($path)
                ? '@'.base64_encode(Storage::disk('local')->get($path))
                : null;
        }

        return $images;
    }
}
