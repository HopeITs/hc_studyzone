<?php

use App\Enums\EduDegree;
use App\Enums\EduLevel;
use App\Enums\EduType;
use App\Enums\EntryType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\Roles;

return [
    'roles' => [
        Roles::Unknown() => 'unknown',
        Roles::GeneralAdmin() => 'General Admin',
        Roles::CenterAdmin() => 'City Admin',
        Roles::CenterAdmin() => 'Center Admin',
    ],

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
        EduLevel::Unknown() => 'غير معرف',
        EduLevel::First() => 'السنة الأولى',
        EduLevel::Second() => 'السنة الثانية',
        EduLevel::Third() => 'السنة الثالثة',
        EduLevel::Forth() => 'السنة الرابعة',
        EduLevel::Fifth() => 'السنة الخامسة',
        EduLevel::Sixth() => 'السنة السادسة',
        EduLevel::Graduate() => 'متخرج',
    ],
    'entry-types' => [
        EntryType::Free() => 'مجاني',
        EntryType::Daily() => 'يومي',
        EntryType::Monthly() => 'شهري',
    ],
    'genders' => [
        Gender::Unknown() => 'غير معرف',
        Gender::Male() => 'ذكر',
        Gender::Female() => 'أنثى',
    ],
    'religions' => [
        Religion::Unknown() => 'غير معروف',
        Religion::Christian() => 'مسيحي',
        Religion::Muslim() => 'مسلم',
    ],
    'labels' => [
        'firstname' => 'الاسم الأول',
        'lastname' => 'اسم العائلة',
        'father_name' => 'اسم الأب',
        'national_number' => 'الرقم الوطني',
        'gender' => 'الجنس',
        'religion' => 'الديانة',
        'date_of_birth' => 'تاريخ الميلاد',
        'is_student' => 'طالب',
        'is_employed' => 'موظف',
        'notes' => 'ملاحظات',
        'personal_information' => 'المعلومات الشخصية',
        'contact_information' => 'معلومات الاتصال',
        'street' => 'الشارع',
        'city' => 'المدينة',
        'phone' => 'الهاتف',
        'whatsapp' => 'واتساب',
        'email' => 'البريد الإلكتروني',
        'address' => 'العنوان',
        'education' => 'التعليم',
        'education_level' => 'مستوى التعليم',
        'education_type' => 'نوع التعليم',
        'add_education' => 'إضافة تعليم',
        'educations' => 'المؤهلات العلمية',
        'view_qr_code' => 'عرض رمز QR',
        'qr_code_modal_heading' => 'تفاصيل رمز الاستجابة السريعة للطالب',
        'entry' => 'تسجيل الدخول',
        'entry_type' => 'نوع الدخول',
        'entry_time' => 'يوم الدخول',
        'record_student_entry' => 'تسجيل دخول الطالب',
        'save_entry' => 'حفظ الدخول',
        'entry_recorded_successfully' => 'تم تسجيل الدخول بنجاح',
        'student_details' => 'تفاصيل الطالب',
        'uuid' => 'المعرف الفريد',
        'url' => 'الرابط',
        'qr_code' => 'رمز الاستجابة السريعة',
        'download_qr_code' => 'تحميل رمز الاستجابة السريعة',
        'print-service' => 'خدمة الطباعة',
        'mecrator-service' => 'خدمة المكراتور',
        'drawer-service' => 'خدمة المرسم',
    ],
];
