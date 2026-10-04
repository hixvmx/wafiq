<?php

namespace Database\Seeders;

use App\Actions\CopyDocument;
use App\Actions\RecordView;
use App\Actions\SaveDocument;
use App\Actions\ShareDocument;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentSend;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Services\DocumentWorkflow;
use App\Support\Installer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Realistic Arabic demo company for the live demo and the Picalica screenshots:
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 *
 * Documents are made with the real actions (create, send, view, approve…) while the clock
 * is moved back, so every one has a genuine tracking history and activity timeline.
 * Owner login: owner@demo.wafiq (php artisan wafiq:login-link owner@demo.wafiq).
 */
class DemoSeeder extends Seeder
{
    private const PHONE_BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private const DESKTOP_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    /** @var array<string, User> */
    private array $team = [];

    private TaxRate $vat;

    public function run(): void
    {
        // Nothing leaves the machine while seeding.
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        $now = Carbon::now();

        $company = $this->company();
        $clients = $this->clients();
        $items = $this->items();

        $plan = [
            // [days ago, salesperson, client, lines, outcome]
            [58, 'sara', 0, [[0, '220'], [3, '1']], 'approved'],
            [52, 'khaled', 1, [[5, '12'], [6, '12']], 'approved'],
            [47, 'sara', 2, [[1, '340']], 'rejected:السعر مرتفع'],
            [44, 'khaled', 3, [[8, '1'], [9, '6']], 'approved'],
            [40, 'sara', 4, [[2, '95'], [0, '95']], 'expired'],
            [36, 'noura', 5, [[10, '1']], 'approved'],
            [33, 'khaled', 6, [[11, '24'], [12, '24']], 'rejected:اخترنا مورداً آخر'],
            [29, 'sara', 7, [[0, '410'], [4, '410'], [3, '2']], 'approved'],
            [25, 'khaled', 8, [[13, '3']], 'viewed'],
            [21, 'sara', 9, [[5, '8'], [7, '8']], 'approved'],
            [18, 'noura', 10, [[14, '1'], [3, '4']], 'revised'],
            [14, 'khaled', 11, [[2, '150']], 'viewed'],
            [11, 'sara', 0, [[6, '30']], 'expired'],
            [9, 'khaled', 2, [[1, '180'], [3, '1']], 'viewed'],
            [7, 'sara', 3, [[8, '2']], 'sent'],
            [5, 'noura', 4, [[11, '10']], 'sent'],
            [4, 'khaled', 5, [[0, '75']], 'viewed'],
            [3, 'sara', 6, [[9, '12']], 'approved'],
            [2, 'khaled', 7, [[12, '40']], 'sent'],
            [1, 'sara', 8, [[13, '1'], [3, '1']], 'draft'],
            [0, 'noura', 9, [[10, '2']], 'draft'],
        ];

        foreach ($plan as $i => [$daysAgo, $who, $client, $lines, $outcome]) {
            $this->story($now, $daysAgo, $this->team[$who], $clients[$client], $items, $lines, $outcome, $i);
        }

        // Two approved quotes became invoices, one already sent and viewed.
        $this->invoices($now);

        $this->comments();

        Carbon::setTestNow();
        // What the hourly job would have done by now: quotes past their date are expired.
        app(DocumentWorkflow::class)->expireDue();
        Installer::markInstalled();
        $this->command?->info("Demo company \"{$company->name}\" ready. Log in: php artisan wafiq:login-link owner@demo.wafiq");
    }

    private function company(): Company
    {
        $company = Company::create([
            'name' => 'مؤسسة الإتقان للمقاولات والصيانة',
            'legal_name' => 'مؤسسة الإتقان للمقاولات والصيانة',
            'vat_number' => '310123456700003',
            'cr_number' => '1010654321',
            'address' => "الرياض، حي العليا، طريق الملك فهد\nص.ب 12345، الرمز البريدي 11564",
            'phone' => '+966112345678',
            'email' => 'info@alitqan.example',
            'currency' => 'SAR',
        ]);
        app(CurrentCompany::class)->set($company);

        $company->preferences()->put([
            'brand_color' => '#0f766e',
            'currencies' => ['SAR', 'USD'],
            'bank_details' => "مصرف الراجحي — مؤسسة الإتقان للمقاولات والصيانة\nالآيبان: SA03 8000 0000 6080 1016 7519",
        ]);

        foreach ([
            'owner' => ['أحمد العتيبي', 'owner@demo.wafiq', Role::Owner],
            'noura' => ['نورة الشهري', 'noura@demo.wafiq', Role::Admin],
            'fahad' => ['فهد القحطاني', 'fahad@demo.wafiq', Role::Accountant],
            'sara' => ['سارة الدوسري', 'sara@demo.wafiq', Role::Sales],
            'khaled' => ['خالد المطيري', 'khaled@demo.wafiq', Role::Sales],
        ] as $key => [$name, $email, $role]) {
            $user = User::create(['name' => $name, 'email' => $email, 'email_verified_at' => now(), 'last_login_at' => now()->subHours(rand(1, 72))]);
            $company->addMember($user, $role);
            $this->team[$key] = $user;
        }

        $this->vat = TaxRate::create(['name' => 'ضريبة القيمة المضافة', 'rate' => 15, 'is_default' => true]);
        TaxRate::create(['name' => 'معفى من الضريبة', 'rate' => 0]);

        return $company;
    }

    /** @return list<Client> */
    private function clients(): array
    {
        $rows = [
            ['company', 'شركة الأفق للتطوير العقاري', 'م. عبدالله الحربي', '+966501112233'],
            ['company', 'مجموعة النخبة التجارية', 'خالد الزهراني', '+966552223344'],
            ['company', 'مدارس المستقبل الأهلية', 'أ. منى السبيعي', '+966563334455'],
            ['company', 'مستشفى الرعاية التخصصي', 'د. سامي العنزي', '+966544445566'],
            ['company', 'فندق الواحة', 'ريم القرشي', '+966505556677'],
            ['company', 'شركة البناء الحديث', 'م. ماجد الغامدي', '+966556667788'],
            ['company', 'مطاعم السفرة الذهبية', 'تركي الشمري', '+966567778899'],
            ['company', 'مجمع الياسمين السكني', 'إدارة المجمع', '+966548889900'],
            ['person', 'محمد الدوسري', null, '+966509990011'],
            ['company', 'شركة الخليج للخدمات اللوجستية', 'فيصل المالكي', '+971501234567'],
            ['person', 'هند العمري', null, '+966551231234'],
            ['company', 'مكتب الريادة للاستشارات الهندسية', 'م. لمى الحارثي', '+966562342345'],
        ];

        return array_map(fn (array $row) => Client::create([
            'type' => $row[0],
            'name' => $row[1],
            'contact_name' => $row[2],
            'phone' => $row[3],
            'email' => 'contact'.rand(100, 999).'@client.example',
            'vat_number' => $row[0] === 'company' ? '3'.rand(10000000000000, 99999999999999) : null,
            'owner_id' => $this->team['sara']->id,
        ]), $rows);
    }

    /** @return list<Item> */
    private function items(): array
    {
        $rows = [
            ['service', 'تركيب أرضيات بورسلان', 'م²', 8500, 'شامل المواد اللاصقة والترويب'],
            ['service', 'أعمال دهانات داخلية', 'م²', 2500, 'دهان جوتن، طبقتان'],
            ['service', 'عزل مائي للأسطح', 'م²', 4500, 'نظام لفائف بيتومينية مع ضمان 10 سنوات'],
            ['service', 'زيارة فنية ومعاينة', 'زيارة', 15000, null],
            ['service', 'أعمال جبس بورد', 'م²', 6000, null],
            ['product', 'مكيف سبليت 18000 وحدة', 'قطعة', 245000, 'إنفرتر، موفر للطاقة'],
            ['service', 'تركيب مكيف سبليت', 'قطعة', 35000, 'شامل التمديدات حتى 4 أمتار'],
            ['service', 'صيانة مكيفات دورية', 'قطعة', 12000, 'تنظيف وفحص الغاز'],
            ['service', 'تمديدات كهربائية لشقة كاملة', 'مشروع', 1850000, null],
            ['product', 'كشاف LED سقفي 18 واط', 'قطعة', 4500, null],
            ['service', 'صيانة شاملة لمبنى (عقد سنوي)', 'سنة', 4800000, 'كهرباء، سباكة، تكييف'],
            ['product', 'سخان مياه 80 لتر', 'قطعة', 65000, null],
            ['service', 'تركيب سخان مياه', 'قطعة', 15000, null],
            ['service', 'تصميم داخلي (مخططات ثلاثية الأبعاد)', 'مشروع', 950000, null],
            ['service', 'إشراف هندسي', 'شهر', 1200000, null],
        ];

        return array_map(fn (array $row) => Item::create([
            'type' => $row[0], 'name' => $row[1], 'unit' => $row[2], 'price_minor' => $row[3],
            'description' => $row[4], 'currency' => 'SAR', 'tax_rate_id' => $this->vat->id,
        ]), $rows);
    }

    /** One quote's life, replayed at its real dates. */
    private function story(Carbon $now, int $daysAgo, User $seller, Client $client, array $items, array $lines, string $outcome, int $i): void
    {
        $workflow = app(DocumentWorkflow::class);
        $at = $now->copy()->subDays($daysAgo)->setTime(9 + $i % 7, ($i * 13) % 60);
        Carbon::setTestNow($at);

        $quote = app(SaveDocument::class)->handle(null, 'quote', [
            'client_id' => $client->id,
            'currency' => 'SAR',
            'issue_date' => $at->toDateString(),
            'valid_until' => $at->copy()->addDays($outcome === 'expired' ? 7 : 15)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => $i % 4 === 0 ? '5' : '',
            'notes' => 'شكراً لثقتكم بنا.',
            'terms' => "الأسعار شاملة ضريبة القيمة المضافة.\nيبدأ التنفيذ خلال 5 أيام عمل من الموافقة.\nدفعة مقدمة 50% عند التوقيع.",
            'lines' => array_map(fn (array $line) => [
                'item_id' => $items[$line[0]]->id,
                'name' => $items[$line[0]]->name,
                'description' => $items[$line[0]]->description,
                'qty' => $line[1],
                'unit' => $items[$line[0]]->unit,
                'unit_price' => number_format($items[$line[0]]->price_minor / 100, 2, '.', ''),
                'discount' => '0',
                'tax_rate_id' => $this->vat->id,
            ], $lines),
        ], $seller);

        if ($outcome === 'draft') {
            return;
        }

        $send = $this->send($quote, $seller, $client, $i);

        if ($outcome === 'sent') {
            return;
        }

        $this->view($send, $at->copy()->addHours(2 + $i % 9), $i);

        if (str_starts_with($outcome, 'rejected:')) {
            Carbon::setTestNow($at->copy()->addDays(2));
            $workflow->reject($quote->fresh(), substr($outcome, strlen('rejected:')));
        } elseif ($outcome === 'approved') {
            Carbon::setTestNow($at->copy()->addHours(20 + ($i * 7) % 60));
            $workflow->approve($quote->fresh(), $client->contact_name ?? $client->name, '188.48.'.($i * 7 % 255).'.'.($i * 13 % 255), self::PHONE_BROWSER);
        } elseif ($outcome === 'expired') {
            Carbon::setTestNow($quote->valid_until->copy()->addDay());
            $workflow->expireIfDue($quote->fresh());
        } elseif ($outcome === 'revised') {
            // The client asked for changes: v2 with an extra line, sent, then approved.
            Carbon::setTestNow($at->copy()->addDays(3));
            $v2 = app(CopyDocument::class)->revise($quote->fresh(), $seller);
            Carbon::setTestNow($at->copy()->addDays(3)->addHour());
            $this->send($v2, $seller, $client, $i);
            Carbon::setTestNow($at->copy()->addDays(4));
            $this->view($v2->sends()->first(), now(), $i);
            Carbon::setTestNow($at->copy()->addDays(5));
            $workflow->approve($v2->fresh(), $client->contact_name ?? $client->name, '188.48.10.20', self::DESKTOP_BROWSER);
        }
    }

    private function send(Document $document, User $seller, Client $client, int $i): DocumentSend
    {
        $channel = ['whatsapp', 'whatsapp', 'email', 'link'][$i % 4];
        $share = app(ShareDocument::class);
        $defaults = $share->defaults($document->fresh(['client', 'company']));

        $result = $share->share($document->fresh(), $channel, match ($channel) {
            'whatsapp' => ['recipient' => $client->phone, 'message' => $defaults['whatsapp']['message']],
            'email' => ['recipient' => $client->email, 'subject' => $defaults['email']['subject'], 'message' => $defaults['email']['body']],
            default => [],
        }, $seller);

        return $result['send'];
    }

    private function view(DocumentSend $send, Carbon $at, int $i): void
    {
        Carbon::setTestNow($at);
        app(RecordView::class)->handle($send->fresh(), null, '188.48.1.'.(10 + $i), $i % 3 ? self::PHONE_BROWSER : self::DESKTOP_BROWSER);

        if ($i % 3 === 0) { // some clients open it again the next day
            Carbon::setTestNow($at->copy()->addDay());
            app(RecordView::class)->handle($send->fresh(), null, '188.48.1.'.(10 + $i), self::PHONE_BROWSER);
        }
    }

    private function invoices(Carbon $now): void
    {
        $approved = Document::where('type', 'quote')->where('status', 'approved')->where('is_latest', true)->orderBy('id')->take(2)->get();

        foreach ($approved as $n => $quote) {
            Carbon::setTestNow($quote->approved_at->copy()->addDay());
            $invoice = app(CopyDocument::class)->convertToInvoice($quote, $this->team['fahad']);

            if ($n === 0) {
                Carbon::setTestNow($quote->approved_at->copy()->addDays(1)->addHour());
                $send = $this->send($invoice, $this->team['fahad'], $quote->client, 1);
                $this->view($send, $quote->approved_at->copy()->addDays(2), 1);
            }
        }

        Carbon::setTestNow($now);
    }

    private function comments(): void
    {
        $viewed = Document::where('status', 'viewed')->orderBy('id')->first();
        $rejected = Document::where('status', 'rejected')->orderBy('id')->first();

        foreach ([
            [$viewed, 'khaled', '@نورة الشهري العميل فتح العرض مرتين، هل نعطيه خصم 5% إذا وافق هذا الأسبوع؟', ['noura']],
            [$viewed, 'noura', 'موافقة على 5% بحد أقصى. أرسل له نسخة جديدة.', []],
            [$rejected, 'sara', 'العميل ذكر أن السعر أعلى من المنافس بحوالي 8%. سأتابع معه الشهر القادم.', []],
        ] as [$document, $who, $body, $mentions]) {
            if (! $document) {
                continue;
            }
            Comment::create([
                'document_id' => $document->id,
                'user_id' => $this->team[$who]->id,
                'body' => $body,
                'mentions' => array_map(fn ($key) => $this->team[$key]->id, $mentions) ?: null,
            ]);
        }
    }
}
