{{--
    PDF layout for TCPDF (rendered by App\Services\PdfRenderer). TCPDF reads a subset of HTML:
    tables for layout, inline styles, widths in %. The page is right-to-left, so the first
    cell of a row is on the right.

    Left-to-right values (emails, phones, numbers) inside Arabic lines are wrapped in
    LRE…PDF marks by $ltr(), otherwise "QT-2026-0001" prints as "2026-0001-QT".
    Values alone in a cell need no marks (and would print them as boxes).
--}}
@php
    use App\Services\PdfRenderer;
    use App\Support\Money;
    use Illuminate\Support\Carbon;

    $ltr = fn (?string $text) => $text === null || $text === '' ? '' : "\u{202A}{$text}\u{202C}";
    // Text typed by people (names, addresses, notes…): escaped, line breaks kept, LTR runs protected.
    $text = fn (?string $value) => nl2br(e(PdfRenderer::bidi($value)), false);
    $date = fn (?string $iso) => $iso ? Carbon::parse($iso)->locale('ar')->translatedFormat('j F Y') : '';
    $isInvoice = $d['type'] === 'invoice';
    $title = $isInvoice
        ? ($company['vat_number'] ? __('ui.documents.paper.tax_invoice_title') : __('ui.documents.paper.invoice_title'))
        : __('ui.documents.paper.quote_title');
    $hasLineDiscount = collect($d['lines'])->contains(fn ($line) => (float) $line['discount_percent'] > 0);
    $hasTax = collect($d['lines'])->contains(fn ($line) => (float) $line['tax_rate'] > 0);
    $hasDiscount = (float) $d['discount'] > 0;
    // Column widths (%), first = rightmost.
    $widths = ['n' => 5, 'item' => 42 - ($hasLineDiscount ? 8 : 0) - ($hasTax ? 8 : 0), 'qty' => 13, 'price' => 18];
@endphp
<style>
    td { line-height: 1.45; }
    .muted { color: #6b7280; }
    .small { font-size: 8pt; }
    .label { color: #6b7280; font-size: 8pt; }
    .th { color: #ffffff; font-weight: bold; font-size: 8.5pt; }
    .num { text-align: left; }
</style>

{{-- Letterhead --}}
<table cellpadding="0">
    <tr>
        <td width="58%">
            @if ($images['logo'])
                <img src="{{ $images['logo'] }}" height="48"><br>
            @endif
            <b style="font-size: 12pt;">{!! $text($company['legal_name'] ?: $company['name']) !!}</b><br>
            @if ($company['address'])
                <span class="muted">{!! $text($company['address']) !!}</span><br>
            @endif
            <span class="small muted">
                @if ($company['vat_number']){{ __('ui.documents.paper.vat', ['number' => '']) }}{{ $ltr($company['vat_number']) }}&nbsp;&nbsp;&nbsp;@endif
                @if ($company['cr_number']){{ __('ui.documents.paper.cr', ['number' => '']) }}{{ $ltr($company['cr_number']) }}@endif
            </span>
            {{-- Each value after an Arabic label: a line of only left-to-right text prints its marks as boxes. --}}
            @if ($company['phone'] || $company['email'])
                <br><span class="small muted">
                    @if ($company['phone']){{ __('ui.documents.paper.phone') }} {{ $ltr($company['phone']) }}&nbsp;&nbsp;&nbsp;@endif
                    @if ($company['email']){{ __('ui.documents.paper.email') }} {{ $ltr($company['email']) }}@endif
                </span>
            @endif
        </td>
        <td width="42%" style="text-align: left;">
            <b style="font-size: 20pt; color: {{ $brand }};">{{ $title }}</b><br>
            <b style="font-size: 11pt;">{{ $d['number'] }}</b><br>
            <span class="label">{{ __('ui.documents.issue_date') }}:</span> {{ $date($d['issue_date']) }}<br>
            @if ($d['valid_until'])
                <span class="label">{{ __('ui.documents.valid_until') }}:</span> {{ $date($d['valid_until']) }}<br>
            @endif
            @if ($d['due_date'])
                <span class="label">{{ __('ui.documents.due_date') }}:</span> {{ $date($d['due_date']) }}
            @endif
        </td>
    </tr>
</table>

<p style="font-size: 4pt;">&nbsp;</p>

{{-- Client --}}
@if ($client)
    <table cellpadding="7">
        <tr>
            <td style="background-color: #f4f4f5;">
                <span class="label">{{ __('ui.documents.paper.to') }}</span><br>
                <b>{!! $text($client['name']) !!}</b>
                @if ($client['contact_name'])
                    <br><span class="muted">{!! $text($client['contact_name']) !!}</span>
                @endif
                @if ($client['address'])
                    <br><span class="muted">{!! $text($client['address']) !!}</span>
                @endif
                @if ($client['vat_number'] || $client['cr_number'])
                    <br><span class="small muted">
                        @if ($client['vat_number']){{ __('ui.documents.paper.vat', ['number' => '']) }}{{ $ltr($client['vat_number']) }}&nbsp;&nbsp;&nbsp;@endif
                        @if ($client['cr_number']){{ __('ui.documents.paper.cr', ['number' => '']) }}{{ $ltr($client['cr_number']) }}@endif
                    </span>
                @endif
            </td>
        </tr>
    </table>
    <p style="font-size: 4pt;">&nbsp;</p>
@endif

{{-- Lines --}}
<table cellpadding="5">
    <thead>
        <tr style="background-color: {{ $brand }};">
            <td class="th" width="{{ $widths['n'] }}%">{{ __('ui.documents.paper.col_number') }}</td>
            <td class="th" width="{{ $widths['item'] }}%">{{ __('ui.documents.paper.col_item') }}</td>
            <td class="th" width="{{ $widths['qty'] }}%">{{ __('ui.documents.paper.col_qty') }}</td>
            <td class="th num" width="{{ $widths['price'] }}%">{{ __('ui.documents.paper.col_price') }}</td>
            @if ($hasLineDiscount)<td class="th num" width="8%">{{ __('ui.documents.paper.col_discount') }}</td>@endif
            @if ($hasTax)<td class="th num" width="8%">{{ __('ui.documents.paper.col_tax') }}</td>@endif
            <td class="th num" width="22%">{{ __('ui.documents.paper.col_amount') }}</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($d['lines'] as $i => $line)
            <tr style="background-color: {{ $i % 2 ? '#fafafa' : '#ffffff' }};" nobr="true">
                <td width="{{ $widths['n'] }}%" class="muted">{{ $i + 1 }}</td>
                {{-- Kept on one line: TCPDF turns the template's own line breaks into blank space. --}}
                <td width="{{ $widths['item'] }}%">{!! $text($line['name']) !!}@if ($line['description'])<div class="small muted">{!! $text($line['description']) !!}</div>@endif</td>
                <td width="{{ $widths['qty'] }}%">{{ $line['qty'] }} {{ $line['unit'] }}</td>
                <td width="{{ $widths['price'] }}%" class="num">{{ Money::group($line['unit_price']) }}</td>
                @if ($hasLineDiscount)<td width="8%" class="num">{{ (float) $line['discount_percent'] > 0 ? $line['discount_percent'].'%' : '—' }}</td>@endif
                @if ($hasTax)<td width="8%" class="num">{{ (float) $line['tax_rate'] > 0 ? $line['tax_rate'].'%' : '—' }}</td>@endif
                <td width="22%" class="num"><b>{{ Money::group($line['net']) }}</b></td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="font-size: 6pt;">&nbsp;</p>

{{-- Amount in words + totals --}}
<table cellpadding="0" nobr="true">
    <tr>
        <td width="52%">
            <span class="label">{{ __('ui.documents.paper.amount_in_words') }}</span><br>
            <b>{{ $d['amount_in_words'] }}</b>
        </td>
        <td width="6%"></td>
        <td width="42%">
            <table cellpadding="3">
                <tr>
                    <td width="55%" class="muted">{{ __('ui.documents.totals.subtotal') }}</td>
                    <td width="45%" class="num">{{ Money::group($d['subtotal']) }}</td>
                </tr>
                @if ($hasDiscount)
                    <tr>
                        <td class="muted">{{ __('ui.documents.totals.discount') }}{{ $d['discount_label'] ? ' ('.$d['discount_label'].')' : '' }}</td>
                        <td class="num">-{{ Money::group($d['discount']) }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('ui.documents.totals.taxable') }}</td>
                        <td class="num">{{ Money::group($d['taxable']) }}</td>
                    </tr>
                @endif
                @foreach ($d['tax_breakdown'] as $tax)
                    <tr>
                        <td class="muted">{{ $tax['name'] ?? __('ui.documents.totals.tax') }} {{ $tax['rate'] }}%</td>
                        <td class="num">{{ Money::group($tax['amount']) }}</td>
                    </tr>
                @endforeach
                <tr style="background-color: #f4f4f5;">
                    <td><b style="font-size: 11pt;">{{ __('ui.documents.totals.total') }}</b></td>
                    <td class="num"><b style="font-size: 11pt; color: {{ $brand }};">{{ Money::group($d['total']) }} {{ $d['currency'] }}</b></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Approval record --}}
@if ($approval)
    <p style="font-size: 6pt;">&nbsp;</p>
    <table cellpadding="7" nobr="true">
        <tr>
            <td style="border: 1px solid #15803d; background-color: #f0fdf4; color: #14532d;">
                <b>{{ __('ui.pdf.approved_by', ['name' => $approval['name'], 'date' => $approval['date']]) }}</b>
                @if ($approval['ip'])
                    <br><span class="small">{{ __('ui.pdf.approved_ip') }} {{ $ltr($approval['ip']) }}</span>
                @endif
                <br><span class="small">{{ __('ui.public.approve_note') }}</span>
            </td>
        </tr>
    </table>
@endif

{{-- Notes, terms, bank details --}}
@php
    $blocks = array_filter([
        __('ui.documents.paper.notes') => $d['notes'],
        __('ui.documents.paper.terms') => $d['terms'],
        __('ui.documents.paper.bank_details') => $isInvoice ? $company['bank_details'] : null,
    ]);
@endphp
@if ($blocks)
    <p style="font-size: 6pt;">&nbsp;</p>
    @foreach ($blocks as $heading => $body)
        <table cellpadding="0" nobr="true">
            <tr>
                <td>
                    <span class="label">{{ $heading }}</span><br>
                    <span class="small">{!! $text($body) !!}</span>
                </td>
            </tr>
        </table>
        <p style="font-size: 4pt;">&nbsp;</p>
    @endforeach
@endif

{{-- Signature and stamp --}}
@if ($images['signature'] || $images['stamp'])
    <table cellpadding="0" nobr="true">
        <tr>
            <td width="50%"></td>
            <td width="50%" style="text-align: left;">
                @if ($images['signature'])<img src="{{ $images['signature'] }}" height="55">&nbsp;&nbsp;@endif
                @if ($images['stamp'])<img src="{{ $images['stamp'] }}" height="70">@endif
            </td>
        </tr>
    </table>
@endif
