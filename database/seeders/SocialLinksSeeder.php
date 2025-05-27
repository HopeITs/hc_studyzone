<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use App\Models\SocialLink;

class SocialLinksSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            ['city' => 'homs', 'facebook' => 'https://www.facebook.com/szhoms', 'instagram' => 'https://www.instagram.com/study.zone.homs', 'whatsapp' => 'https://whatsapp.com/channel/0029VayjTibZ47XeGAbp7xcD0', 'telegram' => null, 'website' => 'https://hcsyria.org/projects/study-zone?tab=homs'],
            ['city' => 'damascus', 'facebook' => 'https://www.facebook.com/szdamascus', 'instagram' => 'https://www.instagram.com/study.zone.damascus', 'whatsapp' => null, 'telegram' => 'https://t.me/studyzonedamascus', 'website' => 'https://hcsyria.org/projects/study-zone?tab=damas'],
            ['city' => 'aleppo', 'facebook' => 'https://www.facebook.com/szaleppo', 'instagram' => 'https://www.instagram.com/study.zone.aleppo', 'whatsapp' => 'https://whatsapp.com/channel/0029VaPi5A89MAyUM1i13', 'telegram' => null, 'website' => 'https://hcsyria.org/projects/study-zone?tab=aleppo'],
            ['city' => 'latakia', 'facebook' => 'https://www.facebook.com/szlatakia', 'instagram' => 'https://www.instagram.com/study.zone.latakia', 'whatsapp' => 'https://whatsapp.com/channel/0029Vb122Ec4ewdJHgux3d', 'telegram' => null, 'website' => 'https://hcsyria.org/projects/study-zone?tab=latakia'],
            ['city' => 'mouzeina', 'facebook' => 'https://www.facebook.com/szmouzeina', 'instagram' => 'https://www.instagram.com/study.zone.mouzeina', 'whatsapp' => 'https://whatsapp.com/channel/0029Vb1CWpp8H4V3E6uvUy', 'telegram' => null, 'website' => 'https://hcsyria.org/projects/study-zone?tab=homs'],
            ['city' => 'tartus', 'facebook' => 'https://www.facebook.com/sztartus', 'instagram' => 'https://www.instagram.com/study.zone.tartus', 'whatsapp' => null, 'telegram' => 'https://t.me/studyzonetartus', 'website' => 'https://hcsyria.org/projects/study-zone?tab=tartus'],
        ];

        foreach ($rows as $r) {
            City::where('code', $r['city'])
                ->first()
                ?->update([
                    'facebook' => $r['facebook'] ?? null,
                    'instagram' => $r['instagram'] ?? null ,
                    'whatsapp' => $r['whatsapp'] ?? null ,
                    'telegram' => $r['telegram'] ?? null ,
                    'website' => $r['website'] ?? null ,
                ]);
        }
    }
}
