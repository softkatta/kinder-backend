<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt #{{ $receiptNumber }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12px; margin: 0; padding: 24px; }
        .header { border-bottom: 2px solid #7c3aed; padding-bottom: 12px; margin-bottom: 20px; }
        .school { font-size: 20px; font-weight: bold; color: #4c1d95; }
        .title { font-size: 16px; margin-top: 4px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        th { width: 35%; color: #64748b; font-weight: 600; }
        .amount { font-size: 22px; font-weight: bold; color: #059669; margin-top: 18px; }
        .footer { margin-top: 28px; font-size: 10px; color: #94a3b8; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #ecfdf5; color: #047857; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">{{ $schoolName }}</div>
        <div class="title">Fee Payment Receipt</div>
    </div>

    <p><strong>Receipt No:</strong> {{ $receiptNumber }}</p>
    <p><strong>Date:</strong> {{ $date }}</p>
    <p><span class="badge">{{ strtoupper($status) }}</span></p>

    <table>
        <tr><th>Student</th><td>{{ $studentName }}</td></tr>
        <tr><th>Admission No.</th><td>{{ $admissionNumber }}</td></tr>
        <tr><th>Paid By</th><td>{{ $payerName }}</td></tr>
        <tr><th>Phone</th><td>{{ $payerPhone }}</td></tr>
        <tr><th>Payment Method</th><td>{{ strtoupper($method) }}</td></tr>
        <tr><th>Reference</th><td>{{ $reference }}</td></tr>
        @if($remarks)
        <tr><th>Remarks</th><td>{{ $remarks }}</td></tr>
        @endif
    </table>

    <div class="amount">Amount Paid: ₹{{ $amount }}</div>

    <div class="footer">
        This is a computer-generated receipt from {{ $schoolName }}.
        @if($verifiedAt)
        Verified on {{ $verifiedAt }}.
        @endif
    </div>
</body>
</html>
