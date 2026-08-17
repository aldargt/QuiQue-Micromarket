@php
    $forPdf ??= false;
    $pointCount = count($chart['labels']);
    $width = max(760, $pointCount * 38);
    $height = 330;
    $plot = ['left' => 58, 'right' => $width - 72, 'top' => 54, 'bottom' => 276];
    $plotWidth = $plot['right'] - $plot['left'];
    $plotHeight = $plot['bottom'] - $plot['top'];
    $maxSales = max(1, ...$chart['sales']);
    $maxTotal = max(1, ...$chart['totals']);
    $slot = $plotWidth / $pointCount;
    $salesPoints = [];
    $totalPoints = [];
    foreach ($chart['labels'] as $index => $label) {
        $x = $plot['left'] + ($slot * $index) + ($slot / 2);
        $salesY = $plot['bottom'] - (($chart['sales'][$index] / $maxSales) * $plotHeight);
        $totalY = $plot['bottom'] - (($chart['totals'][$index] / $maxTotal) * $plotHeight);
        $salesPoints[] = ['x' => $x, 'y' => $salesY];
        $totalPoints[] = ['x' => $x, 'y' => $totalY];
    }
    $labelStep = max(1, (int) ceil($pointCount / 12));
@endphp

<svg class="report-chart-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" role="img" aria-labelledby="report-chart-title report-chart-description" style="display:block;max-width:none;margin:0 auto;">
    <title id="report-chart-title">{{ $chart['title'] }}</title>
    <desc id="report-chart-description">Cantidad de ventas en línea continua con círculos y monto vendido en línea punteada con cuadrados.</desc>

    <g font-family="DejaVu Sans, Arial, sans-serif" font-size="11" fill="{{ $forPdf ? '#374151' : 'var(--report-chart-text, #374151)' }}">
        <line x1="{{ $plot['left'] }}" y1="22" x2="{{ $plot['left'] + 34 }}" y2="22" stroke="{{ $forPdf ? '#4338ca' : 'var(--report-chart-sales, #4338ca)' }}" stroke-width="2" />
        <circle cx="{{ $plot['left'] + 17 }}" cy="22" r="4" fill="{{ $forPdf ? '#fff' : 'var(--report-chart-marker, #fff)' }}" stroke="{{ $forPdf ? '#4338ca' : 'var(--report-chart-sales, #4338ca)' }}" stroke-width="2" />
        <text x="{{ $plot['left'] + 42 }}" y="26">Cantidad de ventas</text>
        <line x1="{{ $plot['left'] + 180 }}" y1="22" x2="{{ $plot['left'] + 218 }}" y2="22" stroke="{{ $forPdf ? '#0891b2' : 'var(--report-chart-total, #0891b2)' }}" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 7" />
        <rect x="{{ $plot['left'] + 195 }}" y="18" width="8" height="8" fill="{{ $forPdf ? '#fff' : 'var(--report-chart-marker, #fff)' }}" stroke="{{ $forPdf ? '#0891b2' : 'var(--report-chart-total, #0891b2)' }}" stroke-width="2" />
        <text x="{{ $plot['left'] + 226 }}" y="26">Monto vendido (Bs)</text>
    </g>

    @for ($step = 0; $step <= 4; $step++)
        @php $y = $plot['bottom'] - (($plotHeight * $step) / 4); @endphp
        <line x1="{{ $plot['left'] }}" y1="{{ $y }}" x2="{{ $plot['right'] }}" y2="{{ $y }}" stroke="{{ $forPdf ? '#d1d5db' : 'var(--report-chart-grid, #d1d5db)' }}" stroke-width="1" />
        <text x="{{ $plot['left'] - 8 }}" y="{{ $y + 4 }}" text-anchor="end" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" fill="{{ $forPdf ? '#4b5563' : 'var(--report-chart-muted, #4b5563)' }}">{{ (int) round(($maxSales * $step) / 4) }}</text>
        <text x="{{ $plot['right'] + 8 }}" y="{{ $y + 4 }}" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" fill="{{ $forPdf ? '#4b5563' : 'var(--report-chart-muted, #4b5563)' }}">Bs {{ (int) round(($maxTotal * $step) / 4) }}</text>
    @endfor

    <polyline points="{{ collect($totalPoints)->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ') }}" fill="none" stroke="{{ $forPdf ? '#0891b2' : 'var(--report-chart-total, #0891b2)' }}" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 7" stroke-linejoin="round" />
    @foreach ($totalPoints as $point)
        <rect x="{{ $point['x'] - 4 }}" y="{{ $point['y'] - 4 }}" width="8" height="8" fill="{{ $forPdf ? '#fff' : 'var(--report-chart-marker, #fff)' }}" stroke="{{ $forPdf ? '#0891b2' : 'var(--report-chart-total, #0891b2)' }}" stroke-width="2" />
    @endforeach

    <polyline points="{{ collect($salesPoints)->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ') }}" fill="none" stroke="{{ $forPdf ? '#4338ca' : 'var(--report-chart-sales, #4338ca)' }}" stroke-width="2" stroke-linejoin="round" />
    @foreach ($salesPoints as $point)
        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="{{ $forPdf ? '#fff' : 'var(--report-chart-marker, #fff)' }}" stroke="{{ $forPdf ? '#4338ca' : 'var(--report-chart-sales, #4338ca)' }}" stroke-width="2" />
    @endforeach

    @foreach ($chart['labels'] as $index => $label)
        @if ($index % $labelStep === 0 || $index === $pointCount - 1)
            <text x="{{ $salesPoints[$index]['x'] }}" y="300" text-anchor="middle" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" fill="{{ $forPdf ? '#4b5563' : 'var(--report-chart-muted, #4b5563)' }}">{{ $label }}</text>
        @endif
    @endforeach
    <text x="18" y="{{ ($plot['top'] + $plot['bottom']) / 2 }}" text-anchor="middle" transform="rotate(-90 18 {{ ($plot['top'] + $plot['bottom']) / 2 }})" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" fill="{{ $forPdf ? '#374151' : 'var(--report-chart-text, #374151)' }}">Ventas</text>
    <text x="{{ $width - 12 }}" y="{{ ($plot['top'] + $plot['bottom']) / 2 }}" text-anchor="middle" transform="rotate(90 {{ $width - 12 }} {{ ($plot['top'] + $plot['bottom']) / 2 }})" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" fill="{{ $forPdf ? '#374151' : 'var(--report-chart-text, #374151)' }}">Monto (Bs)</text>
</svg>

@unless ($forPdf)
    <div class="sr-only"><table><caption>Datos de {{ strtolower($chart['title']) }}</caption><thead><tr><th>{{ $chart['interval'] === 'hour' ? 'Hora' : 'Día' }}</th><th>Ventas</th><th>Total</th></tr></thead><tbody>@foreach ($chart['labels'] as $index => $label)<tr><td>{{ $label }}</td><td>{{ $chart['sales'][$index] }}</td><td>Bs {{ number_format($chart['totals'][$index], 2, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
@endunless
