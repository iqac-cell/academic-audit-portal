<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Parameter;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\BasicInfo;
use Tests\TestCase;

class RepeatableRowsTest extends TestCase
{
    use RefreshDatabase;

    private Audit $audit;
    private User $hod;
    private User $iqac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        $cse = Department::where('short_name', 'CSE')->first();
        $this->audit = Audit::where('department_id', $cse->id)->firstOrFail();
        $this->hod = User::factory()->create();
        UserRole::create(['user_id' => $this->hod->id, 'role' => 'hod', 'department_id' => $cse->id]);
        $this->iqac = User::factory()->create();
        UserRole::create(['user_id' => $this->iqac->id, 'role' => 'iqac']);
    }

    public function test_basic_information_tables_have_add_row_buttons(): void
    {
        $html = $this->actingAs($this->hod)->get(route('audit.basic', $this->audit))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'data-add-row>'));
        foreach (['programs[__i__][name]', 'faculty[__i__][name]', 'sanctioned[__i__][program]', 'enrolled[__i__][counts][First]'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        // empty form starts with the rows of the printed format
        $this->assertStringContainsString('value="B.Tech./B.Pharm/B.B.A."', $html);
        $this->assertStringContainsString('value="Ph.D."', $html);
    }

    public function test_any_number_of_rows_is_saved(): void
    {
        $programs = collect(range(1, 7))->map(fn ($n) => ['name' => "Program {$n}", 'level' => 'UG', 'intake' => 60, 'year' => 2000 + $n])->all();
        $faculty = collect(range(1, 12))->map(fn ($n) => ['name' => "Faculty {$n}", 'qualification' => 'PhD', 'experience' => $n, 'doj' => '01-07-2010'])->all();
        $this->actingAs($this->hod)->post(route('audit.basic.update', $this->audit), [
            'programs' => $programs + [20 => ['name' => '', 'level' => 'UG']],
            'faculty' => $faculty,
            'sanctioned' => [
                0 => ['program' => 'B.Tech. CSE', 'body' => 'AICTE', 'ts' => 240],
                3 => ['program' => 'B.Tech. CSE (AI & DS)', 'body' => 'AICTE', 'ts' => 120],
                9 => ['program' => 'MCA', 'body' => 'AICTE', 'ts' => 60],
                10 => ['program' => '', 'body' => '', 'ts' => ''],
                11 => ['program' => 'M.Tech./MCA/MBA/MSc', 'body' => '', 'ts' => ''],
            ],
            'enrolled' => [
                0 => ['program' => 'B.Tech. CSE', 'counts' => ['First' => 60, 'Second' => 62, 'Third' => 58, 'Final' => 60]],
                5 => ['program' => 'M.Tech. CSE', 'counts' => ['First' => 18, 'Second' => 17]],
                6 => ['program' => 'Ph.D.', 'counts' => ['First' => '', 'Second' => '']],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $info = $this->audit->fresh()->basic_info;
        $this->assertCount(7, $info['programs']);
        $this->assertCount(12, $info['faculty']);
        $this->assertSame(['B.Tech. CSE', 'B.Tech. CSE (AI & DS)', 'MCA'], array_column($info['sanctioned'], 'program'));
        $this->assertSame(['B.Tech. CSE', 'M.Tech. CSE'], array_column($info['enrolled'], 'program'));

        $page = $this->get(route('audit.basic', $this->audit))->assertOk();
        $page->assertSee('value="Program 7"', false)->assertSee('value="Faculty 12"', false)->assertSee('value="MCA"', false);
        $this->get(route('audit.report', $this->audit))->assertOk()
            ->assertSee('B.Tech. CSE (AI &amp; DS)', false)->assertSee('M.Tech. CSE')->assertSee('240')->assertSee('Faculty 12');
    }

    public function test_old_level_keyed_data_is_still_shown(): void
    {
        $this->audit->update(['basic_info' => [
            'sanctioned' => ['UG' => ['body' => 'AICTE', 'ts' => 480], 'PG' => ['body' => 'AICTE', 'ts' => 36]],
            'enrolled' => ['UG' => ['First' => 120, 'Final' => 110], 'PhD' => ['First' => 4]],
        ]]);

        $info = BasicInfo::normalize($this->audit->fresh()->basic_info);
        $this->assertSame([['program' => 'B.Tech./B.Pharm/B.B.A.', 'body' => 'AICTE', 'ts' => 480], ['program' => 'M.Tech./MCA/MBA/MSc', 'body' => 'AICTE', 'ts' => 36]], $info['sanctioned']);
        $this->assertSame(230, BasicInfo::total($info['enrolled'][0]['counts']));
        $this->assertSame('Ph.D.', $info['enrolled'][1]['program']);

        $this->actingAs($this->hod)->get(route('audit.basic', $this->audit))->assertOk()->assertSee('value="480"', false);
        $this->get(route('audit.report', $this->audit))->assertOk()->assertSee('480')->assertSee('230');
    }

    public function test_marks_scheme_slabs_can_be_added(): void
    {
        $param = Parameter::where('cycle_id', $this->audit->cycle_id)->where('scoring', 'slab')->firstOrFail();
        $this->actingAs($this->iqac)->get(route('audit.admin.parameters.edit', $param))->assertOk()
            ->assertSee('data-add-row', false)->assertSee('name="slabs[__i__][threshold]"', false);

        $slabs = collect(range(1, 8))->map(fn ($n) => ['op' => 'gt', 'threshold' => 90 - $n * 10, 'marks' => 9 - $n])->values()->all();
        $this->put(route('audit.admin.parameters.update', $param), ['title' => $param->title, 'max_marks' => $param->max_marks, 'slabs' => $slabs])
            ->assertSessionHasNoErrors();
        $this->assertSame(8, $param->slabs()->count());
    }
}
