<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Request for New DTS User / Update of User Details</title>
    <style>
        @page {
            margin: 20px 34px;
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

        .copy {
            border: 1px solid #222;
            padding: 8px 11px;
        }

        .cut {
            border-top: 1px dashed #777;
            margin: 10px 0;
        }

        .top-line {
            width: 100%;
            border-collapse: collapse;
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
            margin-bottom: 8px;
        }

        .header .title {
            font-size: 13px;
            font-weight: bold;
            color: #1d4ed8;
            margin-top: 3px;
        }

        .section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .section td {
            padding: 3px 3px;
            vertical-align: bottom;
        }

        .label {
            white-space: nowrap;
            font-weight: bold;
            width: 1%;
        }

        .underline {
            border-bottom: 1px solid #333;
            display: block;
            min-height: 13px;
            padding: 0 2px 1px;
            white-space: pre-line;
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

        .caption {
            text-align: center;
            font-size: 7.5px;
            color: #374151;
        }

        .office {
            border: 1px solid #333;
            padding: 5px 7px;
            margin-top: 6px;
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
    </style>
</head>
<body>

@php
    $record = $requestRecord;
    $check = fn (bool $value) => $value ? '✓' : '';
    $longDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : '';

    // Workflow personnel (docs/adr/0002-request-workflow.md).
    $assignedTo = $record->personnel->pluck('display_name')->join(', ');

    $copies = [
        ['label' => 'PGO-IT/MIS FILE', 'feedback' => true],
        ['label' => "CLIENT'S COPY", 'feedback' => false],
    ];
@endphp

@foreach ($copies as $index => $copy)
    @if ($index > 0)
        <div class="cut"></div>
    @endif
    <div class="copy">
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
            <div>PROVINCIAL GOVERNOR'S OFFICE</div>
            <div>Information Technology/Management Information Systems Unit</div>
            <div class="title">REQUEST FOR NEW DTS USER / UPDATE OF USER DETAILS</div>
        </div>

        <table class="section">
            <tr>
                <td class="label">Date:</td>
                <td style="width:38%"><span class="underline">{{ $longDate($record->date_of_request) }}</span></td>
                <td class="label">Control No.:</td>
                <td><span class="underline">{{ $record->control_number }}</span></td>
            </tr>
            <tr>
                <td class="label">Request:</td>
                <td colspan="3">
                    @foreach ($requestTypes as $value => $label)
                        <span style="margin-right:16px"><span class="box">{{ $check($record->request_type === $value) }}</span>{{ $label }}</span>
                    @endforeach
                </td>
            </tr>
            @if ($record->request_type === 'update')
                <tr>
                    <td class="label">Details to update:</td>
                    <td colspan="3"><span class="underline">{{ $record->update_details }}</span></td>
                </tr>
            @endif
            <tr>
                <td class="label">Full name:</td>
                <td><span class="underline">{{ $record->full_name }}</span></td>
                <td class="label">Department:</td>
                <td><span class="underline">{{ $record->department }}</span></td>
            </tr>
            <tr>
                <td class="label">DTS Username:</td>
                <td><span class="underline">{{ $record->dts_username }}</span></td>
                <td class="label">Position:</td>
                <td><span class="underline">{{ $record->position }}</span></td>
            </tr>
            <tr>
                {{-- Never printed from the system: the initial password is not stored in ITSMS and is written in by hand. --}}
                <td class="label">Initial Password:</td>
                <td><span class="underline"></span></td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td class="label">Signature of User:</td>
                <td colspan="3"><span class="underline"></span></td>
            </tr>
            <tr>
                <td class="label">Requested by:</td>
                <td colspan="3"><span class="underline">{{ $record->requested_by }}</span></td>
            </tr>
            <tr>
                <td></td>
                <td colspan="3" class="caption">Department Head &mdash; Name &amp; Signature</td>
            </tr>
        </table>

        <div class="office">
            <div class="label" style="margin-bottom:2px">FOR PGO-IT/MIS USE:</div>
            <table class="section" style="margin-bottom:0">
                <tr>
                    <td class="label">Assigned to:</td>
                    <td colspan="3"><span class="underline">{{ $assignedTo }}</span></td>
                </tr>
                <tr>
                    <td class="label">Created by:</td>
                    <td style="width:45%"><span class="underline">{{ $record->created_by }}</span></td>
                    <td class="label">Date:</td>
                    <td><span class="underline">{{ $longDate($record->date_created) }}</span></td>
                </tr>
            </table>
        </div>

        @if ($copy['feedback'])
            <div class="section-title">REQUESTOR'S FEEDBACK</div>
            <table class="rating">
                <tr>
                    <th colspan="5">RATING SHEET</th>
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
            <table class="section" style="margin-top:4px">
                <tr>
                    <td class="label">Name:</td>
                    <td style="width:45%"><span class="underline">{{ $record->feedback_name }}</span></td>
                    <td class="label">Date:</td>
                    <td><span class="underline">{{ $longDate($record->feedback_date) }}</span></td>
                </tr>
                <tr>
                    <td class="label">Signature:</td>
                    <td><span class="underline"></span></td>
                    <td colspan="2"></td>
                </tr>
            </table>
        @endif
    </div>
@endforeach

</body>
</html>
