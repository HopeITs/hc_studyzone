<?php

use App\Enums\EduDegree;
use App\Enums\EduLevel;
use App\Enums\EduType;
use App\Enums\EntryType;
use App\Enums\Gender;
use App\Enums\Religion;

return [

    'edu-degrees' => [
        EduDegree::MiddleSchool() => 'المرحلة الإعدادية',
        EduDegree::HighSchool() => 'المرحلة الثانوية',
        EduDegree::HigherSecondary() => 'الثانوية العليا',
        EduDegree::Institution() => 'المعهد',
        EduDegree::University() => 'الجامعة',
        EduDegree::Diploma() => 'دبلوم',
        EduDegree::Masters() => 'الماجستير',
        EduDegree::PhD() => 'الدكتوراه',
    ],
    'edu-types' => [
        EduType::Governmental() => 'التعليم الحكومي',
        EduType::Private() => 'التعليم الخاص',
        EduType::Open() => 'التعليم المفتوح',
        EduType::Virtual() => 'التعليم الافتراضي',
    ],

    'edu-levels' => [

        EduLevel::First() => 'السنة الأولى',
        EduLevel::Second() => 'السنة الثانية',
        EduLevel::Third() => 'السنة الثالثة',
        EduLevel::Forth() => 'السنة الرابعة',
        EduLevel::Fifth() => 'السنة الخامسة',
        EduLevel::Sixth() => 'السنة السادسة',
        EduLevel::Graduate() => 'متخرج',
    ]
    ,
    'entry-types' => [
        EntryType::Free() => 'مجاني',
        EntryType::Daily() => 'يومي',
        EntryType::Monthly() => 'شهري',
    ],
    'genders' => [
        Gender::Male() => 'ذكر',
        Gender::Female() => 'أنثى',
    ],
    'religions' => [
        Religion::Unknown() => 'غير معروف',
        Religion::Christian() => 'مسيحي',
        Religion::Muslim() => 'مسلم'
    ]
];
