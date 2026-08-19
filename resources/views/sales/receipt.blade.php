<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $sale->sale_number }} · QuiQue Micromarket</title>
    <link rel="icon" type="image/png" href="{{ asset('images/quique-favicon.png') }}">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef4f5; color: #111; font-family: Arial, sans-serif; font-size: 12px; }
        .toolbar { display: flex; justify-content: center; gap: 10px; padding: 18px; }
        .button { border: 1px solid #bbb; border-radius: 7px; background: #fff; color: #222; cursor: pointer; padding: 10px 14px; font-size: 14px; font-weight: 700; text-decoration: none; }
        .button-primary { border-color: #2EB8CE; background: #2EB8CE; color: #102a2e; }
        .receipt { width: min(80mm, calc(100vw - 24px)); margin: 0 auto 24px; background: #fff; padding: 5mm; box-shadow: 0 8px 30px rgba(15, 23, 42, .15); }
        .center { text-align: center; }
        .logo { display: block; width: 18mm; height: 18mm; object-fit: contain; margin: 0 auto 2mm; }
        h1 { margin: 0; font-size: 17px; }
        .muted { color: #444; }
        .divider { margin: 3mm 0; border: 0; border-top: 1px dashed #555; }
        .meta p { margin: 1mm 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 1.2mm 0; vertical-align: top; }
        th { border-bottom: 1px solid #555; font-size: 10px; text-align: left; }
        .product { width: 42%; overflow-wrap: anywhere; }
        .number { text-align: right; white-space: nowrap; }
        .total { font-size: 15px; font-weight: 700; }
        .tickets { overflow-wrap: anywhere; }
        @media print {
            @page { margin: 3mm; }
            body { background: #fff; }
            .toolbar { display: none !important; }
            .receipt { width: 72mm; max-width: 100%; margin: 0 auto; padding: 0; box-shadow: none; }
        }
        @media (max-width: 420px) { .toolbar { flex-wrap: wrap; padding: 12px; } }
    </style>
</head>
<body>
    <div class="toolbar" aria-label="Acciones del recibo">
        <a class="button" href="{{ route('sales.show', $sale) }}">Volver al detalle</a>
        <button class="button button-primary" type="button" onclick="window.print()">Imprimir recibo</button>
    </div>

    <main class="receipt">
        <header class="center">
            <img class="logo" src="{{ asset('images/quique-logo.png') }}" alt="QuiQue Micromarket">
            <h1>QuiQue Micromarket</h1>
            <div class="muted">{{ $sale->branch->name }}</div>
            @if ($sale->branch->address)<div class="muted">{{ $sale->branch->address }}</div>@endif
        </header>

        <hr class="divider">
        <section class="meta">
            <p><strong>Venta:</strong> {{ $sale->sale_number }}</p>
            <p><strong>Fecha:</strong> {{ $sale->confirmed_at->format('d/m/Y H:i') }}</p>
            <p><strong>Responsable:</strong> {{ $sale->user->name }}</p>
        </section>

        <hr class="divider">
        <table aria-label="Productos vendidos">
            <thead><tr><th class="product">Producto</th><th class="number">Cant.</th><th class="number">P/U</th><th class="number">Subtotal</th></tr></thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr><td class="product">{{ $item->product_name }}</td><td class="number">{{ $item->quantityLabel() }}</td><td class="number">{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td><td class="number">{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <hr class="divider">
        <table>
            <tr class="total"><td>Total</td><td class="number">Bs {{ number_format((float) $sale->total, 2, ',', '.') }}</td></tr>
        </table>

        <hr class="divider">
        <section>
            <strong>Pagos</strong>
            @foreach ($sale->payments as $payment)
                <p>{{ $payment->method->label() }}: <strong>Bs {{ number_format((float) $payment->amount, 2, ',', '.') }}</strong></p>
                @if ($payment->method->value === 'cash' && $payment->received_amount !== null)
                    <p>Recibido: Bs {{ number_format((float) $payment->received_amount, 2, ',', '.') }} · Cambio: Bs {{ number_format((float) $payment->change_amount, 2, ',', '.') }}</p>
                @endif
            @endforeach
        </section>

        @if ($sale->customer || $sale->raffleParticipation?->tickets->isNotEmpty())
            <hr class="divider">
            <section>
                @if ($sale->customer)<p><strong>Cliente:</strong> {{ $sale->customer->full_name }}</p>@endif
                @if ($sale->raffleParticipation?->tickets->isNotEmpty())
                    <p class="tickets"><strong>Tickets:</strong> {{ $sale->raffleParticipation->tickets->pluck('ticket_number')->join(', ') }}</p>
                @endif
            </section>
        @endif

        <hr class="divider">
        <footer class="center">Gracias por su compra.</footer>
    </main>
</body>
</html>
