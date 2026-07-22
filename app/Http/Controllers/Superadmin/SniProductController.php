<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SniProductMaster;
use App\Services\AuditLogger;
use App\Services\SniProductImportService;
use Illuminate\Http\Request;

class SniProductController extends Controller
{
    public function index(Request $request)
    {
        $products = SniProductMaster::query()
            ->when($request->q, fn ($q, $term) => $q->where(fn ($x) => $x
                ->where('product_name', 'like', '%'.$term.'%')
                ->orWhere('product_code', 'like', '%'.$term.'%')
                ->orWhere('sni_number', 'like', '%'.$term.'%')))
            ->orderBy('product_name')
            ->paginate(25)
            ->withQueryString();

        return view('superadmin.sni-products', compact('products'));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'product_code' => ['required', 'string', 'max:100', 'unique:sni_product_master,product_code'],
            'product_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'sni_number' => ['nullable', 'string', 'max:150'],
            'certification_system' => ['required', 'string', 'max:100'],
        ]);
        $product = SniProductMaster::create($data + ['is_active' => true]);
        $audit->log('sni_product.created', $product);

        return back()->with('success', 'Produk SNI ditambahkan.');
    }

    public function update(Request $request, SniProductMaster $product, AuditLogger $audit)
    {
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'sni_number' => ['nullable', 'string', 'max:150'],
            'certification_system' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = $product->toArray();
        $data['is_active'] = $request->boolean('is_active');
        $product->update($data);
        $audit->log('sni_product.updated', $product, $old, $product->fresh()->toArray());

        return back()->with('success', 'Produk SNI diperbarui.');
    }

    public function import(Request $request, SniProductImportService $service, AuditLogger $audit)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']]);
        $result = $service->import($request->file('file'));
        $audit->log('sni_product.imported', null, [], $result);

        $message = "Import selesai: {$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return back()->with('success', $message);
    }
}
