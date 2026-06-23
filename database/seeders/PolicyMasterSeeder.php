<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PolicyMaster;

class PolicyMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $policies = [

            [
                'policy_name'=>'Child Protection Policy',
                  'slug'=>'child-protection-policy',
                  'description'=>'https://www.acecqa.gov.au/nqf/national-law-regulations/national-quality-standard/quality-area-2-childrens-health-and-safety/standard-2-3-child-protection',
                 'sort_order'=>1
            ],
            [
                'policy_name'=>'Behaviour Guidance Policy',
                  'slug'=>'behaviour-guidance-policy',
                  'description'=>'https://www.acecqa.gov.au/nqf/national-law-regulations/national-quality-standard/quality-area-2-childrens-health-and-safety/standard-2-3-child-protection',
                 'sort_order'=>2
            ],
            [
                'policy_name'=>'Child safe Environment Policy',
                  'slug'=>'child-safe-environment-policy',
                  'description'=>'https://www.acecqa.gov.au/nqf/national-law-regulations/national-quality-standard/quality-area-2-childrens-health-and-safety/standard-2-3-child-protection',
                 'sort_order'=>3
            ],
            [
                'policy_name'=>'Emergency and Evacuation Policy',
                  'slug'=>'emergency-and-evacuation-policy',
                  'description'=>'https://www.acecqa.gov.au/nqf/national-law-regulations/national-quality-standard/quality-area-2-childrens-health-and-safety/standard-2-3-child-protection',
                 'sort_order'=>4
            ],

     
        ];

        foreach ($policies as $policy) {

            PolicyMaster::updateOrCreate(
                [
                    'slug' => $policy['slug']
                ],
                $policy
            );
        }
    }
}
