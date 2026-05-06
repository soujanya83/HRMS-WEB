<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MandatoryQuestion;

class MandatoryQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        $questions = [
            'Working With Children Check',
            'First Aid Certification (HLTAID012)',
            'National Police Check',
            'Qualification Certificate',
            'Immunisation Record',
            'Signed Code of Conduct',
            'Completed Induction',
            'Right to Work in Australia',
        ];

        foreach ($questions as $q) {
            MandatoryQuestion::create(['question' => $q]);
        }
    }
}
