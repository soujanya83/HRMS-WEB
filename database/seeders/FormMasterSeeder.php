<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FormMaster;


class FormMasterSeeder extends Seeder
{
    public function run(): void
    {
        $forms = [

            [
                'form_name' => 'Staff Induction',
                'table_name' => 'staff_inductions',
                'slug' => 'staff-induction',
                'sort_order' => 1
            ],

            [
                'form_name' => 'Child Safe Code Of Conduct',
                'table_name' => 'child_safe_code_of_conduct_forms',
                'slug' => 'child-safe-code-of-conduct',
                'sort_order' => 2
            ],

            [
                'form_name' => 'PIDTDC Form',
                'table_name' => 'pidtdc_forms',
                'slug' => 'pidtdc-form',
                'sort_order' => 3
            ],

            [
                'form_name' => 'Superannuation Form',
                'table_name' => 'superannuation_forms',
                'slug' => 'superannuation-form',
                'sort_order' => 4
            ],

            [
                'form_name' => 'TFN Declaration',
                'table_name' => 'tfn_declarations',
                'slug' => 'tfn-declaration',
                'sort_order' => 5
            ],

            [
                'form_name' => 'Staff Record',
                'table_name' => 'staff_records',
                'slug' => 'staff-record',
                'sort_order' => 6
            ],

            [
                'form_name' => 'Prohibition Notice Declaration',
                'table_name' => 'prohibition_notice_declarations',
                'slug' => 'prohibition-notice-declaration',
                'sort_order' => 7
            ],
        ];

        foreach ($forms as $form) {

            FormMaster::updateOrCreate(
                [
                    'table_name' => $form['table_name']
                ],
                $form
            );
        }
    }
}
