<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de ventas - {{ $periodLabel }}</title>
    <style>
        @page { margin: 26px 30px; size: letter landscape; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #111827; font-size: 19px; margin: 0 0 4px; }
        h2 { border-bottom: 1px solid #d1d5db; color: #111827; font-size: 12px; margin: 18px 0 8px; padding-bottom: 4px; }
        p { margin: 2px 0; }
        .header { border-bottom: 2px solid #4338ca; margin-bottom: 14px; min-height: 52px; padding: 0 64px 4px 0; position: relative; }
        .pdf-logo { height: 52px; object-fit: contain; position: absolute; right: 0; top: 0; width: 52px; }
        .meta { color: #4b5563; }
        .summary { border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; width: calc(100% + 12px); }
        .summary td { background: #f3f4f6; border: 1px solid #e5e7eb; padding: 9px; vertical-align: top; width: 25%; }
        .summary-cancellations { table-layout: fixed; }
        .summary-cancellations td { width: 50%; }
        .summary .label { color: #6b7280; font-size: 8px; text-transform: uppercase; }
        .summary .value { color: #111827; font-size: 15px; font-weight: bold; margin-top: 3px; }
        .commercial-criteria { color: #4b5563; margin: 7px 0 0; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th { background: #e5e7eb; color: #374151; font-size: 8px; padding: 6px; text-align: left; text-transform: uppercase; }
        table.data td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        .empty { color: #6b7280; padding: 12px !important; text-align: center; }
        .footer { color: #6b7280; font-size: 8px; margin-top: 16px; text-align: right; }
        .report-chart-wrap { text-align: center; }
        .report-chart { display: inline-block; height: auto; }
    </style>
</head>
<body>
    @php
        $pdfLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/quique-logo.png')));
    @endphp
    <header class="header">
        <img class="pdf-logo" src="{{ $pdfLogo }}" alt="QuiQue Micromarket">
        <h1>QuiQue Micromarket — Reporte de ventas</h1>
        <p><strong>Período:</strong> {{ $periodLabel }}</p>
        <p class="meta">Sucursal: {{ $branchName }} · Generado: {{ $generatedAt->format('d/m/Y H:i') }} · Zona horaria: UTC{{ $generatedAt->format('P') }}</p>
    </header>

    <table class="summary"><tr>
        <td><div class="label">Ventas confirmadas</div><div class="value">{{ $salesCount }}</div><p>Bs {{ number_format((float) $salesTotal, 2, ',', '.') }} vendidos</p></td>
        <td><div class="label">Efectivo</div><div class="value">Bs {{ number_format((float) $cashTotal, 2, ',', '.') }}</div><p>{{ $cashCount }} {{ $cashCount === 1 ? 'operación' : 'operaciones' }}</p></td>
        <td><div class="label">QR</div><div class="value">Bs {{ number_format((float) $qrTotal, 2, ',', '.') }}</div><p>{{ $qrCount }} {{ $qrCount === 1 ? 'operación' : 'operaciones' }}</p></td>
        <td><div class="label">Operaciones mixtas</div><div class="value">{{ $mixedCount }}</div><p>Bs {{ number_format((float) $mixedTotal, 2, ',', '.') }}</p></td>
    </tr></table>
    <table class="summary summary-cancellations" style="margin-top: 6px;"><tr>
        <td colspan="2"><div class="label">Ventas anuladas</div><div class="value">{{ $cancelledSalesCount }}</div><p>Solo informativo</p></td>
        <td colspan="2"><div class="label">Monto anulado</div><div class="value">Bs {{ number_format((float) $cancelledSalesTotal, 2, ',', '.') }}</div><p>No incluido en ingresos</p></td>
    </tr></table>
    <p class="commercial-criteria"><strong>Criterio comercial:</strong> Los totales, productos y gráficas consideran únicamente las ventas confirmadas.</p>

    <h2>{{ $chartData['title'] }}</h2>
    @php
        $chartSvg = view('reports.partials.chart-svg', ['chart' => $chartData, 'forPdf' => true])->render();
        $chartPdfWidth = min(960, max(760, count($chartData['labels']) * 38));
    @endphp
    <div class="report-chart-wrap"><img class="report-chart" width="{{ $chartPdfWidth }}" src="data:image/svg+xml;base64,{{ base64_encode($chartSvg) }}" alt="{{ $chartData['title'] }}"></div>

    @if (in_array($filters['period'], ['range', 'month'], true))
        <h2>Resumen diario</h2>
        <table class="data"><thead><tr><th>Día</th><th class="right">Ventas</th><th class="right">Total</th></tr></thead><tbody>
            @forelse ($dailySummary as $day)<tr><td>{{ \Carbon\Carbon::parse($day->day)->format('d/m/Y') }}</td><td class="right">{{ $day->sales_count }}</td><td class="right">Bs {{ number_format((float) $day->total, 2, ',', '.') }}</td></tr>@empty<tr><td colspan="3" class="empty">No existen ventas confirmadas en este período.</td></tr>@endforelse
        </tbody></table>
    @endif

    <h2>Productos vendidos</h2>
    <table class="data"><thead><tr><th>Producto histórico</th><th class="right">Cantidad</th><th class="right">Importe generado</th></tr></thead><tbody>
        @forelse ($products as $product)<tr><td>{{ $product->product_name }}</td><td class="right">{{ $product->quantity_display }}</td><td class="right">Bs {{ number_format((float) $product->amount_generated, 2, ',', '.') }}</td></tr>@empty<tr><td colspan="3" class="empty">No se vendieron productos durante este período.</td></tr>@endforelse
    </tbody></table>

    <h2>Detalle de ventas confirmadas</h2>
    <table class="data"><thead><tr><th>Número de venta</th><th>Fecha y hora</th><th>Responsable</th><th>Pago</th><th class="right">Total</th><th class="center">Estado</th></tr></thead><tbody>
        @forelse ($sales as $sale)<tr><td>{{ $sale->sale_number }}</td><td>{{ $sale->confirmed_at->format('d/m/Y H:i') }}</td><td>{{ $sale->user->name }}</td><td>{{ $sale->paymentLabel() }}</td><td class="right">Bs {{ number_format((float) $sale->total, 2, ',', '.') }}</td><td class="center">{{ $sale->status->label() }}</td></tr>@empty<tr><td colspan="6" class="empty">No existen ventas confirmadas en el período seleccionado.</td></tr>@endforelse
    </tbody></table>

    <p class="footer">Documento generado por QuiQue Micromarket.</p>
</body>
</html>
