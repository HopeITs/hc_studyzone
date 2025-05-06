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
        EduLevel::Unknown() => ' Unknown',
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
        Gender::Unknown() => 'Unknown',
        Gender::Male() => 'Male',
        Gender::Female() => 'Female',
    ],
    'religions' => [
        Religion::Unknown() => 'Unknown',
        Religion::Christian() => 'Christian',
        Religion::Muslim() => 'Muslim'
    ],

    'labels' => [

        'firstname' => 'First Name',
        'lastname' => 'Last Name',
        'father_name' => "Father's Name",
        'national_number' => 'National Number',
        'gender' => 'Gender',
        'religion' => 'Religion',
        'date_of_birth' => 'Date of Birth',
        'is_student' => 'Is Student',
        'is_employed' => 'Is Employee',
        'notes' => 'Notes',
        'personal_information' => 'Personal Information',
        'contact_information' => 'Contact Information',
        'street' => 'Street',
        'city' => 'City',
        'phone' => 'Phone',
        'whatsapp' => 'Whatsapp',
        'email' => 'Email',
        'address' => 'Address',
        'education' => 'Education',
        'education_level' => 'Education Level',
        'education_type' => 'Education Type',
        'add_education' => 'Add Education',
        'educations' => 'Educations',
        'view_qr_code' => 'View QR Code',
        'qr_code_modal_heading' => 'Student QR Code Details',
        'entry' => 'Entry',
        'entry_type' => 'Entry Type',
        'entry_time' => 'Entry Day',
        'record_student_entry' => 'Record Student Entry',
        'save_entry' => 'Save Entry',
        'entry_recorded_successfully' => 'Entry Recorded Successfully',
        'student_details' => 'Student Details',
        'uuid' => 'UUID',
        'url' => 'URL',
        'qr_code' => 'QR Code',
        'download_qr_code' => 'Download QR Code',
    ]
];
