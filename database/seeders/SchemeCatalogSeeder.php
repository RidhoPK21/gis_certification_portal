<?php

namespace Database\Seeders;

use App\Models\CertificationScheme;
use App\Models\FormConfigurationVersion;
use App\Models\SchemeField;
use App\Models\SchemeRequiredDocument;
use App\Models\SchemeSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchemeCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = json_decode(
            file_get_contents(database_path('seeders/data/schemes.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        DB::transaction(function () use ($catalog) {
            foreach ($catalog as $schemeData) {
                $scheme = CertificationScheme::updateOrCreate(
                    ['code' => $schemeData['code']],
                    [
                        'slug' => $schemeData['slug'],
                        'name' => $schemeData['name'],
                        'short_name' => $schemeData['short_name'],
                        'category' => $schemeData['category'],
                        'standard' => $schemeData['standard'],
                        'description' => $schemeData['description'],
                        'form_version' => 1,
                        'order_prefix' => $schemeData['prefix'],
                        'review_template' => $schemeData['template'],
                        'is_active' => true,
                        'sort_order' => $schemeData['sort_order'],
                    ]
                );

                foreach ($schemeData['sections'] as $sectionData) {
                    $section = SchemeSection::updateOrCreate(
                        ['certification_scheme_id' => $scheme->id, 'code' => $sectionData['code']],
                        [
                            'title' => $sectionData['title'],
                            'description' => $sectionData['description'] ?? null,
                            'sort_order' => $sectionData['sort_order'],
                        ]
                    );

                    foreach ($sectionData['fields'] as $fieldData) {
                        $field = SchemeField::updateOrCreate(
                            ['scheme_section_id' => $section->id, 'code' => $fieldData['code']],
                            [
                                'label' => $fieldData['label'],
                                'type' => $fieldData['type'],
                                'placeholder' => $fieldData['placeholder'] ?? null,
                                'help_text' => $fieldData['help'] ?? null,
                                'unit' => $fieldData['unit'] ?? null,
                                'is_required' => $fieldData['required'] ?? false,
                                'is_repeatable' => $fieldData['repeatable'] ?? false,
                                'validation_rules' => $fieldData['validation'] ?? null,
                                'conditional_rules' => $fieldData['condition'] ?? null,
                                'sort_order' => $fieldData['sort_order'],
                                'version' => 1,
                                'is_active' => true,
                            ]
                        );

                        $field->options()->delete();

                        foreach ($fieldData['options'] ?? [] as $i => $option) {
                            $field->options()->create([
                                'value' => $option['value'],
                                'label' => $option['label'],
                                'sort_order' => $i + 1,
                                'is_active' => true,
                            ]);
                        }
                    }
                }

                foreach ($schemeData['documents'] as $docData) {
                    SchemeRequiredDocument::updateOrCreate(
                        ['certification_scheme_id' => $scheme->id, 'code' => $docData['code']],
                        [
                            'name' => $docData['name'],
                            'description' => $docData['description'] ?? null,
                            'requirement' => $docData['requirement'],
                            'conditional_rules' => $docData['condition'] ?? null,
                            'allowed_extensions' => $docData['extensions'],
                            'max_size_mb' => $docData['max_mb'],
                            'review_group' => $docData['review_group'],
                            'sort_order' => $docData['sort_order'],
                            'is_active' => true,
                        ]
                    );
                }

                FormConfigurationVersion::updateOrCreate(
                    ['certification_scheme_id' => $scheme->id, 'version' => 1],
                    ['snapshot' => $schemeData, 'published_at' => now()]
                );
            }
        });

        // Catatan: master produk SNI (SniProductMaster) diseed pada Fase 9
        // bersama modul Produk SNI dan importer-nya.
    }
}
