<?php

use App\Enums\EduDegree;
use App\Enums\EduLevel;
use App\Enums\EduType;
use App\Enums\EntryType;
use App\Enums\Gender;
use App\Enums\Religion;

return [

    'edu-degrees' => [
        EduDegree::MiddleSchool() => 'Middle School',
        EduDegree::HighSchool() => 'High School',
        EduDegree::HigherSecondary() => 'Higher Secondary',
        EduDegree::Institution() => 'Institution',
        EduDegree::University() => 'University',
        EduDegree::Diploma() => 'Diploma',
        EduDegree::Masters() => 'Masters',
        EduDegree::PhD() => 'PhD',
    ],
    'edu-types' => [
        EduType::Governmental() => 'Governmental Education',
        EduType::Private() => 'Private Education',
        EduType::Open() => 'Open Education',
        EduType::Virtual() => 'Virtual Education',
    ],

    'edu-levels' => [

        EduLevel::First() => 'First Year',
        EduLevel::Second() => 'Second Year',
        EduLevel::Third() => 'Third Year',
        EduLevel::Forth() => 'Fourth Year',
        EduLevel::Fifth() => 'Fifth Year',
        EduLevel::Sixth() => 'Sixth Year',
        EduLevel::Graduate() => 'Graduate',
    ]
    ,
    'entry-types' => [
        EntryType::Free() => 'Free',
        EntryType::Daily() => 'Daily',
        EntryType::Monthly() => 'Monthly',
    ],
    'genders' => [
        Gender::Male() => 'Male',
        Gender::Female() => 'Female',
    ],
    'religions' => [
        Religion::Unknown() => 'Unknown',
        Religion::Christian() => 'Christian',
        Religion::Muslim() => 'Muslim'
    ]
];
