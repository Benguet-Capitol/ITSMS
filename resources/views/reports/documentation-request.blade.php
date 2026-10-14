<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Request for Documentation</title>
    <style>
        @page {
            margin: 16px 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sheet > tbody > tr > td.copy {
            width: 49%;
            vertical-align: top;
            border: 1px solid #222;
            padding: 6px 8px;
        }

        .sheet > tbody > tr > td.cut {
            width: 2%;
            border-left: 1px dashed #777;
        }

        .top-line {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }

        .top-line td {
            font-size: 7.5px;
        }

        .top-line td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .header {
            text-align: center;
            line-height: 1.35;
            margin-bottom: 6px;
        }

        .header .title {
            font-size: 14px;
            font-weight: bold;
            color: #1d4ed8;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .fields {
            width: 100%;
            border-collapse: collapse;
        }

        .fields td {
            border: 1px solid #333;
            padding: 3px 4px;
            vertical-align: top;
        }

        .fields td.label {
            width: 30%;
            font-weight: bold;
        }

        .fields td.value {
            white-space: pre-line;
        }

        .box {
            border: 1px solid #333;
            width: 9px;
            height: 9px;
            display: inline-block;
            text-align: center;
            line-height: 8px;
            font-size: 8px;
            margin-right: 2px;
            vertical-align: middle;
        }

        .service {
            display: inline-block;
            margin-right: 10px;
        }

        .office-use td {
            border: none;
            padding: 1px 0;
        }

        .underline {
            border-bottom: 1px solid #333;
            display: inline-block;
            min-width: 60%;
            min-height: 11px;
            padding: 0 2px;
        }

        .section-title {
            text-align: center;
            font-weight: bold;
            margin: 6px 0 2px;
        }

        .rating {
            width: 100%;
            border-collapse: collapse;
        }

        .rating th,
        .rating td {
            border: 1px solid #333;
            padding: 2px 3px;
            text-align: center;
            font-size: 7.5px;
            vertical-align: top;
        }

        .sign {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .sign td {
            border: 1px solid #333;
            padding: 4px 5px;
            height: 22px;
            vertical-align: bottom;
        }
    </style>
</head>
<body>

@php
    $record = $requestRecord;
    $check = fn (bool $value) => $value ? '✓' : '';
    $longDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : '';

    // e.g. "October 2 to 4 and 7, 2026"
    $documentationDate = $record->scheduleText() ?? $longDate($record->documentation_date);

    $copies = [
        ['label' => 'PGO-IT/MIS FILE', 'feedback' => true],
        ['label' => "CLIENT'S COPY", 'feedback' => false],
    ];
@endphp

<table class="sheet">
    <tr>
        @foreach ($copies as $index => $copy)
            @if ($index > 0)
                <td class="cut"></td>
            @endif
            <td class="copy">
                <table class="top-line">
                    <tr>
                        <td>{{ $formCode }}</td>
                        <td>{{ $copy['label'] }}</td>
                    </tr>
                </table>

                <div class="header">
                    <div>Republic of the Philippines</div>
                    <div><strong>PROVINCE OF BENGUET</strong></div>
                    <div>La Trinidad</div>
                    <div>PROVINCIAL GOVERNMENT OF BENGUET</div>
                    <div>Information Technology/Management Information Systems Unit</div>
                    <div class="title">Request for Documentation</div>
                </div>

                <table class="fields">
                    <tr>
                        <td class="label">Date of request:</td>
                        <td class="value">{{ $longDate($record->date_of_request) }}</td>
                        <td class="label" style="width:18%">Control No.:</td>
                        <td class="value" style="width:22%">{{ $record->control_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Department/Office:</td>
                        <td class="value" colspan="3">{{ $record->department_office }}</td>
                    </tr>
                    <tr>
                        <td class="label">Name of requestor:</td>
                        <td class="value" colspan="3">{{ $record->requestor_name }}</td>
                    </tr>
                    <tr>
                        <td class="label">Signature:</td>
                        <td colspan="3" style="height:20px"></td>
                    </tr>
                    <tr>
                        <td class="label">Service Requested:</td>
                        <td colspan="3">
                            @foreach ($services as $column => $label)
                                <span class="service"><span class="box">{{ $check((bool) $record->{$column}) }}</span> {{ $label }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Program/Activity details:</td>
                        <td class="value" colspan="3">{{ $record->program_activity_details }}</td>
                    </tr>
                    <tr>
                        <td class="label">Date of documentation:</td>
                        <td class="value" colspan="3">{{ $documentationDate }}</td>
                    </tr>
                    <tr>
                        <td class="label">Specific Shots/ Content Required (if any):</td>
                        <td class="value" colspan="3">{{ $record->specific_shots }}</td>
                    </tr>
                    <tr>
                        <td class="label">Additional Notes/ Requirements:</td>
                        <td class="value" colspan="3">{{ $record->additional_notes }}</td>
                    </tr>
                    <tr>
                        <td class="label">For IT/MIS Office use:</td>
                        <td colspan="3">
                            <table class="office-use" style="width:100%">
                                <tr><td>Assigned Personnel: <span class="underline">{{ $record->assigned_personnel }}</span></td></tr>
                                <tr><td>Date Received: <span class="underline">{{ $longDate($record->date_received) }}</span></td></tr>
                                <tr><td>Action Taken: <span class="underline">{{ $record->action_taken }}</span></td></tr>
                            </table>
                        </td>
                    </tr>
                </table>

                @if ($copy['feedback'])
                    <div class="section-title">REQUESTOR'S FEEDBACK</div>
                    <div class="section-title" style="font-weight:normal; margin-top:0">RATING SCALE</div>
                    <table class="rating">
                        <tr>
                            @foreach ([5 => 'Very Satisfied', 4 => 'Satisfied', 3 => 'Neutral', 2 => 'Dissatisfied', 1 => 'Very Dissatisfied'] as $score => $label)
                                <th>
                                    <span class="box">{{ $check((int) $record->feedback_rating === $score) }}</span><br>
                                    {{ $score }}<br>{{ $label }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            <td>The service exceeded expectations and was outstanding.</td>
                            <td>The service met expectations and was generally positive.</td>
                            <td>The service was acceptable, but nothing stood out.</td>
                            <td>Some effort was made, but the experience was poor.</td>
                            <td>The service did not meet expectations at all.</td>
                        </tr>
                    </table>
                    <table class="sign">
                        <tr>
                            <td style="width:45%"><strong>Name:</strong> {{ $record->feedback_name }}</td>
                            <td style="width:30%"><strong>Signature:</strong></td>
                            <td><strong>Date:</strong> {{ $longDate($record->feedback_date) }}</td>
                        </tr>
                    </table>
                @else
                    <div class="section-title" style="text-align:left">END-USER ACCEPTANCE:</div>
                    <table class="sign">
                        <tr>
                            <td style="width:65%"><strong>Name &amp; Signature:</strong></td>
                            <td><strong>Date:</strong></td>
                        </tr>
                    </table>
                @endif
            </td>
        @endforeach
    </tr>
</table>

</body>
</html>
