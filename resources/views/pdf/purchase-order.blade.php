<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $po->number }}</title>
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
        table.meta td { vertical-align: top; width: 33%; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; margin-bottom: 3px; }
        .value { font-size: 12px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.lines th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; text-align: left; padding: 6px 8px; border-bottom: 2px solid #1a202c; }
        table.lines th.num, table.lines td.num { text-align: right; }
        table.lines td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr.total td { border-top: 2px solid #1a202c; border-bottom: none; font-size: 13px; font-weight: bold; padding-top: 10px; }
        .section { margin-top: 18px; }
        .section h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 5px; }
        .section p { font-size: 11px; line-height: 1.5; white-space: pre-line; }
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
                <div class="doc-title">Purchase Order</div>
                <div class="doc-number">{{ $po->number }} &middot; {{ $po->created_at->format('M j, Y') }}</div>
                <span class="badge">{{ strtoupper($po->status) }}</span>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Supplier</div>
                <div class="value">{{ $po->vendor_name }}</div>
                @if ($po->vendor?->location)
                    <div class="value">{{ $po->vendor->location }}</div>
                @endif
                @if ($po->vendor?->phone)
                    <div class="value">{{ $po->vendor->phone }}</div>
                @endif
            </td>
            <td>
                <div class="label">Deliver to</div>
                <div class="value">{{ $po->project?->name }}</div>
                @if ($po->project?->address)
                    <div class="value">{{ $po->project->address }}</div>
                @endif
            </td>
            <td>
                @if ($po->expected_delivery)
                    <div class="label">Expected delivery</div>
                    <div class="value">{{ $po->expected_delivery->format('M j, Y') }}</div>
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
            @foreach ($po->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->qty !== null ? number_format((float) $item->qty, 2) : '—' }}</td>
                    <td>{{ $item->unit ?: '—' }}</td>
                    <td class="num">{{ $item->unit_price_cents !== null ? '$'.number_format($item->unit_price_cents / 100, 2) : '—' }}</td>
                    <td class="num">{{ $item->total_cents !== null ? '$'.number_format($item->total_cents / 100, 2) : 'TBD' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Order Total</td>
                <td class="num">${{ number_format($po->total_cents / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($po->notes)
        <div class="section">
            <h3>Notes</h3>
            <p>{{ $po->notes }}</p>
        </div>
    @endif

    <div class="footer">
        Please reference {{ $po->number }} on all invoices and delivery paperwork.
    </div>
</body>
</html>
