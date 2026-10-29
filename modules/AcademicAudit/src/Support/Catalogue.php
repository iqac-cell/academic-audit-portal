<?php

namespace Mmmut\AcademicAudit\Support;

/**
 * Master definition of the Internal Academic Audit format (MMMUT, Oct 2026).
 *
 * Each parameter describes:
 *  - columns  : the data table the department fills (as printed in the format)
 *  - metric   : how the indicator (SE, HS, P, NFD, FSM ...) is computed
 *  - slabs    : default evaluation-scheme slabs, evaluated top-down; first match wins.
 *               [op, threshold, marks] where op is 'gt' (value > threshold) or 'gte'.
 *               No match => 0 marks.
 *  - faculty  : true when individual faculty members may contribute rows
 *  - notes    : how an ambiguous / mistyped rule in the printed scheme was interpreted
 *
 * Max marks and slabs are copied into aa_parameters / aa_parameter_slabs by the seeder
 * and can be changed by IQAC afterwards; columns and metric logic stay in code.
 */
class Catalogue
{
    public const SECTIONS = [
        'A' => 'Student Quality',
        'B' => 'Curriculum Enrichment',
        'C' => 'Faculty Contribution',
        'D' => 'Extension and Outreach',
    ];

    /** Denominators / single-value inputs the HOD enters once per audit. */
    public const SCALARS = [
        'graduates' => ['label' => 'Number of students graduating as per sanctioned intake (final year, all programs)', 'type' => 'number', 'used_by' => 'A.e, A.f, A.g, A.h, A.i'],
        'int_completed' => ['label' => 'Number of students completed internship/field/research projects', 'type' => 'number', 'used_by' => 'B.b'],
        'int_eligible' => ['label' => 'Number of eligible students for internship', 'type' => 'number', 'used_by' => 'B.b'],
        'feedback_hosted' => ['label' => 'Feedback analysed, action taken & communicated and hosted on Department Home Page', 'type' => 'yn', 'used_by' => 'B.c'],
        'feedback_link' => ['label' => 'Link of feedback / action taken report on department home page', 'type' => 'url', 'used_by' => 'B.c'],
        'poco_submitted' => ['label' => 'Attainment of PO-CO submitted (Annexure IV)', 'type' => 'yn', 'used_by' => 'B.g'],
        'poco_improvement' => ['label' => '% improvement in PO-CO attainment compared to previous academic year', 'type' => 'number', 'used_by' => 'B.g'],
        'popso_submitted' => ['label' => 'POs and PSOs attainment level and actions for improvement submitted (Annexure V)', 'type' => 'yn', 'used_by' => 'B.h'],
        'seed_eligible' => ['label' => 'Number of faculty eligible for seed money', 'type' => 'number', 'used_by' => 'C.a'],
        'phd_seats' => ['label' => 'Total PhD seats available under regular faculty as per university norms', 'type' => 'number', 'used_by' => 'C.c'],
        'recognised_guides' => ['label' => 'Number of eligible regular faculty recognised as PhD guide', 'type' => 'number', 'used_by' => 'C.g'],
        'regular_faculty' => ['label' => 'Number of regular faculty in Department (leave blank to use faculty list count)', 'type' => 'number', 'used_by' => 'Section C ratios'],
    ];

    public const YEARS = ['First', 'Second', 'Third', 'Final'];

    public static function all(): array
    {
        static $cache;
        return $cache ??= self::build();
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public static function bySection(): array
    {
        $out = [];
        foreach (self::all() as $code => $p) {
            $out[$p['section']][$code] = $p;
        }
        return $out;
    }

    /** Parameters faculty members may contribute to. */
    public static function facultyItems(): array
    {
        return array_filter(self::all(), fn ($p) => $p['faculty']);
    }

    private static function build(): array
    {
        $pct4 = fn (int $m) => [['gt', 80, $m], ['gt', 50, intdiv($m, 2)], ['gt', 20, (int) round($m * 0.3)], ['gt', 0, (int) round($m * 0.2)]];

        $p = [];

        // ---------------- SECTION A : STUDENT QUALITY ----------------
        $p['A.a'] = [
            'title' => 'Student Performance of Graduating Batch',
            'max' => 10,
            'columns' => [
                ['program', 'Name of Program', 'program'],
                ['n', 'Number of Students Admitted corresponding first and second year (N)', 'number'],
                ['n1', 'Number successfully completed program in stipulated time frame (N1)', 'number'],
            ],
            'metric' => ['type' => 'ratio_sum', 'num' => 'n1', 'den' => 'n', 'symbol' => 'N1/N', 'decimals' => 3],
            'slabs' => [['gt', 0.9, 10], ['gt', 0.8, 8], ['gt', 0.7, 6]],
            'notes' => 'One row per program. Succession rate = ΣN1 / ΣN across programs.',
        ];
        $p['A.b'] = [
            'title' => 'Student Performance of Graduating Batch without backlog',
            'max' => 10,
            'columns' => [
                ['program', 'Name of Program', 'program'],
                ['n', 'Number of Students Admitted corresponding first and second year (N)', 'number'],
                ['n1', 'Number successfully completed program in stipulated time frame without backlog (N1)', 'number'],
            ],
            'metric' => ['type' => 'ratio_sum', 'num' => 'n1', 'den' => 'n', 'symbol' => 'N1/N', 'decimals' => 3],
            'slabs' => [['gt', 0.9, 10], ['gt', 0.8, 8], ['gt', 0.7, 6]],
            'notes' => 'One row per program. Succession rate = ΣN1 / ΣN across programs.',
        ];
        $p['A.c'] = [
            'title' => 'Academic Performance',
            'max' => 40,
            'columns' => [
                ['program', 'Name of Program', 'program'],
                ['year', 'Year', 'select', self::YEARS],
                ['students', 'No of student', 'number'],
                ['passed', 'No of student pass', 'number'],
                ['cgpa', 'Average CGPA', 'number'],
            ],
            'metric' => ['type' => 'weighted_avg', 'value' => 'cgpa', 'weight' => 'students', 'symbol' => 'CGPA', 'decimals' => 2],
            'slabs' => [['gt', 9, 40], ['gt', 8, 35], ['gt', 7, 30], ['gt', 6, 25]],
            'notes' => 'Department CGPA = average CGPA of all program-year rows weighted by number of students.',
        ];
        $p['A.d'] = [
            'title' => 'Effective implementation of mentor-mentee to address the academic and student psychological issues',
            'max' => 10,
            'annexure' => 'II',
            'columns' => [
                ['faculty', 'Name of faculty', 'faculty'],
                ['meetings', 'No of Meeting Held', 'number'],
                ['students', 'No of student assigned to faculty', 'number'],
                ['issues', 'Major student issue addressed', 'textarea'],
            ],
            'metric' => ['type' => 'manual', 'symbol' => '—'],
            'scoring' => 'manual',
            'slabs' => [],
            'notes' => 'Maintenance of record as per university guidelines: 10 marks. Auditor verifies records and awards marks; HOD enters claimed marks.',
        ];
        $p['A.e'] = [
            'title' => 'Students qualifying in state/ national/ international level examinations',
            'max' => 10,
            'columns' => [
                ['reg_no', 'Registration number/roll number for the exam', 'text'],
                ['student', 'Name of student selected/qualified', 'text'],
                ['exam', 'Examination (NET/SLET/GATE/GMAT/CAT/GRE/JAM/IELTS/TOEFL/Civil Services/State Govt./Other)', 'select', ['NET', 'SLET', 'GATE', 'GMAT', 'CAT', 'GRE', 'JAM', 'IELTS', 'TOEFL', 'Civil Services', 'State Government Examination', 'Other']],
                ['score', 'Score (if any)', 'text'],
                ['rank', 'Rank (if any)', 'text'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'graduates', 'symbol' => 'SE'],
            'slabs' => [['gt', 10, 10], ['gt', 8, 8], ['gt', 6, 6], ['gt', 2, 5]],
            'notes' => 'SE = students qualified / students graduating as per sanctioned intake × 100 (×100 applied since slabs are in %).',
        ];
        $p['A.f'] = [
            'title' => 'Recently graduated students who have progressed to higher education (previous graduating batch)',
            'max' => 15,
            'columns' => [
                ['student', 'Name of student enrolling into higher education', 'text'],
                ['program_from', 'Program graduated from', 'program'],
                ['institution', 'Name of institution admitted to', 'text'],
                ['program_to', 'Name of programme admitted to', 'text'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'graduates', 'symbol' => 'HS'],
            'slabs' => [['gt', 20, 10], ['gt', 10, 8], ['gt', 5, 6], ['gt', 2, 5]],
            'notes' => 'Printed scheme tops out at 10 marks although max is 15 — IQAC should confirm the top slab.',
        ];
        $p['A.g'] = [
            'title' => 'Student Placement',
            'max' => 30,
            'columns' => [
                ['student', 'Name of student placed', 'text'],
                ['program', 'Program graduated from', 'program'],
                ['employer', 'Name of the employer with contact details', 'text'],
                ['package', 'Pay package at appointment (INR/annum)', 'number'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'graduates', 'symbol' => 'P'],
            'slabs' => [['gt', 80, 10], ['gt', 60, 8], ['gt', 50, 6], ['gt', 20, 5]],
            'notes' => 'P = students placed / graduating as per sanctioned intake × 100 (printed numerator "admitted to higher studies" is a typo). Printed scheme tops out at 10 marks although max is 30 — IQAC should confirm.',
        ];
        $p['A.h'] = [
            'title' => 'Awards/medals won by students for outstanding performance in sports/cultural activities at inter-university/state/national/international events',
            'max' => 10,
            'columns' => [
                ['award', 'Name of the award/ medal', 'text'],
                ['team', 'Team / Individual', 'select', ['Team', 'Individual']],
                ['level', 'Inter-university / State / National / International', 'select', ['Inter-university', 'State', 'National', 'International']],
                ['event', 'Name of the event', 'text'],
                ['students', 'Name of the student/s', 'text'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'graduates', 'symbol' => 'A'],
            'slabs' => [['gt', 10, 10], ['gt', 8, 8], ['gt', 6, 6], ['gt', 2, 5]],
            'notes' => 'Award for a team event counted as one (one row per award).',
        ];
        $p['A.i'] = [
            'title' => 'Number of awards received for research/innovations by the research scholars/students',
            'max' => 15,
            'columns' => [
                ['year', 'Year of Award', 'number'],
                ['title', 'Title of the innovation', 'text'],
                ['awardee', 'Name of the Awardee', 'text'],
                ['mentor', 'Name of Supervisor/ Guide/ Mentor, if any', 'faculty'],
                ['agency', 'Name of the Awarding Agency with contact details', 'text'],
                ['category', 'Category', 'select', ['Research scholar', 'Student']],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'graduates', 'symbol' => 'RIA'],
            'slabs' => [['gt', 5, 15], ['gt', 4, 12], ['gt', 3, 10], ['gt', 1, 8]],
            'notes' => 'Form page shows 10 marks, evaluation table shows 15 — 15 used.',
        ];

        // ---------------- SECTION B : CURRICULUM ENRICHMENT ----------------
        $p['B.a'] = [
            'title' => 'New course introduced in academic year (NC)',
            'max' => 30,
            'columns' => [
                ['code', 'Course Code', 'text'],
                ['name', 'Name of Course', 'text'],
                ['enrolled', 'No of Student Enrolled Course', 'number'],
                ['completed', 'No of Student Completed Course', 'number'],
                ['mooc', 'Whether Course offered by NPTEL/ MOOC, etc', 'yn'],
            ],
            'metric' => ['type' => 'count', 'symbol' => 'NC'],
            'slabs' => [['gt', 5, 30], ['gt', 4, 25], ['gt', 3, 20], ['gt', 2, 15], ['gte', 1, 10]],
            'notes' => 'Printed "2 < NC > 1: 10 marks; No course: zero" read as 1–2 courses = 10 marks.',
        ];
        $p['B.b'] = [
            'title' => 'Total number of students completed internship/field/research projects',
            'max' => 10,
            'annexure' => 'III',
            'columns' => [],
            'metric' => ['type' => 'scalar_pct', 'num' => 'int_completed', 'den' => 'int_eligible', 'symbol' => 'INT'],
            'slabs' => [['gt', 80, 10], ['gt', 60, 8], ['gt', 50, 6]],
            'notes' => 'INT = students completed internship / eligible students × 100. Detailed list in Annexure III.',
        ];
        $p['B.c'] = [
            'title' => 'Feedback analysed, action taken & communicated and hosted on the Department Home Page',
            'max' => 20,
            'columns' => [],
            'metric' => ['type' => 'yes_no', 'key' => 'feedback_hosted', 'symbol' => 'Yes/No'],
            'scoring' => 'yes_no',
            'slabs' => [],
        ];
        $p['B.d'] = [
            'title' => 'Upgradation of Laboratory',
            'max' => 20,
            'columns' => [
                ['lab', 'Name of Laboratory', 'text'],
                ['oc', 'Laboratory O/C', 'faculty'],
                ['facility', 'Facility Upgraded/ New Facility Developed (NFD)', 'text'],
                ['mode', 'Developed Inhouse/ Procured', 'select', ['Inhouse', 'Procured']],
                ['amount', 'Amount (Rs)', 'number'],
                ['agency', 'Funding Agency', 'text'],
                ['purpose', 'Purpose', 'select', ['Research', 'Experiment', 'Both']],
            ],
            'metric' => ['type' => 'count', 'symbol' => 'NFD'],
            'slabs' => [['gt', 5, 20], ['gt', 4, 15], ['gt', 3, 10], ['gt', 2, 5], ['gte', 1, 2]],
            'notes' => 'Printed "4 < NFD > 5: 15" read as NFD = 5 → 15; "2 < NFD > 1: 2" read as 1–2 → 2.',
        ];
        $p['B.e'] = [
            'title' => 'New experiment developed/introduced',
            'max' => 10,
            'columns' => [
                ['lab', 'Name of Laboratory', 'text'],
                ['oc', 'Laboratory O/C', 'faculty'],
                ['course_code', 'Course Code', 'text'],
                ['course', 'Course Name', 'text'],
                ['title', 'Title of Experiment', 'text'],
            ],
            'metric' => ['type' => 'count', 'symbol' => 'NED'],
            'slabs' => [['gt', 10, 10], ['gt', 8, 8], ['gt', 6, 6], ['gt', 4, 4], ['gt', 2, 2]],
        ];
        $p['B.f'] = [
            'title' => 'Application of ICT tools for enhancing student centric learning and study material developed for the same',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['course_code', 'Course Code', 'text'],
                ['course', 'Course Name', 'text'],
                ['faculty', 'Name of Faculty', 'faculty'],
                ['link', 'Link for ICT material', 'url'],
            ],
            'metric' => ['type' => 'count', 'symbol' => 'ICT'],
            'slabs' => [['gt', 10, 10], ['gt', 8, 8], ['gt', 6, 6], ['gt', 4, 4], ['gt', 2, 2]],
        ];
        $p['B.g'] = [
            'title' => 'Attainments of PO-CO (for all courses offered)',
            'max' => 50,
            'annexure' => 'IV',
            'columns' => [],
            'metric' => ['type' => 'yes_no', 'key' => 'poco_submitted', 'symbol' => 'Yes/No'],
            'scoring' => 'yes_no',
            'slabs' => [],
        ];
        $p['B.h'] = [
            'title' => 'POs and PSOs attainment level and actions for improvement (for all courses offered)',
            'max' => 50,
            'annexure' => 'V',
            'columns' => [],
            'metric' => ['type' => 'yes_no', 'key' => 'popso_submitted', 'symbol' => 'Yes/No'],
            'scoring' => 'yes_no',
            'slabs' => [],
        ];

        // ---------------- SECTION C : FACULTY CONTRIBUTION ----------------
        $p['C.a'] = [
            'title' => 'Seed money received to faculty',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of Faculty', 'faculty'],
                ['amount', 'Amount (Rs in Lakh)', 'number'],
            ],
            'metric' => ['type' => 'distinct_pct', 'col' => 'faculty', 'den' => 'seed_eligible', 'symbol' => 'FSM'],
            'slabs' => [['gt', 80, 10], ['gt', 50, 5], ['gt', 20, 3], ['gt', 0, 2]],
            'notes' => 'FSM = distinct faculty who received seed money / faculty eligible × 100.',
        ];
        $p['C.b'] = [
            'title' => 'Teachers receiving national/ international fellowship/financial support by various agencies for advanced studies/ research',
            'max' => 30,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of Faculty', 'faculty'],
                ['award', 'Name of Award/ Fellowship', 'text'],
                ['amount', 'Amount of Award, if any', 'number'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'faculty', 'symbol' => 'FNIF'],
            'slabs' => [['gt', 10, 30], ['gt', 8, 25], ['gt', 6, 20], ['gt', 4, 15], ['gt', 2, 10], ['gt', 0, 5]],
        ];
        $p['C.c'] = [
            'title' => 'JRF/SRF enrolled as PhD Scholar in Department',
            'max' => 30,
            'faculty' => true,
            'columns' => [
                ['fellow', 'Name of the Research fellow', 'text'],
                ['year', 'Year of enrolment', 'number'],
                ['duration', 'Duration of fellowship', 'text'],
                ['type', 'Type of the fellowship', 'select', ['JRF', 'SRF', 'Other']],
                ['agency', 'Granting agency', 'text'],
                ['exam', 'Qualifying exam if any (NET, GATE, etc.)', 'text'],
                ['faculty', 'Name of PI/ Supervisor', 'faculty'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'phd_seats', 'symbol' => 'PhD'],
            'slabs' => [['gt', 80, 30], ['gt', 60, 25], ['gt', 40, 20], ['gt', 20, 15], ['gt', 10, 10], ['gt', 0, 5]],
        ];
        $p['C.d'] = [
            'title' => 'Research projects funded by government and non-government agencies',
            'max' => 40,
            'faculty' => true,
            'columns' => [
                ['scheme', 'Name of the Scheme/Project/ Endowments/ Chairs', 'text'],
                ['faculty', 'Name of the Principal Investigator/ Co Investigator', 'faculty'],
                ['agency', 'Name of the Funding agency', 'text'],
                ['type', 'Type', 'select', ['Government', 'Non-Government']],
                ['year', 'Year of Award', 'number'],
                ['funds', 'Funds provided (INR in lakhs)', 'number'],
                ['duration', 'Duration of the project', 'text'],
            ],
            'metric' => ['type' => 'sum', 'col' => 'funds', 'symbol' => 'FFR (₹ lakh)'],
            'slabs' => [['gt', 100, 40], ['gt', 50, 30], ['gt', 30, 20], ['gt', 10, 10], ['gt', 0, 5]],
        ];
        $p['C.e'] = [
            'title' => 'Number of awards received for research/innovations by the teachers',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['year', 'Year of Award', 'number'],
                ['title', 'Title of the innovation', 'text'],
                ['faculty', 'Name of the Awardee', 'faculty'],
                ['mentor', 'Name of Supervisor/ Guide/ Mentor, if any', 'text'],
                ['agency', 'Name of the Awarding Agency with contact details', 'text'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'faculty', 'symbol' => 'FRA'],
            'slabs' => $pct4(10),
        ];
        $p['C.f'] = [
            'title' => 'Number of patents',
            'max' => 20,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of the Teacher', 'faculty'],
                ['number', 'Patent Number', 'text'],
                ['title', 'Title of the patent', 'text'],
                ['published_on', 'Date of Publication', 'date'],
                ['granted_on', 'Date of Grant', 'date'],
            ],
            'metric' => ['type' => 'nonempty_pct', 'cols' => ['published_on', 'granted_on'], 'den' => 'faculty', 'symbol' => 'Patent'],
            'slabs' => [['gt', 60, 20], ['gt', 40, 15], ['gt', 10, 10], ['gt', 0, 5]],
            'notes' => 'Patent = (patents granted + patents published) / regular faculty × 100 (×100 applied since slabs are in %).',
        ];
        $p['C.g'] = [
            'title' => 'PhD awarded under recognized guide',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['scholar', 'Name of the PhD scholar (with Roll No)', 'text'],
                ['faculty', 'Name of the guide/s', 'faculty'],
                ['thesis', 'Title of the thesis', 'text'],
                ['reg_year', 'Year of registration of the scholar', 'number'],
                ['award_year', 'Year of PhD awarded', 'number'],
                ['recognised', 'Whether recognised as a Research Guide for Ph.D.', 'yn'],
                ['recognition_year', 'Year of Recognition as a Research Guide', 'number'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'recognised_guides', 'symbol' => 'PhDA'],
            'slabs' => $pct4(10),
        ];
        $p['C.h'] = [
            'title' => 'Research papers published per teacher in peer reviewed journals',
            'max' => 40,
            'faculty' => true,
            'columns' => [
                ['title', 'Title of paper', 'text'],
                ['faculty', 'Name of the author/s', 'faculty'],
                ['department', 'Department of the teacher', 'text'],
                ['journal', 'Name of journal', 'text'],
                ['year', 'Year of publication', 'number'],
                ['issn', 'ISSN number', 'text'],
                ['link', 'Link of paper', 'url'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'faculty', 'symbol' => 'Paper'],
            'slabs' => [['gt', 80, 40], ['gt', 60, 30], ['gt', 40, 20], ['gt', 0, 10]],
            'notes' => 'Paper = papers / regular faculty × 100 (×100 applied since slabs are in %). Printed slab labels "Patent"/"FRA" are typos for "Paper".',
        ];
        $p['C.i'] = [
            'title' => 'Books and chapters in edited volumes published',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of the teacher', 'faculty'],
                ['book', 'Title of the book/ chapters published', 'text'],
                ['paper', 'Title of the paper', 'text'],
                ['proceedings', 'Title of the proceedings of the conference', 'text'],
                ['year', 'Year of publication', 'number'],
                ['isbn', 'ISBN/ISSN number of the proceeding', 'text'],
                ['same_affiliation', 'Whether at the time of publication affiliating institution was same', 'yn'],
                ['publisher', 'Name of the publisher', 'text'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'faculty', 'symbol' => 'book'],
            'slabs' => $pct4(10),
        ];
        $p['C.j'] = [
            'title' => 'E-content developed by Faculty',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of the teacher', 'faculty'],
                ['module', 'Name of the module developed', 'text'],
                ['platform', 'Platform on which module is developed', 'select', ['e-PG-Pathshala', 'CEC (Undergraduate)', 'SWAYAM', 'Other MOOCs platform', 'Other Government Initiative', 'Institutional LMS']],
                ['launched_on', 'Date of launching e content', 'date'],
                ['doc_link', 'Link to the relevant document and facility available in the institution', 'url'],
                ['facility', 'List of the e-content development facility available', 'text'],
                ['video_link', 'Link to videos of the media centre and recording facility', 'url'],
            ],
            'metric' => ['type' => 'count_pct', 'den' => 'faculty', 'symbol' => 'EC'],
            'slabs' => $pct4(10),
        ];
        $p['C.k'] = [
            'title' => 'Citation in Scopus/Web of Science (as per SCOPUS record)',
            'max' => 20,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of Faculty', 'faculty'],
                ['citations', 'No of Citation', 'number'],
                ['h_index', 'h-Index', 'number'],
            ],
            'metric' => ['type' => 'sum_per', 'col' => 'citations', 'den' => 'faculty', 'symbol' => 'AC'],
            'slabs' => [['gt', 1000, 20], ['gt', 400, 15], ['gt', 100, 10], ['gt', 0, 5]],
        ];
        $p['C.l'] = [
            'title' => 'Revenue generated from consultancy',
            'max' => 20,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of the teacher consultant', 'faculty'],
                ['project', 'Name of consultancy project', 'text'],
                ['agency', 'Consulting/Sponsoring agency with contact details', 'text'],
                ['revenue', 'Revenue generated (INR in Lakhs)', 'number'],
            ],
            'metric' => ['type' => 'sum', 'col' => 'revenue', 'symbol' => 'Consultancy (₹ lakh)'],
            'slabs' => [['gt', 20, 20], ['gt', 5, 15], ['gt', 1, 10], ['gt', 0, 5]],
        ];
        $p['C.m'] = [
            'title' => 'Revenue generated from corporate training',
            'max' => 20,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Names of the teacher-consultants/corporate trainers', 'faculty'],
                ['program', 'Title of the corporate training program', 'text'],
                ['agency', 'Agency seeking training with contact details', 'text'],
                ['revenue', 'Revenue generated (amount in rupees)', 'number'],
                ['trainees', 'Number of trainees', 'number'],
            ],
            'metric' => ['type' => 'sum', 'col' => 'revenue', 'scale' => 0.00001, 'symbol' => 'Corporate (₹ lakh)'],
            'slabs' => [['gt', 20, 20], ['gt', 5, 15], ['gt', 1, 10], ['gt', 0, 5]],
            'notes' => 'Revenue entered in rupees, converted to lakhs for evaluation.',
        ];
        $p['C.n'] = [
            'title' => 'Membership of professional body',
            'max' => 10,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of Teacher', 'faculty'],
                ['body', 'Name of Professional Body', 'text'],
                ['nature', 'Nature of Membership', 'select', ['Fellow', 'Corporate', 'Life', 'Annual', 'Other']],
                ['univ_support', 'Whether Membership Received from University for Membership? (Y/N)', 'yn'],
            ],
            'metric' => ['type' => 'count_per', 'den' => 'faculty', 'symbol' => 'Membership', 'decimals' => 2],
            'slabs' => [['gt', 5, 10], ['gt', 4, 8], ['gt', 3, 6], ['gt', 2, 4], ['gt', 1, 2], ['gt', 0, 1]],
            'notes' => 'Membership = memberships / regular faculty (ratio, not %), as printed.',
        ];
        $wscCols = [
            ['faculty', 'Name of Teacher', 'faculty'],
            ['title', 'Title', 'text'],
            ['organizer', 'Name Institute Organized with address', 'text'],
            ['from', 'From', 'date'],
            ['to', 'To', 'date'],
            ['days', 'No of days', 'number'],
            ['support', 'Financial Support Received from Institute? (Y/N)', 'yn'],
            ['support_amount', 'If yes, amount received', 'number'],
        ];
        $p['C.o'] = [
            'title' => 'Workshop/Seminar/Conference attended by Faculty',
            'max' => 10,
            'faculty' => true,
            'columns' => $wscCols,
            'metric' => ['type' => 'count_per', 'den' => 'faculty', 'symbol' => 'WSC', 'decimals' => 2],
            'slabs' => [['gt', 2, 10], ['gt', 1, 8], ['gt', 0, 6]],
        ];
        $p['C.p'] = [
            'title' => 'FDP/STTP attended by Faculty (min one week)',
            'max' => 20,
            'faculty' => true,
            'columns' => $wscCols,
            'metric' => ['type' => 'count_per', 'den' => 'faculty', 'symbol' => 'FDP', 'decimals' => 2, 'min_days' => 5],
            'slabs' => [['gt', 2, 20], ['gt', 1, 15], ['gt', 0, 10]],
            'notes' => 'Only programmes of at least one week (≥ 5 days) are counted.',
        ];
        $p['C.q'] = [
            'title' => 'Professional development (Expert Talk/Seminar/Workshop/Conference/FDP/STTP, etc) / administrative training programs organized',
            'max' => 20,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of Faculty Coordinator', 'faculty'],
                ['from', 'From', 'date'],
                ['to', 'To', 'date'],
                ['days', 'No of Days', 'number'],
                ['title', 'Title of the Expert Talk/ Seminar/Workshop/Conference/FDP/STTP, etc', 'text'],
                ['participants', 'No. of participants', 'number'],
            ],
            'metric' => ['type' => 'count_per', 'den' => 'faculty', 'symbol' => 'Professional', 'decimals' => 2],
            'slabs' => [['gt', 1, 20], ['gt', 0, 15]],
        ];
        $p['C.r'] = [
            'title' => 'Faculty as Resource Persons in STTPs/FDPs',
            'max' => 20,
            'faculty' => true,
            'columns' => [
                ['faculty', 'Name of the Faculty as Resource Person', 'faculty'],
                ['program', 'Name of the STTP/FDP', 'text'],
                ['date', 'Date', 'date'],
                ['location', 'Location', 'text'],
                ['organizer', 'Organized By', 'text'],
            ],
            'metric' => ['type' => 'count_per', 'den' => 'faculty', 'symbol' => 'Resource', 'decimals' => 2],
            'slabs' => [['gt', 1, 20], ['gt', 0, 15]],
        ];

        // ---------------- SECTION D : EXTENSION AND OUTREACH ----------------
        $p['D.a'] = [
            'title' => 'Industrial/Social Outreach activity/Field Visit',
            'max' => 20,
            'columns' => [
                ['faculty', 'Name of Faculty Coordinator', 'faculty'],
                ['date', 'Date', 'date'],
                ['class', 'Class', 'text'],
                ['organization', 'Name of Industry/ Organization/Location with Contact Person', 'text'],
                ['students', 'No of students participated', 'number'],
            ],
            'metric' => ['type' => 'count', 'symbol' => 'IV'],
            'slabs' => [['gt', 2, 20], ['gte', 1, 10]],
            'notes' => 'Printed "2 < IV > 1: 10 marks" read as 1–2 visits = 10 marks.',
        ];
        $p['D.b'] = [
            'title' => 'Activities carried by professional student chapter',
            'max' => 50,
            'columns' => [
                ['activity', 'Name of Activity', 'text'],
                ['date', 'Date', 'date'],
                ['student_coordinator', 'Student Coordinator', 'text'],
                ['faculty', 'Faculty Coordinator', 'faculty'],
                ['students', 'No of students participated', 'number'],
            ],
            'metric' => ['type' => 'count', 'symbol' => 'PSC'],
            'slabs' => [['gt', 20, 50], ['gt', 15, 40], ['gt', 10, 30], ['gt', 5, 20], ['gt', 0, 10]],
            'notes' => 'Printed "20 > PSC: 50" read as PSC > 20.',
        ];
        $p['D.c'] = [
            'title' => 'Department Magazine/Newsletter',
            'max' => 30,
            'columns' => [
                ['title', 'Title of Magazine/ Newsletter', 'text'],
                ['published', 'Published (Y/N)', 'yn'],
                ['link', 'Link to Department Home page', 'url'],
            ],
            'metric' => ['type' => 'any_yes', 'col' => 'published', 'symbol' => 'Yes/No'],
            'scoring' => 'yes_no',
            'slabs' => [],
        ];

        $order = 0;
        foreach ($p as $code => &$def) {
            $def['code'] = $code;
            $def['section'] = $code[0];
            $def['letter'] = substr($code, 2);
            $def['faculty'] ??= false;
            $def['scoring'] ??= 'slab';
            $def['annexure'] ??= null;
            $def['notes'] ??= null;
            $def['order'] = ++$order;
            $def['columns'] = array_map(fn ($c) => [
                'key' => $c[0], 'label' => $c[1], 'type' => $c[2], 'options' => $c[3] ?? null,
            ], $def['columns']);
        }

        return $p;
    }
}
