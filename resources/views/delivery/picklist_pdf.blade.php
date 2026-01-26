<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pick List Order #{{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { margin-bottom: 16px; }
        .title { font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f2f2f2; text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Pick List - Order #{{ $order->id }}</div>
        <div>Customer: {{ $order->customer_name }}</div>
        <div>Delivery Date: {{ $order->delivery_date->format('d M Y') }}</div>
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>ID Box</th>
                <th>Part</th>
                <th>PCS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($session->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->box->box_number }}</td>
                    <td>{{ $item->part_number }}</td>
                    <td>{{ $item->pcs_quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>