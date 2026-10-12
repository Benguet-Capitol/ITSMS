<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <title>Ticket Report</title>
    <style>
        @page {
            margin: 18px 22px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .logo-cell-left {
            width: 70px;
            text-align: left;
        }

        .logo-cell-right {
            width: 130px;
            text-align: right;
        }

        .logo-cell-left img {
            width: 60px;
            height: 60px;
        }

        .logo-cell-right img {
            width: 55px;
            height: 55px;
            margin-left: 6px;
        }

        .header-text-cell {
            text-align: center;
            vertical-align: middle;
        }

        .subtitle {
            font-size: 10px;
            margin-bottom: 10px;
            color: #555;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .meta-table td {
            border: 1px solid #cfcfcf;
            padding: 4px 6px;
            font-size: 9px;
        }

        .meta-label {
            width: 90px;
            font-weight: bold;
            background: #f2f2f2;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #bfbfbf;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
            white-space: normal;
        }

        .report-table th {
            background: #e9ecef;
            text-transform: uppercase;
            font-size: 8px;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .no-col          { width: 4%; }
        .ticket-col       { width: 10%; }
        .date-col         { width: 8%; }
        .office-col       { width: 12%; }
        .client-col       { width: 12%; }
        .concern-col      { width: 18%; }
        .category-col     { width: 9%; }
        .technicians-col  { width: 13%; }
        .status-col       { width: 8%; }

        .no-records td {
            text-align: center;
            padding: 12px;
            font-style: italic;
        }

        .footer-note {
            margin-top: 8px;
            font-size: 8px;
            color: #666;
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell-left">
                @if ($logo_province)
                    <img src="{{ $logo_province }}" alt="Benguet Seal">
                @endif
            </td>
            <td class="header-text-cell">
                <div class="title">Ticket Report as of {{ $generatedAt->format('F Y') }}</div>
                <div class="subtitle">Generated ticket listing based on selected filters</div>
            </td>
            <td class="logo-cell-right">
                @if ($logo_bagong_pilipinas)
                    <img src="{{ $logo_bagong_pilipinas }}" alt="Bagong Pilipinas">
                @endif
                @if ($logo_mis)
                    <img src="{{ $logo_mis }}" alt="MIS">
                @endif
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Generated as of</td>
            <td>{{ $generatedAt->format('Y-m-d h:i A') }}</td>
            <td class="meta-label">Total Records</td>
            <td>{{ count($rows) }}</td>
        </tr>
        <tr>
            <td class="meta-label">Office</td>
            <td>{{ $filters['office'] ?: 'All' }}</td>
            <td class="meta-label">Category</td>
            <td>{{ $filters['category'] ?: 'All' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Technician</td>
            <td>{{ $filters['technician'] ?: 'All' }}</td>
            <td class="meta-label">Status Group</td>
            <td>{{ $filters['status_group'] ?: 'All' }}</td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th class="no-col">NO.</th>
                <th class="ticket-col">TICKET #</th>
                <th class="date-col">DATE</th>
                <th class="office-col">OFFICE</th>
                <th class="client-col">CLIENT</th>
                <th class="concern-col">CONCERN</th>
                <th class="category-col">CATEGORY</th>
                <th class="technicians-col">TECHNICIAN(S)</th>
                <th class="status-col">QUERY STATUS</th>
                <th class="status-col">REQUEST STATUS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $row['ticket_number'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['office'] ?: '—' }}</td>
                    <td>{{ $row['client'] ?: '—' }}</td>
                    <td>{{ $row['concern'] }}</td>
                    <td>{{ $row['category'] ?: '—' }}</td>
                    <td style="white-space: pre-line;">{{ $row['technicians'] ?: '—' }}</td>
                    <td class="text-center">{{ $row['query_status'] ?: '—' }}</td>
                    <td class="text-center">{{ $row['request_status'] ?: '—' }}</td>
                </tr>
            @empty
                <tr class="no-records">
                    <td colspan="10">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-note">
        Generated by ITSMS
    </div>
</body>
</html>
