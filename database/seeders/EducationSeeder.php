<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Enums\EduDegree;

class EducationSeeder extends Seeder
{
    public function run()
    {
        $educations = [
            // البكالوريا
            ['name' => ['ar' => 'البكالوريا علمي', 'en' => 'Scientific Baccalaureate'], 'degree' => EduDegree::HigherSecondary()],
            ['name' => ['ar' => 'البكالوريا أدبي', 'en' => 'Literary Baccalaureate'], 'degree' => EduDegree::HigherSecondary()],

            // المعاهد
            ['name' => ['ar' => 'معهد تقاني للحاسوب', 'en' => 'Technical Institute of Computer Science'], 'degree' => EduDegree::Institution()],
            ['name' => ['ar' => 'معهد تقاني طبي', 'en' => 'Technical Medical Institute'], 'degree' => EduDegree::Institution()],

            ['name' => ['ar' => 'هندسة معلوماتية', 'en' => 'Information Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة مدنية', 'en' => 'Civil Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة معمارية', 'en' => 'Architecture Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة ميكانيكية', 'en' => 'Mechanical Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة كهربائية', 'en' => 'Electrical Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة إلكترونيات واتصالات', 'en' => 'Electronics & Communications Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة طبية حيوية', 'en' => 'Biomedical Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة بيئية', 'en' => 'Environmental Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة صناعية', 'en' => 'Industrial Engineering'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'هندسة الميكاترونيك', 'en' => 'Mechatronics Engineering'], 'degree' => EduDegree::University()],

            // 🏥 التخصصات الطبية
            ['name' => ['ar' => 'الطب البشري', 'en' => 'Medicine'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'طب الأسنان', 'en' => 'Dentistry'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'الصيدلة', 'en' => 'Pharmacy'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'التمريض', 'en' => 'Nursing'], 'degree' => EduDegree::University()],

            // 🎓 باقي التخصصات
            ['name' => ['ar' => 'الحقوق', 'en' => 'Law'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'الاقتصاد', 'en' => 'Economics'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'الآداب والعلوم الإنسانية', 'en' => 'Arts and Humanities'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'العلوم السياسية', 'en' => 'Political Science'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'الإعلام', 'en' => 'Media and Journalism'], 'degree' => EduDegree::University()],
            ['name' => ['ar' => 'التربية', 'en' => 'Education'], 'degree' => EduDegree::University()],

            // 📚 الدبلومات
            ['name' => ['ar' => 'دبلوم تأهيل تربوي', 'en' => 'Educational Qualification Diploma'], 'degree' => EduDegree::Diploma()],
            ['name' => ['ar' => 'دبلوم إدارة أعمال', 'en' => 'Business Administration Diploma'], 'degree' => EduDegree::Diploma()],

            // 🎓 الماجستير
            ['name' => ['ar' => 'ماجستير في الهندسة المدنية', 'en' => 'Master in Civil Engineering'], 'degree' => EduDegree::Masters()],
            ['name' => ['ar' => 'ماجستير في الفيزياء', 'en' => 'Master in Physics'], 'degree' => EduDegree::Masters()],
            ['name' => ['ar' => 'ماجستير في هندسة البرمجيات', 'en' => 'Master in Software Engineering'], 'degree' => EduDegree::Masters()],
            ['name' => ['ar' => 'ماجستير في الاقتصاد', 'en' => 'Master in Economics'], 'degree' => EduDegree::Masters()],

            // 🎓 الدكتوراه
            ['name' => ['ar' => 'دكتوراه في الطب', 'en' => 'PhD in Medicine'], 'degree' => EduDegree::PhD()],
            ['name' => ['ar' => 'دكتوراه في الفيزياء', 'en' => 'PhD in Physics'], 'degree' => EduDegree::PhD()],
            ['name' => ['ar' => 'دكتوراه في العلوم السياسية', 'en' => 'PhD in Political Science'], 'degree' => EduDegree::PhD()],



        ];

        foreach ($educations as $education) {
            DB::table('educations')->insert([
                'name' => json_encode($education['name'], JSON_UNESCAPED_UNICODE),
                'degree' => $education['degree'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
