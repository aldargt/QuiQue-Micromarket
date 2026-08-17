<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario y Abastecimiento</title>
    <style>
        @page { margin: 26px 30px; size: letter landscape; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #111827; font-size: 19px; margin: 0 0 4px; }
        h2 { border-bottom: 1px solid #d1d5db; color: #111827; font-size: 12px; margin: 16px 0 7px; padding-bottom: 4px; }
        p { margin: 2px 0; }
        .header { border-bottom: 2px solid #4338ca; margin-bottom: 12px; padding-bottom: 10px; }
        .meta { color: #4b5563; }
        .section { page-break-inside: avoid; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #e5e7eb; color: #374151; font-size: 8px; padding: 6px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .right { text-align: right; }
        .empty { color: #6b7280; padding: 10px; text-align: center; }
        .footer { color: #6b7280; font-size: 8px; margin-top: 14px; text-align: right; }
    </style>
</head>
<body>
    <header class="header">
        <h1>QuiQue MicroMarket</h1>
        <p><strong>Reporte de Inventario y Abastecimiento</strong></p>
        <p class="meta">Sucursal: {{ $branchName }} · Generado: {{ $generatedAt->format('d/m/Y H:i') }} · Zona horaria: UTC{{ $generatedAt->format('P') }}</p>
    </header>

    <section class="section">
        <h2>Productos agotados</h2>
        <table><thead><tr><th>Producto</th><th class="right">Stock actual</th></tr></thead><tbody>
            @forelse ($zeroStockProducts as $product)<tr><td>{{ $product->name }}</td><td class="right">{{ $product->unit->formatQuantity($product->stock) }}</td></tr>@empty<tr><td colspan="2" class="empty">No hay productos agotados.</td></tr>@endforelse
        </tbody></table>
    </section>

    <section class="section">
        <h2>Productos con stock bajo</h2>
        <table><thead><tr><th>Producto</th><th class="right">Stock actual</th><th class="right">Stock mínimo</th></tr></thead><tbody>
            @forelse ($lowStockProducts as $product)<tr><td>{{ $product->name }}</td><td class="right">{{ $product->unit->formatQuantity($product->stock) }}</td><td class="right">{{ $product->unit->formatQuantity($product->minimum_stock) }}</td></tr>@empty<tr><td colspan="3" class="empty">No hay productos con stock bajo.</td></tr>@endforelse
        </tbody></table>
    </section>

    <section class="section">
        <h2>Productos próximos a vencer</h2>
        <table><thead><tr><th>Producto</th><th class="right">Stock actual</th><th class="right">Fecha de vencimiento</th></tr></thead><tbody>
            @forelse ($expiringProducts as $product)<tr><td>{{ $product->name }}</td><td class="right">{{ $product->unit->formatQuantity($product->stock) }}</td><td class="right">{{ $product->expires_at->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="3" class="empty">No hay productos próximos a vencer.</td></tr>@endforelse
        </tbody></table>
    </section>

    <section class="section">
        <h2>Productos vencidos</h2>
        <table><thead><tr><th>Producto</th><th class="right">Stock actual</th><th class="right">Fecha de vencimiento</th></tr></thead><tbody>
            @forelse ($expiredProducts as $product)<tr><td>{{ $product->name }}</td><td class="right">{{ $product->unit->formatQuantity($product->stock) }}</td><td class="right">{{ $product->expires_at->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="3" class="empty">No hay productos vencidos.</td></tr>@endforelse
        </tbody></table>
    </section>

    <p class="footer">Documento generado por QuiQue Micromarket.</p>
</body>
</html>
