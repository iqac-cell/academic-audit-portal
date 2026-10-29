<?php

namespace Mmmut\AcademicAudit\Contracts;

use Mmmut\AcademicAudit\Models\Audit;

/**
 * Supplies data already available elsewhere (IQAC project, ERP) so departments
 * only verify instead of re-typing.
 *
 * Rows returned for a parameter must use the column keys defined in
 * Support\Catalogue for that parameter. source_ref must be stable so that a
 * second prefill does not duplicate rows.
 */
interface PrefillProvider
{
    /** Human-readable name of the source, shown on the "Prefill" button. */
    public function name(): string;

    /** Parameter codes this provider can prefill. */
    public function supports(): array;

    /**
     * @return iterable<array{data: array, source_ref: string, faculty_id?: int|null}>
     */
    public function rows(string $parameterCode, Audit $audit): iterable;

    /** Values for Catalogue::SCALARS keys that can be derived (e.g. regular_faculty). */
    public function scalars(Audit $audit): array;

    /** Basic information (about, vision, mission, programs ...). */
    public function basicInfo(Audit $audit): array;
}
