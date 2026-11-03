<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\Catalogue;
use Mmmut\AcademicAudit\Support\ItemForm;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UrlFieldTest extends TestCase
{
    use RefreshDatabase;

    private Audit $audit;
    private User $hod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        $cse = Department::where('short_name', 'CSE')->first();
        $this->audit = Audit::where('department_id', $cse->id)->firstOrFail();
        $this->hod = User::factory()->create();
        UserRole::create(['user_id' => $this->hod->id, 'role' => 'hod', 'department_id' => $cse->id]);
    }

    public static function badLinks(): array
    {
        return [['javascript:alert(1)'], ['JavaScript:alert(1)'], [' javascript:alert(1)'], ['data:text/html,<script>alert(1)</script>'],
            ['vbscript:x'], ['//evil.example/x'], ['https://ok.example/"onmouseover="alert(1)']];
    }

    #[DataProvider('badLinks')]
    public function test_non_http_links_are_refused_on_entry(string $link): void
    {
        $this->actingAs($this->hod)
            ->post(route('audit.items.store', [$this->audit, 'C.h']), ['data' => ['title' => 'Paper', 'year' => 2025, 'link' => $link]])
            ->assertSessionHasErrors('data.link');
        $this->assertSame(0, Entry::count());
    }

    public function test_http_links_are_saved_and_shown_as_links(): void
    {
        $this->actingAs($this->hod)
            ->post(route('audit.items.store', [$this->audit, 'C.h']), ['data' => ['title' => 'Paper', 'year' => 2025, 'link' => 'https://doi.org/10.1/abc']])
            ->assertSessionHasNoErrors();
        $this->get(route('audit.items', [$this->audit, 'C.h']))->assertOk()->assertSee('href="https://doi.org/10.1/abc"', false);
    }

    public function test_stored_bad_link_is_shown_as_plain_text(): void
    {
        Entry::create(['audit_id' => $this->audit->id, 'parameter_code' => 'C.h', 'status' => Entry::APPROVED,
            'data' => ['title' => 'Old row', 'link' => 'javascript:alert(1)']]);

        $this->actingAs($this->hod)->get(route('audit.items', [$this->audit, 'C.h']))->assertOk()
            ->assertDontSee('href="javascript', false)
            ->assertSee('javascript:alert(1)');
    }

    public function test_excel_import_skips_rows_with_bad_links(): void
    {
        $def = Catalogue::get('C.h');
        $keys = array_column($def['columns'], 'key');
        $row = fn (string $title, string $link) => array_map(fn ($k) => match ($k) {
            'title' => $title, 'year' => 2025, 'link' => $link, default => null,
        }, $keys);
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([array_column($def['columns'], 'label'), $row('Good', 'https://x.example/p'), $row('Bad', 'javascript:alert(1)')]);
        $path = tempnam(sys_get_temp_dir(), 'aa').'.xlsx';
        (new Xlsx($book))->save($path);

        $this->actingAs($this->hod)->post(route('audit.items.import', [$this->audit, 'C.h']), [
            'file' => new UploadedFile($path, 'rows.xlsx', null, null, true),
        ]);

        $this->assertSame(['Good'], Entry::pluck('data')->pluck('title')->all());
    }

    public function test_scalar_link_must_be_http(): void
    {
        $this->actingAs($this->hod)->post(route('audit.inputs.update', $this->audit), ['feedback_link' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('feedback_link');
        $this->actingAs($this->hod)->post(route('audit.inputs.update', $this->audit), ['feedback_link' => 'https://mmmut.ac.in/cse'])
            ->assertSessionHasNoErrors();
    }

    public function test_is_http_url(): void
    {
        $this->assertTrue(ItemForm::isHttpUrl('http://a.b/c?d=1'));
        $this->assertFalse(ItemForm::isHttpUrl('javascript:alert(1)'));
        $this->assertFalse(ItemForm::isHttpUrl(null));
    }
}
