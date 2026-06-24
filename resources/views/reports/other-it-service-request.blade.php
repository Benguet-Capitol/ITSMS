<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Other IT Service Request</title>
    <style>
        @page {
            size: letter;
            margin: 20px 30px;
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
            border: 1px solid #222;
            padding: 8px 10px;
            width: 98%;
            margin: 0 auto;
            page-break-inside: avoid;
        }

        .copy + .copy {
            page-break-before: always;
        }

        .top-line {
            width: 100%;
            margin-bottom: 4px;
        }

        .top-line td:first-child { text-align: left; font-size: 8px; }
        .top-line td:last-child  { text-align: right; font-size: 8px; }

        .header {
            text-align: center;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .header .title {
            font-size: 17px;
            font-weight: bold;
            color: #1d4ed8;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .section td {
            vertical-align: middle;
            padding: 2px 3px;
            font-size: 9.5px;
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
            width: 9px;
            height: 9px;
            display: inline-block;
            text-align: center;
            line-height: 8px;
            font-size: 8px;
            margin-right: 2px;
            vertical-align: middle;
        }

        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .service-table td {
            padding: 2px 3px;
            vertical-align: middle;
            font-size: 9.5px;
        }

        .divider {
            border: none;
            border-top: 1px dashed #555;
            margin: 6px 0;
        }

        .subsection-label {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 3px;
        }

        .feedback-title {
            text-align: center;
            font-weight: bold;
            font-size: 9.5px;
            margin-top: 4px;
            margin-bottom: 1px;
        }

        .rating-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .rating-table th,
        .rating-table td {
            border: 1px solid #333;
            padding: 3px 4px;
            text-align: center;
            font-size: 8.5px;
            vertical-align: top;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            border: 1px solid #333;
            padding: 5px 8px;
            font-size: 9.5px;
        }

        .selected-rating {
            font-size: 9px;
            margin: 3px 0;
        }
    </style>
</head>
<body>

@php
    $checked      = fn ($val) => $val ? '✓' : '';
    $dateReq      = $requestRecord->date_of_request
                        ? \Carbon\Carbon::parse($requestRecord->date_of_request)->format('F d, Y')
                        : '';
    $dateReceived = $requestRecord->date_received
                        ? \Carbon\Carbon::parse($requestRecord->date_received)->format('F d, Y')
                        : '';
    $feedbackDate = $requestRecord->feedback_date
                        ? \Carbon\Carbon::parse($requestRecord->feedback_date)->format('F d, Y')
                        : '';
    $activityDisplay = trim(($requestRecord->activity_date_text ?? '') . '  ' . ($requestRecord->activity_time ?? ''));
@endphp

{{-- ======================== TOP COPY ======================== --}}
<div class="copy">

    <table class="top-line">
        <tr>
            <td>PLGU-BENGUET|ICT-09-V2|2025</td>
            <td>PGO-IT/MIS FILE</td>
        </tr>
    </table>

    <div class="header">
        <div>Republic of the Philippines</div>
        <div><strong>PROVINCE OF BENGUET</strong></div>
        <div>La Trinidad</div>
        <div>Provincial Governor's Office</div>
        <div>Information Technology/Management Information Systems Unit</div>
        <div class="title">Request for Other IT Services</div>
    </div>

    <table class="section">
        <tr>
            <td class="label">Date of request:</td>
            <td style="width:30%"><span class="underline">{{ $dateReq }}</span></td>
            <td class="label">Control No.:</td>
            <td><span class="underline">{{ $requestRecord->control_number }}</span></td>
        </tr>
        <tr>
            <td class="label">Department/Office:</td>
            <td><span class="underline">{{ $requestRecord->department_office }}</span></td>
            <td class="label">Signature:</td>
            <td><span class="underline"></span></td>
        </tr>
        <tr>
            <td class="label">Name of requestor:</td>
            <td colspan="3"><span class="underline">{{ $requestRecord->requestor_name }}</span></td>
        </tr>
    </table>

    <table class="service-table">
        <tr>
            <td class="label" style="width:1%; white-space:nowrap">Service Requested:</td>
            <td><span class="box">{{ $checked($requestRecord->service_printing) }}</span> Printing Services</td>
            <td><span class="box">{{ $checked($requestRecord->service_information_material) }}</span> Information Material</td>
            <td><span class="box">{{ $checked($requestRecord->service_program_paper) }}</span> Program Paper</td>
            <td><span class="box">{{ $checked($requestRecord->service_brochure) }}</span> Brochure</td>
        </tr>
        <tr>
            <td></td>
            <td><span class="box">{{ $checked($requestRecord->service_iec_material) }}</span> IEC Material</td>
            <td><span class="box">{{ $checked($requestRecord->service_handbook) }}</span> Handbook</td>
            <td><span class="box">{{ $checked($requestRecord->service_certificates) }}</span> Certificates</td>
            <td>
                <span class="box">{{ $checked($requestRecord->service_others) }}</span> Others
                &nbsp;Qty: <span style="border-bottom:1px solid #333; display:inline-block; width:50px;">{{ $requestRecord->service_qty }}</span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td colspan="4">
                <span class="box">{{ $checked($requestRecord->service_laptop_tv_setup) }}</span>
                Set-up of laptop/TV for Meetings/Activities
            </td>
        </tr>
        <tr>
            <td></td>
            <td colspan="4">
                Others (Please Specify): <span class="underline">{{ $requestRecord->service_others_specify }}</span>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td class="label">Program/Activity details:</td>
            <td><span class="underline">{{ $requestRecord->program_activity_details }}</span></td>
        </tr>
        <tr>
            <td class="label">Date and Time of Activity:</td>
            <td><span class="underline">{{ $activityDisplay }}</span></td>
        </tr>
    </table>

    <hr class="divider">

    <table class="section">
        <tr>
            <td class="label" rowspan="3" style="vertical-align:top; padding-top:2px;">For IT/MIS Office use:</td>
            <td class="label">Assigned Personnel:</td>
            <td><span class="underline">{{ $requestRecord->assigned_personnel }}</span></td>
        </tr>
        <tr>
            <td class="label">Date Received:</td>
            <td><span class="underline">{{ $dateReceived }}</span></td>
        </tr>
        <tr>
            <td class="label">Action Taken:</td>
            <td><span class="underline">{{ $requestRecord->action_taken }}</span></td>
        </tr>
    </table>

    <hr class="divider">

    <div class="feedback-title">REQUESTOR'S FEEDBACK</div>
    <div class="feedback-title" style="font-size:9px; font-weight:normal;">RATING SCALE</div>

    <table class="rating-table">
        <thead>
            <tr>
                <th>5<br>Very Satisfied</th>
                <th>4<br>Satisfied</th>
                <th>3<br>Neutral</th>
                <th>2<br>Dissatisfied</th>
                <th>1<br>Very Dissatisfied</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>The service exceeded expectations and was outstanding.</td>
                <td>The service met expectations and was generally positive.</td>
                <td>The service was acceptable, but nothing stood out.</td>
                <td>Some effort was made, but the experience was poor.</td>
                <td>The service did not meet expectations at all.</td>
            </tr>
        </tbody>
    </table>

    <div class="selected-rating">Selected Rating: <strong>{{ $requestRecord->feedback_rating ?: '___' }}</strong></div>

    <table class="signature-table">
        <tr>
            <td><strong>Name:</strong> {{ $requestRecord->feedback_name }}</td>
            <td><strong>Signature:</strong></td>
            <td><strong>Date:</strong> {{ $feedbackDate }}</td>
        </tr>
    </table>

</div>

{{-- ======================== BOTTOM COPY ======================== --}}
<div class="copy" style="page-break-before: always;">

    <table class="top-line">
        <tr>
            <td>PLGU-BENGUET|ICT-09-V3|2026</td>
            <td>CLIENT FILE</td>
        </tr>
    </table>

    <div class="header">
        <div>Republic of the Philippines</div>
        <div><strong>PROVINCE OF BENGUET</strong></div>
        <div>La Trinidad</div>
        <div>Provincial Governor's Office</div>
        <div>Information Technology/Management Information Systems Unit</div>
        <div class="title">Request for Other IT Services</div>
    </div>

    <table class="section">
        <tr>
            <td class="label">Date of request:</td>
            <td style="width:30%"><span class="underline">{{ $dateReq }}</span></td>
            <td class="label">Control No.:</td>
            <td><span class="underline">{{ $requestRecord->control_number }}</span></td>
        </tr>
        <tr>
            <td class="label">Department/Office:</td>
            <td><span class="underline">{{ $requestRecord->department_office }}</span></td>
            <td class="label">Signature:</td>
            <td><span class="underline"></span></td>
        </tr>
        <tr>
            <td class="label">Name of requestor:</td>
            <td colspan="3"><span class="underline">{{ $requestRecord->requestor_name }}</span></td>
        </tr>
    </table>

    <table class="service-table">
        <tr>
            <td class="label" style="width:1%; white-space:nowrap">Service Requested:</td>
            <td><span class="box">{{ $checked($requestRecord->service_printing) }}</span> Printing Services</td>
            <td><span class="box">{{ $checked($requestRecord->service_information_material) }}</span> Information Material</td>
            <td><span class="box">{{ $checked($requestRecord->service_program_paper) }}</span> Program Paper</td>
            <td><span class="box">{{ $checked($requestRecord->service_brochure) }}</span> Brochure</td>
        </tr>
        <tr>
            <td></td>
            <td><span class="box">{{ $checked($requestRecord->service_iec_material) }}</span> IEC Material</td>
            <td><span class="box">{{ $checked($requestRecord->service_handbook) }}</span> Handbook</td>
            <td><span class="box">{{ $checked($requestRecord->service_certificates) }}</span> Certificates</td>
            <td>
                <span class="box">{{ $checked($requestRecord->service_others) }}</span> Others
                &nbsp;Qty: <span style="border-bottom:1px solid #333; display:inline-block; width:50px;">{{ $requestRecord->service_qty }}</span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td colspan="4">
                <span class="box">{{ $checked($requestRecord->service_laptop_tv_setup) }}</span>
                Set-up of laptop/TV for Meetings/Activities
            </td>
        </tr>
        <tr>
            <td></td>
            <td colspan="4">
                Others (Please Specify): <span class="underline">{{ $requestRecord->service_others_specify }}</span>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td class="label">Program/Activity details:</td>
            <td><span class="underline">{{ $requestRecord->program_activity_details }}</span></td>
        </tr>
        <tr>
            <td class="label">Date and Time of Activity:</td>
            <td><span class="underline">{{ $activityDisplay }}</span></td>
        </tr>
    </table>

    <hr class="divider">

    <table class="section">
        <tr>
            <td class="label" rowspan="3" style="vertical-align:top; padding-top:2px;">For IT/MIS Office use:</td>
            <td class="label">Assigned Personnel:</td>
            <td><span class="underline">{{ $requestRecord->assigned_personnel }}</span></td>
        </tr>
        <tr>
            <td class="label">Date Received:</td>
            <td><span class="underline">{{ $dateReceived }}</span></td>
        </tr>
        <tr>
            <td class="label">Action Taken:</td>
            <td><span class="underline">{{ $requestRecord->action_taken }}</span></td>
        </tr>
    </table>

    <hr class="divider">

    <div class="feedback-title">REQUESTOR'S ACCEPTANCE:</div>

    <table class="signature-table">
        <tr>
            <td><strong>Name:</strong> {{ $requestRecord->requestor_name }}</td>
            <td><strong>Signature:</strong></td>
            <td><strong>Date:</strong> {{ $dateReq }}</td>
        </tr>
    </table>

</div>

</body>
</html>