<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Inspection Report</title>
    <style>
        @page {
            margin: 36px 40px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            line-height: 1.35;
            margin-bottom: 10px;
        }

        .title {
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 8px;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .details td {
            padding: 3px 4px;
            vertical-align: bottom;
        }

        .details .label {
            width: 17%;
            font-weight: bold;
            white-space: nowrap;
        }

        .details .value {
            border-bottom: 1px solid #222;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .items th,
        .items td {
            border: 1px solid #222;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items th {
            background: #e5e7eb;
            font-size: 8px;
            text-transform: uppercase;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .result-failed {
            color: #b91c1c;
            font-weight: bold;
        }

        .result-partial {
            color: #b45309;
            font-weight: bold;
        }

        .box {
            border: 1px solid #222;
            padding: 6px;
            min-height: 48px;
            margin-bottom: 14px;
            white-space: pre-line;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .signatures td {
            width: 33%;
            padding: 28px 10px 0;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #222;
            padding-top: 2px;
            font-weight: bold;
        }

        .signature-role {
            font-size: 8px;
            color: #374151;
        }

        .footer {
            margin-top: 18px;
            font-size: 7px;
            color: #6b7280;
        }
    </style>
</head>
<body>

@php
    $record = $requestRecord;
    $longDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : '';
    $money = fn ($amount) => $amount === null ? '' : number_format((float) $amount, 2);
    $overall = $record->overallResult();
    // The inspectors sign; the IT staff who handled the request (workflow
    // personnel, docs/adr/0002-request-workflow.md) are listed above.
    $inspectors = $record->inspectors;
    $itPersonnel = $record->personnel->pluck('display_name')->join(', ');
@endphp

<div class="header">
    <div>Republic of the Philippines</div>
    <div><strong>PROVINCE OF BENGUET</strong></div>
    <div>La Trinidad</div>
    <div>Provincial Governor's Office</div>
    <div>Information Technology/Management Information Systems Unit</div>
    <div class="title">INSPECTION REPORT</div>
</div>

<table class="details">
    <tr>
        <td class="label">Control No.:</td>
        <td class="value" style="width:33%">{{ $record->control_number }}</td>
        <td class="label">Date of Request:</td>
        <td class="value">{{ $longDate($record->date_of_request) }}</td>
    </tr>
    <tr>
        <td class="label">End User:</td>
        <td class="value" colspan="3">{{ $record->end_user_office }}</td>
    </tr>
    <tr>
        <td class="label">Procurement:</td>
        <td class="value">{{ $record->purchaseReference() }}</td>
        <td class="label">Supplier:</td>
        <td class="value">{{ $record->supplier }}</td>
    </tr>
    <tr>
        <td class="label">Date Inspected:</td>
        <td class="value">{{ $longDate($record->inspection_date) }}</td>
        <td class="label">Overall Result:</td>
        <td class="value {{ $overall ? 'result-'.$overall : '' }}">{{ $overall ? $results[$overall] : '' }}</td>
    </tr>
    <tr>
        <td class="label">IT Personnel:</td>
        <td class="value" colspan="3">{{ $itPersonnel }}</td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:6%">Qty</th>
            <th style="width:25%">Description</th>
            <th style="width:15%">Brand/Model</th>
            <th style="width:12%">Unit Cost</th>
            <th style="width:13%">Total</th>
            <th style="width:9%">Result</th>
            <th style="width:16%">Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($record->items ?? [] as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ $item['quantity'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td>{{ $item['brand'] }}</td>
                <td class="right">{{ $money($item['unit_cost']) }}</td>
                <td class="right">{{ $money(\App\Models\InspectionRequest::lineTotal($item)) }}</td>
                <td class="center {{ $item['result'] ? 'result-'.$item['result'] : '' }}">{{ $item['result'] ? $results[$item['result']] : '' }}</td>
                <td>{{ $item['remarks'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" class="right"><strong>Total Amount</strong></td>
            <td class="right"><strong>{{ $money($record->totalAmount()) }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

<div><strong>Remarks:</strong></div>
<div class="box">{{ $record->remarks }}</div>

<div><strong>Inspected by:</strong></div>
<table class="signatures">
    @forelse ($inspectors->chunk(3) as $row)
        <tr>
            @foreach ($row as $inspector)
                <td>
                    <div class="signature-line">{{ $inspector->name }}</div>
                    <div class="signature-role">{{ $inspector->designation() }}</div>
                </td>
            @endforeach
            @for ($i = $row->count(); $i < 3; $i++)
                <td></td>
            @endfor
        </tr>
    @empty
        <tr>
            <td><div class="signature-line">&nbsp;</div></td>
            <td></td>
            <td></td>
        </tr>
    @endforelse
</table>

<div class="footer">Printed {{ now()->format('F d, Y g:i A') }}</div>

</body>
</html>
