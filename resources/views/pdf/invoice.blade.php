<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Faktura {{ $invoice->invoice_number }}</title>
    @php
        $currencySymbols = ['EUR' => '€', 'USD' => '$', 'SOL' => '◎'];
        $symbol = $currencySymbols[$invoice->currency] ?? '€';
        $decimals = $invoice->currency === 'SOL' ? 4 : 2;
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
        .container { padding: 30px; }
        
        .header { margin-bottom: 30px; border-bottom: 2px solid #3b82f6; padding-bottom: 15px; }
        .header table { width: 100%; }
        .logo { font-size: 24px; font-weight: bold; color: #3b82f6; }
        .invoice-title { font-size: 28px; color: #1f2937; }
        .invoice-number { font-size: 14px; color: #6b7280; }
        
        .status { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-unpaid, .status-sent { background: #dbeafe; color: #1e40af; }
        .status-draft { background: #f3f4f6; color: #374151; }
        
        .parties { margin-bottom: 25px; }
        .parties table { width: 100%; }
        .parties td { width: 50%; vertical-align: top; padding-right: 15px; }
        .party-box { background: #f9fafb; padding: 15px; border-radius: 5px; border: 1px solid #e5e7eb; }
        .party-label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin-bottom: 5px; font-weight: bold; letter-spacing: 0.5px; }
        .party-name { font-size: 14px; font-weight: bold; color: #1f2937; margin-bottom: 3px; }
        .party-details { color: #4b5563; font-size: 11px; }
        
        .info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .info-table td { padding: 10px; background: #f3f4f6; border: 1px solid #e5e7eb; }
        .info-label { font-size: 9px; color: #6b7280; text-transform: uppercase; display: block; }
        .info-value { font-weight: bold; color: #1f2937; font-size: 12px; }
        
        .items { margin-bottom: 25px; }
        .items table { width: 100%; border-collapse: collapse; }
        .items th { background: #1f2937; color: white; padding: 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .items td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .items .text-right { text-align: right; }
        
        .totals { width: 280px; margin-left: auto; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 8px 10px; }
        .totals .label { color: #6b7280; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .total-row td { background: #3b82f6; color: white; font-size: 14px; }
        
        .bank-info { background: #fef3c7; padding: 15px; border-radius: 5px; margin-top: 25px; border: 1px solid #fcd34d; }
        .bank-info h4 { color: #92400e; margin-bottom: 8px; font-size: 12px; }
        .bank-info p { color: #78350f; font-size: 11px; margin: 0; }
        
        .notes { margin-top: 20px; padding: 12px; background: #f3f4f6; border-radius: 5px; }
        .notes-label { font-weight: bold; margin-bottom: 5px; }
        
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <table>
                <tr>
                    <td style="width: 50%;">
                        <div class="logo">🏆 BetStudio</div>
                        <div style="font-size: 11px; color: #6b7280; margin-top: 5px;">Sports Streaming Platform</div>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <div class="invoice-title">FAKTURA</div>
                        <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                        <div style="margin-top: 8px;">
                            <span class="status status-{{ $invoice->status }}">
                                @if($invoice->status === 'paid') PLAĆENO @elseif($invoice->status === 'sent') POSLATO @else NACRT @endif
                            </span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Parties -->
        <div class="parties">
            <table>
                <tr>
                    <td>
                        <div class="party-box">
                            <div class="party-label">Prodavac</div>
                            <div class="party-name">{{ $seller->name ?? 'BetStudio' }}</div>
                            <div class="party-details">
                                @if($seller)
                                    @if($seller->address){{ $seller->address }}<br>@endif
                                    @if($seller->city){{ $seller->city }}, @endif{{ $seller->country ?? '' }}<br>
                                    @if($seller->vat_number)PIB: {{ $seller->vat_number }}<br>@endif
                                    @if($seller->registration_number)MB: {{ $seller->registration_number }}<br>@endif
                                    @if($seller->email){{ $seller->email }}@endif
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="party-box">
                            <div class="party-label">Kupac</div>
                            <div class="party-name">{{ $invoice->buyer_company_name ?? $buyer->name ?? 'N/A' }}</div>
                            <div class="party-details">
                                @if($invoice->buyer_address){{ $invoice->buyer_address }}<br>@endif
                                @if($invoice->buyer_vat)PIB: {{ $invoice->buyer_vat }}<br>@endif
                                {{ $buyer->email ?? '' }}
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Invoice Info -->
        <table class="info-table">
            <tr>
                <td style="width: 25%;">
                    <span class="info-label">Datum izdavanja</span>
                    <span class="info-value">{{ \Carbon\Carbon::parse($invoice->issue_date)->format('d.m.Y') }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="info-label">Rok plaćanja</span>
                    <span class="info-value">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y') }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="info-label">Broj porudžbine</span>
                    <span class="info-value">{{ $invoice->order->order_number ?? '-' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="info-label">Valuta</span>
                    <span class="info-value">{{ $invoice->currency }}</span>
                </td>
            </tr>
        </table>

        <!-- Items -->
        <div class="items">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%;">Opis</th>
                        <th style="width: 15%;">Količina</th>
                        <th style="width: 17%;">Cena</th>
                        <th style="width: 18%;" class="text-right">Ukupno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ $symbol }}{{ number_format($item->unit_price, $decimals) }}</td>
                        <td class="text-right">{{ $symbol }}{{ number_format($item->total_price, $decimals) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #6b7280;">Nema stavki</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="totals">
            <table>
                <tr>
                    <td class="label">Osnovica:</td>
                    <td class="value">{{ $symbol }}{{ number_format($invoice->subtotal, $decimals) }}</td>
                </tr>
                @if($invoice->discount_percent > 0)
                <tr style="color: #059669;">
                    <td class="label">Popust ({{ number_format($invoice->discount_percent, 0) }}%):</td>
                    <td class="value">-{{ $symbol }}{{ number_format($invoice->discount_amount, $decimals) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">PDV ({{ number_format($invoice->tax_rate, 0) }}%):</td>
                    <td class="value">{{ $symbol }}{{ number_format($invoice->tax_amount, $decimals) }}</td>
                </tr>
                <tr class="total-row">
                    <td><strong>ZA UPLATU:</strong></td>
                    <td class="value"><strong>{{ $symbol }}{{ number_format($invoice->total, $decimals) }}</strong></td>
                </tr>
            </table>
        </div>

        <!-- Bank Info -->
        @if($seller && $seller->bank_account)
        <div class="bank-info">
            <h4>💳 Podaci za uplatu</h4>
            <p>
                <strong>Banka:</strong> {{ $seller->bank_name ?? 'N/A' }}<br>
                <strong>Broj računa:</strong> {{ $seller->bank_account }}<br>
                @if($seller->swift_bic)<strong>SWIFT/BIC:</strong> {{ $seller->swift_bic }}<br>@endif
                <strong>Poziv na broj:</strong> {{ $invoice->invoice_number }}
            </p>
        </div>
        @endif

        <!-- Notes -->
        @if($invoice->notes)
        <div class="notes">
            <div class="notes-label">Napomena:</div>
            {{ $invoice->notes }}
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Hvala na poverenju! | {{ $seller->website ?? 'www.betstudio.com' }}</p>
            <p style="margin-top: 5px;">Dokument je automatski generisan i važi bez pečata i potpisa.</p>
        </div>
    </div>
</body>
</html>
