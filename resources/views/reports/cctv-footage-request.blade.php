<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Request for CCTV Footage</title>
    <style>
        @page {
            margin: 18px 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .copy {
            width: 100%;
            page-break-inside: avoid;
        }

        .copy + .copy {
            page-break-before: always;
        }

        .top-line {
            width: 100%;
            margin-bottom: 4px;
            border-collapse: collapse;
        }

        .top-line td {
            font-size: 8px;
        }

        .top-line td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .header .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .section td {
            vertical-align: bottom;
            padding: 2px 3px;
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
            width: 100%;
        }

        .box {
            border: 1px solid #333;
            width: 10px;
            height: 10px;
            display: inline-block;
            text-align: center;
            line-height: 9px;
            font-size: 9px;
            margin-right: 3px;
            vertical-align: middle;
        }

        .heading {
            font-weight: bold;
            margin: 6px 0 2px;
        }

        .option-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .option-table td {
            padding: 2px 3px;
            vertical-align: top;
        }

        .notice {
            margin-top: 6px;
            text-align: justify;
            font-size: 8.5px;
            line-height: 1.35;
        }

        .notice .heading {
            text-align: center;
            font-size: 9px;
        }

        .notice ol {
            margin: 2px 0 0 0;
            padding-left: 16px;
        }

        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .sign-table td {
            width: 50%;
            padding: 3px 10px;
            vertical-align: bottom;
        }

        .sign-line {
            border-bottom: 1px solid #333;
            min-height: 22px;
            text-align: center;
            font-weight: bold;
            padding-top: 8px;
        }

        .sign-caption {
            text-align: center;
            font-size: 8.5px;
        }

        .requirements {
            margin-top: 8px;
            font-size: 8.5px;
            font-weight: bold;
        }

        .it-section {
            margin-top: 8px;
            border: 1px solid #333;
            padding: 5px 7px;
        }

        .it-section .heading {
            margin-top: 0;
            text-align: center;
        }

        .muted {
            color: #4b5563;
        }
    </style>
</head>
<body>

@php
    $record = $requestRecord;
    $check = fn (bool $value) => $value ? '✓' : '';
    $longDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : '';
    $time12 = fn (?string $time) => $time ? \Carbon\Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? "{$time}:00" : $time)->format('g:i A') : '';

    // With a window per date, the windows are printed beside each date.
    $footageTime = $record->hasScheduleTimes()
        ? 'As indicated per date'
        : trim($time12($record->footage_time_from).($record->footage_time_to ? ' - '.$time12($record->footage_time_to) : ''));
    $selectedReasons = $record->reasons ?? [];
    $reasonRows = array_chunk($reasons, 4, true);

    $copies = [
        ['label' => 'OG-IT COPY', 'itSection' => true],
        ['label' => 'CLIENT COPY', 'itSection' => false],
    ];
@endphp

@foreach ($copies as $copy)
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
        <div class="title">Request for CCTV Footage</div>
    </div>

    <table class="section">
        <tr>
            <td class="label">Date:</td>
            <td style="width:35%"><span class="underline">{{ $longDate($record->date_of_request) }}</span></td>
            <td class="label">Control No.:</td>
            <td><span class="underline">{{ $record->control_number }}</span></td>
        </tr>
        <tr>
            <td class="label">Name:</td>
            <td colspan="3"><span class="underline">{{ $record->requester_name }}</span></td>
        </tr>
        <tr>
            <td class="label">Complete Address:</td>
            <td><span class="underline">{{ $record->requester_address }}</span></td>
            <td class="label">Contact No.:</td>
            <td><span class="underline">{{ $record->requester_contact_number }}</span></td>
        </tr>
        <tr>
            <td class="label">Date of Incident:</td>
            <td><span class="underline">{{ $record->scheduleText() ?? $longDate($record->date_of_incident) }}</span></td>
            <td class="label">Inclusive Time of Footage:</td>
            <td><span class="underline">{{ $footageTime }}</span></td>
        </tr>
        <tr>
            <td class="label">Location of CCTV Camera/Area Covered:</td>
            <td colspan="3"><span class="underline">{{ $record->camera_location }}</span></td>
        </tr>
    </table>

    <div class="heading">Capacity of the Requester</div>
    <table class="option-table">
        @foreach ($capacities as $value => $label)
            <tr>
                <td>
                    <span class="box">{{ $check($record->requester_capacity === $value) }}</span>
                    {{ $label }}
                    @if ($value === 'other' && $record->requester_capacity === 'other')
                        : <strong>{{ $record->requester_capacity_other }}</strong>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <table class="option-table">
        <tr>
            <td class="label">Purpose of Request:</td>
            <td style="width:20%"><span class="box">{{ $check($record->purpose === 'viewing') }}</span> Viewing only</td>
            <td>
                <span class="box">{{ $check($record->purpose === 'copy') }}</span> Obtain a copy of CCTV footage
                <span class="muted">(Kindly provide a USB/flash drive and submit together with this form)</span>
            </td>
        </tr>
    </table>

    <div class="heading">Reason:</div>
    <table class="option-table">
        @foreach ($reasonRows as $row)
            <tr>
                @foreach ($row as $value => $label)
                    <td><span class="box">{{ $check(in_array($value, $selectedReasons, true)) }}</span> {{ $label }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr>
            <td colspan="4">
                <span class="box">{{ $check(in_array('others', $selectedReasons, true)) }}</span>
                Others please specify: <strong>{{ $record->reason_others }}</strong>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td class="label" style="vertical-align:top">Detailed Description of Request:</td>
            <td><span class="underline" style="white-space:pre-line">{{ $record->description }}</span></td>
        </tr>
    </table>

    <div class="notice">
        <div class="heading">DATA PRIVACY NOTICE</div>
        Your personal data is being collected and processed to evaluate and act on your request for CCTV footage,
        in accordance with the Data Privacy Act of 2012 and applicable regulations. Your information will be stored
        securely and retained only as long as necessary to fulfill the purpose of this request or as required by law.
        You have the right to access, correct, or request deletion of your personal data, subject to applicable legal
        limitations.
    </div>

    <div class="notice">
        <div class="heading">TERMS AND CONDITIONS/UNDERTAKING</div>
        <ol>
            <li>All requests are subject to evaluation and approval;</li>
            <li>Only relevant portions of the footage shall be disclosed. The provincial Government reserves the right to redact, blur, or restrict portions of the footage to protect the privacy of other individuals;</li>
            <li>The footage shall be used strictly for the purpose and reason stated in the request;</li>
            <li>The requesting party shall not reproduce, publish, upload, or distribute the footage without lawful authority;</li>
            <li>Any copy provided must be securely stored and deleted or returned after its lawful use;</li>
            <li>Unauthorized disclosure or misuse may result in administrative, civil, and/or criminal liability under applicable laws;</li>
            <li>The provincial government reserves the right to deny any request that does not comply with legal, security, or privacy requirements.</li>
        </ol>
    </div>

    <div class="notice">
        <div class="heading">NON-DISCLOSURE AGREEMENT</div>
        I hereby undertake to maintain the confidentiality of any CCTV footage or information obtained pursuant to this
        request. I shall not disclose or share the same to any unauthorized person or entity, except as required by law
        or lawful order of a competent authority.
    </div>

    <table class="sign-table">
        <tr>
            <td>
                <div>Requesting Party:</div>
                <div class="sign-line">{{ $record->requester_name }}</div>
                <div class="sign-caption">Signature over printed name</div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div>Recommending approval:</div>
                <div class="sign-line">{{ $signatories['recommending']['name'] }}</div>
                <div class="sign-caption">{{ $signatories['recommending']['title'] }}</div>
            </td>
            <td>
                <div>Approved:</div>
                <div class="sign-line">{{ $signatories['approving']['name'] }}</div>
                <div class="sign-caption">{{ $signatories['approving']['title'] }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div>Extracted CCTV Footage Released by:</div>
                <div class="sign-line">{{ $record->released_by }}</div>
                <div class="sign-caption">Signature over Printed Name / Date</div>
            </td>
            <td>
                <div>Viewed / Received Footage by:</div>
                <div class="sign-line">{{ $record->received_by }}</div>
                <div class="sign-caption">Signature over Printed Name / Date</div>
            </td>
        </tr>
    </table>

    <div class="requirements">
        *** Requirements for releasing CCTV footage: POLICE BLOTTER REPORT, COURT ORDER, PROOF OF INVESTIGATION,
        WHICHEVER IS APPLICABLE.
    </div>

    @if ($copy['itSection'])
        <div class="it-section">
            <div class="heading">DETAILS TO BE COMPLETED BY IT PERSONNEL</div>
            <div class="heading" style="text-align:left">EXTRACTION/VIEWING DETAILS:</div>
            <table class="section">
                <tr>
                    <td class="label">Date and Time of Playback/Extraction:</td>
                    <td><span class="underline">{{ $record->extracted_at?->format('F d, Y g:i A') }}</span></td>
                    <td class="label">Camera Name:</td>
                    <td><span class="underline">{{ $record->camera_name }}</span></td>
                    <td class="label">NVR No.:</td>
                    <td style="width:10%"><span class="underline">{{ $record->nvr_number }}</span></td>
                </tr>
                <tr>
                    <td class="label">For Requested Copy: Name of File/s:</td>
                    <td colspan="5"><span class="underline" style="white-space:pre-line">{{ $record->file_names }}</span></td>
                </tr>
                <tr>
                    <td class="label">Extracted by:</td>
                    <td colspan="5"><span class="underline">{{ $record->extracted_by }}</span></td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="5" class="sign-caption">Name, Signature, Date &mdash; IT Personnel</td>
                </tr>
            </table>
        </div>
    @endif

</div>
@endforeach

</body>
</html>
