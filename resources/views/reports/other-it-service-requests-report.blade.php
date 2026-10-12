<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <title>Other IT Service Requests Report</title>
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

        .columns {
            width: 100%;
        }

        .columns td {
            vertical-align: top;
            width: 50%;
            padding: 0 8px 0 0;
        }

        .summary-title {
            margin-top: 4px;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: bold;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #cfcfcf;
            padding: 5px 6px;
            font-size: 9px;
        }

        .summary-table th {
            background: #f2f2f2;
            text-align: left;
        }

        .no-records td {
            text-align: center;
            padding: 10px;
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
                <div class="title">Other IT Service Requests Report as of {{ $generatedAt->format('F Y') }}</div>
                <div class="subtitle">Aggregate summary of other IT service requests</div>
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
            <td class="meta-label">Year Filter</td>
            <td>{{ $year }}</td>
        </tr>
        <tr>
            <td class="meta-label">Total Requests</td>
            <td colspan="3" style="font-weight: bold;">{{ $summary['total'] }}</td>
        </tr>
    </table>

    <div class="summary-title">Requests by Status</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Status</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['by_status'] as $status => $count)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="columns">
        <tr>
            <td>
                <div class="summary-title">Requests by Month</div>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['by_month'] as $month => $count)
                            <tr>
                                <td>{{ $month }}</td>
                                <td>{{ $count }}</td>
                            </tr>
                        @empty
                            <tr class="no-records">
                                <td colspan="2">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="summary-title">Requests by Office/Department</div>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Office/Department</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['by_office'] as $office => $count)
                            <tr>
                                <td>{{ $office }}</td>
                                <td>{{ $count }}</td>
                            </tr>
                        @empty
                            <tr class="no-records">
                                <td colspan="2">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td>
                <div class="summary-title">Requests by Assigned Personnel</div>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Assigned Personnel</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['by_personnel'] as $personnel => $count)
                            <tr>
                                <td>{{ $personnel }}</td>
                                <td>{{ $count }}</td>
                            </tr>
                        @empty
                            <tr class="no-records">
                                <td colspan="2">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Generated by ITSMS
    </div>
</body>
</html>
