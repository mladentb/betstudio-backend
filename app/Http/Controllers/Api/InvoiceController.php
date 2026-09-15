<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    // User invoices with filters and stats
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        
        $query = Invoice::with(['order', 'sellerCompany'])
            ->where('user_id', $userId);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('year')) {
            $query->whereYear('issue_date', $request->year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('issue_date', $request->month);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        // Statistics for user
        $allInvoices = Invoice::where('user_id', $userId);
        $totalSpent = (clone $allInvoices)->sum('total');
        $paidTotal = (clone $allInvoices)->where('status', 'paid')->sum('total');
        $unpaidTotal = (clone $allInvoices)->whereIn('status', ['sent', 'draft'])->sum('total');
        $totalCount = (clone $allInvoices)->count();
        $paidCount = Invoice::where('user_id', $userId)->where('status', 'paid')->count();

        // Monthly spending for current year
        $monthlySpending = Invoice::where('user_id', $userId)
            ->whereYear('issue_date', now()->year)
            ->selectRaw("EXTRACT(MONTH FROM issue_date) as month, SUM(total) as total, COUNT(*) as count")
            ->groupByRaw("EXTRACT(MONTH FROM issue_date)")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Get available years for filter
        $availableYears = Invoice::where('user_id', $userId)
            ->selectRaw("EXTRACT(YEAR FROM issue_date) as year")
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return response()->json([
            'data' => $invoices,
            'stats' => [
                'total_spent' => round($totalSpent, 2),
                'paid_total' => round($paidTotal, 2),
                'unpaid_total' => round($unpaidTotal, 2),
                'total_count' => $totalCount,
                'paid_count' => $paidCount,
                'pending_count' => $totalCount - $paidCount,
            ],
            'monthly_spending' => $monthlySpending,
            'available_years' => $availableYears,
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        if ($invoice->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $invoice->load(['order.items.game', 'sellerCompany', 'user'])
        ]);
    }

    public function download(Request $request, Invoice $invoice)
    {
        // Allow user's own invoice or admin
        if ($invoice->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $this->generatePdf($invoice, 'download');
    }

    public function preview(Request $request, Invoice $invoice)
    {
        // Allow user's own invoice or admin
        if ($invoice->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $this->generatePdf($invoice, 'stream');
    }

    // Admin invoices
    public function adminIndex(Request $request)
    {
        $query = Invoice::with(['order', 'sellerCompany', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        return response()->json([
            'data' => $query->orderBy('created_at', 'desc')->get()
        ]);
    }

    public function adminDownload(Request $request, Invoice $invoice)
    {
        return $this->generatePdf($invoice, 'download');
    }

    private function generatePdf(Invoice $invoice, string $mode)
    {
        $invoice->load(['order.items.game', 'sellerCompany', 'user']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'seller' => $invoice->sellerCompany,
            'buyer' => $invoice->user,
            'items' => $invoice->order?->items ?? collect([]),
        ]);

        $filename = "faktura-{$invoice->invoice_number}.pdf";

        if ($mode === 'download') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
