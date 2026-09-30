<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public website content (Administration → Website):
 *   landing_items   services, common questions (faq) and clinic team members
 *   announcements   advisories for the public website and / or the staff dashboard
 *
 * Seeds plain default services and common questions so a fresh install shows a
 * complete page. No team members or advisories are seeded (those sections stay
 * hidden until the clinic adds real ones).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_items', function (Blueprint $table) {
            $table->id();
            $table->string('section', 20);                 // service | faq | team
            $table->string('title', 150);                  // service name, question, person's name
            $table->string('subtitle', 150)->nullable();   // team: role
            $table->text('body')->nullable();              // description, answer, short bio
            $table->string('icon', 60)->nullable();        // Bootstrap Icons name (services)
            $table->string('image')->nullable();           // public disk path (landing/...)
            $table->string('link_label', 60)->nullable();
            $table->string('link_url', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['section', 'is_enabled', 'sort_order']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->string('type', 20)->default('info');      // info | advisory | urgent
            $table->string('link_label', 60)->nullable();
            $table->string('link_url', 300)->nullable();
            $table->string('audience', 10)->default('public'); // public | staff | both
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'audience']);
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('landing_items');
    }

    private function seedDefaults(): void
    {
        $now = now();

        $services = [
            ['bandaid', 'First aid and emergency care',
                'Care for wounds, sprains, nosebleeds, fainting and other injuries or sudden illness on campus. Serious cases are brought to the nearest hospital and the parent or guardian is called right away.'],
            ['clipboard2-pulse', 'Consultation with the school nurse',
                'For headache, fever, stomach ache and other complaints. The nurse checks your vital signs, gives advice, and lets you rest in the clinic or sends you home when needed.'],
            ['capsule', 'Medicine dispensing',
                'Basic over-the-counter medicines for common complaints, given according to the clinic\'s medicine policy and the allergies in your health record. Every dose is written in your clinic record.'],
            ['file-earmark-medical', 'Health records and clearances',
                'The clinic keeps each student\'s health information form, visit history and vaccination details, and issues medical clearances for school activities, sports and field trips.'],
            ['calendar2-heart', 'Doctor and dentist visit days',
                'The school physician and dentist come on scheduled days for check-ups, dental examinations and follow-ups. The next visit days are listed under Clinic hours.'],
            ['heart-pulse', 'Health monitoring',
                'Yearly height, weight and vision checks, and follow-up of students with asthma, allergies or other conditions that need care during school hours.'],
        ];

        $faqs = [
            ['When is the clinic open?',
                'The hours for each day of the week are listed under Clinic hours on this page. Changes, such as holiday closings, are posted under Advisories.'],
            ['What should I bring when I go to the clinic?',
                'Bring your school ID. If you take maintenance medicine or have a doctor\'s prescription, bring it so the nurse can note it in your record. For a clearance, bring the laboratory results or medical certificates you were asked for.'],
            ['Will my parent or guardian know that I visited the clinic?',
                'Yes. When a guardian\'s mobile number is on file, the clinic can send a text message saying when you came in, why, and what was done. If you need to go home or see a doctor, the clinic calls your parent or guardian.'],
            ['Can the clinic give me medicine?',
                'The nurse can give basic over-the-counter medicine for common complaints such as headache, fever or stomach ache. Medicine is not given if your record shows an allergy to it. Prescription medicine is given only with a doctor\'s order and your parent or guardian\'s consent.'],
            ['How do I book an appointment?',
                'Go to the clinic during clinic hours, or use the appointment request form on this website when online requests are open. The clinic staff review each request, and you get a text message once your appointment is approved.'],
            ['What is the student health information form?',
                'It is a short form about your health history, allergies, medicines and emergency contacts. Fill it in when you enroll and update it when something changes, so the clinic can care for you safely. When the online form is open, you can send it from this website.'],
        ];

        $rows = [];
        foreach ($services as $i => [$icon, $title, $body]) {
            $rows[] = [
                'section' => 'service', 'title' => $title, 'subtitle' => null, 'body' => $body, 'icon' => $icon,
                'image' => null, 'link_label' => null, 'link_url' => null,
                'sort_order' => $i + 1, 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach ($faqs as $i => [$title, $body]) {
            $rows[] = [
                'section' => 'faq', 'title' => $title, 'subtitle' => null, 'body' => $body, 'icon' => null,
                'image' => null, 'link_label' => null, 'link_url' => null,
                'sort_order' => $i + 1, 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::table('landing_items')->insert($rows);
    }
};
