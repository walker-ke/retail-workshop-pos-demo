<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventory Count Report</title>
<style>
    body{
        font-family: 'Segoe UI', Arial, sans-serif;
        color: #222;
        margin: 24px;
    }
    .report-toolbar{
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-bottom: 16px;
    }
    .report-toolbar button{
        background: #7a1f36;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 8px 16px;
        font-size: 13px;
        cursor: pointer;
    }
    .report-header{
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #7a1f36;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .report-title{
        font-size: 20px;
        font-weight: 700;
        color: #7a1f36;
    }
    .report-meta{
        font-size: 12px;
        color: #555;
        text-align: right;
    }
    .group-heading{
        background: #f4f0f1;
        font-weight: 700;
        font-size: 13px;
        padding: 6px 10px;
        margin-top: 18px;
        border-left: 4px solid #7a1f36;
    }
    table{
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    th, td{
        border: 1px solid #ddd;
        padding: 6px 8px;
        font-size: 12.5px;
        text-align: left;
    }
    th{
        background: #fafafa;
    }
    .col-count, .col-diff{
        width: 110px;
    }
    .col-qty{
        width: 80px;
        text-align: right;
    }
    @media print{
        .report-toolbar{ display: none; }
        body{ margin: 0.5cm; }
    }
@media screen { .report-table-scroll { overflow-x: auto; } table { min-width: 640px; } .report-header { flex-wrap: wrap; gap: 12px; } .report-toolbar { flex-wrap: wrap; } .report-toolbar button { min-height: 44px; } }
</style>
</head>
<body>
    <div class="report-toolbar">
        <button onclick="window.print()">Print</button>
    </div>

    <div class="report-header">
        <div>
            <div class="report-title">DEMO RETAIL WORKSHOP</div>
            <div>Inventory Count Report{{ $selectedBrand ? ' — '.$selectedBrand : '' }}</div>
        </div>
        <div class="report-meta">
            Shop: {{ $shopName }}<br>
            Generated: {{ now()->format('d/m/Y H:i') }}<br>
            Products: {{ $prod->count() }}
        </div>
    </div>

    @php $currentGroup = null; @endphp
    @foreach($prod as $item)
        @if($item->brand !== $currentGroup)
            @if($currentGroup !== null)
                </tbody>
                </table></div>
            @endif
            @php $currentGroup = $item->brand; @endphp
            <div class="group-heading">{{ $item->brand ?: 'No group' }}</div>
            <div class="report-table-scroll" role="region" aria-label="Inventory table" tabindex="0"><table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th class="col-qty">System qty</th>
                        <th class="col-count">Counted qty</th>
                        <th class="col-diff">Difference</th>
                    </tr>
                </thead>
                <tbody>
        @endif
        <tr>
            <td>{{ $item->product_code }}</td>
            <td>{{ $item->product_name }}</td>
            <td class="col-qty">{{ $item->quantity }}</td>
            <td class="col-count">&nbsp;</td>
            <td class="col-diff">&nbsp;</td>
        </tr>
    @endforeach
    @if($currentGroup !== null)
        </tbody>
        </table></div>
    @else
        <p>No products to count.</p>
    @endif
</body>
</html>
