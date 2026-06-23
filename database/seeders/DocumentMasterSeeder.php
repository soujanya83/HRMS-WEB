<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentMaster;

class DocumentMasterSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [

            [
                'document_name' => 'Qualification Certificate (Latest/Highest Qualification)',
                'document_type' => 'Qualification Certificate',
                'slug' => 'qualification-certificate',
                'description' => 'Latest or highest qualification certificate',
                'icon' => '🎓',
                'is_required' => true,
                'has_expiry' => false,
                'expiry_years' => null,
                'sort_order' => 1
            ],

            [
                'document_name' => 'CPR Certificate (Full course – every 3 years)',
                'document_type' => 'CPR Certificate',
                'slug' => 'cpr-certificate',
                'description' => 'CPR training certificate',
                'icon' => '🫁',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 3,
                'sort_order' => 2
            ],

            [
                'document_name' => 'First-aid Certificate (Refresher Annually)',
                'document_type' => 'First Aid Certificate',
                'slug' => 'first-aid-certificate',
                'description' => 'Provide First Aid certificate (HLTAID012 or equivalent)',
                'icon' => '🚑',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 1,
                'sort_order' => 3
            ],

            [
                'document_name' => 'Anaphylaxis (Refresher Annually)',
                'document_type' => 'Anaphylaxis Certificate',
                'slug' => 'anaphylaxis',
                'description' => 'Anaphylaxis management training certificate',
                'icon' => '💉',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 1,
                'sort_order' => 4
            ],

            [
                'document_name' => 'Protecting Children - Mandatory Reporting (Annually)',
                'document_type' => 'Mandatory Reporting',
                'slug' => 'protecting-children-mandatory-reporting',
                'description' => 'Child protection training certificate',
                'icon' => '🛡️',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 1,
                'sort_order' => 5
            ],

            [
                'document_name' => 'Foundations of Child Safety Training (Every 2 years)',
                'document_type' => 'Foundations of Child Safety',
                'slug' => 'foundations-child-safety',
                'description' => 'Foundations of child safety training',
                'icon' => '👶',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 2,
                'sort_order' => 6
            ],

            [
                'document_name' => 'Foundations of Child Safety Training – Advanced (Every 2 years)',
                'document_type' => 'Advanced Child Safety',
                'slug' => 'advanced-child-safety',
                'description' => 'Advanced child safety training',
                'icon' => '🚀',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 2,
                'sort_order' => 7
            ],

            [
                'document_name' => 'Do Food Safely Certificate (Annually)',
                'document_type' => 'Food Safety Certificate',
                'slug' => 'food-safety',
                'description' => 'Do Food Safely certificate',
                'icon' => '🍎',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 1,
                'sort_order' => 8
            ],

            [
                'document_name' => "Allergens for Children's (CEC) Certificate (Every 2 years)",
                'document_type' => 'Allergens Certificate',
                'slug' => 'allergens',
                'description' => 'Allergen training certificate',
                'icon' => '🚫',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 2,
                'sort_order' => 9
            ],

            [
                'document_name' => 'SunSmart Certificate (Every 2 years)',
                'document_type' => 'SunSmart Certificate',
                'slug' => 'sunsmart',
                'description' => 'Sun safety certificate',
                'icon' => '☀️',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 2,
                'sort_order' => 10
            ],

            [
                'document_name' => 'Red Nose – Sleep Safe (Under 3 Years Old)',
                'document_type' => 'Sleep Safe Certificate',
                'slug' => 'sleep-safe',
                'description' => 'Sleep safety training certificate (optional)',
                'icon' => '💤',
                'is_required' => false,
                'has_expiry' => false,
                'expiry_years' => null,
                'sort_order' => 11
            ],

            [
                'document_name' => "Working with Children's Check (Renew Every 5 years)",
                'document_type' => 'Working With Children Check',
                'slug' => 'wwcc',
                'description' => 'WWCC card or notice',
                'icon' => '🆔',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 5,
                'sort_order' => 12
            ],

            [
                'document_name' => 'National Police Check',
                'document_type' => 'Police Check',
                'slug' => 'police-check',
                'description' => 'National Police Check certificate',
                'icon' => '👮',
                'is_required' => true,
                'has_expiry' => true,
                'expiry_years' => 3,
                'sort_order' => 13
            ],

            [
                'document_name' => 'Right to Work in Australia',
                'document_type' => 'Right to Work',
                'slug' => 'right-to-work',
                'description' => 'Proof of citizenship, passport, or visa',
                'icon' => '🇦🇺',
                'is_required' => true,
                'has_expiry' => false,
                'expiry_years' => null,
                'sort_order' => 14
            ]

        ];

        foreach ($documents as $document) {

            DocumentMaster::updateOrCreate(
                [
                    'slug' => $document['slug']
                ],
                $document
            );

        }
    }
}