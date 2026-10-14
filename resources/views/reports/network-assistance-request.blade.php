<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Request for Network Assistance</title>
    <style>
        @page {
            margin: 18px 30px;
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

        .copy {
            border: 1px solid #222;
            padding: 6px 9px;
        }

        .cut {
            border-top: 1px dashed #777;
            margin: 8px 0;
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
            line-height: 1.3;
            margin-bottom: 5px;
        }

        .header .title {
            font-size: 13px;
            font-weight: bold;
            color: #1d4ed8;
            text-transform: uppercase;
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
            min-height: 12px;
            padding: 0 2px 1px;
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
            margin-right: 3px;
            vertical-align: middle;
        }

        .services td {
            padding: 2px 3px;
            vertical-align: top;
            width: 37%;
        }

        .divider {
            border: none;
            border-top: 1px dashed #555;
            margin: 4px 0;
        }

        .section-title {
            text-align: center;
            font-weight: bold;
            margin: 3px 0 2px;
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
            margin-top: 3px;
        }

        .sign td {
            border: 1px solid #333;
            padding: 3px 5px;
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

    // e.g. "October 2 to 4 and 7, 2026, 8:00 AM", or with a time per date
    // "October 2, 2026 (8:00 AM); October 5, 2026 (1:00 PM)" -- the shared
    // time is empty then.
    $schedule = $record->scheduleText() ?? $longDate($record->activity_date);
    if ($record->activityTimeHm()) {
        $schedule .= ', '.\Carbon\Carbon::createFromFormat('H:i', $record->activityTimeHm())->format('g:i A');
    }

    // Same 2x2 grid as the paper form: Wi-Fi | Network Planning on the
    // first row, Ethernet | Other on the second.
    $serviceRows = array_map(
        fn (array $columns) => array_intersect_key($services, array_flip($columns)),
        [
            ['service_wifi_installation', 'service_network_planning'],
            ['service_ethernet_wiring', 'service_other_support'],
        ],
    );

    $copies = [
        ['label' => 'PGO-IT/MIS FILE', 'feedback' => true, 'actionLabel' => 'Action Taken/Remarks:'],
        ['label' => "CLIENT'S COPY", 'feedback' => false, 'actionLabel' => 'Action Taken:'],
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
            <div>Provincial Governor's Office</div>
            <div>Information Technology/Management Information Systems Unit</div>
            <div class="title">Network Assistance (Office Activities/Meetings)</div>
        </div>

        <table class="section">
            <tr>
                <td class="label">Date of request:</td>
                <td style="width:38%"><span class="underline">{{ $longDate($record->date_of_request) }}</span></td>
                <td class="label">Control No.:</td>
                <td><span class="underline">{{ $record->control_number }}</span></td>
            </tr>
            <tr>
                <td class="label">Department/Office:</td>
                <td colspan="3"><span class="underline">{{ $record->department_office }}</span></td>
            </tr>
            <tr>
                <td class="label">Name of requestor:</td>
                <td><span class="underline">{{ $record->requestor_name }}</span></td>
                <td class="label">Signature:</td>
                <td><span class="underline"></span></td>
            </tr>
        </table>

        <table class="section services">
            @foreach ($serviceRows as $rowIndex => $row)
                <tr>
                    <td class="label" style="width:1%">{{ $rowIndex === 0 ? 'Service Requested:' : '' }}</td>
                    @foreach ($row as $column => $label)
                        <td>
                            <span class="box">{{ $check((bool) $record->{$column}) }}</span>{{ $label }}
                            @if ($column === 'service_other_support' && $record->service_other_specify)
                                : <strong>{{ $record->service_other_specify }}</strong>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>

        <table class="section">
            <tr>
                <td class="label">Program/Activity details:</td>
                <td><span class="underline">{{ $record->program_activity_details }}</span></td>
            </tr>
            <tr>
                <td class="label">Date &amp; Time of Activity:</td>
                <td><span class="underline">{{ $schedule }}</span></td>
            </tr>
            <tr>
                <td class="label">Venue:</td>
                <td><span class="underline">{{ $record->venue }}</span></td>
            </tr>
        </table>

        <hr class="divider">

        <table class="section">
            <tr>
                <td class="label" rowspan="3" style="vertical-align:top">For IT/MIS Office use:</td>
                <td class="label">Assigned Personnel:</td>
                <td><span class="underline">{{ $record->assigned_personnel }}</span></td>
            </tr>
            <tr>
                <td class="label">Date Received:</td>
                <td><span class="underline">{{ $longDate($record->date_received) }}</span></td>
            </tr>
            <tr>
                <td class="label">{{ $copy['actionLabel'] }}</td>
                <td><span class="underline">{{ $record->action_taken }}</span></td>
            </tr>
        </table>

        <hr class="divider">

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
                    <td style="width:50%"><strong>Name:</strong> {{ $record->feedback_name }}</td>
                    <td style="width:28%"><strong>Signature:</strong></td>
                    <td><strong>Date:</strong> {{ $longDate($record->feedback_date) }}</td>
                </tr>
            </table>
        @else
            <div class="section-title" style="text-align:left">REQUESTOR'S ACCEPTANCE:</div>
            <table class="sign">
                <tr>
                    <td style="width:50%"><strong>Name:</strong> {{ $record->requestor_name }}</td>
                    <td style="width:28%"><strong>Signature:</strong></td>
                    <td><strong>Date:</strong></td>
                </tr>
            </table>
        @endif
    </div>
@endforeach

</body>
</html>
