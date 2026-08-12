<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, sans-serif;
        font-size: 9pt;
        color: #000;
        padding: 18px 24px;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }
    .header-table td {
        border: none;
        vertical-align: middle;
        padding: 0;
    }
    .logo-cell-left {
        width: 18%;
        text-align: center;
    }
    .logo-cell-left img {
        height: 55px;
        vertical-align: middle;
    }
    .logo-cell-left img.first-logo {
        margin-right: 12px;
    }
    .logo-cell-right {
        width: 18%;
        text-align: center;
    }
    .logo-cell-right img {
        height: 55px;
        vertical-align: middle;
    }
    .header-text-cell {
        width: 64%;
        text-align: center;
    }
    .header-text-cell p {
        font-size: 9pt;
        line-height: 1.4;
    }
    .header-text-cell .title {
        color: #00B0F0;
        font-size: 12pt;
        font-weight: bold;
        letter-spacing: 1px;
        margin-top: 2px;
    }

    table { width: 100%; border-collapse: collapse; }

    .info-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        font-size: 8.5pt;
        vertical-align: top;
    }

    .section-label {
        font-weight: bold;
        background: #f0f0f0;
        border: 1px solid #000;
        padding: 3px 5px;
        font-size: 8.5pt;
    }

    .component-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        font-size: 8pt;
        vertical-align: middle;
    }
    .component-table .col-header {
        font-weight: bold;
        background: #f0f0f0;
        text-align: center;
    }

    .checkbox {
        display: inline-block;
        width: 10px;
        height: 10px;
        border: 1px solid #000;
        margin-right: 4px;
        vertical-align: middle;
        text-align: center;
        line-height: 10px;
        font-size: 8pt;
    }
    .checked { background: #000; color: #fff; }

    .findings-box {
        border: 1px solid #000;
        min-height: 36px;
        padding: 4px 5px;
        font-size: 8.5pt;
        margin-bottom: -1px;
    }

    .recommendation-row td {
        border: 1px solid #000;
        padding: 4px 5px;
        font-size: 8pt;
        vertical-align: middle;
    }

    .signatory-table td {
        padding: 4px 5px;
        font-size: 8.5pt;
        vertical-align: top;
        border: none;
    }

    .notes {
        margin-top: 6px;
        font-size: 7.5pt;
        color: #333;
    }
    .notes p { line-height: 1.5; color: #ff0000 }

    .divider { border-top: 1px solid #000; margin: 4px 0; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .bold { font-weight: bold; }
    .underline { text-decoration: underline; }
    .mt-2 { margin-top: 6px; }
    .label-muted { font-size: 7.5pt; color: #555; }

    .footer-note {
        margin-top: 8px;
        font-size: 8px;
        color: #666;
        text-align: right;
    }
    </style>
</head>
<body>

    {{-- HEADER --}}
    {{-- HEADER WITH 3 LOGOS (2 left, 1 right) --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell-left">
                @if($logo_province)
                    <img src="{{ $logo_province }}" class="first-logo" alt="Province Seal">
                @endif
            </td>
            <td class="header-text-cell">
                <p>PROVINCE OF BENGUET</p>
                <p style="color: #1F4E78">MANAGEMENT INFORMATION SERVICES</p>
                <p class="title">REPAIR / ASSESSMENT REPORT</p>
            </td>
            <td class="logo-cell-right">
                @if($logo_bagong_pilipinas)
                    <img src="{{ $logo_bagong_pilipinas }}" alt="Bagong Pilipinas">
                @endif
                @if($logo_mis)
                    <img src="{{ $logo_mis }}" alt="MIS Logo">
                @endif
            </td>
        </tr>
    </table>

    {{-- TOP INFO GRID --}}
    <table class="info-table" style="margin-top: 6px;">
        <tr>
            <td style="width:50%">
                
            </td>
            <td style="width:50%">
                <span class="label-muted" style="color: #ff0000">CONTROL NO.:</span>
                <span class="bold"> {{ $control_no }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label-muted">Date:</span>
                <span class="bold"> {{ $date }}</span>
            </td>
            <td>
                <span class="label-muted">Department / Office:</span> {{ $office }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="label-muted">Name of Item:</span> {{ $item_name }}
            </td>
            <td>
                <span class="label-muted">Property No.:</span> {{ $property_no }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="label-muted">Date Acquired:</span> {{ $date_acquired }}
            </td>
            <td>
                <span class="label-muted">Issued to:</span> {{ $issued_to }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="label-muted row-span-2">Model / Description:</span> {{ $brand_model }}
            </td>
            <td>
                <span class="label-muted">Serial Number:</span> {{ $serial_number }}
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <span class="label-muted">Acquisition Cost:</span>
                <span style="font-family: 'DejaVu Sans', sans-serif;">&#8369;</span>{{ number_format($assessment->acquisition_cost, 2) }}
            </td>
        </tr>
    </table>

    {{-- END-USER COMPLAINT --}}
    <div class="section-label mt-2">END-USER'S COMPLAINT:</div>
    <div class="findings-box">{{ $concern }}</div>

    {{-- COMPONENTS TABLE --}}
    <table class="component-table" style="margin-top: -1px;">
        <tr>
            <td colspan="2" class="col-header" style="width:50%">SYSTEM UNIT</td>
            <td class="col-header" style="width:15%">REMARKS</td>
            <td colspan="2" class="col-header" style="width:35%">PERIPHERALS / ACCESSORIES</td>
            <td class="col-header" style="width:15%">REMARKS</td>
        </tr>
        @foreach(array_map(null, $system_unit_parts, $peripherals) as $pair)
        <tr>
            <td colspan="2" style="width:35%">
                @php $checked = in_array($pair[0], $components ?? []); @endphp
                <span class="checkbox {{ $checked ? 'checked' : '' }}">
                  <span style="font-family: 'DejaVu Sans', sans-serif;">{{ $checked ? '✔' : '' }}</span>
                </span>
                {{ $pair[0] }}
            </td>
            <td style="width:15%"></td>
            <td colspan="2" style="width:35%">
                @if($pair[1])
                    @php $checked2 = in_array($pair[1], $components ?? []); @endphp
                    <span class="checkbox {{ $checked2 ? 'checked' : '' }}">
                      <span style="font-family: 'DejaVu Sans', sans-serif;">{{ $checked2 ? '✔' : '' }}</span>
                    </span>
                    {{ $pair[1] }}
                @endif
            </td>
            <td style="width:15%"></td>
        </tr>
        @endforeach
    </table>

    {{-- FINDINGS --}}
    <div class="section-label mt-2">FINDINGS:</div>
    <div class="findings-box">{{ $assessment->findings }}</div>

    {{-- RECOMMENDATIONS --}}
    <div class="section-label">RECOMMENDATIONS:</div>
    <div class="findings-box">{{ $assessment->recommendations }}</div>

    {{-- REPLACEMENT PARTS --}}
    <table class="recommendation-row" style="margin-top: -1px;">
        <tr>
            <td style="width:70%">
                <span class="checkbox {{ $assessment->replacement_available ? 'checked' : '' }}">
                    <span style="font-family: 'DejaVu Sans', sans-serif;">{{ $assessment->replacement_available ? '✔' : '' }}</span>
                </span>
                Replacement parts is available at the IT Office. Kindly prepare Pre and Post Repair Inspection Report and accomplish the attached RIS Form accordingly.
            </td>
            <td style="width:30%; text-align:center;">
                <span class="checkbox {{ !$assessment->replacement_available ? 'checked' : '' }}">
                    <span style="font-family: 'DejaVu Sans', sans-serif;">{{ !$assessment->replacement_available ? '✔' : '' }}</span>
                </span>
                <span class="bold">NO AVAILABLE IT STOCK</span>
            </td>
        </tr>
    </table>

    {{-- SPECIFICATIONS --}}
    @if($assessment->specifications)
    <table class="info-table" style="margin-top:-1px;">
        <tr>
            <td>
                <span class="label-muted">Specifications:</span> {{ $assessment->specifications }}
            </td>
        </tr>
    </table>
    @endif

    {{-- SIGNATORIES --}}
    <table class="signatory-table" style="margin-top: 10px; width: 100%;">
        <tr>
            <td style="width:50%">Assessed by:</td>
            <td style="width:50%">Reviewed by:</td>
        </tr>
        <tr>
            <td style="padding-top: 16px; text-align: center;">
                <span class="bold underline">{{ $assessment->assessed_by }}</span>
            </td>
            <td style="padding-top: 16px; text-align: center;">
                <span class="bold underline">{{ $assessment->reviewed_by }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding-top: 1px; text-align: center; font-size: 10px;">
                <span class="">{{ $assessment->assessed_by_position }}</span>
            </td>
            <td style="padding-top: 1px; text-align: center; font-size: 10px;">
                <span class="">{{ $assessment->reviewed_by_position }}</span>
            </td>
        </tr>
    </table>

    {{-- NOTES --}}
    <div class="notes mt-2">
        <div class="divider"></div>
        <p>Note:</p>
        <p>* Any type of computer repair may result in the loss of data</p>
        <p>* Backing up data is the user's responsibility</p>
        <p>* This form is made for Benguet Provincial Government</p>
        <p style="margin-top: 4px; color: #00B0F0">// PGO-IT</p>
    </div>

    <div class="footer-note">
        Generated by ITSMS
    </div>

</body>
</html>