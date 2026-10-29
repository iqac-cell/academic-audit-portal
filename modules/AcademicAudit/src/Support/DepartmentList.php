<?php

namespace Mmmut\AcademicAudit\Support;

/** MMMUT academic departments and the spellings used for them in Samarth ERP. */
class DepartmentList
{
    public const DEPARTMENTS = [
        'Civil Engineering' => ['CE', ['Civil Engineering Department', 'Civil Engg. Deptt.', 'Civil Engineering Deptt.']],
        'Electrical Engineering' => ['EE', ['Electrical Engg. Deptt.', 'Electrical Engg']],
        'Electronics & Communication Engineering' => ['ECE', ['E.C.E.D.', 'Electronics Communication Engineering']],
        'Mechanical Engineering' => ['ME', ['Mechanical Engg. Deptt.', 'Mechanical Engg']],
        'Computer Science & Engineering' => ['CSE', ['Computer Science & Engg.']],
        'Information Technology and Computer Application' => ['ITCA', []],
        'Chemical Engineering' => ['CHE', ['Chemical Engg. Deptt.']],
        'Physics and Material Science' => ['PMS', ['Physics and Material Science Department', 'Physics Science Deptt.']],
        'Mathematics & Scientific Computing' => ['MSC', ['Mathematics & Scientific Computing Department']],
        'Humanities & Social Science' => ['HSS', ['Humanities & Social Science Department']],
        'Chemistry and Environmental Science' => ['CES', ['Chemistry and Environmental Science Department']],
        'Management Studies' => ['MBA', []],
    ];

    /** Loose key for matching names that differ only in punctuation / "Deptt." / "Engg.". */
    public static function key(string $name): string
    {
        $s = mb_strtolower($name);
        $s = str_replace(['engg.', 'engg', 'deptt.', 'deptt', 'department', '&', ' and '], ['engineering', 'engineering', '', '', '', ' ', ' '], $s);
        return preg_replace('/[^a-z]/', '', $s);
    }
}
