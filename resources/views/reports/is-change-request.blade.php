<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Request for Change or Modification of Information System</title>
    <style>
        @page {
            margin: 26px 38px;
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

        .top-line {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
        }

        .header {
            text-align: center;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .header .title {
            font-size: 13px;
            font-weight: bold;
            color: #1d4ed8;
            margin-top: 4px;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .grid td {
            border: 1px solid #333;
            padding: 4px 5px;
            vertical-align: top;
        }

        .grid td.label {
            width: 26%;
            font-weight: bold;
        }

        .grid td.value {
            white-space: pre-line;
        }

        .hint {
            display: block;
            font-weight: normal;
            font-size: 7px;
            color: #4b5563;
            margin-top: 2px;
        }

        .sub {
            font-weight: bold;
            font-size: 7.5px;
            display: block;
            color: #374151;
        }

        .box {
            border: 1px solid #333;
            width: 10px;
            height: 10px;
            display: inline-block;
            text-align: center;
            line-height: 9px;
            font-size: 8px;
            margin-right: 3px;
            vertical-align: middle;
        }

        .option {
            display: inline-block;
            margin-right: 12px;
        }

        .sign-line {
            border-bottom: 1px solid #333;
            min-height: 14px;
            display: block;
            padding: 0 2px;
        }

        .section-title {
            text-align: center;
            font-weight: bold;
            margin: 10px 0 3px;
        }

        .rating {
            width: 100%;
            border-collapse: collapse;
        }

        .rating th,
        .rating td {
            border: 1px solid #333;
            padding: 3px;
            text-align: center;
            font-size: 7.5px;
            vertical-align: top;
        }

        .acceptance {
            border: 1px solid #333;
            border-top: none;
            padding: 6px 8px;
        }
    </style>
</head>
<body>

@php
    $record = $requestRecord;
    $check = fn (bool $value) => $value ? '✓' : '';
    $longDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : '';
@endphp

@if ($formCode)
    <table class="top-line">
        <tr>
            <td>{{ $formCode }}</td>
        </tr>
    </table>
@endif

<div class="header">
    <div>Republic of the Philippines</div>
    <div><strong>PROVINCE OF BENGUET</strong></div>
    <div>La Trinidad</div>
    <div>OFFICE OF THE GOVERNOR</div>
    <div>Information Technology/Management Information Services</div>
    <div class="title">Request for change or modification of Information System</div>
</div>

<table class="grid">
    <tr>
        <td class="label">Date of request:</td>
        <td class="value">{{ $longDate($record->date_of_request) }}</td>
        <td class="label" style="width:20%">Change Request No:</td>
        <td class="value">{{ $record->control_number }}</td>
    </tr>
    <tr>
        <td class="label">Department/Office:</td>
        <td class="value" colspan="3">{{ $record->department_office }}</td>
    </tr>
    <tr>
        <td class="label">Name of Information System:</td>
        <td class="value" colspan="3">{{ $record->information_system }}</td>
    </tr>
    <tr>
        <td class="label">Type of Request:</td>
        <td colspan="3">
            @foreach ($requestTypes as $value => $label)
                <span class="option">
                    <span class="box">{{ $check($record->request_type === $value) }}</span>{{ $label }}@if ($value === 'others'): <strong>{{ $record->request_type_other }}</strong>@endif
                </span>
            @endforeach
        </td>
    </tr>
    <tr>
        <td class="label">Module(s) affected or proposed functionality to be added:</td>
        <td class="value" colspan="3">{{ $record->modules_affected }}</td>
    </tr>
    <tr>
        <td class="label">
            Details of the Request:
            <span class="hint">*attach relevant information/documentation/sample output for reference of developer (required).</span>
        </td>
        <td class="value" colspan="3" style="height:90px">{{ $record->details }}</td>
    </tr>
    <tr>
        <td class="label">Attachments submitted:</td>
        <td class="value" colspan="3">{{ $record->attachments_description }}</td>
    </tr>
    <tr>
        <td class="label">Priority Level:</td>
        <td>
            @foreach ($priorities as $value => $label)
                <span class="option"><span class="box">{{ $check($record->priority === $value) }}</span>{{ $label }}</span>
            @endforeach
        </td>
        <td class="label">Date required:</td>
        <td class="value">{{ $longDate($record->date_required) }}</td>
    </tr>
    <tr>
        <td class="label">
            Requested by:
            <span class="hint">(Preferably end-user project head/focal person)</span>
        </td>
        <td colspan="2"><span class="sub">Name:</span>{{ $record->requested_by }}</td>
        <td><span class="sub">Signature:</span></td>
    </tr>
    <tr>
        <td class="label">
            Reviewed by:
            <span class="hint">(Supervisor of Programmer)</span>
        </td>
        <td><span class="sub">Name:</span>{{ $record->reviewed_by }}</td>
        <td><span class="sub">Signature:</span></td>
        <td><span class="sub">Date:</span>{{ $longDate($record->reviewed_date) }}</td>
    </tr>
    <tr>
        <td class="label">
            Action by:
            <span class="hint">(Programmer)</span>
        </td>
        <td colspan="2"><span class="sub">Name:</span>{{ $record->programmer_name }}</td>
        <td><span class="sub">Date received request:</span>{{ $longDate($record->date_received) }}</td>
    </tr>
    <tr>
        <td class="label" rowspan="2">
            Testing Details:
            <span class="hint">(to be accomplished during end-user testing only)</span>
        </td>
        <td><span class="sub">Testing schedule:</span>{{ $record->testing_schedule }}</td>
        <td><span class="sub">Deployed by:</span>{{ $record->deployed_by }}</td>
        <td>
            <span class="sub">Approved by/Date:</span>{{ $record->approved_by }}@if ($record->approved_date) &mdash; {{ $longDate($record->approved_date) }}@endif
        </td>
    </tr>
    <tr>
        <td colspan="3" class="value"><span class="sub">Remarks:</span>{{ $record->remarks }}</td>
    </tr>
</table>

<table class="grid" style="margin-top:8px">
    <tr>
        <td class="label">Requestor:</td>
        <td colspan="3">
            <span class="sign-line">{{ $record->requested_by }}</span>
            <span class="hint" style="text-align:center">Name, Signature and date</span>
        </td>
    </tr>
</table>

<div class="section-title">REQUESTOR'S FEEDBACK and User acceptance Sign-off Form</div>
<table class="rating">
    <tr>
        <th colspan="5">RATING SCALE</th>
    </tr>
    <tr>
        @foreach ([5 => 'Very Satisfied', 4 => 'Satisfied', 3 => 'Neutral', 2 => 'Dissatisfied', 1 => 'Very Dissatisfied'] as $score => $label)
            <th>{{ $score }}<br>{{ $label }}</th>
        @endforeach
    </tr>
    <tr>
        <td>The service exceeded expectations and was outstanding.</td>
        <td>The service met expectations and was generally positive.</td>
        <td>The service was acceptable, but nothing stood out.</td>
        <td>Some effort was made, but the experience was poor.</td>
        <td>The service did not meet expectations at all.</td>
    </tr>
    <tr>
        @foreach ([5, 4, 3, 2, 1] as $score)
            <td><span class="box">{{ $check((int) $record->feedback_rating === $score) }}</span></td>
        @endforeach
    </tr>
</table>
<div class="acceptance">
    @if ($record->feedback_name || $record->feedback_date)
        <div style="margin-bottom:6px">
            <strong>Feedback by:</strong> {{ $record->feedback_name }}
            @if ($record->feedback_date)&nbsp;&nbsp;<strong>Date:</strong> {{ $longDate($record->feedback_date) }}@endif
        </div>
    @endif
    This is to confirm that an application testing has been accepted and therefore can be implemented in the
    Production Environment:
    <table style="width:100%; border-collapse:collapse; margin-top:12px">
        <tr>
            <td style="width:55%; padding-right:20px">
                <span class="sign-line" style="text-align:center">{{ $record->accepted_by }}</span>
                <span class="hint" style="text-align:center">Name and Signature</span>
            </td>
            <td>
                <strong>Date:</strong>
                <span class="sign-line" style="display:inline-block; width:75%">{{ $longDate($record->accepted_date) }}</span>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
