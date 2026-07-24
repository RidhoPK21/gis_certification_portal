<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SniProductCategory;
use App\Models\SniProductGroup;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SniProductTaxonomyController extends Controller
{
    public function index()
    {
        return view('superadmin.sni-product-taxonomy', [
            'groups' => SniProductGroup::with('categories')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeGroup(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', 'unique:sni_product_groups,name'],
            'mandatory_type' => ['nullable', Rule::in(['wajib', 'sukarela'])],
        ]);
        $data['sort_order'] = (int) SniProductGroup::max('sort_order') + 1;
        $data['is_active'] = true;
        $group = SniProductGroup::create($data);
        $audit->log('sni_taxonomy.group_created', $group);

        return back()->with('success', 'Grup produk ditambahkan.');
    }

    public function updateGroup(Request $request, SniProductGroup $group, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', Rule::unique('sni_product_groups', 'name')->ignore($group->id)],
            'mandatory_type' => ['nullable', Rule::in(['wajib', 'sukarela'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = $group->toArray();
        $data['is_active'] = $request->boolean('is_active');
        $group->update($data);
        $audit->log('sni_taxonomy.group_updated', $group, $old, $group->fresh()->toArray());

        return back()->with('success', 'Grup produk diperbarui.');
    }

    public function storeCategory(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'sni_product_group_id' => ['required', 'exists:sni_product_groups,id'],
            'name' => ['required', 'string', 'max:200'],
        ]);
        $exists = SniProductCategory::where('sni_product_group_id', $data['sni_product_group_id'])
            ->where('name', $data['name'])->exists();
        abort_if($exists, 422, 'Kategori sudah ada pada grup ini.');

        $data['sort_order'] = (int) SniProductCategory::where('sni_product_group_id', $data['sni_product_group_id'])->max('sort_order') + 1;
        $data['is_active'] = true;
        $category = SniProductCategory::create($data);
        $audit->log('sni_taxonomy.category_created', $category);

        return back()->with('success', 'Kategori produk ditambahkan.');
    }

    public function updateCategory(Request $request, SniProductCategory $category, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = $category->toArray();
        $data['is_active'] = $request->boolean('is_active');
        $category->update($data);
        $audit->log('sni_taxonomy.category_updated', $category, $old, $category->fresh()->toArray());

        return back()->with('success', 'Kategori produk diperbarui.');
    }
}
