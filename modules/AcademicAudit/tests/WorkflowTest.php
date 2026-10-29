<?php

namespace Mmmut\AcademicAudit\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\Score;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\Catalogue;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Department $cse;
    private Department $ce;
    private Audit $audit;
    private User $iqac, $hod, $otherHod, $facultyUser, $aud1, $aud2;
    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        Storage::fake('local');

        $this->cse = Department::where('short_name', 'CSE')->first();
        $this->ce = Department::where('short_name', 'CE')->first();
        $this->audit = Audit::where('department_id', $this->cse->id)->firstOrFail();

        $make = function (string $role, ?Department $dept = null) {
            $u = User::factory()->create();
            UserRole::create(['user_id' => $u->id, 'role' => $role, 'department_id' => $dept?->id]);
            return $u;
        };
        $this->iqac = $make('iqac');
        $this->hod = $make('hod', $this->cse);
        $this->otherHod = $make('hod', $this->ce);
        $this->facultyUser = $make('faculty', $this->cse);
        $this->aud1 = $make('auditor');
        $this->aud2 = $make('auditor');
        $this->faculty = Faculty::create(['name' => 'Asha Verma', 'salutation' => 'Dr.', 'department_id' => $this->cse->id, 'user_id' => $this->facultyUser->id, 'is_regular' => true]);
        foreach (range(1, 3) as $i) {
            Faculty::create(['name' => "Faculty {$i}", 'department_id' => $this->cse->id, 'is_regular' => true]);
        }
    }

    private function url(string $name, array $params = []): string
    {
        return route("audit.{$name}", array_merge([$this->audit], $params));
    }

    public function test_every_page_renders_for_every_role(): void
    {
        $this->actingAs($this->hod)->post($this->url('items.store', ['C.h']), ['data' => ['title' => 'Paper', 'faculty' => 'Dr. Asha Verma', 'year' => 2025]]);
        $pages = [$this->url('show'), $this->url('basic'), $this->url('inputs'), $this->url('evaluation'), $this->url('report')];
        foreach (Catalogue::all() as $code => $def) {
            if ($def['columns']) {
                $pages[] = $this->url('items', [$code]);
            }
        }
        foreach ([$this->iqac, $this->hod, $this->facultyUser] as $user) {
            $this->actingAs($user)->get(route('audit.dashboard'))->assertOk();
            foreach ($pages as $p) {
                $this->actingAs($user)->get($p)->assertOk();
            }
        }
        foreach (['admin.cycles', 'admin.users', 'admin.departments', 'admin.faculty', 'reports.university'] as $r) {
            $this->actingAs($this->iqac)->get(route("audit.{$r}"))->assertOk();
        }
        $this->actingAs($this->iqac)->get(route('audit.admin.parameters', $this->audit->cycle_id))->assertOk();
        $this->actingAs($this->iqac)->get(route('audit.admin.parameters.edit', $this->audit->cycle->parameters->first()))->assertOk();
        $this->actingAs($this->iqac)->get($this->url('report.xlsx'))->assertOk();
        $this->actingAs($this->iqac)->get($this->url('report.pdf'))->assertOk();
        $this->actingAs($this->iqac)->get(route('audit.reports.university.xlsx'))->assertOk();
        $this->actingAs($this->hod)->get($this->url('items.template', ['C.h']))->assertOk();
    }

    public function test_department_isolation(): void
    {
        $this->actingAs($this->otherHod)->get($this->url('show'))->assertForbidden();
        $this->actingAs($this->otherHod)->post($this->url('items.store', ['A.e']), ['data' => ['reg_no' => 'X']])->assertForbidden();
        $this->actingAs($this->facultyUser)->post($this->url('items.store', ['A.g']), ['data' => ['student' => 'X']])->assertForbidden(); // dept-level item
        $this->actingAs($this->facultyUser)->get(route('audit.admin.users'))->assertForbidden();
        $this->actingAs($this->aud1)->get($this->url('show'))->assertForbidden(); // not yet assigned
    }

    public function test_full_cycle_from_entry_to_lock(): void
    {
        // Faculty contribution goes to HOD for approval
        $this->actingAs($this->facultyUser)->post($this->url('items.store', ['C.h']), [
            'data' => ['title' => 'Deep learning for flood mapping', 'faculty' => 'Dr. Asha Verma', 'journal' => 'J. Hydrology', 'year' => 2025],
            'proof' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $entry = Entry::where('parameter_code', 'C.h')->firstOrFail();
        $this->assertSame(Entry::PENDING, $entry->status);
        $this->assertNotNull($entry->proof_path);

        // HOD cannot submit while faculty rows are pending
        $this->actingAs($this->hod)->post($this->url('workflow', ['submit']))->assertSessionHasErrors('submit');
        $this->actingAs($this->hod)->post($this->url('entries.review', [$entry]), ['decision' => 'approve'])->assertRedirect();

        // Department data: 3 of 4 faculty published → Paper = 1/4*100 = 25 → 10 marks
        $this->actingAs($this->hod)->post($this->url('inputs.update'), ['graduates' => 60, 'feedback_hosted' => 'Y', 'poco_submitted' => 'Y'])->assertRedirect();
        foreach (range(1, 7) as $i) {
            $this->actingAs($this->hod)->post($this->url('items.store', ['A.g']), ['data' => ['student' => "S{$i}", 'employer' => 'TCS', 'package' => 400000]]);
        }
        $this->actingAs($this->hod)->post($this->url('evaluation.claims'), ['claim' => ['A.d' => 8]])->assertRedirect();
        $this->actingAs($this->hod)->post($this->url('workflow', ['submit']))->assertSessionHasNoErrors();
        $this->audit->refresh();
        $this->assertSame(Audit::SUBMITTED, $this->audit->status);
        $this->assertFalse($this->audit->isEditable());

        $claimed = Score::where('audit_id', $this->audit->id)->pluck('claimed_marks', 'parameter_code');
        $this->assertEquals(10, $claimed['C.h']);   // 25 % → 10
        $this->assertEquals(0, $claimed['A.g']);    // 7/60 = 11.7 % → 0 (≤ 20)
        $this->assertEquals(20, $claimed['B.c']);
        $this->assertEquals(50, $claimed['B.g']);
        $this->assertEquals(8, $claimed['A.d']);

        // HOD locked out after submission
        $this->actingAs($this->hod)->post($this->url('items.store', ['A.g']), ['data' => ['student' => 'late']])->assertForbidden();

        // IQAC returns, HOD resubmits
        $this->actingAs($this->iqac)->post($this->url('workflow', ['return']), ['remark' => 'Attach placement letters'])->assertRedirect();
        $this->assertSame(Audit::RETURNED, $this->audit->fresh()->status);
        $this->actingAs($this->hod)->post($this->url('workflow', ['submit']))->assertSessionHasNoErrors();

        // Verification needs auditors
        $this->actingAs($this->iqac)->post($this->url('workflow', ['verify']))->assertSessionHasErrors('verify');
        $this->actingAs($this->iqac)->post($this->url('auditors'), ['auditor1_user_id' => $this->aud1->id, 'auditor2_user_id' => $this->aud2->id])->assertRedirect();
        $this->actingAs($this->iqac)->post($this->url('workflow', ['verify']))->assertSessionHasNoErrors();
        $this->assertSame(Audit::VERIFIED, $this->audit->fresh()->status);

        // Auditors mark; a mark above max is capped
        $codes = $this->audit->cycle->parameters->pluck('code');
        $marks1 = $codes->mapWithKeys(fn ($c) => [$c => 4])->all();
        $marks2 = $codes->mapWithKeys(fn ($c) => [$c => 6])->all();
        $marks1['A.a'] = 99;
        $this->actingAs($this->aud1)->post($this->url('evaluation.marks'), ['marks' => $marks1])->assertRedirect();
        $this->actingAs($this->aud1)->post($this->url('workflow', ['auditor-done']))->assertSessionHasNoErrors();
        $this->actingAs($this->aud1)->post($this->url('evaluation.marks'), ['marks' => $marks1])->assertStatus(422); // already finalised
        $this->actingAs($this->aud2)->post($this->url('evaluation.marks'), ['marks' => $marks2])->assertRedirect();
        $this->actingAs($this->aud2)->post($this->url('workflow', ['auditor-done']))->assertSessionHasNoErrors();

        $this->audit->refresh();
        $this->assertSame(Audit::AUDITED, $this->audit->status);
        $scores = Score::where('audit_id', $this->audit->id)->get()->keyBy('parameter_code');
        $this->assertEquals(10, $scores['A.a']->auditor1_marks);   // capped at max 10
        $this->assertEquals(8, $scores['A.a']->awarded_marks);     // average(10, 6)
        $this->assertEquals(5, $scores['C.h']->awarded_marks);     // average(4, 6)

        $this->actingAs($this->iqac)->post($this->url('workflow', ['lock']))->assertSessionHasNoErrors();
        $this->assertSame(Audit::LOCKED, $this->audit->fresh()->status);
        $this->actingAs($this->iqac)->get($this->url('report.pdf'))->assertOk();
    }

    public function test_excel_import_of_rows(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['Course Code', 'Name', 'Enrolled', 'Completed', 'MOOC'],
            ['number', '', '', '', 'Y / N'],
            ['CS501', 'Quantum Computing', 40, 38, 'yes'],
            ['', 'Missing code', 10, 9, 'N'],
        ]);
        $file = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save($file);

        $this->actingAs($this->hod)->post($this->url('items.import', ['B.a']), [
            'file' => new UploadedFile($file, 'courses.xlsx', null, null, true),
        ])->assertSessionHasErrors('import');
        $rows = Entry::where('parameter_code', 'B.a')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Y', $rows[0]->data['mooc']);
    }
}
