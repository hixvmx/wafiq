<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * The SaaS promise: company A can never see company B's data.
 * Uses a throwaway table so the guarantee is tested on the trait itself;
 * every real business model reuses the same trait.
 */
class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('isolation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->string('body');
            $table->timestamps();
        });

        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
    }

    public function test_new_rows_get_the_current_company(): void
    {
        $this->actAs($this->b);

        $note = IsolationNote::create(['body' => 'hello']);

        $this->assertSame($this->b->id, $note->company_id);
    }

    public function test_queries_only_see_the_current_company(): void
    {
        $this->actAs($this->a);
        $mine = IsolationNote::create(['body' => 'A']);

        $this->actAs($this->b);
        $theirs = IsolationNote::create(['body' => 'B']);

        $this->assertSame(['B'], IsolationNote::pluck('body')->all());
        $this->assertNull(IsolationNote::find($mine->id));

        $this->actAs($this->a);
        $this->assertSame(['A'], IsolationNote::pluck('body')->all());
        $this->assertNull(IsolationNote::find($theirs->id));
        $this->assertSame(0, IsolationNote::whereKey($theirs->id)->update(['body' => 'hacked']));
    }

    public function test_no_current_company_shows_nothing_and_cannot_create(): void
    {
        $this->actAs($this->a);
        IsolationNote::create(['body' => 'A']);

        $this->actAs(null);

        $this->assertSame(0, IsolationNote::count());

        $this->expectException(LogicException::class);
        IsolationNote::create(['body' => 'orphan']);
    }

    private function actAs(?Company $company): void
    {
        app(CurrentCompany::class)->set($company);
    }
}

class IsolationNote extends Model
{
    use BelongsToCompany;

    protected $table = 'isolation_notes';

    protected $fillable = ['body'];
}
