<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Request for Design/Lay-out/Editing</title>
    <style>
        @page {
            margin: 14px 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.8px;
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
            padding: 5px 7px;
        }

        .sheet > tbody > tr > td.cut {
            width: 2%;
            border-left: 1px dashed #777;
        }

        .top-line {
            width: 100%;
            border-collapse: collapse;
        }

        .top-line td {
            font-size: 7px;
        }

        .top-line td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .header {
            text-align: center;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .header .title {
            font-size: 12.5px;
            font-weight: bold;
            color: #1d4ed8;
            margin-top: 2px;
        }

        .section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .section td {
            padding: 2px 3px;
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
            min-height: 11px;
            padding: 0 2px 1px;
        }

        .box {
            border: 1px solid #333;
            width: 9px;
            height: 9px;
            display: inline-block;
            text-align: center;
            line-height: 8px;
            font-size: 8px;
            vertical-align: middle;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 3px;
        }

        .items th,
        .items td {
            border: 1px solid #333;
            padding: 2px 3px;
            vertical-align: middle;
        }

        .items th {
            font-size: 7px;
            background: #f3f4f6;
        }

        .items td.check {
            text-align: center;
        }

        .options span.option {
            display: inline-block;
            margin-right: 10px;
        }

        .office {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        .office td {
            border: 1px solid #333;
            padding: 3px 4px;
            vertical-align: top;
            height: 22px;
        }

        .office td strong {
            display: block;
            font-size: 7px;
        }

        .section-title {
            text-align: center;
            font-weight: bold;
            margin: 4px 0 2px;
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
            font-size: 7px;
            vertical-align: top;
        }

        .sign {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }

        .sign td {
            border: 1px solid #333;
            padding: 3px 5px;
            height: 20px;
            vertical-align: bottom;
        }
    </style>
</head>
<body>

@php
    $record = $requestRecord;
    $check = fn (bool $value) => $value ? '✓' : '';
    $longDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : '';

    $requested = $record->itemsByType();
    $formats = $record->file_formats ?? [];
    $mediaProvided = $record->media_provided ?? [];

    // The paper form's two side-by-side item columns.
    $itemColumns = [
        ['booklet', 'brochure', 'logo', 'certificate', 'invitation', 'poster', 'program_paper'],
        ['tarpaulin', 'video_editing', 'audio_editing', 'template_editing', 'others'],
    ];
    $rowCount = max(array_map('count', $itemColumns));

    $copies = [
        ['label' => 'PGO-IT/MIS FILE', 'feedback' => true],
        ['label' => "CLIENT'S FILE", 'feedback' => false],
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
                    <div>Provincial Governor's Office</div>
                    <div>Information Technology/Management Information Systems Unit</div>
                    <div class="title">REQUEST FOR DESIGN/LAY-OUT/EDITING</div>
                </div>

                <table class="section">
                    <tr>
                        <td class="label">Date of Request:</td>
                        <td style="width:36%"><span class="underline">{{ $longDate($record->date_of_request) }}</span></td>
                        <td class="label">Control No.:</td>
                        <td><span class="underline">{{ $record->control_number }}</span></td>
                    </tr>
                    <tr>
                        <td class="label">Department/Office:</td>
                        <td colspan="3"><span class="underline">{{ $record->department_office }}</span></td>
                    </tr>
                    <tr>
                        <td class="label">Date Needed:</td>
                        <td colspan="3"><span class="underline">{{ $longDate($record->date_needed) }}</span></td>
                    </tr>
                </table>

                <div class="label" style="margin:2px 0">Service Requested:</div>
                <table class="items">
                    <tr>
                        @foreach ($itemColumns as $column)
                            <th style="width:7%">Check Here</th>
                            <th style="width:17%">Design/Project</th>
                            <th style="width:14%">Dimension/ Resolution</th>
                            <th style="width:12%">Quantity/ Length</th>
                        @endforeach
                    </tr>
                    @for ($row = 0; $row < $rowCount; $row++)
                        <tr>
                            @foreach ($itemColumns as $column)
                                @php
                                    $value = $column[$row] ?? null;
                                    $entry = $value ? ($requested[$value] ?? null) : null;
                                @endphp
                                @if ($value)
                                    <td class="check"><span class="box">{{ $check((bool) $entry) }}</span></td>
                                    <td>
                                        {{ $items[$value] }}@if ($value === 'others'):
                                            <strong>{{ $entry['specify'] ?? '' }}</strong>
                                        @endif
                                    </td>
                                    <td>{{ $entry['dimension'] ?? '' }}</td>
                                    <td>{{ $entry['quantity'] ?? '' }}</td>
                                @else
                                    <td></td><td></td><td></td><td></td>
                                @endif
                            @endforeach
                        </tr>
                    @endfor
                </table>

                <table class="section options">
                    <tr>
                        <td class="label">File Format requirements:</td>
                        <td>
                            @foreach ($fileFormats as $value => $label)
                                <span class="option"><span class="box">{{ $check(in_array($value, $formats, true)) }}</span> {{ $label }}</span>
                            @endforeach
                            <span class="option">others: <strong>{{ $record->file_format_other }}</strong></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Media files provided:</td>
                        <td>
                            @foreach ($media as $value => $label)
                                <span class="option"><span class="box">{{ $check(in_array($value, $mediaProvided, true)) }}</span> {{ $label }}</span>
                            @endforeach
                        </td>
                    </tr>
                </table>

                <table class="office">
                    <tr>
                        <td rowspan="2" style="width:14%"><strong>Requested by:</strong></td>
                        <td style="width:46%"><strong>Name:</strong>{{ $record->requester_name }}</td>
                        <td><strong>Signature:</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Position:</strong>{{ $record->requester_position }}</td>
                        <td><strong>Email address:</strong>{{ $record->requester_email }}</td>
                    </tr>
                </table>

                <table class="office">
                    <tr>
                        <td style="width:14%"><strong>For IT/MIS Office Use:</strong></td>
                        <td style="width:32%"><strong>Assigned to:</strong>{{ $record->assigned_personnel }}</td>
                        <td style="width:26%"><strong>Date Received:</strong>{{ $longDate($record->date_received) }}</td>
                        <td><strong>Signature:</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Date Released:</strong>{{ $longDate($record->date_released) }}</td>
                        <td><strong>Received by:</strong>{{ $record->received_by }}</td>
                        <td><strong>Signature:</strong></td>
                        <td><strong>Remarks:</strong>{{ $record->remarks }}</td>
                    </tr>
                </table>

                @if ($copy['feedback'])
                    <div class="section-title">REQUESTOR'S FEEDBACK</div>
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
                    <table class="sign">
                        <tr>
                            <td style="width:48%"><strong>Name:</strong> {{ $record->feedback_name }}</td>
                            <td style="width:28%"><strong>Signature:</strong></td>
                            <td><strong>Date:</strong> {{ $longDate($record->feedback_date) }}</td>
                        </tr>
                    </table>
                @endif
            </td>
        @endforeach
    </tr>
</table>

</body>
</html>
