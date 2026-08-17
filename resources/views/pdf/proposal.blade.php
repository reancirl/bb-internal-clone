<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a202c; padding: 36px 44px; }
        .header { width: 100%; border-bottom: 3px solid #1a202c; padding-bottom: 14px; margin-bottom: 20px; }
        .header td { vertical-align: top; }
        .company { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; }
        .company-sub { color: #64748b; font-size: 10px; margin-top: 2px; }
        .doc-meta { text-align: right; }
        .doc-title { font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .doc-number { color: #64748b; margin-top: 2px; }
        .badge { display: inline-block; margin-top: 6px; padding: 2px 10px; border: 1px solid #94a3b8; border-radius: 10px; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #475569; }
        table.meta { width: 100%; margin-bottom: 20px; }
        table.meta td { vertical-align: top; width: 50%; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; margin-bottom: 3px; }
        .value { font-size: 12px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.lines th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; text-align: left; padding: 6px 8px; border-bottom: 2px solid #1a202c; }
        table.lines th.num, table.lines td.num { text-align: right; }
        table.lines td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr.category td { background: #f1f5f9; font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; padding-top: 7px; }
        tr.total td { border-top: 2px solid #1a202c; border-bottom: none; font-size: 13px; font-weight: bold; padding-top: 10px; }
        .section { margin-top: 18px; }
        .section h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 5px; }
        .section p { font-size: 11px; line-height: 1.5; white-space: pre-line; }
        .signatures { width: 100%; margin-top: 44px; }
        .signatures td { width: 50%; padding-right: 30px; }
        .sig-line { border-top: 1px solid #1a202c; margin-top: 34px; padding-top: 4px; font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="company">{{ config('app.name', 'BuffaloBuilt') }}</div>
                <div class="company-sub">General Contractor &middot; Sheridan, Wyoming</div>
            </td>
            <td class="doc-meta">
                <div class="doc-title">{{ $proposal->title }}</div>
                <div class="doc-number">{{ $proposal->number }} &middot; {{ $proposal->created_at->format('M j, Y') }}</div>
                <span class="badge">{{ strtoupper($proposal->status) }}</span>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Prepared for</div>
                <div class="value">{{ $proposal->project?->client_name ?: '—' }}</div>
                @if ($proposal->project?->address)
                    <div class="value">{{ $proposal->project->address }}</div>
                @endif
            </td>
            <td>
                <div class="label">Project</div>
                <div class="value">{{ $proposal->project?->name }}</div>
                @if ($proposal->valid_until)
                    <div class="label" style="margin-top: 8px;">Valid until</div>
                    <div class="value">{{ $proposal->valid_until->format('M j, Y') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th>Unit</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $currentCategory = false; @endphp
            @foreach ($proposal->lines as $line)
                @if ($line->category !== $currentCategory)
                    @php $currentCategory = $line->category; @endphp
                    <tr class="category"><td colspan="5">{{ $currentCategory ?: 'General' }}</td></tr>
                @endif
                <tr>
                    <td>{{ $line->item }}</td>
                    <td class="num">{{ $line->qty !== null ? number_format((float) $line->qty, 2) : '—' }}</td>
                    <td>{{ $line->unit ?: '—' }}</td>
                    <td class="num">{{ $line->unit_price_cents !== null ? '$'.number_format($line->unit_price_cents / 100, 2) : '—' }}</td>
                    <td class="num">{{ $line->total_cents !== null ? '$'.number_format($line->total_cents / 100, 2) : 'TBD' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Proposal Total</td>
                <td class="num">${{ number_format($proposal->total_cents / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($proposal->payment_terms)
        <div class="section">
            <h3>Payment Terms</h3>
            <p>{{ $proposal->payment_terms }}</p>
        </div>
    @endif

    @if ($proposal->notes)
        <div class="section">
            <h3>Notes</h3>
            <p>{{ $proposal->notes }}</p>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="sig-line">Customer Signature &amp; Date</div></td>
            <td><div class="sig-line">{{ config('app.name', 'BuffaloBuilt') }} Representative &amp; Date</div></td>
        </tr>
    </table>

    <div class="footer">
        Prices reflect the estimate as of {{ $proposal->created_at->format('M j, Y') }} and are honored through
        {{ $proposal->valid_until?->format('M j, Y') ?? 'the date noted above' }}. Lines marked TBD will be priced before contract.
    </div>
</body>
</html>
