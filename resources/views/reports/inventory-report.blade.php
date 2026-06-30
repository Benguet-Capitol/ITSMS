<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <title>Inventory Report</title>
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
        .property-col    { width: 11%; }
        .employee-col    { width: 14%; }
        .division-col    { width: 13%; }
        .office-col      { width: 11%; }
        .item-type-col   { width: 10%; }
        .brand-col       { width: 16%; }
        .components-col  { width: 21%; }

        .no-records td {
            text-align: center;
            padding: 12px;
            font-style: italic;
        }

        .eol-row td {
            background-color: #fff3cd;
        }

        .eol-badge {
            display: inline-block;
            font-size: 7px;
            font-weight: bold;
            background-color: #e65c00;
            color: #ffffff;
            padding: 1px 4px;
            border-radius: 3px;
            letter-spacing: 0.3px;
            margin-top: 3px;
        }

        .primary-row td {
            background-color: #dbeafe;
        }

        .primary-row.eol-row td {
            background-color: #fef3c7;
        }

        .primary-badge {
            display: inline-block;
            font-size: 7px;
            font-weight: bold;
            background-color: #1d4ed8;
            color: #ffffff;
            padding: 1px 4px;
            border-radius: 3px;
            letter-spacing: 0.3px;
            margin-top: 3px;
        }

        .summary-title {
            margin-top: 14px;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: bold;
        }

        .summary-table {
            width: 45%;
            border-collapse: collapse;
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

        .legend {
            margin-top: 10px;
            font-size: 8px;
            color: #444;
        }

        .legend-swatch {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #fff3cd;
            border: 1px solid #c8a000;
            margin-right: 4px;
            vertical-align: middle;
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
    <div class="title">Inventory Report as of {{ $generatedAt->format('F Y') }}</div>
    <div class="subtitle">Generated inventory listing based on selected filters</div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Generated as of</td>
            <td>{{ $generatedAt->format('Y-m-d h:i A') }}</td>
            <td class="meta-label">Total Records</td>
            <td>{{ count($rows) }}</td>
        </tr>
        <tr>
            <td class="meta-label">Item Type</td>
            <td>{{ $filters['item_type'] ?: 'All' }}</td>
            <td class="meta-label">Employee</td>
            <td>{{ $filters['employee'] ?: 'All' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Office</td>
            <td>{{ $filters['office'] ?: 'All' }}</td>
            <td class="meta-label">Status</td>
            <td>{{ $filters['status'] ?: 'All' }}</td>
        </tr>
        <tr>
            <td class="meta-label">End of Useful Life (EOL) Items</td>
            <td style="background-color: #fff3cd; font-weight: bold; color: #7a4f00;">
                {{ $obsoleteCount }} item{{ $obsoleteCount !== 1 ? 's' : '' }}
                ({{ count($rows) > 0 ? round(($obsoleteCount / count($rows)) * 100, 1) : 0 }}% of total)
            </td>
            <td colspan="2"></td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th class="no-col">NO.</th>
                <th class="division-col">DIVISION / SECTION</th>
                <th class="employee-col">ACTUAL USER</th>
                <th class="property-col">PROPERTY NUMBER</th>
                <th class="office-col">DATE ACQUIRED</th>
                <th class="item-type-col">ITEM TYPE</th>
                <th class="brand-col">BRAND / MODEL</th>
                <th class="components-col">CHILD COMPONENTS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr class="{{ $row['is_obsolete'] ? 'eol-row' : '' }} {{ $row['is_primary'] ? 'primary-row' : '' }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $row['division_section'] ?: '—' }}</td>
                    <td>{{ $row['employee_name'] ?: '—' }}</td>
                    <td>{{ $row['property_number'] ?: '—' }}</td>
                    <td>
                        {{ $row['date_acquired'] ?: '—' }}
                        @if ($row['is_obsolete'])
                            <br><span class="eol-badge">EOL</span>
                        @endif
                    </td>
                    <td>
                      {{ $row['item_type'] ?: '—' }}
                      @if ($row['is_primary'])
                        <br><span class="primary-badge">PARENT</span>
                      @endif
                    </td>
                     
                    <td>{{ $row['brand_model'] ?: '—' }}</td>
                    <td style="white-space: pre-line;">{{ $row['child_components'] ?: '—' }}</td>
                </tr>
            @empty
                <tr class="no-records">
                    <td colspan="8">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="legend">
        <span class="legend-swatch"></span>
        Highlighted rows indicate items acquired more than <strong>5 years</strong> ago and are considered <strong>End of Useful Life (EOL)</strong>.
        &nbsp;&nbsp;
        <span style="display:inline-block; width:10px; height:10px; background-color:#dbeafe; border:1px solid #1d4ed8; vertical-align:middle; margin-right:4px;"></span>
        Blue rows indicate <strong>System Unit</strong> or <strong>Laptop</strong> items (parent devices).
    </div>

    <div class="summary-title">Item Type Summary</div>

    <table class="summary-table">
        <thead>
            <tr>
                <th>Item Type</th>
                <th>Count</th>
                <th>End of Useful Life (EOL) Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summary as $itemType => $data)
                <tr>
                    <td>{{ $itemType ?: 'Unspecified' }}</td>
                    <td>{{ $data['count'] }}</td>
                    <td>
                        @if ($data['obsolete'] > 0)
                            <span style="color: #7a4f00; font-weight: bold;">{{ $data['obsolete'] }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No summary available.</td>
                </tr>
            @endforelse

            {{-- Total row --}}
            @if (count($rows) > 0)
                <tr style="border-top: 2px solid #bfbfbf;">
                    <td style="font-weight: bold;">
                        Items Total  
                    </td>
                    <td style="font-weight: bold;">
                        {{ array_sum(array_column($summary->toArray(), 'count')) }}
                    </td>
                    <td style="background-color: #fff3cd; font-weight: bold; color: #7a4f00;">
                        {{ $obsoleteCount }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer-note">
        Generated by ITSMS
    </div>
</body>
</html>