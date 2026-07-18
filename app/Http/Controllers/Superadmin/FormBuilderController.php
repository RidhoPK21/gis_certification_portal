<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\CertificationScheme;
use App\Models\SchemeField;
use App\Models\SchemeRequiredDocument;
use App\Models\SchemeSection;
use App\Services\FormPublisherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FormBuilderController extends Controller
{
    public function edit(CertificationScheme $scheme): View
    {
        $scheme->load(['sections.fields.options', 'requiredDocuments']);

        return view('superadmin.form-builder', compact('scheme'));
    }

    public function storeSection(Request $request, CertificationScheme $scheme, FormPublisherService $publisher): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        if ($scheme->sections()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode section sudah digunakan.']);
        }

        $scheme->sections()->create($data);
        $publisher->publish($scheme, $request->user()->id, 'Section added');

        return back()->with('success', 'Section ditambahkan dan versi form baru dipublikasikan.');
    }

    public function storeField(Request $request, CertificationScheme $scheme, FormPublisherService $publisher): RedirectResponse
    {
        $data = $this->fieldData($request);
        $section = SchemeSection::where('certification_scheme_id', $scheme->id)
            ->findOrFail($data['scheme_section_id']);

        if (SchemeField::whereHas('section', fn ($q) => $q->where('certification_scheme_id', $scheme->id))
            ->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode field sudah digunakan pada section ini.']);
        }

        $options = $this->options($data['options_text'] ?? '');
        unset($data['options_text']);
        $field = $section->fields()->create($data);
        $this->syncOptions($field, $options);
        $publisher->publish($scheme, $request->user()->id, 'Field added: ' . $field->code);

        return back()->with('success', 'Field ditambahkan dan versi form baru dipublikasikan.');
    }

    public function updateField(Request $request, CertificationScheme $scheme, SchemeField $field, FormPublisherService $publisher): RedirectResponse
    {
        abort_unless($field->section->certification_scheme_id === $scheme->id, 404);
        $data = $this->fieldData($request, $field);

        if (SchemeField::whereKeyNot($field->id)
            ->whereHas('section', fn ($q) => $q->where('certification_scheme_id', $scheme->id))
            ->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode field sudah digunakan pada skema ini.']);
        }

        $options = $this->options($data['options_text'] ?? '');
        unset($data['options_text'], $data['scheme_section_id']);
        $field->update($data);
        $this->syncOptions($field, $options);
        $publisher->publish($scheme, $request->user()->id, 'Field updated: ' . $field->code);

        return back()->with('success', 'Field diperbarui dan versi form baru dipublikasikan.');
    }

    public function toggleField(Request $request, CertificationScheme $scheme, SchemeField $field, FormPublisherService $publisher): RedirectResponse
    {
        abort_unless($field->section->certification_scheme_id === $scheme->id, 404);
        $field->update(['is_active' => ! $field->is_active]);
        $publisher->publish($scheme, $request->user()->id, 'Field status changed: ' . $field->code);

        return back()->with('success', 'Status field diperbarui.');
    }

    public function storeDocument(Request $request, CertificationScheme $scheme, FormPublisherService $publisher): RedirectResponse
    {
        $data = $this->documentData($request);

        if ($scheme->requiredDocuments()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode dokumen sudah digunakan.']);
        }

        $scheme->requiredDocuments()->create($data);
        $publisher->publish($scheme, $request->user()->id, 'Document rule added: ' . $data['code']);

        return back()->with('success', 'Aturan dokumen ditambahkan.');
    }

    public function updateDocument(Request $request, CertificationScheme $scheme, SchemeRequiredDocument $document, FormPublisherService $publisher): RedirectResponse
    {
        abort_unless($document->certification_scheme_id === $scheme->id, 404);
        $document->update($this->documentData($request, $document));
        $publisher->publish($scheme, $request->user()->id, 'Document rule updated: ' . $document->code);

        return back()->with('success', 'Aturan dokumen diperbarui.');
    }

    public function toggleDocument(Request $request, CertificationScheme $scheme, SchemeRequiredDocument $document, FormPublisherService $publisher): RedirectResponse
    {
        abort_unless($document->certification_scheme_id === $scheme->id, 404);
        $document->update(['is_active' => ! $document->is_active]);
        $publisher->publish($scheme, $request->user()->id, 'Document status changed: ' . $document->code);

        return back()->with('success', 'Status dokumen diperbarui.');
    }

    private function fieldData(Request $request, ?SchemeField $field = null): array
    {
        $data = $request->validate([
            'scheme_section_id' => [$field ? 'nullable' : 'required', 'integer'],
            'code' => ['required', 'alpha_dash', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['text', 'textarea', 'number', 'email', 'date', 'url', 'select', 'radio', 'checkbox_group', 'boolean'])],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:30'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'validation_rules_text' => ['nullable', 'string'],
            'conditional_rules_text' => ['nullable', 'string'],
            'options_text' => ['nullable', 'string'],
        ]);

        $data['is_required'] = $request->boolean('is_required');
        $data['is_repeatable'] = false;
        $data['is_active'] = $field ? $field->is_active : true;
        $data['validation_rules'] = $this->rules($data['validation_rules_text'] ?? '');
        $data['conditional_rules'] = $this->json($data['conditional_rules_text'] ?? '', 'conditional_rules_text');
        unset($data['validation_rules_text'], $data['conditional_rules_text']);

        return $data;
    }

    private function documentData(Request $request, ?SchemeRequiredDocument $document = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirement' => ['required', Rule::in(['required', 'optional', 'conditional'])],
            'review_group' => ['required', Rule::in(['administration', 'technical'])],
            'allowed_extensions_text' => ['required', 'string'],
            'max_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'conditional_rules_text' => ['nullable', 'string'],
        ]);

        $data['allowed_extensions'] = array_values(array_filter(array_map(
            fn ($v) => strtolower(trim($v)),
            preg_split('/[,;\s]+/', $data['allowed_extensions_text'])
        )));
        $data['conditional_rules'] = $this->json($data['conditional_rules_text'] ?? '', 'conditional_rules_text');
        $data['is_active'] = $document ? $document->is_active : true;
        unset($data['allowed_extensions_text'], $data['conditional_rules_text']);

        return $data;
    }

    private function rules(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $text))));
    }

    private function json(string $text, string $field): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $value = json_decode($text, true);

        if (! is_array($value)) {
            throw ValidationException::withMessages([$field => 'JSON kondisi tidak valid.']);
        }

        return $value;
    }

    private function options(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$value, $label] = array_pad(explode('|', $line, 2), 2, null);
            $rows[] = ['value' => trim($value), 'label' => trim($label ?: $value)];
        }

        return $rows;
    }

    private function syncOptions(SchemeField $field, array $options): void
    {
        $field->options()->delete();

        foreach ($options as $i => $option) {
            $field->options()->create($option + ['sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
